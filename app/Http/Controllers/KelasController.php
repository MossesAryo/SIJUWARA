<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\jurusan;
use App\Models\guru_bk;
use App\Models\walikelas;
use Illuminate\Http\Request;
use App\Models\ketua_program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen data kelas beserta jurusan.
 * CRUD kelas, impor/ekspor, dan relasi wali atau guru BK.
 *
 * Kelas X = Program Keahlian, kelas XI/XII = Kompetensi Keahlian
 * (lihat konstanta di model kelas).
 */
class KelasController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $jurusanList = jurusan::all();
        $query       = kelas::with('jurusan')
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
                    switch ($tingkat) {
                        case 'X':
                            $q->orWhere('nama_kelas', 'REGEXP', '^X ');
                            break;
                        case 'XI':
                            $q->orWhere('nama_kelas', 'LIKE', 'XI %');
                            break;
                        case 'XII':
                            $q->orWhere('nama_kelas', 'LIKE', 'XII %');
                            break;
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
                        WHEN nama_kelas LIKE 'X %' THEN 1
                        WHEN nama_kelas LIKE 'XI %' THEN 2
                        WHEN nama_kelas LIKE 'XII %' THEN 3
                        ELSE 4 END")
                        ->orderBy('nama_kelas', 'asc');
                    break;
                case 'tingkat_desc':
                    $query->orderByRaw("CASE
                        WHEN nama_kelas LIKE 'XII %' THEN 1
                        WHEN nama_kelas LIKE 'XI %' THEN 2
                        WHEN nama_kelas LIKE 'X %' THEN 3
                        ELSE 4 END")
                        ->orderBy('nama_kelas', 'asc');
                    break;
            }
        } else {
            $query->orderByRaw("CASE
                WHEN nama_kelas LIKE 'X %' THEN 1
                WHEN nama_kelas LIKE 'XI %' THEN 2
                WHEN nama_kelas LIKE 'XII %' THEN 3
                ELSE 4 END")
                ->orderBy('nama_kelas', 'asc');
        }

        $kelas = $query->paginate(10)->appends($request->all());

        return view('wakasek.kelas.kelas', compact('kelas', 'jurusanList'));
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
            kelas::where('id_kelas', $id)->delete();

            return redirect()->route('kelas')->with('success', 'Kelas berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}