<?php

namespace App\Http\Controllers;

use App\Exports\Siswa_ExportExcel;
use App\Exports\Siswa_Template;
use App\Imports\Siswa_Import;
use App\Models\ActivityLog;
use App\Models\aspek_penilaian;
use App\Models\guru_bk;
use App\Models\intervensi;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\ketua_program;
use App\Models\penghargaan;
use App\Models\penilaian;
use App\Models\siswa;
use App\Models\siswa_penghargaan;
use App\Models\siswa_sp;
use App\Models\surat_peringatan;
use App\Models\walikelas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $jurusanList = jurusan::all();
        $query = siswa::with(['kelas.jurusan']);

        if ($user->role == '2') {
            $guruBk = guru_bk::where('username', $user->username)->first();
            if ($guruBk) {
                $kelasIds = $guruBk->kelas()->pluck('kelas.id_kelas')->toArray();
                if (! empty($kelasIds)) {
                    $query->whereIn('id_kelas', $kelasIds);
                } else {
                    $kelasList = kelas::with('jurusan')->get();
                    $penghargaanList = siswa_penghargaan::all();
                    $siswa = siswa::paginate(10);

                    return view('wakasek.siswa.index', compact('siswa', 'jurusanList', 'kelasList', 'penghargaanList'));
                }
            }
        }

        $ketua = null;
        if ($user->role == '4') {
            $ketua = ketua_program::where('username', $user->username)->first();
            if (! $ketua) {
                abort(403, 'Data Ketua Program tidak ditemukan.');
            }
            $query->whereHas('kelas.jurusan', fn($q) => $q->where('id_jurusan', $ketua->id_jurusan));
        }

        $wali = null;
        if ($user->role == '3') {
            $wali = walikelas::where('username', $user->username)->first();
            if (! $wali) {
                abort(403, 'Data Walikelas tidak ditemukan.');
            }
            $query->where('id_kelas', $wali->id_kelas);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_siswa', 'like', '%' . $search . '%')
                    ->orWhere('nis', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('jurusan') && $user->role != '4') {
            $query->where('id_jurusan', $request->jurusan);
        }

        if ($request->filled('kelas') && $user->role != '3') {
            $query->where('id_kelas', $request->kelas);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($user->role == '4' && $ketua) {
            $kelasList = kelas::with('jurusan')
                ->where('id_jurusan', $ketua->id_jurusan)
                ->get();
        } elseif ($user->role == '3' && $wali) {
            $kelasList = kelas::with('jurusan')
                ->where('id_kelas', $wali->id_kelas)
                ->get();
        } else {
            $kelasList = kelas::with('jurusan')->get();
        }

        $penghargaanList = siswa_penghargaan::all();

        $siswa = $query->orderBy('nama_siswa')->paginate(10)
            ->appends($request->only(['search', 'jurusan', 'kelas', 'status']));

        return view('wakasek.siswa.index', compact('siswa', 'jurusanList', 'kelasList', 'penghargaanList'));
    }

    public function fetchAPI()
    {
        $siswa = siswa::all();

        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil diambil',
            'data' => $siswa,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nis' => 'required|string',
                'nama_siswa' => 'required|string',
                'id_kelas' => 'required',
            ]);

            siswa::create([
                'nis' => $request->nis,
                'nama_siswa' => $request->nama_siswa,
                'id_kelas' => $request->id_kelas,
            ]);

            return redirect()->route('siswa.index')->with('success', 'Siswa berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function Penghargaan(Request $request, string $nis)
    {
        try {
            $request->validate([
                'id_penghargaan' => 'required|string',
            ]);

            siswa_penghargaan::create([
                'nis' => $nis,
                'id_penghargaan' => $request->id_penghargaan,
            ]);

            return redirect()->back()->with('success', 'Penghargaan berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function peringatan(Request $request, string $nis)
    {
        try {
            $request->validate([
                'id_sp' => 'required|string',
            ]);

            siswa_sp::create([
                'nis' => $nis,
                'id_sp' => $request->id_sp,
            ]);

            return redirect()->back()->with('success', 'Surat Peringatan berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function show(string $nis)
    {
        $siswa = siswa::where('nis', $nis)->first();

        if (! $siswa) {
            return redirect()->route('siswa.index')->with('error', 'Siswa tidak ditemukan');
        }

        $penghargaan = penghargaan::all();
        $penghargaanList = siswa_penghargaan::where('nis', $nis)->get();
        $peringatan = surat_peringatan::all();
        $skoringpenghargaan = aspek_penilaian::where('jenis_poin', 'Apresiasi')->get();
        $skoringpelanggaran = aspek_penilaian::where('jenis_poin', 'Pelanggaran')->get();
        $peringatanList = siswa_sp::where('nis', $nis)->get();
        $intervensiList = intervensi::where('nis', $nis)->orderBy('created_at', 'desc')->get();

        $poinPositif = $siswa->poin_apresiasi ?? 0;
        $poinNegatif = $siswa->poin_pelanggaran ?? 0;
        $poinTotal = $siswa->poin_total ?? 0;

        $this->cekPenghargaanOtomatis($siswa, $poinTotal);
        $this->cekSPOtomatis($siswa, $poinTotal);

        $activities = ActivityLog::where('nis', $siswa->nis)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('wakasek.siswa.show', [
            'siswa' => $siswa,
            'kelasList' => kelas::with('jurusan')->get(),
            'activities' => $activities,
            'poinPositif' => $poinPositif,
            'poinNegatif' => $poinNegatif,
            'poinTotal' => $poinTotal,
            'penghargaanList' => $penghargaanList,
            'penghargaan' => $penghargaan,
            'peringatanList' => $peringatanList,
            'peringatan' => $peringatan,
            'skoringpenghargaan' => $skoringpenghargaan,
            'skoringpelanggaran' => $skoringpelanggaran,
            'intervensiList' => $intervensiList,
        ]);
    }

    private function cekPenghargaanOtomatis($siswa, $poinTotal)
    {
        $poinTotal = $poinTotal ?? ($siswa->poin_total ?? 0);

        if ($poinTotal <= -25) {
            siswa_penghargaan::where('nis', $siswa->nis)->delete();

            return;
        }

        $level = null;
        if ($poinTotal >= 151) {
            $level = 'PH3';
        } elseif ($poinTotal >= 126) {
            $level = 'PH2';
        } elseif ($poinTotal >= 100) {
            $level = 'PH1';
        }

        if ($level) {
            $penghargaan = penghargaan::firstOrCreate(
                ['level_penghargaan' => $level],
                ['tanggal_penghargaan' => now(), 'alasan' => 'Penghargaan otomatis – Poin total sesuai rentang']
            );

            if (! siswa_penghargaan::where('nis', $siswa->nis)->where('id_penghargaan', $penghargaan->id_penghargaan)->exists()) {
                siswa_penghargaan::create(['nis' => $siswa->nis, 'id_penghargaan' => $penghargaan->id_penghargaan]);
                ActivityLog::create([
                    'user_id' => Auth::id() ?? 1,
                    'nis' => $siswa->nis,
                    'kategori' => 'Apresiasi',
                    'activity' => 'Penghargaan Otomatis',
                    'description' => "Mendapatkan {$level}",
                    'point' => 0,
                ]);
            }

            $otherPengh = siswa_penghargaan::where('nis', $siswa->nis)
                ->whereHas('penghargaan', fn($q) => $q->where('level_penghargaan', '!=', $level));
            if ($otherPengh->exists()) {
                $otherPengh->delete();
            }
        } else {
            siswa_penghargaan::where('nis', $siswa->nis)->delete();
        }
    }

    private function cekSPOtomatis($siswa, $poinTotal)
    {
        $level = null;
        if ($poinTotal <= -76) {
            $level = 'SP3';
        } elseif ($poinTotal <= -51) {
            $level = 'SP2';
        } elseif ($poinTotal <= -25) {
            $level = 'SP1';
        }

        if ($level) {
            siswa_penghargaan::where('nis', $siswa->nis)->delete();
            $this->buatSP($siswa, $level, 'poin sesuai rentang');

            $otherSP = siswa_sp::where('nis', $siswa->nis)
                ->whereHas('peringatan', fn($q) => $q->where('level_sp', '!=', $level));
            if ($otherSP->exists()) {
                $otherSP->delete();
            }
        } else {
            siswa_sp::where('nis', $siswa->nis)->delete();
        }
    }

    private function buatSP($siswa, $level, $keterangan)
    {
        try {
            $sp = surat_peringatan::firstOrCreate(
                ['level_sp' => $level],
                ['tanggal_sp' => now(), 'alasan' => "Surat Peringatan otomatis – {$keterangan}"]
            );

            if (! siswa_sp::where('nis', $siswa->nis)->where('id_sp', $sp->id_sp)->exists()) {
                siswa_sp::create(['nis' => $siswa->nis, 'id_sp' => $sp->id_sp]);
                ActivityLog::create([
                    'user_id' => Auth::id() ?? 1,
                    'nis' => $siswa->nis,
                    'kategori' => 'Pelanggaran',
                    'activity' => 'Surat Peringatan Otomatis',
                    'description' => "Mendapatkan {$level} ({$keterangan})",
                    'point' => 0,
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function update(Request $request, $nis)
    {
        try {
            $request->validate([
                'nis' => 'required|integer',
                'nama_siswa' => 'required|string',
                'id_kelas' => 'required|string',
            ]);

            $siswa = siswa::where('nis', $nis)->firstOrFail();
            $siswa->update([
                'nis' => $request->nis,
                'nama_siswa' => $request->nama_siswa,
                'id_kelas' => $request->id_kelas,
            ]);

            if ($request->input('redirect_to') === 'show') {
                return redirect()->route('siswa.show', $siswa->nis)->with('success', 'Data berhasil diperbarui.');
            }

            return redirect()->route('siswa.index')->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function destroy(string $nis)
    {
        try {
            $siswa = siswa::where('nis', $nis)->first();

            if (! $siswa) {
                return redirect()->route('siswa.index')->with('error', 'Siswa tidak ditemukan');
            }

            $siswa->delete();

            return redirect()->route('siswa.index')->with('success', 'Siswa berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function arsip(Request $request)
    {
        $query = siswa::onlyTrashed()->with(['kelas.jurusan']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_siswa', 'like', '%'.$search.'%')
                    ->orWhere('nis', 'like', '%'.$search.'%');
            });
        }

        $siswa = $query->orderBy('deleted_at', 'desc')->paginate(10)
            ->appends($request->only(['search']));

        return view('wakasek.siswa.arsip', compact('siswa'));
    }

    public function restore(string $nis)
    {
        try {
            $siswa = siswa::onlyTrashed()->where('nis', $nis)->first();

            if (! $siswa) {
                return redirect()->route('siswa.arsip')->with('error', 'Siswa tidak ditemukan di arsip');
            }

            $siswa->restore();

            return redirect()->route('siswa.arsip')->with('success', 'Siswa berhasil dipulihkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function forceDelete(string $nis)
    {
        try {
            $siswa = siswa::onlyTrashed()->where('nis', $nis)->first();

            if (! $siswa) {
                return redirect()->route('siswa.arsip')->with('error', 'Siswa tidak ditemukan di arsip');
            }

            $siswa->forceDelete();

            return redirect()->route('siswa.arsip')->with('success', 'Siswa berhasil dihapus permanen');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function destroyPenghargaan(string $nis, int $id)
    {
        try {
            $penghargaanList = siswa_penghargaan::where('id', $id)->where('nis', $nis)->first();

            if (! $penghargaanList) {
                return back()->with('error', 'Penghargaan tidak ditemukan');
            }

            $penghargaanList->delete();

            return back()->with('success', 'Penghargaan berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function destroyPeringatan(string $nis, int $id)
    {
        try {
            $peringatanList = siswa_sp::where('id', $id)->where('nis', $nis)->first();

            if (! $peringatanList) {
                return back()->with('error', 'Peringatan tidak ditemukan');
            }

            $peringatanList->delete();

            return back()->with('success', 'Peringatan berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $query = siswa::with(['kelas.jurusan']);

            if ($request->filled('jurusan')) {
                $query->whereHas('kelas.jurusan', function ($q) use ($request) {
                    $q->where('id_jurusan', $request->jurusan);
                });
            }

            if ($request->filled('kelas')) {
                $query->where('id_kelas', $request->kelas);
            }

            $siswa = $query->limit(200)->get();
            $pdf = Pdf::loadView('Export.siswa.pdf', compact('siswa'));

            return $pdf->download('Data_Siswa.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function exportPdfActivity(Request $request, $nis)
    {
        try {
            $siswa = siswa::where('nis', $nis)->firstOrFail();

            $activity = ActivityLog::where('nis', $nis)->orderBy('created_at', 'desc')->get();

            $pdf = Pdf::loadView('Export.siswa.pdfActivity', compact('siswa', 'activity'));

            return $pdf->download('Data_Aktivitas_' . $nis . '.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan : ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $query = siswa::with(['kelas.jurusan']);

            if ($request->filled('jurusan')) {
                $query->whereHas('kelas.jurusan', function ($q) use ($request) {
                    $q->where('id_jurusan', $request->jurusan);
                });
            }

            if ($request->filled('kelas')) {
                $query->where('id_kelas', $request->kelas);
            }

            $siswa = $query->orderBy('id_kelas')->orderBy('nama_siswa')->get();

            return Excel::download(new Siswa_ExportExcel($siswa), 'Data_Siswa.xlsx');
        } catch (\Exception $e) {
            Log::error('Export Excel siswa gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Gagal export Excel: ' . $e->getMessage());
        }
    }

    public function template()
    {
        $kelas = kelas::orderBy('id_kelas')->get();

        return Excel::download(new Siswa_Template($kelas), 'Template_Import_Siswa.xlsx');
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            ], [
                'file.required' => 'Pilih file Excel terlebih dahulu.',
                'file.mimes'    => 'Format file harus .xlsx, .xls, atau .csv.',
                'file.max'      => 'Ukuran file maksimal 10MB.',
            ]);

            $import = new Siswa_Import;
            Excel::import($import, $request->file('file'));

            $redirect = redirect()->back();

            if ($import->hasImported()) {
                $redirect->with('success', 'Import selesai: ' . $import->summary());
            } else {
                $redirect->with('error', 'Tidak ada data yang diimport. ' . $import->summary());
            }

            if (!empty($import->errors)) {
                $redirect->with('import_errors', $import->errors);
            }

            return $redirect;
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first());
        } catch (\Exception $e) {
            Log::error('Import siswa gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    public function naikKelasSemua()
    {
        try {
            $semuaKelas = kelas::all();

            foreach ($semuaKelas as $kelasAsal) {
                $kelasTujuan = null;

                if (str_starts_with($kelasAsal->id_kelas, 'X-')) {
                    $kelasTujuan = str_replace('X-', 'XI-', $kelasAsal->id_kelas);
                } elseif (str_starts_with($kelasAsal->id_kelas, 'XI-')) {
                    $kelasTujuan = str_replace('XI-', 'XII-', $kelasAsal->id_kelas);
                } elseif (str_starts_with($kelasAsal->id_kelas, 'XII-')) {
                    continue;
                }

                $kelasTujuanData = kelas::where('id_kelas', $kelasTujuan)->first();

                if ($kelasTujuanData) {
                    siswa::where('id_kelas', $kelasAsal->id_kelas)
                        ->update(['id_kelas' => $kelasTujuanData->id_kelas]);
                }
            }

            return back()->with('success', 'Semua siswa berhasil dinaikkan kelas');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function skoringPenghargaan(Request $request)
    {
        $request->validate([
            'nis' => 'required',
            'id_aspekpenilaian' => 'required',
        ]);

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($request, $user) {
                // Kunci baris siswa agar request duplikat (double submit) diproses berurutan,
                // sehingga pengecekan duplikat di bawah tidak lolos secara bersamaan.
                $siswa = siswa::where('nis', $request->nis)->lockForUpdate()->first();

                if (!$siswa) {
                    return redirect()->back()->with('error', 'Data siswa tidak ditemukan');
                }

                // Tolak penilaian identik yang dikirim hampir bersamaan (race condition).
                $duplikat = penilaian::where('nis', $request->nis)
                    ->where('id_aspekpenilaian', $request->id_aspekpenilaian)
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->exists();

                if ($duplikat) {
                    return redirect()->route('siswa.show', $request->nis)
                        ->with('error', 'Penghargaan ini baru saja ditambahkan. Mohon tunggu sebentar.');
                }

                $aspek = aspek_penilaian::findOrFail($request->id_aspekpenilaian);
                $skor = (int) $aspek->indikator_poin;
                $uraian = $aspek->uraian;

                penilaian::create([
                    'nis' => $request->nis,
                    'id_aspekpenilaian' => $request->id_aspekpenilaian,
                    'nip_bk' => $user->gurubk->nip_bk ?? null,
                    'nip_walikelas' => null,
                    'nip_wakasek' => $user->wakasek->nip_wakasek ?? null,
                    'created_at' => now(),
                ]);

                $siswa->poin_apresiasi += $skor;
                $siswa->poin_total += $skor;
                $siswa->save();

                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'nis' => $siswa->nis,
                    'kategori' => 'Apresiasi',
                    'activity' => 'Tambah Penghargaan',
                    'description' => $uraian,
                    'point' => $skor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->route('siswa.show', $request->nis)
                    ->with('success', 'Data penghargaan berhasil ditambahkan.');
            });
        } catch (\Exception $e) {
            Log::error('Gagal menambah penghargaan: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function skoringPelanggaran(Request $request)
    {
        try {
            $request->validate([
                'nis' => 'required',
                'id_aspekpenilaian' => 'required',
            ]);

            $user = Auth::user();
            $aspek = aspek_penilaian::findOrFail($request->id_aspekpenilaian);
            $skor = (int) $aspek->indikator_poin;
            $uraian = $aspek->uraian;

            penilaian::create([
                'nis' => $request->nis,
                'id_aspekpenilaian' => $request->id_aspekpenilaian,
                'nip_bk' => $user->gurubk->nip_bk ?? null,
                'nip_walikelas' => $user->walikelas->nip_walikelas ?? null,
                'nip_wakasek' => $user->wakasek->nip_wakasek ?? null,
                'created_at' => now(),
            ]);

            $siswa = siswa::where('nis', $request->nis)->first();

            if ($siswa) {
                $siswa->poin_pelanggaran += $skor;
                $siswa->poin_total -= $skor;
                $siswa->save();

                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'nis' => $siswa->nis,
                    'kategori' => 'Pelanggaran',
                    'activity' => 'Tambah Pelanggaran',
                    'description' => $uraian,
                    'point' => $skor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return redirect()->route('siswa.show', $request->nis)
                ->with('success', 'Data Pelanggaran berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function createPenanganan(Request $request, $nis)
    {
        try {
            $request->validate([
                'nis' => 'required',
                'nama_intervensi' => 'required|string|max:255',
                'isi_intervensi' => 'required|string|max:1000',
                'tanggal_Mulai_Perbaikan' => 'required|date',
                'tanggal_Selesai_Perbaikan' => 'required|date|after_or_equal:tanggal_Mulai_Perbaikan',
                'status' => 'required|string|max:50',
            ]);

            $user = Auth::user();

            intervensi::create([
                'nis' => $request->nis,
                'nip_bk' => $user->gurubk->nip_bk ?? null,
                'nip_walikelas' => $user->walikelas->nip_walikelas ?? null,
                'nip_wakasek' => $user->wakasek->nip_wakasek ?? null,
                'nama_intervensi' => $request->nama_intervensi,
                'isi_intervensi' => $request->isi_intervensi,
                'tanggal_Mulai_Perbaikan' => $request->tanggal_Mulai_Perbaikan,
                'tanggal_Selesai_Perbaikan' => $request->tanggal_Selesai_Perbaikan,
                'status' => $request->status,
                'created_at' => now(),
            ]);

            return redirect()->route('siswa.show', $request->nis)
                ->with('success', 'Data Penanganan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function nonaktif($nis)
    {
        try {
            $siswa = siswa::where('nis', $nis)->firstOrFail();

            if ($siswa->status !== 'aktif') {
                return back()->with('error', 'Siswa sudah tidak aktif');
            }

            $siswa->update([
                'status' => 'nonaktif',
            ]);

            return redirect()->route('siswa.show', $nis)
                ->with('success', 'Siswa berhasil dinonaktifkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}