<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model pengguna sistem.
 * Autentikasi, peran, dan relasi ke entitas guru BK/wali/ketua/wakasek.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Konstanta role pengguna.
     */
    public const ROLE_WAKASEK = 1;
    public const ROLE_GURU_BK = 2;
    public const ROLE_WALIKELAS = 3;
    public const ROLE_KETUA_PROGRAM = 4;
    public const ROLE_SUPER_ADMIN = 5;

    /**
     * Memeriksa apakah pengguna adalah Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi ke data Guru BK.
     */
    public function gurubk()
    {
        return $this->hasOne(guru_bk::class, 'username', 'username');
    }

    /**
     * Relasi ke data Wali Kelas.
     */
    public function walikelas()
    {
        return $this->hasOne(walikelas::class, 'username', 'username');
    }

    /**
     * Relasi ke data Wakasek.
     */
    public function wakasek()
    {
        return $this->hasOne(wakasek::class, 'username', 'username');
    }

    /**
     * Relasi ke data Ketua Program.
     */
    public function ketua_program()
    {
        return $this->hasOne(ketua_program::class, 'username', 'username');
    }

    /**
     * Memeriksa apakah pengguna memiliki salah satu role tertentu.
     *
     * @param int|array $roles
     */
    public function hasRole($roles): bool
    {
        return in_array((int) $this->role, array_map('intval', (array) $roles), true);
    }

    /** Label tiap tugas/jabatan. */
    public const LABEL_ROLE = [
        self::ROLE_WAKASEK       => 'Wakasek',
        self::ROLE_GURU_BK       => 'Guru BK',
        self::ROLE_WALIKELAS     => 'Wali Kelas',
        self::ROLE_KETUA_PROGRAM => 'Ketua Program',
        self::ROLE_SUPER_ADMIN   => 'Super Admin',
    ];

    /** Cache daftar tugas per instance (navbar dan halaman pilih tugas memanggilnya berkali-kali). */
    protected ?array $cacheTugas = null;

    /**
     * Tugas/jabatan yang benar-benar dipegang akun ini, beserta keterangannya.
     * Dibaca dari tabel profil jabatan (username sama), bukan dari kolom role,
     * karena kolom role hanya menyimpan tugas yang sedang aktif.
     *
     * @return array<int, array{kode: int, label: string, detail: ?string}>
     */
    public function daftarTugas(): array
    {
        if ($this->cacheTugas !== null) {
            return $this->cacheTugas;
        }

        if ($this->isSuperAdmin()) {
            return $this->cacheTugas = [[
                'kode'   => self::ROLE_SUPER_ADMIN,
                'label'  => self::LABEL_ROLE[self::ROLE_SUPER_ADMIN],
                'detail' => 'Akses penuh seluruh sistem',
            ]];
        }

        $tugas = [];

        if ($this->wakasek) {
            $tugas[] = [
                'kode'   => self::ROLE_WAKASEK,
                'label'  => self::LABEL_ROLE[self::ROLE_WAKASEK],
                'detail' => 'Data master, laporan, dan tahun ajaran',
            ];
        }

        if ($bk = $this->gurubk) {
            $jumlah = $bk->kelas->count();

            $tugas[] = [
                'kode'   => self::ROLE_GURU_BK,
                'label'  => self::LABEL_ROLE[self::ROLE_GURU_BK],
                'detail' => $jumlah > 0 ? $jumlah . ' kelas binaan' : 'Bimbingan konseling siswa',
            ];
        }

        if ($this->ketua_program) {
            $jurusan = optional($this->ketua_program->jurusan)->nama_jurusan;

            $tugas[] = [
                'kode'   => self::ROLE_KETUA_PROGRAM,
                'label'  => self::LABEL_ROLE[self::ROLE_KETUA_PROGRAM],
                'detail' => $jurusan ?: 'Program keahlian',
            ];
        }

        if ($this->walikelas) {
            $kelas = optional($this->walikelas->kelas)->nama_kelas;

            $tugas[] = [
                'kode'   => self::ROLE_WALIKELAS,
                'label'  => self::LABEL_ROLE[self::ROLE_WALIKELAS],
                'detail' => $kelas ?: 'Kelas perwalian',
            ];
        }

        return $this->cacheTugas = $tugas;
    }

    /** Kode tugas yang dipegang akun ini. */
    public function kodeTugas(): array
    {
        return array_column($this->daftarTugas(), 'kode');
    }

    /** Apakah akun memegang lebih dari satu tugas? */
    public function punyaBanyakTugas(): bool
    {
        return count($this->daftarTugas()) > 1;
    }

    /** Label tugas dari kodenya. */
    public function labelRole(int $kode): string
    {
        return self::LABEL_ROLE[$kode] ?? 'Role ' . $kode;
    }

    /** Nama orang di balik akun (dari data jabatan pertama), atau username. */
    public function namaTampil(): string
    {
        return optional($this->wakasek)->nama_wakasek
            ?? optional($this->gurubk)->nama_guru_bk
            ?? optional($this->ketua_program)->nama_ketua_program
            ?? optional($this->walikelas)->nama_walikelas
            ?? $this->username;
    }

    /** Rute dashboard untuk tugas yang sedang aktif. */
    public function ruteDashboard(): string
    {
        return $this->isSuperAdmin() ? 'superadmin.dashboard' : 'wakasek.dashboard';
    }
}
