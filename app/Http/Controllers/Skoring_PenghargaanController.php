<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\siswa;
use App\Models\guru_bk;
use App\Models\penilaian;
use Illuminate\Http\Request;
use App\Models\aspek_penilaian;
use App\Models\ketua_program;
use App\Models\walikelas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Skoring penghargaan siswa.
 * Tambah poin apresiasi dan laporan penghargaan.
 */
class Skoring_PenghargaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $jurusanKetua = null;
        $kelasWalikelas = null;

        // Query awal: hanya aspek Apresiasi
        $query = penilaian::whereHas('aspek_penilaian', function ($q) {
            $q->where('jenis_poin', 'Apresiasi');
        })->with(['siswa.kelas', 'aspek_penilaian']);

        // Jika user adalah ketua program
        if ($user->role == 4) {
            $ketua = ketua_program::where('username', $user->username)->first();

            if ($ketua && $ketua->id_jurusan) {
                $jurusanKetua = $ketua->id_jurusan;

                // Filter berdasarkan jurusan ketua
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
        if ($user->role == 2) {
    $guru = guru_bk::where('username', $user->username)->first();

    if ($guru) {
        $kelasIds = $guru->kelas()->pluck('kelas.id_kelas')->toArray();

        $query->whereHas('siswa.kelas', function ($q) use ($kelasIds) {
            $q->whereIn('id_kelas', $kelasIds);
        });
    }
}

        // Filter kelas
        if ($request->filled('kelas')) {
            $query->whereHas('siswa', function ($q) use ($request) {
                $q->where('id_kelas', $request->kelas);
            });
        }

        // Filter range tanggal
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('created_at', '<=', $request->tanggal_akhir);
        }

        // Filter jenis penghargaan
        if ($request->filled('jenis_penghargaan')) {
            $query->where('id_aspekpenilaian', $request->jenis_penghargaan);
        }

        // 🔥 SEARCH — HARUS Lewat Relasi siswa (karena nama_siswa bukan di tabel penilaian)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nis', 'like', '%' . $search . '%')
                    ->orWhere('nama_siswa', 'like', '%' . $search . '%');
            });
        }

        // Urutan data (terbaru dulu)
        $query->latest();

        $penilaian = $query->paginate(10)->appends($request->all());

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

        return view('wakasek.skoring.penghargaan.index', [
            "penilaian"    => $penilaian,
            "siswa"        => $siswaList,
            "aspekPel"     => aspek_penilaian::where('jenis_poin', 'Apresiasi')->get(),
            "kelas"        => $kelasList,
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

                $siswa->poin_apresiasi = (int) $siswa->poin_apresiasi + $skor;
                $siswa->poin_total      = (int) $siswa->poin_total + $skor;
                $siswa->save();

                DB::table('activity_logs')->insert([
                    'user_id'     => $user->id,
                    'nis'         => $siswa->nis,
                    'kategori'    => 'Apresiasi',
                    'activity'    => 'Tambah Penghargaan',
                    'description' => $uraian,
                    'point'       => $skor,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });

            return redirect()->route('skoring_penghargaan.index')
                ->with('success', 'Data penghargaan berhasil ditambahkan.');
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
                    $siswa->poin_apresiasi = (int) $siswa->poin_apresiasi - $skor;
                    $siswa->poin_total      = (int) $siswa->poin_total - $skor;
                    $siswa->save();

                    DB::table('activity_logs')->insert([
                        'user_id'     => $user->id,
                        'nis'         => $siswa->nis,
                        'kategori'    => 'Apresiasi',
                        'activity'    => 'Hapus Penghargaan',
                        'description' => $uraian,
                        'point'       => $skor,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                $skoring->delete();
            });

            return redirect()->back()->with('success', 'Penghargaan berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus skoring', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}