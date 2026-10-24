<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\siswa;
use App\Models\penilaian;
use Illuminate\Http\Request;
use App\Models\aspek_penilaian;
use App\Models\ketua_program;
use App\Models\walikelas;
use App\Models\guru_bk;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Skoring pelanggaran siswa.
 * Hitung/tambah poin pelanggaran dan generate dokumen SP.
 */
class Skoring_PelanggaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = penilaian::whereHas('aspek_penilaian', function ($q) {
            $q->where('jenis_poin', 'Pelanggaran');
        });

        $user = Auth::user();
        $jurusanKetua = null;
        $kelasWalikelas = null;

        // Jika user adalah ketua program (role 3)
        if ($user->role == 4) {
            $ketua = ketua_program::where('username', $user->username)->first();

            if ($ketua && $ketua->id_jurusan) {
                $jurusanKetua = $ketua->id_jurusan;

                // Filter penilaian berdasarkan jurusan ketua program
                $query->whereHas('siswa.kelas', function ($q) use ($jurusanKetua) {
                    $q->where('id_jurusan', $jurusanKetua);
                });
            }
        }

        if ($user->role == 3) {
            $walikelas = walikelas::where('username', $user->username)->first();

            if ($walikelas && $walikelas->id_kelas) {
                $kelasWalikelas = $walikelas->id_kelas;

                // Filter berdasarkan jurusan ketua
                $query->whereHas('siswa.kelas', function ($q) use ($kelasWalikelas) {
                    $q->where('id_kelas', $kelasWalikelas);
                });
            }
        }
        if ($user->role == '2') {
    $guru = guru_bk::where('username', $user->username)->first();

    if ($guru) {
        $kelasIds = $guru->kelas()->pluck('kelas.id_kelas')->toArray();

        $query->whereHas('siswa.kelas', function ($q) use ($kelasIds) {
            $q->whereIn('id_kelas', $kelasIds);
        });
    }
}

        // Filter berdasarkan kelas (tetap berjalan tetapi hanya pada kelas yang masuk jurusan ketua)
        if ($request->filled('kelas')) {
            $query->whereHas('siswa', function ($q) use ($request) {
                $q->where('id_kelas', $request->kelas);
            });
        }

        // Filter tanggal mulai
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->tanggal_mulai);
        }

        // Filter tanggal akhir
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('created_at', '<=', $request->tanggal_akhir);
        }

        // Filter jenis pelanggaran
        if ($request->filled('jenis_pelanggaran')) {
            $query->where('id_aspekpenilaian', $request->jenis_pelanggaran);
        }

        // 🔥 SEARCH — HARUS Lewat Relasi siswa (karena nama_siswa bukan di tabel penilaian)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nis', 'like', '%' . $search . '%')
                    ->orWhere('nama_siswa', 'like', '%' . $search . '%');
            });
        }

        $penilaian = $query->paginate(10)->appends($request->all());

        // Sorting data terbaru
        $query->latest();

        // Tentukan list kelas untuk dropdown filter
        $kelasList = ($jurusanKetua)
            ? kelas::where('id_jurusan', $jurusanKetua)->get()
            : kelas::all();
        $kelasListWalikelas = ($kelasWalikelas)
            ? kelas::where('id_kelas', $kelasWalikelas)->get()
            : kelas::all();

        $siswaList = siswa::with('kelas');

        if ($user->role == 4 && $jurusanKetua) {
            // Kaprog hanya boleh melihat siswa dengan jurusan yang sama
            $siswaList->whereHas('kelas', function ($q) use ($jurusanKetua) {
                $q->where('id_jurusan', $jurusanKetua);
            });
        }
        if ($user->role == 2) {
    $guru = guru_bk::where('username', $user->username)->first();

    if ($guru) {
        $kelasIds = $guru->kelas()->pluck('kelas.id_kelas')->toArray();

        $siswaList->whereIn('id_kelas', $kelasIds);
    }
}

        if ($user->role == 3 && isset($kelasWalikelas)) {
            // Wali kelas hanya boleh melihat siswa kelasnya
            $siswaList->where('id_kelas', $kelasWalikelas);
        }

        $siswaList = $siswaList->orderBy('nama_siswa')->get();


        return view('wakasek.skoring.pelanggaran.index', [
            "penilaian" => $penilaian,
            "siswa"     => $siswaList,
            "aspekPel"  => aspek_penilaian::where('jenis_poin', 'Pelanggaran')->get(),
            "kelas"     => $kelasList,
            "jurusanKetua" => $jurusanKetua,
            "kelasWalikelas"    => $kelasListWalikelas,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nis'               => 'required|exists:siswa,nis',
                'id_aspekpenilaian' => 'required|exists:aspek_penilaian,id_aspekpenilaian',
            ]);

            // Ambil skor & uraian dari aspek_penilaian
            $aspek  = aspek_penilaian::findOrFail($request->id_aspekpenilaian);
            $skor   = (int) $aspek->indikator_poin;
            $uraian = $aspek->uraian;
            $user   = Auth::user();

            // Penilaian, poin siswa, dan log disimpan atomik: gagal sebagian = batal semua.
            DB::transaction(function () use ($request, $user, $skor, $uraian) {
                $siswa = siswa::where('nis', $request->nis)->lockForUpdate()->firstOrFail();

                penilaian::create([
                    'nis'               => $request->nis,
                    'id_aspekpenilaian' => $request->id_aspekpenilaian,
                    'nip_bk'            => $user->gurubk->nip_bk ?? null,
                    'nip_walikelas'     => $user->walikelas->nip_walikelas ?? null,
                    'nip_wakasek'       => $user->wakasek->nip_wakasek ?? null,
                    'created_at'        => now(),
                ]);

                $siswa->poin_pelanggaran = (int) $siswa->poin_pelanggaran + $skor;
                $siswa->poin_total       = (int) $siswa->poin_total - $skor;
                $siswa->save();

                DB::table('activity_logs')->insert([
                    'user_id'     => $user->id,
                    'nis'         => $siswa->nis,
                    'kategori'    => 'Pelanggaran',
                    'activity'    => 'Tambah Pelanggaran',
                    'description' => $uraian,
                    'point'       => $skor,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });

            return redirect()->route('skoring_pelanggaran.index')
                ->with('success', 'Data pelanggaran berhasil ditambahkan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan skoring', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $skoring = penilaian::findOrFail($id);
            $user    = Auth::user();

            DB::transaction(function () use ($skoring, $user) {
                $siswa  = $skoring->siswa;
                $skor   = (int) ($skoring->aspek_penilaian->indikator_poin ?? 0);
                $uraian = $skoring->aspek_penilaian->uraian ?? '-';

                if ($siswa) {
                    $siswa->poin_pelanggaran = (int) $siswa->poin_pelanggaran - $skor;
                    $siswa->poin_total       = (int) $siswa->poin_total + $skor;
                    $siswa->save();

                    DB::table('activity_logs')->insert([
                        'user_id'     => $user->id,
                        'nis'         => $siswa->nis,
                        'kategori'    => 'Pelanggaran',
                        'activity'    => 'Hapus Pelanggaran',
                        'description' => $uraian,
                        'point'       => $skor,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                $skoring->delete();
            });

            return redirect()->back()->with('success', 'Skoring berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus skoring', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}