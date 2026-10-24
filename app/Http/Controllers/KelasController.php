<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\jurusan;
use App\Models\guru_bk;
use App\Models\siswa;
use App\Models\walikelas;
use Illuminate\Http\Request;
use App\Models\ketua_program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen data kelas beserta jurusan.
 * CRUD kelas, impor/ekspor, dan relasi wali atau guru BK.
 *
 * Kelas 10 = Program Keahlian, kelas 11/12 = Konsentrasi Keahlian
 * (lihat konstanta di model kelas).
 */
class KelasController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $jurusanList = jurusan::all();

        // siswa_count = jumlah siswa AKTIF di kelas (siswa arsip tidak dihitung).
        $query       = kelas::with('jurusan')
                        ->withCount(['siswa' => fn ($q) => $q->where('status', 'aktif')])
                        ->where('nama_kelas', '!=', 'Alumni');
        if ($user) {

            // === Role 4: Ketua Program — hanya kelas dari jurusannya ===
            if ($user->role == '4') {
                $ketua = ketua_program::where('username', $user->username)->first();

                if (!$ketua) {
                    abort(403, 'Data Ketua Program tidak ditemukan');
                }

                $query->where('id_jurusan', $ketua->id_jurusan);
            }

            // === Role 2: Guru BK — hanya kelas yang dipegang ===
            if ($user->role == '2') {
                $guru = guru_bk::where('username', $user->username)->first();

                if ($guru) {
                    $kelasIds = $guru->kelas()->pluck('kelas.id_kelas')->toArray();
                    $query->whereIn('id_kelas', $kelasIds);
                }
            }

            // === Role 3: Walikelas — hanya kelas yang dipegang ===
            if ($user->role == '3') {
                $wali = walikelas::where('username', $user->username)->first();

                if (!$wali) {
                    abort(403, 'Data Walikelas tidak ditemukan');
                }

                $query->where('id_kelas', $wali->id_kelas);
            }
        }

        // Filter jurusan (skip untuk role 4 karena sudah di-filter otomatis)
        if ($request->filled('jurusan') && (!$user || $user->role != '4')) {
            $query->whereIn('id_jurusan', (array) $request->jurusan);
        }

        if ($request->filled('tingkat')) {
            $query->where(function ($q) use ($request) {
                foreach ((array) $request->tingkat as $tingkat) {
                    if (in_array($tingkat, kelas::TINGKAT, true)) {
                        $q->orWhere('nama_kelas', 'LIKE', $tingkat . ' %');
                    }
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%$search%")
                  ->orWhere('id_kelas', 'like', "%$search%")
                  ->orWhereHas('jurusan', function ($q) use ($search) {
                      $q->where('nama_jurusan', 'like', "%$search%");
                  });
            });
        }

        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'nama_kelas_asc':
                    $query->orderBy('nama_kelas', 'asc');
                    break;
                case 'nama_kelas_desc':
                    $query->orderBy('nama_kelas', 'desc');
                    break;
                case 'jurusan_asc':
                    $query->orderBy('id_jurusan', 'asc')
                        ->orderBy('nama_kelas', 'asc');
                    break;
                case 'jurusan_desc':
                    $query->orderBy('id_jurusan', 'desc')
                        ->orderBy('nama_kelas', 'asc');
                    break;
                case 'tingkat_asc':
                    $query->orderByRaw("CASE
                        WHEN nama_kelas LIKE '10 %' THEN 1
                        WHEN nama_kelas LIKE '11 %' THEN 2
                        WHEN nama_kelas LIKE '12 %' THEN 3
                        ELSE 4 END")
                        ->orderBy('nama_kelas', 'asc');
                    break;
                case 'tingkat_desc':
                    $query->orderByRaw("CASE
                        WHEN nama_kelas LIKE '12 %' THEN 1
                        WHEN nama_kelas LIKE '11 %' THEN 2
                        WHEN nama_kelas LIKE '10 %' THEN 3
                        ELSE 4 END")
                        ->orderBy('nama_kelas', 'asc');
                    break;
            }
        } else {
            $query->orderByRaw("CASE
                WHEN nama_kelas LIKE '10 %' THEN 1
                WHEN nama_kelas LIKE '11 %' THEN 2
                WHEN nama_kelas LIKE '12 %' THEN 3
                ELSE 4 END")
                ->orderBy('nama_kelas', 'asc');
        }

        $kelas = $query->paginate(10)->appends($request->all());

        return view('wakasek.kelas.kelas', compact('kelas', 'jurusanList'));
    }

    /**
     * Daftar siswa aktif satu kelas (JSON), dipakai popup "Jumlah Siswa" di halaman kelas.
     * Hak akses mengikuti halaman kelas: Ketua Program hanya jurusannya,
     * Guru BK dan Walikelas hanya kelas yang dipegang.
     */
    public function siswa(string $id)
    {
        $kelas = kelas::with('jurusan')->where('id_kelas', $id)->firstOrFail();

        $this->pastikanBolehMelihat($kelas);

        $daftar = siswa::query()
            ->where('id_kelas', $kelas->id_kelas)
            ->where('status', 'aktif')
            ->orderBy('nama_siswa')
            ->get(['nis', 'nama_siswa']);

        return response()->json([
            'id_kelas'   => $kelas->id_kelas,
            'nama_kelas' => $kelas->nama_kelas,
            'jurusan'    => optional($kelas->jurusan)->nama_jurusan ?? $kelas->id_jurusan,
            'jumlah'     => $daftar->count(),
            'siswa'      => $daftar->map(fn ($s) => [
                'nis'  => (string) $s->nis,
                'nama' => $s->nama_siswa,
            ])->values(),
        ]);
    }

    /**
     * Batasi akses per role, sama seperti filter di index().
     */
    private function pastikanBolehMelihat(kelas $kelas): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(403);
        }

        // Ketua Program: hanya kelas di jurusannya.
        if ($user->role == '4') {
            $ketua = ketua_program::where('username', $user->username)->first();

            if (!$ketua || $ketua->id_jurusan !== $kelas->id_jurusan) {
                abort(403, 'Kelas ini bukan bagian dari program Anda.');
            }
        }

        // Guru BK: hanya kelas yang dipegang.
        if ($user->role == '2') {
            $guru = guru_bk::where('username', $user->username)->first();

            if (!$guru || !in_array($kelas->id_kelas, $guru->kelas()->pluck('kelas.id_kelas')->toArray(), true)) {
                abort(403, 'Kelas ini tidak Anda pegang.');
            }
        }

        // Walikelas: hanya kelasnya sendiri.
        if ($user->role == '3') {
            $wali = walikelas::where('username', $user->username)->first();

            if (!$wali || $wali->id_kelas !== $kelas->id_kelas) {
                abort(403, 'Kelas ini bukan kelas Anda.');
            }
        }
    }

    public function FetchApi()
    {
        $user  = Auth::user();
        $query = kelas::with('jurusan');

        if ($user) {

            // === Role 4: Ketua Program ===
            if ($user->role == '4') {
                $ketua = ketua_program::where('username', $user->username)->first();
                if ($ketua) {
                    $query->where('id_jurusan', $ketua->id_jurusan);
                }
            }

            // === Role 2: Guru BK ===
            if ($user->role == '2') {
                $guru = guru_bk::where('username', $user->username)->first();
                if ($guru) {
                    $kelasIds = $guru->kelas()->pluck('kelas.id_kelas')->toArray();
                    $query->whereIn('id_kelas', $kelasIds);
                }
            }

            // === Role 3: Walikelas ===
            if ($user->role == '3') {
                $wali = walikelas::where('username', $user->username)->first();
                if ($wali) {
                    $query->where('id_kelas', $wali->id_kelas);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil diambil',
            'data'    => $query->get()->each->append(['tingkat', 'label_keahlian', 'kode_keahlian']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id_kelas'   => 'required|string|unique:kelas,id_kelas',
                'nama_kelas' => 'required|string',
                'id_jurusan' => 'required|exists:jurusan,id_jurusan',
            ], [
                'required'          => ':attribute wajib diisi.',
                'id_kelas.unique'   => 'ID kelas sudah dipakai.',
                'id_jurusan.exists' => 'Jurusan tidak valid.',
            ]);

            $data['id_kelas']   = strtoupper(trim($data['id_kelas']));
            $data['nama_kelas'] = kelas::normalisasiNama($data['nama_kelas']);

            if ($error = kelas::validasiNama($data['nama_kelas'], $data['id_jurusan'])) {
                return redirect()->back()->withInput()->with('error', $error);
            }

            kelas::create($data);

            return redirect()->route('kelas')->with('success', 'Kelas berhasil ditambahkan');
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()
                ->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            // id_kelas tidak boleh berubah (dipakai proses naik kelas), jadi tidak diupdate
            $data = $request->validate([
                'nama_kelas' => 'required|string',
                'id_jurusan' => 'required|exists:jurusan,id_jurusan',
            ], [
                'required'          => ':attribute wajib diisi.',
                'id_jurusan.exists' => 'Jurusan tidak valid.',
            ]);

            $data['nama_kelas'] = kelas::normalisasiNama($data['nama_kelas']);

            if ($error = kelas::validasiNama($data['nama_kelas'], $data['id_jurusan'])) {
                return redirect()->back()->withInput()->with('error', $error);
            }

            kelas::where('id_kelas', $id)->update($data);

            return redirect()->route('kelas')->with('success', 'Kelas berhasil diedit');
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()
                ->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function destroy(string $id)
    {
        try {
            // FK siswa.id_kelas & walikelas.id_kelas bersifat ON DELETE CASCADE:
            // tanpa pengecekan ini, menghapus kelas ikut menghapus seluruh siswa
            // (termasuk arsip) beserta penilaian, intervensi, dan catatannya.
            $jumlahSiswa = \Illuminate\Support\Facades\DB::table('siswa')
                ->where('id_kelas', $id)
                ->count();

            if ($jumlahSiswa > 0) {
                return redirect()->back()->with(
                    'error',
                    "Kelas tidak dapat dihapus karena masih memiliki {$jumlahSiswa} siswa (termasuk arsip). Pindahkan siswanya terlebih dahulu."
                );
            }

            if (\Illuminate\Support\Facades\DB::table('walikelas')->where('id_kelas', $id)->exists()) {
                return redirect()->back()->with(
                    'error',
                    'Kelas tidak dapat dihapus karena masih memiliki wali kelas. Pindahkan atau hapus wali kelasnya terlebih dahulu.'
                );
            }

            kelas::where('id_kelas', $id)->delete();

            return redirect()->route('kelas')->with('success', 'Kelas berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}