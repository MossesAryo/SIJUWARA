<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Model log aktivitas pengguna (audit trail).
 * Menyimpan jejak aksi: siapa (user), apa (activity), kapan (created_at), dan siswa terkait.
 */
class ActivityLog extends Model
{
    public const KATEGORI_APRESIASI   = 'Apresiasi';
    public const KATEGORI_PELANGGARAN = 'Pelanggaran';
    public const KATEGORI_AKUN        = 'Akun';
    public const KATEGORI_SISWA       = 'Siswa';

    public const DAFTAR_KATEGORI = [
        self::KATEGORI_APRESIASI,
        self::KATEGORI_PELANGGARAN,
        self::KATEGORI_AKUN,
        self::KATEGORI_SISWA,
    ];

    public const LABEL_ROLE = [
        User::ROLE_WAKASEK       => 'Wakasek',
        User::ROLE_GURU_BK       => 'Guru BK',
        User::ROLE_WALIKELAS     => 'Wali Kelas',
        User::ROLE_KETUA_PROGRAM => 'Ketua Program',
        User::ROLE_SUPER_ADMIN   => 'Super Admin',
    ];

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function siswa()
    {
        return $this->belongsTo(siswa::class, 'nis', 'nis');
    }

    /**
     * Relasi siswa yang tetap menyertakan siswa terarsip (soft delete).
     */
    public function siswaDenganArsip()
    {
        return $this->belongsTo(siswa::class, 'nis', 'nis')->withTrashed();
    }

    /**
     * Catat satu aktivitas atas nama pengguna yang sedang login.
     * Kegagalan mencatat tidak boleh menggagalkan aksi utamanya, jadi hanya ditulis ke log file.
     *
     * @param  int|string|null  $nis  NIS siswa terkait (harus masih ada di tabel siswa).
     */
    public static function catat(string $kategori, string $activity, ?string $description = null, $nis = null, int $point = 0): ?self
    {
        try {
            $user = Auth::user();

            return static::create([
                'user_id'        => $user?->id,
                'actor_username' => $user?->username,
                'actor_role'     => $user ? (int) $user->role : null,
                'nis'            => $nis,
                'kategori'       => $kategori,
                'activity'       => $activity,
                'description'    => $description,
                'point'          => $point,
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal mencatat log aktivitas', [
                'activity' => $activity,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Nama pelaku: akun yang masih ada, atau snapshot username bila akunnya sudah dihapus.
     */
    public function getNamaPelakuAttribute(): string
    {
        if ($this->user) {
            return $this->user->username;
        }

        return $this->actor_username ?: 'Tidak diketahui';
    }

    public function getPelakuDihapusAttribute(): bool
    {
        return $this->user === null && $this->actor_username !== null;
    }

    public function getLabelRolePelakuAttribute(): ?string
    {
        $role = $this->user?->role ?? $this->actor_role;

        return $role !== null ? (self::LABEL_ROLE[(int) $role] ?? null) : null;
    }
}