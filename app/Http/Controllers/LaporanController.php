<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\penilaian;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanSkoringExport;
use Illuminate\Support\Facades\Auth;
use App\Models\user;
use App\Models\ketua_program;
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $jurusanKetua = null;
        $walikelasId = null;

        if ($user->role == 3) {

            $ketua = ketua_program::where('username', $user->username)->first();

            if (!$ketua) {
                abort(403, "Data Ketua Program tidak ditemukan.");
            }

            $jurusanKetua = $ketua->jurusan;

            $kelas = kelas::where('jurusan', $jurusanKetua)->get();

        } else {

            $kelas = kelas::all();
        }

        if ($user->role == 4) {
            $walikelas = \App\Models\walikelas::where('username', $user->username)->first();
            if ($walikelas && $walikelas->id_kelas) {
                $walikelasId = $walikelas->id_kelas;
                $kelas = kelas::where('id_kelas', $walikelasId)->get();
            } else {
                $walikelasId = null;
            }
        }

        return view('wakasek.laporan.index', compact('kelas', 'walikelasId'));
    }

    public function exportPdf(Request $request)
    {
        try {
            $type      = $request->query('type');
            $kelas     = $request->query('kelas');
            $tingkat   = $request->query('tingkat');
            $jurusan   = $request->query('jurusan');
            $startDate = $request->query('start_date');
            $endDate   = $request->query('end_date');

            $query = penilaian::with(['siswa.kelas', 'aspek_penilaian'])
                ->whereHas('aspek_penilaian', function ($q) use ($type) {
                    $q->where('jenis_poin', $type === 'pelanggaran' ? 'Pelanggaran' : 'Apresiasi');
                });

            if ($kelas) {
                $query->whereHas('siswa.kelas', fn($q) => $q->where('id_kelas', $kelas));
            }

            if ($tingkat) {
                $query->whereHas('siswa.kelas', fn($q) => $q->where('tingkat', $tingkat));
            }

            if ($jurusan && !$kelas) {
                $query->whereHas('siswa.kelas', fn($q) => $q->where('jurusan', $jurusan));
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            $user = Auth::user();
            if (!$startDate && !$endDate && $user && $user->role == 4) {
                $walikelas = \App\Models\walikelas::where('username', $user->username)->first();
                if ($walikelas && $walikelas->id_kelas) {
                    $query->whereHas('siswa.kelas', fn($q) => $q->where('id_kelas', $walikelas->id_kelas));
                    $kelas = $walikelas->id_kelas;
                }
            }

            $data = $query->get();

            $kelasNama     = 'Semua Kelas';
            $keahlianLabel = 'Program / Kompetensi Keahlian';
            $keahlianNama  = 'Semua';

            if (!$kelas && in_array($tingkat, ['X', 'XI', 'XII'], true)) {
                $keahlianLabel = \App\Models\kelas::labelKeahlian($tingkat);
            }

            if ($kelas) {
                $kelasModel = \App\Models\kelas::find($kelas);
                if ($kelasModel) {
                    $kelasNama     = $kelasModel->nama_kelas;
                    $keahlianLabel = $kelasModel->label_keahlian;
                    $keahlianNama  = $kelasModel->kode_keahlian;
                }
            } elseif ($jurusan) {
                $j = strtoupper($jurusan);

                if (in_array($tingkat, ['X', 'XI', 'XII'], true)) {
                    $keahlianNama = \App\Models\kelas::kodeKeahlian($j, $tingkat) ?? $j;
                } else {
                    $keahlianNama = \App\Models\kelas::ringkasanKode($j) ?: $j;
                }
            }

            $pdf = Pdf::loadView('Export.laporan.laporan', [
                'data'          => $data,
                'type'          => $type,
                'kelas'         => $kelasNama,
                'tingkat'       => $tingkat ?: 'Semua Tingkat',
                'jurusan'       => $keahlianNama,
                'labelKeahlian' => $keahlianLabel,
                'startDate'     => $startDate,
                'endDate'       => $endDate,
            ]);

            $fileName = 'laporan_' . $type;
            if ($startDate && $endDate) {
                $fileName .= '_' . $startDate . '_to_' . $endDate;
            }
            $fileName .= '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $type = $request->query('type');
            $kelas = $request->query('kelas');
            $tingkat = $request->query('tingkat');
            $jurusan = $request->query('jurusan');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $user = Auth::user();
            if (!$startDate && !$endDate && $user && $user->role == 4) {
                $walikelas = \App\Models\walikelas::where('username', $user->username)->first();
                if ($walikelas && $walikelas->id_kelas) {
                    $kelas = $walikelas->id_kelas;
                }
            }

            $fileName = 'laporan_' . $type;
            if ($startDate && $endDate) {
                $fileName .= '_' . $startDate . '_to_' . $endDate;
            }
            $fileName .= '.xlsx';

            return Excel::download(
                new LaporanSkoringExport($type, $kelas, $tingkat, $jurusan, $startDate, $endDate),
                $fileName
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}