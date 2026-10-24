<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Manajemen User: satu pintu untuk membuat, mengubah, dan menghapus akun semua role.
 */
class UserController extends Controller
{
    private const PETA = [
        User::ROLE_WAKASEK => [
            'label' => 'Wakasek',
            'relasi' => 'wakasek',
            'tabel' => 'wakasek',
            'nip' => 'nip_wakasek',
            'nama' => 'nama_wakasek',
            'riwayat' => [
                'penilaian' => 'nip_wakasek',
                'catatan' => 'nip_wakasek',
                'intervensi' => 'nip_wakasek',
            ],
        ],
        User::ROLE_GURU_BK => [
            'label' => 'Guru BK',
            'relasi' => 'gurubk',
            'tabel' => 'guru_bk',
            'nip' => 'nip_bk',
            'nama' => 'nama_guru_bk',
            'riwayat' => [
                'penilaian' => 'nip_bk',
                'intervensi' => 'nip_bk',
            ],
        ],
        User::ROLE_WALIKELAS => [
            'label' => 'Wali Kelas',
            'relasi' => 'walikelas',
            'tabel' => 'walikelas',
            'nip' => 'nip_walikelas',
            'nama' => 'nama_walikelas',
            'riwayat' => [
                'penilaian' => 'nip_walikelas',
                'catatan' => 'nip_walikelas',
                'intervensi' => 'nip_walikelas',
            ],
        ],
        User::ROLE_KETUA_PROGRAM => [
            'label' => 'Ketua Program',
            'relasi' => 'ketua_program',
            'tabel' => 'ketua_program',
            'nip' => 'nip_kaprog',
            'nama' => 'nama_ketua_program',
            'riwayat' => [],
        ],
        User::ROLE_SUPER_ADMIN => [
            'label' => 'Super Admin',
            'relasi' => null,
            'tabel' => null,
            'nip' => null,
            'nama' => null,
            'riwayat' => [],
        ],
    ];

    /**
     * Semua role dapat dibuat lewat form Tambah Akun.
     */
    private const ROLE_BISA_DIBUAT = [
        User::ROLE_WAKASEK,
        User::ROLE_GURU_BK,
        User::ROLE_WALIKELAS,
        User::ROLE_KETUA_PROGRAM,
        User::ROLE_SUPER_ADMIN,
    ];

    /**
     * Password awal semua akun baru yang dibuat lewat form Tambah Akun.
     */
    private const PASSWORD_AWAL = 'password123';

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $roleDipilih = $request->query('role');
        $roleDipilih = in_array(
            $roleDipilih,
            array_map('strval', array_keys(self::PETA)),
            true
        ) ? (int) $roleDipilih : null;

