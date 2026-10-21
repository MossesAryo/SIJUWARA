<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\wakasek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class WakasekController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $daftarWakasek = wakasek::with('user:id,username,email')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    $q
                ) . '%';

                $query->where(function ($w) use ($like) {
                    $w->where('nip_wakasek', 'like', $like)
                        ->orWhere('nama_wakasek', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhereHas(
                            'user',
                            fn ($u) => $u->where('email', 'like', $like)
                        );
                });
            })
            ->orderBy('nama_wakasek')
            ->paginate(10)
            ->withQueryString();

        return view(
            'superadmin.wakasek.index',
            compact('daftarWakasek', 'q')
        );
    }

    public function create()
    {
        return view('superadmin.wakasek.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nip_wakasek' => [
                'required',
                'regex:/^\d{5,18}$/',
                'unique:wakasek,nip_wakasek',
                $this->nipTidakDipakaiRoleLain(),
            ],
            'nama_wakasek' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._-]+$/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->pesan(), $this->atribut());

        try {
            DB::transaction(function () use ($data) {
                User::create([
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => User::ROLE_WAKASEK,
                ]);

                wakasek::create([
                    'nip_wakasek' => $data['nip_wakasek'],
                    'username' => $data['username'],
                    'nama_wakasek' => $data['nama_wakasek'],
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Gagal membuat akun Wakasek', [
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(
                    'password',
                    'password_confirmation'
                ))
                ->with(
                    'error',
                    'Akun Wakasek gagal dibuat. Tidak ada data yang tersimpan, silakan coba lagi.'
                );
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Tambah Akun',
            'Akun Wakasek "' . $data['username']
                . '" dibuat (NIP ' . $data['nip_wakasek']
                . ', nama ' . $data['nama_wakasek'] . ').'
        );

        return redirect()
            ->route('superadmin.wakasek.index')
            ->with(
                'success',
                'Akun Wakasek "' . $data['nama_wakasek']
                    . '" berhasil dibuat. Login memakai NIP dan password awal.'
            );
    }

    public function show(string $nip)
    {
        $wakasek = $this->cariWakasek($nip);

        return view('superadmin.wakasek.show', compact('wakasek'));
    }

    public function edit(string $nip)
    {
        $wakasek = $this->cariWakasek($nip);

        return view('superadmin.wakasek.edit', compact('wakasek'));
    }

    /**
     * Hanya nama dan email yang dapat diubah.
     * NIP dan username tetap dikunci.
     */
    public function update(Request $request, string $nip)
    {
        $wakasek = $this->cariWakasek($nip);
        $user = $wakasek->user;

        abort_if(
            !$user || !$user->hasRole(User::ROLE_WAKASEK),
            404
        );

        $data = $request->validate([
            'nama_wakasek' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], $this->pesan(), $this->atribut());

        // Catat perubahan sebelum data diperbarui.
        $perubahan = [];

        if ($user->email !== $data['email']) {
            $perubahan[] = "email {$user->email} → {$data['email']}";
        }

        if ($wakasek->nama_wakasek !== $data['nama_wakasek']) {
            $perubahan[] = "nama {$wakasek->nama_wakasek} → {$data['nama_wakasek']}";
        }

        try {
            DB::transaction(function () use ($wakasek, $user, $data) {
                $user->update([
                    'email' => $data['email'],
                ]);

                wakasek::where(
                    'nip_wakasek',
                    $wakasek->nip_wakasek
                )->update([
                    'nama_wakasek' => $data['nama_wakasek'],
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui Wakasek', [
                'nip' => $nip,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Data Wakasek gagal diperbarui. Silakan coba lagi.'
                );
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Ubah Akun',
            'Akun Wakasek "' . $user->username . '": '
                . ($perubahan
                    ? implode('; ', $perubahan)
                    : 'tidak ada perubahan data')
                . '.'
        );

        return redirect()
            ->route('superadmin.wakasek.show', $wakasek->nip_wakasek)
            ->with(
                'success',
                'Data Wakasek berhasil diperbarui.'
            );
    }

    /**
     * Halaman konfirmasi hapus akun Wakasek.
     */
    public function konfirmasiHapus(string $nip)
    {
        $wakasek = $this->cariWakasek($nip);

        return view('superadmin.wakasek.delete', [
            'wakasek' => $wakasek,
            'dampak' => $this->hitungDampak($wakasek->nip_wakasek),
            'wakasekTerakhir' => wakasek::count() <= 1,
        ]);
    }

    /**
     * Hapus akun Wakasek beserta profil dan riwayatnya, lalu catat aktivitasnya.
     */
    public function destroy(Request $request, string $nip)
    {
        $request->validate(
            ['konfirmasi' => ['accepted']],
            ['accepted' => 'Centang pernyataan persetujuan sebelum menghapus.']
        );

        $wakasek = $this->cariWakasek($nip);
        $user = $wakasek->user;

        // Akun yang sedang dipakai tidak boleh dihapus (pengaman, walau Wakasek bukan Super Admin).
        if ($user && $user->id === $request->user()->id) {
            return redirect()
                ->route('superadmin.wakasek.index')
                ->with('error', 'Akun yang sedang Anda pakai tidak dapat dihapus.');
        }

        $nipWakasek = $wakasek->nip_wakasek;
        $nama = $wakasek->nama_wakasek;
        $username = $wakasek->username;
        $dampak = $this->hitungDampak($nipWakasek);

        // Simpan path file bukti sebelum data terkait terhapus melalui cascade.
        $fileBukti = DB::table('bukti_pembinaan')
            ->whereIn(
                'intervensi_id',
                DB::table('intervensi')
                    ->where('nip_wakasek', $nipWakasek)
                    ->select('id_intervensi')
            )
            ->pluck('file')
            ->all();

        try {
            DB::transaction(function () use ($user, $nipWakasek) {
                if ($user) {
                    // Pertahankan identitas pelaku pada log lama.
                    DB::table('activity_logs')
                        ->where('user_id', $user->id)
                        ->whereNull('actor_username')
                        ->update([
                            'actor_username' => $user->username,
                            'actor_role' => (int) $user->role,
                        ]);
                }

                DB::table('wakasek')
                    ->where('nip_wakasek', $nipWakasek)
                    ->delete();

                if ($user) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus akun Wakasek', [
                'nip' => $nip,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.wakasek.index')
                ->with(
                    'error',
                    'Akun Wakasek gagal dihapus. Tidak ada data yang berubah, silakan coba lagi.'
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

        // Paksa logout jika akun masih punya sesi aktif (database session).
        if ($user) {
            $this->hapusSesi($user->id);
        }

        $ringkasDampak = collect($dampak)
            ->filter()
            ->map(fn ($jumlah, $namaData) => "{$namaData} {$jumlah}")
            ->implode(', ');

        ActivityLog::catat(
            ActivityLog::KATEGORI_AKUN,
            'Hapus Akun',
            'Akun Wakasek "' . $username . '", NIP ' . $nipWakasek
                . ', nama ' . $nama . '. Ikut terhapus: '
                . ($ringkasDampak !== '' ? $ringkasDampak : 'tidak ada riwayat')
                . '.'
        );

        return redirect()
            ->route('superadmin.wakasek.index')
            ->with(
                'success',
                'Akun Wakasek "' . $nama . '" berhasil dihapus.'
            );
    }

    /**
     * Hitung data yang ikut terhapus bersama akun Wakasek.
     */
    private function hitungDampak(int|string $nip): array
    {
        return [
            'Penilaian' => DB::table('penilaian')
                ->where('nip_wakasek', $nip)
                ->count(),

            'Catatan' => DB::table('catatan')
                ->where('nip_wakasek', $nip)
                ->count(),

            'Intervensi' => DB::table('intervensi')
                ->where('nip_wakasek', $nip)
                ->count(),

            'Bukti pembinaan' => DB::table('bukti_pembinaan')
                ->whereIn(
                    'intervensi_id',
                    DB::table('intervensi')
                        ->where('nip_wakasek', $nip)
                        ->select('id_intervensi')
                )
                ->count(),
        ];
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

    private function cariWakasek(string $nip): wakasek
    {
        abort_unless(ctype_digit($nip), 404);

        return wakasek::with('user:id,username,email,role,created_at')
            ->where('nip_wakasek', $nip)
            ->firstOrFail();
    }

    /**
     * Pastikan NIP belum digunakan oleh role lain.
     */
    private function nipTidakDipakaiRoleLain(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $dipakai = DB::table('guru_bk')
                ->where('nip_bk', $value)
                ->exists()
                || DB::table('ketua_program')
                    ->where('nip_kaprog', $value)
                    ->exists()
                || DB::table('walikelas')
                    ->where('nip_walikelas', $value)
                    ->exists();

            if ($dipakai) {
                $fail('NIP sudah dipakai oleh pengguna dengan role lain.');
            }
        };
    }

    private function atribut(): array
    {
        return [
            'nip_wakasek' => 'NIP',
            'nama_wakasek' => 'Nama Wakasek',
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password',
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
            'nip_wakasek.regex' => 'NIP harus berupa angka 5 sampai 18 digit.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
        ];
    }
}