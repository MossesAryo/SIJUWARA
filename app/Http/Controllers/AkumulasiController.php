<?php

namespace App\Http\Controllers;

// PERBAIKAN: Gunakan huruf besar awal untuk nama Model agar sesuai standar PSR-4
use App\Models\kelas as Kelas;
use App\Models\siswa as Siswa;
use App\Models\walikelas as Walikelas;
use App\Models\ketua_program as Ketua_program; // Sesuaikan jika nama file model berbeda (misal: KetuaProgram.php)
use App\Models\guru_bk as Guru_bk;      // Sesuaikan jika nama file model berbeda (misal: GuruBk.php)
use App\Models\jurusan as Jurusan;      // Sesuaikan jika nama file model berbeda (misal: Jurusan.php)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

// PERBAIKAN: Pastikan ejaan 'Controller' benar (tadi kurang 'r')
class AkumulasiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Menggunakan nama model dengan huruf besar awal
        $jurusanList = Jurusan::all();
        $kelasList   = Kelas::with('jurusan')->get();

        $query = Siswa::with(['kelas.jurusan']);

        // === Guru BK (role 2) ===
        if ($user->role == 2) {
            $guruBk = Guru_bk::where('username', $user->username)->first();

            if ($guruBk) {
                // Asumsi relasi di model Guru_bk bernama 'kelas'
                $kelasIds = $guruBk->kelas()->pluck('id_kelas')->toArray();

                if (!empty($kelasIds)) {
                    $query->whereIn('id_kelas', $kelasIds);
                } else {
                    // Jika guru BK tidak punya kelas, tampilkan kosong atau semua (sesuai kebutuhan)
                    $siswa = collect([]);
                    return view('wakasek.akumulasi.index', compact('siswa', 'jurusanList', 'kelasList'));
                }
            }
        }

        // === Walikelas (role 3) ===
        elseif ($user->role == 3) {
            $walikelas = Walikelas::where('username', $user->username)->first();

            if (!$walikelas) {
                abort(403, "Data Walikelas tidak ditemukan.");
            }

            $query->where('id_kelas', $walikelas->id_kelas);

            // Filter dropdown kelas hanya milik walikelas ini
            $kelasList = Kelas::where('id_kelas', $walikelas->id_kelas)->get();
        }

        // === Ketua Program (role 4) ===
        elseif ($user->role == 4) {
            $ketua = Ketua_program::where('username', $user->username)->first();

            if (!$ketua) {
                abort(403, "Data Ketua Program tidak ditemukan.");
            }

            $query->whereHas('kelas.jurusan', function ($q) use ($ketua) {
                $q->where('id_jurusan', $ketua->id_jurusan);
            });

            $kelasList = Kelas::whereHas('jurusan', function ($q) use ($ketua) {
                $q->where('id_jurusan', $ketua->id_jurusan);
            })->get();
        }

        // Terapkan filter tambahan (search, dll)
        $query = $this->applyRequestFilters($request, $query, $user);

        $siswa = $query->orderBy('nama_siswa')->paginate(10)->appends($request->all());

        return view('wakasek.akumulasi.index', compact('siswa', 'jurusanList', 'kelasList'));
    }

    public function export_pdf(Request $request)
    {
        try {
            ini_set('memory_limit', '512M');

            $user  = Auth::user();
            $query = Siswa::with(['kelas.jurusan']);

            // Gunakan helper scope role agar data yang di-export sama dengan yang dilihat user
            $query = $this->applyRoleScope($query, $user);
            $query = $this->applyRequestFilters($request, $query, $user);

            $akumulasi = $query->limit(500)->get(); // Limit diperbesar sedikit untuk export

            $pdf = Pdf::loadView('Export.akumulasi.pdf', compact('akumulasi'));
            return $pdf->download('laporan_akumulasi.pdf');
        } catch (\Exception $e) {
            // Debugging: Uncomment baris bawah untuk melihat error asli jika PDF gagal
            // throw $e;
            return redirect()->back()->with('error', 'Gagal membuat PDF: ' . $e->getMessage());
        }
    }

    public function export_excel(Request $request) // PERBAIKAN: Nama method lowercase semua agar aman
    {
        try {
            $user  = Auth::user();
            $query = Siswa::with(['kelas.jurusan']);

            $query = $this->applyRoleScope($query, $user);
            $query = $this->applyRequestFilters($request, $query, $user);

            $akumulasi = $query->get();

            // Pastikan class Export Excel ini sudah ada di app/Exports/AkumulasiExport.php
            // Jika belum ada, buat dulu atau comment baris ini sementara
            return Excel::download(new \App\Exports\Akumulasi_ExportExcel($akumulasi), 'akumulasi.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat Excel: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint API (mobile): daftar akumulasi poin siswa sesuai role pengguna.
     * GET /api/akumulasi?search=&kelas=&jurusan=&per_page=
     */
    public function fetchAPI(Request $request)
    {
        $user  = Auth::user();
        $query = Siswa::with(['kelas.jurusan']);

        $query = $this->applyRoleScope($query, $user);
        $query = $this->applyRequestFilters($request, $query, $user);

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        $data = $query->orderBy('nama_siswa')->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Data akumulasi berhasil diambil',
            'data'    => $data,
        ], 200);
    }

    // Helper untuk Role Scope (digunakan di export)
    private function applyRoleScope($query, $user)
    {
        if ($user->role == 2) {
            $guruBk = Guru_bk::where('username', $user->username)->first();
            if ($guruBk) {
                $kelasIds = $guruBk->kelas()->pluck('id_kelas')->toArray();
                if (!empty($kelasIds)) {
                    $query->whereIn('id_kelas', $kelasIds);
                }
            }
        } elseif ($user->role == 3) {
            $walikelas = Walikelas::where('username', $user->username)->first();
            if ($walikelas) {
                $query->where('id_kelas', $walikelas->id_kelas);
            }
        } elseif ($user->role == 4) {
            $ketua = Ketua_program::where('username', $user->username)->first();
            if ($ketua) {
                $query->whereHas('kelas.jurusan', fn($q) => $q->where('id_jurusan', $ketua->id_jurusan));
            }
        }
        return $query;
    }

    // Helper untuk Filter Request
    private function applyRequestFilters(Request $request, $query, $user)
    {
        if ($request->filled('jurusan') && $user->role != 4) {
            $query->whereHas('kelas.jurusan', fn($q) => $q->where('id_jurusan', $request->jurusan));
        }

        if ($request->filled('kelas') && $user->role != 3) {
            $query->where('id_kelas', $request->kelas);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nis', 'like', '%' . $search . '%')
                  ->orWhere('nama_siswa', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }
}