        $like = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $q
        ) . '%';

        $pengguna = User::query()
            ->select('id', 'username', 'email', 'role', 'created_at')
            ->with([
                'wakasek:username,nip_wakasek,nama_wakasek',
                'gurubk:username,nip_bk,nama_guru_bk',
                'walikelas:username,nip_walikelas,nama_walikelas',
                'ketua_program:username,nip_kaprog,nama_ketua_program',
            ])
            ->when(
                $roleDipilih !== null,
                fn ($query) => $query->where('role', $roleDipilih)
            )
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('username', 'like', $like)
                        ->orWhere('email', 'like', $like);

                    foreach (self::PETA as $cfg) {
                        if ($cfg['relasi'] === null) {
                            continue;
                        }

                        $w->orWhereHas(
                            $cfg['relasi'],
                            function ($profil) use ($cfg, $like) {
                                $profil->where(function ($x) use ($cfg, $like) {
                                    $x->where($cfg['nip'], 'like', $like)
                                        ->orWhere($cfg['nama'], 'like', $like);
                                });
                            }
                        );
                    }
                });
            })
            ->orderBy('role')
            ->orderBy('username')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => $this->susunBaris($u));

        $jumlahPerRole = User::selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $daftarRole = collect(self::PETA)
            ->map(fn ($cfg, $kode) => [
                'kode' => $kode,
                'label' => $cfg['label'],
                'total' => (int) ($jumlahPerRole[$kode] ?? 0),
            ])
            ->values();

        return view('superadmin.user.index', [
            'pengguna' => $pengguna,
            'daftarRole' => $daftarRole,
            'totalSemua' => (int) $jumlahPerRole->sum(),
            'q' => $q,
            'roleDipilih' => $roleDipilih,
            'idSaya' => $request->user()->id,
        ]);
    }

    /**
     * Form tambah akun (semua role: Wakasek, Guru BK, Wali Kelas, Ketua Program, Super Admin).
     */
    public function create(Request $request)
    {
        $roleAwal = (int) $request->query('role');
        $roleAwal = in_array($roleAwal, self::ROLE_BISA_DIBUAT, true)
            ? $roleAwal
            : null;

        return view('superadmin.user.create', [
            'daftarRole' => collect(self::ROLE_BISA_DIBUAT)
                ->map(fn ($kode) => [
                    'kode' => $kode,
                    'label' => self::PETA[$kode]['label'],
                ])
                ->all(),
            'roleAwal' => $roleAwal,
            'passwordAwal' => self::PASSWORD_AWAL,
            'daftarKelas' => kelas::where('id_kelas', '!=', 'ALUMNI')
                ->orderBy('id_kelas')
                ->get(['id_kelas', 'nama_kelas']),
            // Kelas yang sudah punya wali kelas (satu kelas hanya satu wali).
            'kelasTerisi' => DB::table('walikelas')
                ->pluck('id_kelas')
                ->all(),
            'daftarJurusan' => jurusan::orderBy('nama_jurusan')->get(),
            // Jurusan yang sudah punya ketua program (satu jurusan hanya satu kaprog).
            'jurusanTerisi' => DB::table('ketua_program')
                ->pluck('id_jurusan')
                ->all(),
        ]);
    }

    /**
     * Simpan akun baru beserta profilnya dan catat aktivitasnya.
     */
    public function store(Request $request)
    {
        $role = (int) $request->input('role');

        $punyaProfil = $role !== User::ROLE_SUPER_ADMIN;

        $rules = [
            'role' => ['required', Rule::in(self::ROLE_BISA_DIBUAT)],
            // Password opsional (kosong = password awal), kecuali Super Admin wajib mengisi.
            'password' => [
                Rule::requiredIf($role === User::ROLE_SUPER_ADMIN),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._-]+$/',
                // Tidak boleh angka saja: login memeriksa NIP lebih dulu.
                'not_regex:/^\d+$/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
        ];

        if ($punyaProfil) {
            $rules['nip'] = [
                'required',
                'regex:/^\d{5,18}$/',
                function ($attribute, $value, $fail) {
                    if ($this->nipSudahDipakai((string) $value)) {
                        $fail('NIP sudah dipakai oleh pengguna lain.');
                    }
                },
            ];
            $rules['nama'] = ['required', 'string', 'max:255'];
        }

        if ($role === User::ROLE_WALIKELAS) {
            $rules['id_kelas'] = [
                'required',
                'string',
                'exists:kelas,id_kelas',
                'not_in:ALUMNI',
                'unique:walikelas,id_kelas',
            ];
        }

        if ($role === User::ROLE_KETUA_PROGRAM) {
            $rules['id_jurusan'] = [
                'required',
                'string',
                'exists:jurusan,id_jurusan',
                'unique:ketua_program,id_jurusan',
            ];
        }

        if ($role === User::ROLE_GURU_BK) {
            $rules['kelas'] = ['nullable', 'array'];
            $rules['kelas.*'] = [
                'string',
                'exists:kelas,id_kelas',
                'not_in:ALUMNI',
            ];
        }

        $data = $request->validate(
            $rules,
            $this->pesan(),
            $this->atribut()
        );

        $cfg = self::PETA[$role];

        $passwordKustom = !empty($data['password']);
        $password = $passwordKustom ? $data['password'] : self::PASSWORD_AWAL;

        try {
            DB::transaction(function () use ($data, $role, $cfg, $password) {
                User::create([
                    'username' => $data['username'],
                    'email' => $data['email'],
                    // Di-hash otomatis oleh cast 'hashed' pada model User.
                    'password' => $password,
                    'role' => $role,
                ]);

                // Super Admin tidak punya tabel profil.
                if ($cfg['tabel'] === null) {
                    return;
                }

                $now = now();

                $profil = [
                    $cfg['nip'] => $data['nip'],
                    'username' => $data['username'],
                    $cfg['nama'] => $data['nama'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($role === User::ROLE_WALIKELAS) {
                    $profil['id_kelas'] = $data['id_kelas'];
                }

                if ($role === User::ROLE_KETUA_PROGRAM) {
                    $profil['id_jurusan'] = $data['id_jurusan'];
                }

                DB::table($cfg['tabel'])->insert($profil);

                if ($role === User::ROLE_GURU_BK) {
                    $this->sinkronKelasGuruBk(
                        (int) $data['nip'],
                        $data['kelas'] ?? []
                    );
                }
            });
        } catch (Throwable $e) {
            Log::error('Gagal membuat akun', [
                'role' => $role,
                'username' => $data['username'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(
                    'password',
                    'password_confirmation'
                ))
                ->with(
                    'error',
                    'Akun gagal dibuat. Tidak ada data yang tersimpan, silakan coba lagi.'
                );
        }

        $keterangan = match ($role) {
            User::ROLE_WALIKELAS => ', kelas ' . $data['id_kelas'],
            User::ROLE_KETUA_PROGRAM => ', jurusan ' . $data['id_jurusan'],
            User::ROLE_GURU_BK => ', kelas binaan ['
                . implode(', ', array_values(array_unique($data['kelas'] ?? [])))
                . ']',
            default => '',
        };

        // Password tidak dicatat di log.
        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Tambah Akun',
            'Akun ' . $cfg['label'] . ' "' . $data['username'] . '" dibuat'
                . ($punyaProfil
                    ? ' (NIP ' . $data['nip']
                        . ', nama ' . $data['nama']
                        . $keterangan . ')'
                    : '')
                . '.'
        );

        $namaTampil = $punyaProfil ? $data['nama'] : $data['username'];
        $infoLogin = $punyaProfil
            ? 'Login memakai NIP'
            : 'Login memakai username pada kolom NIP';
        $infoPassword = $passwordKustom
            ? 'dan password yang Anda tentukan.'
            : 'dan password awal: ' . self::PASSWORD_AWAL . '.';

        return redirect()
            ->route('superadmin.user.index', [
                'role' => $role,
                'q' => $data['username'],
            ])
            ->with(
                'success',
                'Akun ' . $cfg['label'] . ' "' . $namaTampil
                    . '" berhasil dibuat. ' . $infoLogin . ' ' . $infoPassword
            );
    }

    /**
     * Form ubah data akun.
     */
    public function edit(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        [$cfg, $profil] = $this->ambilProfil($user);
        $role = (int) $user->role;

        $daftarKelas = collect();
        $kelasDipilih = [];
        $daftarJurusan = collect();

        if ($profil && in_array(
            $role,
            [User::ROLE_WALIKELAS, User::ROLE_GURU_BK],
            true
        )) {
            $daftarKelas = kelas::where('id_kelas', '!=', 'ALUMNI')
                ->orderBy('id_kelas')
                ->get(['id_kelas', 'nama_kelas']);
        }

        if ($profil && $role === User::ROLE_GURU_BK) {
            $kelasDipilih = DB::table('guru_bk_kelas')
                ->where('guru_bk_id', $profil->{$cfg['nip']})
                ->pluck('kelas_id')
                ->all();
        }

        if ($profil && $role === User::ROLE_KETUA_PROGRAM) {
            $daftarJurusan = jurusan::orderBy('nama_jurusan')->get();
        }

        return view('superadmin.user.edit', [
            'user' => $user,
            'cfg' => $cfg,
            'profil' => $profil,
            'role' => $role,
            'daftarKelas' => $daftarKelas,
            'kelasDipilih' => $kelasDipilih,
            'daftarJurusan' => $daftarJurusan,
            'akunSendiri' => $user->id === $request->user()->id,
        ]);
    }

    /**
     * Simpan perubahan akun dan catat aktivitasnya.
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        [$cfg, $profil] = $this->ambilProfil($user);
        $role = (int) $user->role;

        $rules = [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];

        if ($profil) {
            $nip = $profil->{$cfg['nip']};
            $rules['nama'] = ['required', 'string', 'max:255'];

            if ($role === User::ROLE_WALIKELAS) {
                $rules['id_kelas'] = [
                    'required',
                    'exists:kelas,id_kelas',
                    Rule::unique('walikelas', 'id_kelas')
                        ->ignore($nip, 'nip_walikelas'),
                ];
            }

            if ($role === User::ROLE_KETUA_PROGRAM) {
                $rules['id_jurusan'] = [
                    'required',
                    'exists:jurusan,id_jurusan',
                    Rule::unique('ketua_program', 'id_jurusan')
                        ->ignore($nip, 'nip_kaprog'),
                ];
            }

            if ($role === User::ROLE_GURU_BK) {
                $rules['kelas'] = ['nullable', 'array'];
                $rules['kelas.*'] = ['string', 'exists:kelas,id_kelas'];
            }
        }

        $data = $request->validate(
            $rules,
            $this->pesan(),
            $this->atribut()
        );

        // Rangkum perubahan sebelum data diperbarui.
        $perubahan = $this->rangkumPerubahan(
            $user,
            $profil,
            $cfg,
            $role,
            $data
        );

        try {
            DB::transaction(function () use (
                $user,
                $profil,
                $cfg,
                $role,
                $data
            ) {
                $user->email = $data['email'];

                if (!empty($data['password'])) {
                    $user->password = $data['password'];
                }

                $user->save();

                if (!$profil) {
                    return;
                }

                $nip = $profil->{$cfg['nip']};

                $ubah = [
                    $cfg['nama'] => $data['nama'],
                    'updated_at' => now(),
                ];

                if ($role === User::ROLE_WALIKELAS) {
                    $ubah['id_kelas'] = $data['id_kelas'];
                }

                if ($role === User::ROLE_KETUA_PROGRAM) {
                    $ubah['id_jurusan'] = $data['id_jurusan'];
                }

                DB::table($cfg['tabel'])
                    ->where($cfg['nip'], $nip)
                    ->update($ubah);

                if ($role === User::ROLE_GURU_BK) {
                    $this->sinkronKelasGuruBk(
                        (int) $nip,
                        $data['kelas'] ?? []
                    );
                }
            });
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui akun', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(
                    'password',
                    'password_confirmation'
                ))
                ->with(
                    'error',
                    'Data akun gagal diperbarui. Tidak ada perubahan yang tersimpan, silakan coba lagi.'
                );
        }

        // Paksa akun lain login ulang jika password-nya diganti.
        if (
            !empty($data['password'])
            && $user->id !== $request->user()->id
        ) {
            $this->hapusSesi($user->id);
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Ubah Akun',
            'Akun "' . $user->username . '" ('
                . ($cfg['label'] ?? 'Role ' . $role)
                . '): '
                . ($perubahan !== ''
                    ? $perubahan
                    : 'tidak ada perubahan data')
                . '.'
        );

        return redirect()
            ->route('superadmin.user.index')
            ->with(
                'success',
                'Data akun "' . $user->username . '" berhasil diperbarui.'
            );
    }

    /**
     * Halaman konfirmasi hapus.
     */
    public function konfirmasiHapus(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($alasan = $this->alasanTidakBolehHapus(
            $user,
            $request->user()->id
        )) {
            return redirect()
                ->route('superadmin.user.index')
                ->with('error', $alasan);
        }

        [$cfg, $profil] = $this->ambilProfil($user);

        return view('superadmin.user.delete', [
            'user' => $user,
            'cfg' => $cfg,
            'profil' => $profil,
            'role' => (int) $user->role,
            'dampak' => $this->hitungDampak(
                $cfg,
                $profil,
                (int) $user->role
            ),
            'wakasekTerakhir' => (int) $user->role === User::ROLE_WAKASEK
                && User::where('role', User::ROLE_WAKASEK)->count() <= 1,
        ]);
    }

    /**
     * Hapus akun beserta profilnya dan catat aktivitasnya.
     */
    public function destroy(Request $request, int $id)
    {
        $request->validate(
            ['konfirmasi' => ['accepted']],
            ['accepted' => 'Centang pernyataan persetujuan sebelum menghapus.']
        );

        $user = User::findOrFail($id);

        if ($alasan = $this->alasanTidakBolehHapus(
            $user,
            $request->user()->id
        )) {
            return redirect()
                ->route('superadmin.user.index')
                ->with('error', $alasan);
        }

        [$cfg, $profil] = $this->ambilProfil($user);

        $username = $user->username;
        $role = (int) $user->role;
        $label = $cfg['label'] ?? 'Role ' . $role;
        $nip = ($profil && $cfg) ? $profil->{$cfg['nip']} : null;
        $nama = ($profil && $cfg) ? $profil->{$cfg['nama']} : null;
        $dampak = $this->hitungDampak($cfg, $profil, $role);

        // Simpan path file bukti sebelum data terkait terhapus melalui cascade (khusus Wakasek).
        $fileBukti = [];

        if ($profil && $role === User::ROLE_WAKASEK) {
            $fileBukti = DB::table('bukti_pembinaan')
                ->whereIn(
                    'intervensi_id',
                    DB::table('intervensi')
                        ->where('nip_wakasek', $nip)
                        ->select('id_intervensi')
                )
                ->pluck('file')
                ->all();
        }

        try {
            DB::transaction(function () use ($user, $cfg, $profil) {
                // Pertahankan identitas pelaku pada log lama.
                DB::table('activity_logs')
                    ->where('user_id', $user->id)
                    ->whereNull('actor_username')
                    ->update([
                        'actor_username' => $user->username,
                        'actor_role' => (int) $user->role,
                    ]);

                if ($profil) {
                    $nip = $profil->{$cfg['nip']};

                    if ($cfg['tabel'] === 'guru_bk') {
                        DB::table('guru_bk_kelas')
                            ->where('guru_bk_id', $nip)
                            ->delete();
                    }

                    DB::table($cfg['tabel'])
                        ->where($cfg['nip'], $nip)
                        ->delete();
                }

                $user->tokens()->delete();
                $user->delete();
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus akun', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.user.index')
                ->with(
                    'error',
                    'Akun gagal dihapus. Tidak ada data yang berubah, silakan coba lagi.'
                );
        }

        // Bersihkan file bukti pembinaan dari disk (gagal hapus file tidak membatalkan penghapusan data).
        foreach ($fileBukti as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable $e) {
                Log::warning('File bukti pembinaan gagal dihapus', [
                    'file' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->hapusSesi($id);

        $ringkasDampak = collect($dampak)
            ->filter()
            ->map(fn ($jumlah, $nm) => "{$nm} {$jumlah}")
            ->implode(', ');

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Hapus Akun',
            'Akun "' . $username . '" (' . $label . ')'
                . ($nip ? ", NIP {$nip}, nama {$nama}" : '')
                . '. Ikut terhapus: '
                . ($ringkasDampak !== ''
                    ? $ringkasDampak
                    : 'tidak ada riwayat')
                . '.'
        );

        return redirect()
            ->route('superadmin.user.index')
            ->with(
                'success',
                'Akun "' . $username . '" berhasil dihapus.'
            );
    }

    /**
     * Rangkum perubahan data akun untuk log aktivitas.
     * Nilai password tidak pernah dicatat.
     */
    private function rangkumPerubahan(
        User $user,
        ?object $profil,
        ?array $cfg,
        int $role,
        array $data
    ): string {
        $hasil = [];

        if ($user->email !== $data['email']) {
            $hasil[] = "email {$user->email} → {$data['email']}";
        }

        if (!empty($data['password'])) {
            $hasil[] = 'password diganti';
        }

        if ($profil && $cfg) {
            $namaLama = $profil->{$cfg['nama']};

            if ($namaLama !== $data['nama']) {
                $hasil[] = "nama {$namaLama} → {$data['nama']}";
            }

            if (
                $role === User::ROLE_WALIKELAS
                && (string) $profil->id_kelas
                    !== (string) $data['id_kelas']
            ) {
                $hasil[] = "kelas {$profil->id_kelas} → {$data['id_kelas']}";
            }

            if (
                $role === User::ROLE_KETUA_PROGRAM
                && (string) $profil->id_jurusan
                    !== (string) $data['id_jurusan']
            ) {
                $hasil[] = "jurusan {$profil->id_jurusan} → {$data['id_jurusan']}";
            }

            if ($role === User::ROLE_GURU_BK) {
                $lama = DB::table('guru_bk_kelas')
                    ->where('guru_bk_id', $profil->{$cfg['nip']})
                    ->pluck('kelas_id')
                    ->sort()
                    ->values()
                    ->all();

                $baru = collect($data['kelas'] ?? [])
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($lama !== $baru) {
                    $hasil[] = 'kelas binaan ['
                        . implode(', ', $lama)
                        . '] → ['
                        . implode(', ', $baru)
                        . ']';
                }
            }
        }

        return implode('; ', $hasil);
    }

    /**
     * Periksa apakah NIP sudah dipakai di salah satu tabel profil.
     * Login mencari NIP di semua tabel profil, jadi NIP harus unik lintas role.
     */
    private function nipSudahDipakai(string $nip): bool
    {
        foreach (self::PETA as $cfg) {
            if ($cfg['tabel'] === null) {
                continue;
            }

            if (
                DB::table($cfg['tabel'])
                    ->where($cfg['nip'], $nip)
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ambil konfigurasi role dan profil akun.
     */
    private function ambilProfil(User $user): array
    {
        $cfg = self::PETA[(int) $user->role] ?? null;

        if ($cfg === null || $cfg['tabel'] === null) {
            return [$cfg, null];
        }

        $profil = DB::table($cfg['tabel'])
            ->where('username', $user->username)
            ->first();

        return [$cfg, $profil];
    }

    /**
     * Periksa apakah akun boleh dihapus.
     */
    private function alasanTidakBolehHapus(
        User $user,
        int $idSaya
    ): ?string {
        if ($user->id === $idSaya) {
            return 'Akun yang sedang Anda pakai tidak dapat dihapus.';
        }

        if (
            (int) $user->role === User::ROLE_SUPER_ADMIN
            && User::where('role', User::ROLE_SUPER_ADMIN)->count() <= 1
        ) {
            return 'Super Admin terakhir tidak dapat dihapus.';
        }

        return null;
    }

    /**
     * Hitung data yang ikut terhapus bersama profil.
     */
    private function hitungDampak(
        ?array $cfg,
        ?object $profil,
        int $role
    ): array {
        if (!$cfg || !$profil) {
            return [];
        }

        $nip = $profil->{$cfg['nip']};
        $dampak = [];

        foreach ($cfg['riwayat'] as $tabel => $kolom) {
            $dampak[ucfirst($tabel)] = DB::table($tabel)
                ->where($kolom, $nip)
                ->count();
        }

        if ($role === User::ROLE_WAKASEK) {
            $dampak['Bukti pembinaan'] = DB::table('bukti_pembinaan')
                ->whereIn(
                    'intervensi_id',
                    DB::table('intervensi')
                        ->where('nip_wakasek', $nip)
                        ->select('id_intervensi')
                )
                ->count();
        }

        if ($role === User::ROLE_GURU_BK) {
            $dampak['Penugasan kelas'] = DB::table('guru_bk_kelas')
                ->where('guru_bk_id', $nip)
                ->count();
        }

        return $dampak;
    }

    /**
     * Sinkronkan penugasan kelas Guru BK.
     */
    private function sinkronKelasGuruBk(
        int $nip,
        array $kelasBaru
    ): void {
        $kelasBaru = array_values(array_unique($kelasBaru));

        $lama = DB::table('guru_bk_kelas')
            ->where('guru_bk_id', $nip)
            ->pluck('kelas_id')
            ->all();

        $hapus = array_diff($lama, $kelasBaru);

        if ($hapus) {
            DB::table('guru_bk_kelas')
                ->where('guru_bk_id', $nip)
                ->whereIn('kelas_id', $hapus)
                ->delete();
        }

        $tambah = array_diff($kelasBaru, $lama);

        if ($tambah) {
            $now = now();

            DB::table('guru_bk_kelas')->insert(
                array_map(fn ($k) => [
                    'guru_bk_id' => $nip,
                    'kelas_id' => $k,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_values($tambah))
            );
        }
    }

    /**
     * Hapus sesi login akun jika menggunakan database session.
     */
    private function hapusSesi(int $userId): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->delete();
        }
    }

    /**
     * Susun data baris tabel pengguna.
     */
    private function susunBaris(User $u): array
    {
        $role = (int) $u->role;
        $cfg = self::PETA[$role] ?? null;
        $profil = ($cfg && $cfg['relasi'])
            ? $u->{$cfg['relasi']}
            : null;

        if ($cfg === null) {
            $sesuai = false;
            $catatan = 'Role tidak dikenal sistem';
        } elseif ($cfg['relasi'] === null) {
            $sesuai = true;
            $catatan = 'Tanpa tabel profil';
        } elseif ($profil === null) {
            $sesuai = false;
            $catatan = 'Profil ' . $cfg['label'] . ' tidak ditemukan';
        } else {
            $sesuai = true;
            $catatan = 'Sesuai';
        }

        return [
            'id' => $u->id,
            'username' => $u->username,
            'email' => $u->email,
            'role' => $role,
            'role_label' => $cfg['label'] ?? 'Tidak Dikenal (' . $role . ')',
            'nip' => $profil ? $profil->{$cfg['nip']} : null,
            'nama' => $profil ? $profil->{$cfg['nama']} : null,
            'sesuai' => $sesuai,
            'catatan' => $catatan,
        ];
    }

    private function atribut(): array
    {
        return [
            'role' => 'Role',
            'nip' => 'NIP',
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password',
            'nama' => 'Nama',
            'id_kelas' => 'Kelas',
            'id_jurusan' => 'Jurusan',
            'kelas' => 'Kelas',
            'kelas.*' => 'Kelas',
        ];
    }

    private function pesan(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan.',
            'email' => 'Format :attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
            'min' => ':attribute minimal :min karakter.',
            'confirmed' => 'Konfirmasi password tidak cocok.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'id_kelas.unique' => 'Kelas ini sudah memiliki wali kelas lain.',
            'role.required' => 'Pilih role terlebih dahulu.',
            'role.in' => 'Role yang dipilih tidak valid.',
            'password.required' => 'Password wajib diisi untuk akun Super Admin.',
            'nip.regex' => 'NIP harus berupa angka 5 sampai 18 digit.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
            'username.not_regex' => 'Username tidak boleh hanya berisi angka.',
            'id_kelas.not_in' => 'Kelas ALUMNI tidak dapat dipilih.',
            'kelas.*.not_in' => 'Kelas ALUMNI tidak dapat dipilih.',
            'id_jurusan.unique' => 'Jurusan ini sudah memiliki ketua program lain.',
        ];
    }
}