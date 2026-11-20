<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GuruController;
use App\Models\ActivityLog;
use App\Models\guru;
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
 * Manajemen User: satu pintu untuk membuat, mengubah, dan menghapus akun.
 *
 * Satu orang = satu akun (satu username, satu password). Tugas tambahan (Wakasek, Guru BK,
 * Wali Kelas, Ketua Program) disimpan sebagai satu baris profil per tugas dengan username
 * yang sama. Kolom users.role menyimpan tugas yang sedang aktif; pengguna yang memegang
 * lebih dari satu tugas memilih tugasnya setelah login.
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

    /** Role yang dapat dicentang di form Tambah Akun. */
    private const ROLE_BISA_DIBUAT = [
        User::ROLE_WAKASEK,
        User::ROLE_GURU_BK,
        User::ROLE_WALIKELAS,
        User::ROLE_KETUA_PROGRAM,
        User::ROLE_SUPER_ADMIN,
    ];

    /** Role yang punya tabel profil (tugas tambahan guru/staf). */
    private const ROLE_PROFIL = [
        User::ROLE_WAKASEK,
        User::ROLE_GURU_BK,
        User::ROLE_WALIKELAS,
        User::ROLE_KETUA_PROGRAM,
    ];

    /** Password awal bila kolom password dikosongkan. */
    private const PASSWORD_AWAL = 'password123';

    public function index(Request $request)
    {
        // Tab Master Data Guru (kolom Tugas Tambahan) memakai data dan modal yang sama dengan menu Guru Wakasek.
        if ($request->query('tab') === 'guru') {
            return view('superadmin.user.index', array_merge(
                app(GuruController::class)->dataIndex($request),
                [
                    'tab' => 'guru',
                    'totalSemua' => User::count(),
                    'totalGuru' => guru::count(),
                ]
            ));
        }

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
            // Filter berdasarkan tugas yang dipegang (bukan hanya tugas yang sedang aktif).
            ->when($roleDipilih !== null, function ($query) use ($roleDipilih) {
                if ($roleDipilih === User::ROLE_SUPER_ADMIN) {
                    $query->where('role', User::ROLE_SUPER_ADMIN);
                } else {
                    $query->whereHas(self::PETA[$roleDipilih]['relasi']);
                }
            })
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

        $daftarRole = collect(self::PETA)
            ->map(function ($cfg, $kode) {
                $total = $cfg['tabel'] === null
                    ? User::where('role', User::ROLE_SUPER_ADMIN)->count()
                    : DB::table($cfg['tabel'])->distinct()->count('username');

                return [
                    'kode' => $kode,
                    'label' => $cfg['label'],
                    'total' => (int) $total,
                ];
            })
            ->values();

        return view('superadmin.user.index', [
            'tab' => 'akun',
            'totalGuru' => guru::count(),
            'pengguna' => $pengguna,
            'daftarRole' => $daftarRole,
            'totalSemua' => User::count(),
            'q' => $q,
            'roleDipilih' => $roleDipilih,
            'idSaya' => $request->user()->id,
        ]);
    }

    /**
     * Form tambah akun.
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
            'daftarKelas' => $this->daftarKelas(),
            // Kelas yang sudah punya wali kelas (satu kelas hanya satu wali).
            'kelasTerisi' => DB::table('walikelas')
                ->whereNotNull('id_kelas')
                ->pluck('id_kelas')
                ->all(),
            'daftarJurusan' => jurusan::orderBy('nama_jurusan')->get(),
            // Jurusan yang sudah punya ketua program (satu jurusan hanya satu kaprog).
            'jurusanTerisi' => DB::table('ketua_program')
                ->whereNotNull('id_jurusan')
                ->pluck('id_jurusan')
                ->all(),
        ]);
    }

    /**
     * Simpan satu akun baru. Tiap tugas yang dicentang menjadi satu baris profil
     * dengan username akun yang sama. Semuanya dibuat dalam satu transaksi.
     */
    public function store(Request $request)
    {
        $roles = $this->ambilRoleDipilih($request->input('role'));
        $superAdmin = in_array(User::ROLE_SUPER_ADMIN, $roles, true);
        $punyaProfil = $roles !== [] && !$superAdmin;

        $rules = [
            'role' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $nilai = array_map('strval', (array) $value);

                    if (in_array((string) User::ROLE_SUPER_ADMIN, $nilai, true) && count($nilai) > 1) {
                        $fail('Super Admin tidak dapat digabung dengan tugas tambahan lain.');
                    }
                },
            ],
            'role.*' => ['required', Rule::in(self::ROLE_BISA_DIBUAT)],
            'username' => $this->aturanUsername(),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Password opsional (kosong = password awal), kecuali Super Admin wajib mengisi.
            'password' => [
                Rule::requiredIf($superAdmin),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ];

        if ($punyaProfil) {
            $rules['nip'] = [
                'required',
                'regex:/^\d{5,18}$/',
                function ($attribute, $value, $fail) {
                    if ($this->nipSudahDipakai((string) $value)) {
                        $fail('NIP ini sudah terdaftar. Untuk menambah tugas, edit akun pemilik NIP tersebut.');
                    }
                },
            ];
            $rules['nama'] = ['required', 'string', 'max:255'];
        }

        $rules += $this->aturanDetail($roles, null);

        $data = $request->validate($rules, $this->pesan(), $this->atribut());

        $passwordKustom = !empty($data['password']);
        $password = $passwordKustom ? $data['password'] : self::PASSWORD_AWAL;

        try {
            $ringkas = DB::transaction(fn () => $this->buatAkun($roles, $data, $password));
        } catch (Throwable $e) {
            Log::error('Gagal membuat akun', [
                'roles' => $roles,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Akun gagal dibuat. Tidak ada data yang tersimpan, silakan coba lagi.');
        }

        $label = $this->labelTugas($roles);

        // Password tidak dicatat di log.
        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Tambah Akun',
            'Akun "' . $data['username'] . '" (' . implode(', ', $label) . ') dibuat'
                . ($punyaProfil
                    ? ' (NIP ' . $data['nip'] . ', nama ' . $data['nama'] . $ringkas . ')'
                    : '')
                . '.'
        );

        $pesanSukses = 'Akun "' . $data['username'] . '" dengan tugas ' . implode(', ', $label) . ' berhasil dibuat. '
            . ($punyaProfil ? 'Login memakai NIP ' : 'Login memakai username pada kolom NIP ')
            . ($passwordKustom ? 'dan password yang Anda tentukan.' : 'dan password awal: ' . self::PASSWORD_AWAL . '.');

        if (count($roles) > 1) {
            $pesanSukses .= ' Setelah login, pengguna memilih tugas yang akan dipakai.';
        }

        return redirect()
            ->route('superadmin.user.index', ['q' => $punyaProfil ? $data['nip'] : $data['username']])
            ->with('success', $pesanSukses);
    }

    /**
     * Form ubah data akun.
     */
    public function edit(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $profilList = $this->profilUser($user);
        $adaProfil = $profilList !== [];

        [$nip, $nama] = $this->nipNama($profilList);

        $detail = [];

        if (isset($profilList[User::ROLE_WALIKELAS])) {
            $detail[User::ROLE_WALIKELAS] = ['id_kelas' => $profilList[User::ROLE_WALIKELAS]->id_kelas];
        }

        if (isset($profilList[User::ROLE_KETUA_PROGRAM])) {
            $detail[User::ROLE_KETUA_PROGRAM] = ['id_jurusan' => $profilList[User::ROLE_KETUA_PROGRAM]->id_jurusan];
        }

        if (isset($profilList[User::ROLE_GURU_BK])) {
            $detail[User::ROLE_GURU_BK] = [
                'kelas' => DB::table('guru_bk_kelas')
                    ->where('guru_bk_id', $profilList[User::ROLE_GURU_BK]->nip_bk)
                    ->pluck('kelas_id')
                    ->all(),
            ];
        }

        return view('superadmin.user.edit', [
            'user' => $user,
            'akunSendiri' => $user->id === $request->user()->id,
            'adaProfil' => $adaProfil,
            'nip' => $nip,
            'nama' => $nama,
            'daftarRole' => collect(self::ROLE_PROFIL)
                ->map(fn ($kode) => ['kode' => $kode, 'label' => self::PETA[$kode]['label']])
                ->all(),
            'tugasDipegang' => $this->labelTugas(array_keys($profilList)),
            'detail' => $detail,
            'daftarKelas' => $this->daftarKelas(),
            // Kelas / jurusan yang sudah dipegang orang LAIN (milik akun ini sendiri tidak dikunci).
            'kelasTerisi' => DB::table('walikelas')
                ->whereNotNull('id_kelas')
                ->when($nip !== null, fn ($q) => $q->where('nip_walikelas', '!=', $nip))
                ->pluck('id_kelas')
                ->all(),
            'daftarJurusan' => jurusan::orderBy('nama_jurusan')->get(),
            'jurusanTerisi' => DB::table('ketua_program')
                ->whereNotNull('id_jurusan')
                ->when($nip !== null, fn ($q) => $q->where('nip_kaprog', '!=', $nip))
                ->pluck('id_jurusan')
                ->all(),
        ]);
    }

    /**
     * Simpan perubahan akun: data akun, data orang, dan tugas (tambah / lepas / ubah isian).
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $profilList = $this->profilUser($user);
        $adaProfil = $profilList !== [];

        [$nipLama, $namaLama] = $this->nipNama($profilList);
        $nipLama = $nipLama !== null ? (string) $nipLama : null;

        $roles = $adaProfil
            ? array_values(array_intersect(
                $this->ambilRoleDipilih($request->input('role')),
                self::ROLE_PROFIL
            ))
            : [];

        $rules = [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ];

        // Username lama dari impor bisa berisi spasi/koma (dibuat dari nama),
        // jadi aturan format hanya berlaku bila username benar-benar diganti.
        if ((string) $request->input('username') !== $user->username) {
            $rules['username'][] = 'regex:/^[A-Za-z0-9._-]+$/';
            // Tidak boleh angka saja: login memeriksa NIP lebih dulu.
            $rules['username'][] = 'not_regex:/^\d+$/';
        }

        if ($adaProfil) {
            $rules['role'] = ['required', 'array', 'min:1'];
            $rules['role.*'] = ['required', Rule::in(self::ROLE_PROFIL)];
            $rules['nama'] = ['required', 'string', 'max:255'];
            $rules['nip'] = [
                'required',
                'regex:/^\d{5,18}$/',
                function ($attribute, $value, $fail) use ($nipLama) {
                    if ((string) $value !== $nipLama && $this->nipSudahDipakai((string) $value)) {
                        $fail('NIP sudah dipakai oleh pengguna lain.');
                    }
                },
            ];
            $rules += $this->aturanDetail($roles, $nipLama);
        }

        $data = $request->validate($rules, $this->pesan(), $this->atribut());

        $punyaSekarang = array_keys($profilList);
        $dilepas = array_values(array_diff($punyaSekarang, $roles));
        $ditambah = array_values(array_diff($roles, $punyaSekarang));
        $tetap = array_values(array_intersect($roles, $punyaSekarang));

        $perubahan = $this->rangkumPerubahan($user, $profilList, $roles, $data, $nipLama, $namaLama);

        try {
            DB::transaction(function () use ($user, $data, $adaProfil, $roles, $dilepas, $ditambah, $tetap, $nipLama) {
                $usernameLama = $user->username;

                $user->email = $data['email'];

                if (!empty($data['password'])) {
                    $user->password = $data['password'];
                }

                // Username baru ikut menurun ke tabel profil lewat ON UPDATE CASCADE.
                $user->username = $data['username'];
                $user->save();

                // dibuat_oleh menyimpan username apa adanya (tanpa foreign key), ikutkan manual.
                if ($usernameLama !== $data['username']) {
                    DB::table('rencana_pindah_kelas')
                        ->where('dibuat_oleh', $usernameLama)
                        ->update(['dibuat_oleh' => $data['username']]);
                }

                if (!$adaProfil) {
                    return;
                }

                // 1. Lepas tugas yang tidak dicentang lagi (memakai NIP lama, sebelum NIP berubah).
                foreach ($dilepas as $r) {
                    $this->lepasTugas($r, $nipLama);
                }

                // 2. Isian khusus tugas yang tetap (kelas perwalian / jurusan), selagi NIP masih lama.
                foreach ($tetap as $r) {
                    $d = $data['detail'][$r] ?? [];

                    if ($r === User::ROLE_WALIKELAS) {
                        DB::table('walikelas')->where('nip_walikelas', $nipLama)
                            ->update(['id_kelas' => $d['id_kelas'] ?? null, 'updated_at' => now()]);
                    }

                    if ($r === User::ROLE_KETUA_PROGRAM) {
                        DB::table('ketua_program')->where('nip_kaprog', $nipLama)
                            ->update(['id_jurusan' => $d['id_jurusan'] ?? null, 'updated_at' => now()]);
                    }
                }

                // 3. NIP dan nama berlaku untuk SEMUA tugas yang masih dipegang.
                //    NIP baru ikut menurun ke tabel riwayat lewat ON UPDATE CASCADE.
                foreach (self::PETA as $c) {
                    if ($c['tabel'] === null) {
                        continue;
                    }

                    DB::table($c['tabel'])
                        ->where($c['nip'], $nipLama)
                        ->update([
                            $c['nama'] => $data['nama'],
                            $c['nip'] => $data['nip'],
                            'updated_at' => now(),
                        ]);
                }

                // 4. Master data guru ikut disesuaikan (bila barisnya ada dan NIP baru tidak bentrok).
                if ($nipLama === (string) $data['nip']) {
                    DB::table('guru')->where('nip_guru', $nipLama)
                        ->update(['nama_guru' => $data['nama'], 'updated_at' => now()]);
                } elseif (!DB::table('guru')->where('nip_guru', $data['nip'])->exists()) {
                    DB::table('guru')->where('nip_guru', $nipLama)
                        ->update(['nip_guru' => $data['nip'], 'nama_guru' => $data['nama'], 'updated_at' => now()]);
                }

                // 5. Tambah tugas baru (memakai username, NIP, dan nama terbaru).
                foreach ($ditambah as $r) {
                    $this->buatProfil($r, $data['username'], (string) $data['nip'], (string) $data['nama'], $data['detail'][$r] ?? []);
                }

                // 6. Kelas binaan Guru BK (guru_bk_kelas sudah ikut pindah ke NIP baru lewat cascade).
                if (in_array(User::ROLE_GURU_BK, $roles, true)) {
                    $this->sinkronKelasGuruBk((int) $data['nip'], $data['detail'][User::ROLE_GURU_BK]['kelas'] ?? []);
                }

                // 7. Tugas aktif harus salah satu tugas yang masih dipegang.
                if (!in_array((int) $user->role, $roles, true)) {
                    $user->role = $roles[0];
                    $user->save();
                }
            });
        } catch (\DomainException $e) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui akun', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Data akun gagal diperbarui. Tidak ada perubahan yang tersimpan, silakan coba lagi.');
        }

        // Paksa akun lain login ulang jika password-nya diganti.
        if (!empty($data['password']) && $user->id !== $request->user()->id) {
            $this->hapusSesi($user->id);
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Ubah Akun',
            'Akun "' . $user->username . '" ('
                . ($adaProfil ? implode(', ', $this->labelTugas($roles)) : self::PETA[(int) $user->role]['label'] ?? 'Role ' . $user->role)
                . '): '
                . ($perubahan !== '' ? $perubahan : 'tidak ada perubahan data')
                . '.'
        );

        return redirect()
            ->route('superadmin.user.index')
            ->with('success', 'Data akun "' . $user->username . '" berhasil diperbarui.');
    }

    /**
     * Halaman konfirmasi hapus.
     */
    public function konfirmasiHapus(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($alasan = $this->alasanTidakBolehHapus($user, $request->user()->id)) {
            return redirect()
                ->route('superadmin.user.index')
                ->with('error', $alasan);
        }

        $profilList = $this->profilUser($user);
        [$nip, $nama] = $this->nipNama($profilList);

        $labelTugas = $profilList !== []
            ? array_values($this->labelTugas(array_keys($profilList)))
            : [self::PETA[(int) $user->role]['label'] ?? 'Role ' . $user->role];

        $adaWakasek = isset($profilList[User::ROLE_WAKASEK]);

        return view('superadmin.user.delete', [
            'user' => $user,
            'labelTugas' => $labelTugas,
            'nipProfil' => $nip,
            'namaProfil' => $nama,
            'dampak' => $this->hitungDampak($profilList),
            'adaWakasek' => $adaWakasek,
            'wakasekTerakhir' => $adaWakasek && DB::table('wakasek')->count() <= 1,
        ]);
    }

    /**
     * Hapus akun beserta semua profil tugasnya dan catat aktivitasnya.
     */
    public function destroy(Request $request, int $id)
    {
        $request->validate(
            ['konfirmasi' => ['accepted']],
            ['accepted' => 'Centang pernyataan persetujuan sebelum menghapus.']
        );

        $user = User::findOrFail($id);

        if ($alasan = $this->alasanTidakBolehHapus($user, $request->user()->id)) {
            return redirect()
                ->route('superadmin.user.index')
                ->with('error', $alasan);
        }

        $profilList = $this->profilUser($user);
        [$nip, $nama] = $this->nipNama($profilList);

        $username = $user->username;
        $label = $profilList !== []
            ? implode(', ', $this->labelTugas(array_keys($profilList)))
            : (self::PETA[(int) $user->role]['label'] ?? 'Role ' . $user->role);
        $dampak = $this->hitungDampak($profilList);

        // Simpan path file bukti sebelum data terkait terhapus melalui cascade (khusus Wakasek).
        $fileBukti = [];

        if (isset($profilList[User::ROLE_WAKASEK])) {
            $fileBukti = DB::table('bukti_pembinaan')
                ->whereIn(
                    'intervensi_id',
                    DB::table('intervensi')
                        ->where('nip_wakasek', $profilList[User::ROLE_WAKASEK]->nip_wakasek)
                        ->select('id_intervensi')
                )
                ->pluck('file')
                ->all();
        }

        try {
            DB::transaction(function () use ($user, $profilList) {
                // Pertahankan identitas pelaku pada log lama.
                DB::table('activity_logs')
                    ->where('user_id', $user->id)
                    ->whereNull('actor_username')
                    ->update([
                        'actor_username' => $user->username,
                        'actor_role' => (int) $user->role,
                    ]);

                foreach ($profilList as $r => $profil) {
                    $cfg = self::PETA[$r];
                    $nipProfil = $profil->{$cfg['nip']};

                    if ($r === User::ROLE_GURU_BK) {
                        DB::table('guru_bk_kelas')->where('guru_bk_id', $nipProfil)->delete();
                    }

                    DB::table($cfg['tabel'])->where($cfg['nip'], $nipProfil)->delete();
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
                ->with('error', 'Akun gagal dihapus. Tidak ada data yang berubah, silakan coba lagi.');
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
                . ($ringkasDampak !== '' ? $ringkasDampak : 'tidak ada riwayat')
                . '.'
        );

        return redirect()
            ->route('superadmin.user.index')
            ->with('success', 'Akun "' . $username . '" berhasil dihapus.');
    }

    // ---------------------------------------------------------------------
    // Pembantu: akun & profil
    // ---------------------------------------------------------------------

    /**
     * Profil tugas yang dipegang akun ini, dicari lewat username: [kode role => baris profil].
     *
     * @return array<int, object>
     */
    private function profilUser(User $user): array
    {
        $hasil = [];

        foreach (self::ROLE_PROFIL as $r) {
            $baris = DB::table(self::PETA[$r]['tabel'])
                ->where('username', $user->username)
                ->first();

            if ($baris) {
                $hasil[$r] = $baris;
            }
        }

        return $hasil;
    }

    /**
     * NIP dan nama orang dari profil mana pun yang dipegang (semuanya sama).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function nipNama(array $profilList): array
    {
        if ($profilList === []) {
            return [null, null];
        }

        $r = array_key_first($profilList);
        $cfg = self::PETA[$r];

        return [
            (string) $profilList[$r]->{$cfg['nip']},
            (string) $profilList[$r]->{$cfg['nama']},
        ];
    }

    /**
     * @param  int[]  $roles
     * @return array<int, string>  [kode role => label]
     */
    private function labelTugas(array $roles): array
    {
        $hasil = [];

        foreach ($roles as $r) {
            if (isset(self::PETA[$r])) {
                $hasil[$r] = self::PETA[$r]['label'];
            }
        }

        return $hasil;
    }

    /**
     * Buat satu akun (User) beserta profil tiap tugasnya. Dipanggil di dalam transaksi.
     *
     * @param  int[]  $roles
     * @return string keterangan tambahan untuk log (kelas / jurusan / kelas binaan)
     */
    private function buatAkun(array $roles, array $data, string $password): string
    {
        User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            // Di-hash otomatis oleh cast 'hashed' pada model User.
            'password' => $password,
            // Tugas aktif awal = tugas pertama yang dicentang.
            'role' => $roles[0],
        ]);

        // Super Admin tidak punya tabel profil.
        if ($roles[0] === User::ROLE_SUPER_ADMIN) {
            return '';
        }

        $nip = (string) $data['nip'];
        $nama = (string) $data['nama'];
        $keterangan = '';

        // Pastikan orang ini ada di master data guru.
        guru::firstOrCreate(['nip_guru' => $nip], ['nama_guru' => $nama]);

        foreach ($roles as $r) {
            $d = $data['detail'][$r] ?? [];
            $keterangan .= $this->buatProfil($r, $data['username'], $nip, $nama, $d);
        }

        if (in_array(User::ROLE_GURU_BK, $roles, true)) {
            $this->sinkronKelasGuruBk((int) $nip, $data['detail'][User::ROLE_GURU_BK]['kelas'] ?? []);
        }

        return $keterangan;
    }

    /**
     * Tambah satu baris profil tugas untuk akun yang sudah ada.
     * Kelas binaan Guru BK disinkronkan terpisah oleh pemanggil.
     *
     * @return string keterangan untuk log
     */
    private function buatProfil(int $r, string $username, string $nip, string $nama, array $d): string
    {
        $cfg = self::PETA[$r];
        $now = now();
        $keterangan = '';

        $baris = [
            $cfg['nip'] => $nip,
            'username' => $username,
            $cfg['nama'] => $nama,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($r === User::ROLE_WALIKELAS) {
            $baris['id_kelas'] = $d['id_kelas'] ?? null;
            $keterangan = ', kelas ' . ($d['id_kelas'] ?? '-');
        }

        if ($r === User::ROLE_KETUA_PROGRAM) {
            $baris['id_jurusan'] = $d['id_jurusan'] ?? null;
            $keterangan = ', jurusan ' . ($d['id_jurusan'] ?? '-');
        }

        if ($r === User::ROLE_GURU_BK) {
            $keterangan = ', kelas binaan [' . implode(', ', array_values(array_unique($d['kelas'] ?? []))) . ']';
        }

        DB::table($cfg['tabel'])->insert($baris);

        return $keterangan;
    }

    /**
     * Lepas satu tugas dari akun. Ditolak (DomainException) bila tugas itu masih punya riwayat
     * kerja, karena riwayat ikut terhapus lewat cascade.
     */
    private function lepasTugas(int $r, string $nip): void
    {
        $cfg = self::PETA[$r];

        $riwayat = 0;

        foreach ($cfg['riwayat'] as $tabel => $kolom) {
            $riwayat += DB::table($tabel)->where($kolom, $nip)->count();
        }

        if ($riwayat > 0) {
            throw new \DomainException(
                'Tugas ' . $cfg['label'] . ' tidak dapat dilepas karena memiliki ' . $riwayat
                . ' riwayat penilaian/intervensi/catatan.'
            );
        }

        if ($r === User::ROLE_WAKASEK && DB::table('wakasek')->count() <= 1) {
            throw new \DomainException('Wakasek terakhir tidak dapat dilepas.');
        }

        if ($r === User::ROLE_GURU_BK) {
            DB::table('guru_bk_kelas')->where('guru_bk_id', $nip)->delete();
        }

        DB::table($cfg['tabel'])->where($cfg['nip'], $nip)->delete();
    }

    /** Kode role yang dicentang di form (dibersihkan, tanpa duplikat, terurut). @return int[] */
    private function ambilRoleDipilih(mixed $input): array
    {
        return collect(is_array($input) ? $input : [$input])
            ->filter(fn ($r) => is_numeric($r))
            ->map(fn ($r) => (int) $r)
            ->filter(fn ($r) => in_array($r, self::ROLE_BISA_DIBUAT, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function daftarKelas()
    {
        return kelas::where('id_kelas', '!=', 'ALUMNI')
            ->orderBy('id_kelas')
            ->get(['id_kelas', 'nama_kelas']);
    }

    // ---------------------------------------------------------------------
    // Pembantu: validasi
    // ---------------------------------------------------------------------

    private function aturanUsername(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'regex:/^[A-Za-z0-9._-]+$/',
            // Tidak boleh angka saja: login memeriksa NIP lebih dulu.
            'not_regex:/^\d+$/',
            'unique:users,username',
        ];
    }

    /**
     * Aturan isian khusus tugas: detail[<kode role>][...].
     *
     * @param  int[]        $roles
     * @param  string|null  $nipAbaikan NIP akun yang sedang diedit (kelas/jurusan miliknya sendiri tidak dianggap bentrok)
     */
    private function aturanDetail(array $roles, ?string $nipAbaikan): array
    {
        $rules = [];

        if (in_array(User::ROLE_WALIKELAS, $roles, true)) {
            $unik = Rule::unique('walikelas', 'id_kelas');

            if ($nipAbaikan !== null) {
                $unik = $unik->ignore($nipAbaikan, 'nip_walikelas');
            }

            $rules['detail.' . User::ROLE_WALIKELAS . '.id_kelas'] = [
                'required',
                'string',
                'exists:kelas,id_kelas',
                'not_in:ALUMNI',
                $unik,
            ];
        }

        if (in_array(User::ROLE_KETUA_PROGRAM, $roles, true)) {
            $unik = Rule::unique('ketua_program', 'id_jurusan');

            if ($nipAbaikan !== null) {
                $unik = $unik->ignore($nipAbaikan, 'nip_kaprog');
            }

            $rules['detail.' . User::ROLE_KETUA_PROGRAM . '.id_jurusan'] = [
                'required',
                'string',
                'exists:jurusan,id_jurusan',
                $unik,
            ];
        }

        if (in_array(User::ROLE_GURU_BK, $roles, true)) {
            $rules['detail.' . User::ROLE_GURU_BK . '.kelas'] = ['nullable', 'array'];
            $rules['detail.' . User::ROLE_GURU_BK . '.kelas.*'] = ['string', 'exists:kelas,id_kelas', 'not_in:ALUMNI'];
        }

        return $rules;
    }

    /**
     * Apakah NIP ini sudah ada di salah satu tabel profil (milik siapa pun).
     */
    private function nipSudahDipakai(string $nip): bool
    {
        foreach (self::PETA as $cfg) {
            if ($cfg['tabel'] !== null && DB::table($cfg['tabel'])->where($cfg['nip'], $nip)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Periksa apakah akun boleh dihapus.
     */
    private function alasanTidakBolehHapus(User $user, int $idSaya): ?string
    {
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

    // ---------------------------------------------------------------------
    // Pembantu: data turunan
    // ---------------------------------------------------------------------

    /**
     * Hitung data yang ikut terhapus bersama profil (dijumlahkan untuk semua tugas akun).
     *
     * @param  array<int, object>  $profilList
     */
    private function hitungDampak(array $profilList): array
    {
        $dampak = [];

        foreach ($profilList as $r => $profil) {
            $cfg = self::PETA[$r];
            $nip = $profil->{$cfg['nip']};

            foreach ($cfg['riwayat'] as $tabel => $kolom) {
                $nama = ucfirst($tabel);
                $dampak[$nama] = ($dampak[$nama] ?? 0) + DB::table($tabel)->where($kolom, $nip)->count();
            }

            if ($r === User::ROLE_WAKASEK) {
                $dampak['Bukti pembinaan'] = ($dampak['Bukti pembinaan'] ?? 0) + DB::table('bukti_pembinaan')
                    ->whereIn(
                        'intervensi_id',
                        DB::table('intervensi')->where('nip_wakasek', $nip)->select('id_intervensi')
                    )
                    ->count();
            }

            if ($r === User::ROLE_GURU_BK) {
                $dampak['Penugasan kelas'] = ($dampak['Penugasan kelas'] ?? 0)
                    + DB::table('guru_bk_kelas')->where('guru_bk_id', $nip)->count();
            }
        }

        return $dampak;
    }

    /**
     * Sinkronkan penugasan kelas Guru BK.
     */
    private function sinkronKelasGuruBk(int $nip, array $kelasBaru): void
    {
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
     * Rangkum perubahan data akun untuk log aktivitas. Nilai password tidak pernah dicatat.
     *
     * @param  array<int, object>  $profilList
     * @param  int[]               $roles
     */
    private function rangkumPerubahan(
        User $user,
        array $profilList,
        array $roles,
        array $data,
        ?string $nipLama,
        ?string $namaLama
    ): string {
        $hasil = [];

        if ($user->username !== $data['username']) {
            $hasil[] = "username {$user->username} → {$data['username']}";
        }

        if ($user->email !== $data['email']) {
            $hasil[] = "email {$user->email} → {$data['email']}";
        }

        if (!empty($data['password'])) {
            $hasil[] = 'password diganti';
        }

        if ($profilList === []) {
            return implode('; ', $hasil);
        }

        if ($nipLama !== (string) $data['nip']) {
            $hasil[] = "NIP {$nipLama} → {$data['nip']}";
        }

        if ($namaLama !== $data['nama']) {
            $hasil[] = "nama {$namaLama} → {$data['nama']}";
        }

        $sekarang = array_keys($profilList);
        $dilepas = array_diff($sekarang, $roles);
        $ditambah = array_diff($roles, $sekarang);

        if ($ditambah) {
            $hasil[] = 'tugas ditambah: ' . implode(', ', $this->labelTugas($ditambah));
        }

        if ($dilepas) {
            $hasil[] = 'tugas dilepas: ' . implode(', ', $this->labelTugas($dilepas));
        }

        foreach (array_intersect($roles, $sekarang) as $r) {
            $d = $data['detail'][$r] ?? [];
            $lama = $profilList[$r];

            if ($r === User::ROLE_WALIKELAS && (string) $lama->id_kelas !== (string) ($d['id_kelas'] ?? '')) {
                $hasil[] = "kelas {$lama->id_kelas} → " . ($d['id_kelas'] ?? '-');
            }

            if ($r === User::ROLE_KETUA_PROGRAM && (string) $lama->id_jurusan !== (string) ($d['id_jurusan'] ?? '')) {
                $hasil[] = "jurusan {$lama->id_jurusan} → " . ($d['id_jurusan'] ?? '-');
            }

            if ($r === User::ROLE_GURU_BK) {
                $kelasLama = DB::table('guru_bk_kelas')
                    ->where('guru_bk_id', $lama->nip_bk)
                    ->pluck('kelas_id')
                    ->sort()
                    ->values()
                    ->all();

                $kelasBaru = collect($d['kelas'] ?? [])->unique()->sort()->values()->all();

                if ($kelasLama !== $kelasBaru) {
                    $hasil[] = 'kelas binaan [' . implode(', ', $kelasLama) . '] → [' . implode(', ', $kelasBaru) . ']';
                }
            }
        }

        return implode('; ', $hasil);
    }

    /**
     * Susun data baris tabel pengguna.
     * role_label = tugas yang sedang aktif; tugas_lain = tugas lain yang dipegang akun yang sama.
     */
    private function susunBaris(User $u): array
    {
        $role = (int) $u->role;
        $cfg = self::PETA[$role] ?? null;

        // Profil yang dipegang, dari relasi yang sudah di-load.
        $profil = [];

        foreach (self::ROLE_PROFIL as $r) {
            $p = $u->{self::PETA[$r]['relasi']};

            if ($p) {
                $profil[$r] = $p;
            }
        }

        if ($cfg === null) {
            $sesuai = false;
            $catatan = 'Role tidak dikenal sistem';
        } elseif ($role === User::ROLE_SUPER_ADMIN) {
            $sesuai = true;
            $catatan = 'Tanpa tabel profil';
        } elseif ($profil === []) {
            $sesuai = false;
            $catatan = 'Profil ' . $cfg['label'] . ' tidak ditemukan';
        } elseif (!isset($profil[$role])) {
            $sesuai = false;
            $catatan = 'Tugas aktif tidak sesuai tugas yang dipegang';
        } else {
            $sesuai = true;
            $catatan = 'Sesuai';
        }

        // NIP/nama dari profil tugas aktif; bila tidak ada, dari profil lain yang dipegang.
        $kodeAcuan = isset($profil[$role]) ? $role : array_key_first($profil);

        $nipProfil = $kodeAcuan !== null ? $profil[$kodeAcuan]->{self::PETA[$kodeAcuan]['nip']} : null;
        $namaProfil = $kodeAcuan !== null ? $profil[$kodeAcuan]->{self::PETA[$kodeAcuan]['nama']} : null;

        return [
            'id' => $u->id,
            'username' => $u->username,
            'email' => $u->email,
            'role' => $role,
            'role_label' => $cfg['label'] ?? 'Tidak Dikenal (' . $role . ')',
            'nip' => $nipProfil,
            'nama' => $namaProfil,
            'tugas_lain' => array_values($this->labelTugas(array_diff(array_keys($profil), [$role]))),
            'sesuai' => $sesuai,
            'catatan' => $catatan,
        ];
    }

    private function atribut(): array
    {
        return [
            'role' => 'Tugas Tambahan',
            'nip' => 'NIP',
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password',
            'nama' => 'Nama',
            'detail.' . User::ROLE_WALIKELAS . '.id_kelas' => 'Kelas perwalian',
            'detail.' . User::ROLE_KETUA_PROGRAM . '.id_jurusan' => 'Jurusan',
            'detail.' . User::ROLE_GURU_BK . '.kelas' => 'Kelas binaan',
            'detail.' . User::ROLE_GURU_BK . '.kelas.*' => 'Kelas binaan',
        ];
    }

    private function pesan(): array
    {
        $wali = 'detail.' . User::ROLE_WALIKELAS . '.id_kelas';
        $kaprog = 'detail.' . User::ROLE_KETUA_PROGRAM . '.id_jurusan';

        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan.',
            'email' => 'Format :attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
            'min' => ':attribute minimal :min karakter.',
            'confirmed' => 'Konfirmasi password tidak cocok.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'role.required' => 'Pilih tugas tambahan terlebih dahulu.',
            'role.min' => 'Pilih minimal satu tugas tambahan.',
            'role.*.in' => 'Tugas tambahan yang dipilih tidak valid.',
            'password.required' => 'Password wajib diisi untuk akun Super Admin.',
            'nip.regex' => 'NIP harus berupa angka 5 sampai 18 digit.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
            'username.not_regex' => 'Username tidak boleh hanya berisi angka.',
            $wali . '.required' => 'Kelas perwalian wajib dipilih.',
            $wali . '.unique' => 'Kelas ini sudah memiliki wali kelas lain.',
            $wali . '.not_in' => 'Kelas ALUMNI tidak dapat dipilih.',
            $kaprog . '.required' => 'Jurusan wajib dipilih untuk Ketua Program.',
            $kaprog . '.unique' => 'Jurusan ini sudah memiliki ketua program lain.',
            'detail.' . User::ROLE_GURU_BK . '.kelas.*.not_in' => 'Kelas ALUMNI tidak dapat dipilih.',
        ];
    }
}