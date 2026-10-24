<?php

namespace App\Http\Controllers;

use App\Exports\Aspek_Penghargaan_ExportExcel;
use App\Imports\Aspek_Penghargaan_Import;
use App\Exports\Aspek_Pelanggaran_ExportExcel;
use App\Imports\Aspek_Pelanggaran_Import;
use App\Models\aspek_penilaian;
use Illuminate\Http\Request;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Mengelola API aspek penilaian (kategori poin).
 * CRUD jenis apresiasi dan pelanggaran yang dipakai skoring.
 */
class Aspek_penilaianController extends Controller
{
    /**
     * Display a listing of the resource.
     */


    public function FetchApi()
    {
        try {
        $aspek_penilaian = aspek_penilaian::all();

        return response()->json([
            'success' => true,
            'message' => 'Data Aspek Penilaian berhasil diambil',
            'data'    => $aspek_penilaian
        ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
            ], 500);
        }
    }





    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        if ($pesan = $this->aspekDipakai($id)) {
            return redirect()->back()->with('error', $pesan);
        }
        aspek_penilaian::findOrFail($id)->delete();

        return redirect()->route('aspekpenilaian')->with('success', 'Aspek Penilaian berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }




    public function indexPenghargaan(Request $request)
    {
        $query = aspek_penilaian::where('jenis_poin', 'Apresiasi');
         $kategoriList = aspek_penilaian::where('jenis_poin', 'Apresiasi')
        ->select('kategori')
        ->distinct()
        ->get();


        if ($request->filled('kategori')) {
            $query->where('kategori', 'LIKE', '%' . $request->kategori . '%');
        }

         // GLOBAL SEARCH
    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('id_aspekpenilaian', 'like', "%$search%")
              ->orWhere('kategori', 'like', "%$search%")
              ->orWhere('uraian', 'like', "%$search%")
              ->orWhereRaw("CAST(indikator_poin AS CHAR) LIKE ?", ["%$search%"]);
        });
    }

    $aspek_penilaian = $query->paginate(10)->appends($request->all());

        return view('wakasek.aspek_penilaian.aspek_penghargaan.index', compact('aspek_penilaian', 'kategoriList'));
    }

    public function storePenghargaan(Request $request)
    {
        try {
        $request->validate([


            'kategori' => 'required',
            'uraian' => 'required',
            'indikator_poin' => 'required',
        ]);

        aspek_penilaian::create([

            'jenis_poin' => 'Apresiasi',
            'kategori' => $request->kategori,
            'uraian' => $request->uraian,
            'indikator_poin' => $request->indikator_poin,

        ]);


        return redirect()->route('aspek_penghargaan.index')->with('success', 'Aspek Penilaian berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function updatePenghargaan(Request $request, string $id)
    {
        try {
        $data = $request->validate([


            'kategori' => 'required',
            'uraian' => 'required',
            'indikator_poin' => 'required',
        ]);

        $aspek_penilaian = aspek_penilaian::where('id_aspekpenilaian', $id)->firstOrFail();

        $aspek_penilaian->update([

            'jenis_poin' => 'Apresiasi',
            'kategori' => $data['kategori'],
            'uraian' => $data['uraian'],
            'indikator_poin' => $data['indikator_poin'],
        ]);

        return redirect()->route('aspek_penghargaan.index')->with('success', 'Aspek Penilaian berhasil diedit');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroyPenghargaan(string $id)
    {
        try {
        if ($pesan = $this->aspekDipakai($id)) {
            return redirect()->back()->with('error', $pesan);
        }
        aspek_penilaian::findOrFail($id)->delete();

        return redirect()->route('aspek_penghargaan.index')->with('success', 'Aspek Penilaian berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_pdf()
    {
        try {
        $aspek_penilaian = aspek_penilaian::where('jenis_poin', 'Apresiasi')->get();

        $pdf = PDF::loadView('Export.aspek_penghargaan.pdf', compact('aspek_penilaian'));
        return $pdf->download('aspek_penghargaan.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_excel()
    {
        try {
        return Excel::download(new Aspek_Penghargaan_ExportExcel, 'aspek_penghargaan.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function import(Request $request)
    {
        try {
        $aspek_penilaian = aspek_penilaian::where('jenis_poin', 'Apresiasi')->get();


        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',

        ]);

        Excel::import(new Aspek_Penghargaan_Import, $request->file('file'));

        return redirect()->back()->with('success', 'Data Aspek Penghargaan berhasil diimport!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }





        public function indexPelanggaran(Request $request)
    {
        $query = aspek_penilaian::where('jenis_poin', 'Pelanggaran');
        $kategoriList = aspek_penilaian::where('jenis_poin', 'Pelanggaran')
            ->select('kategori')
            ->distinct()
            ->get();
            

        if ($request->filled('kategori')) {
            $query->where('kategori', 'like', '%' . $request->kategori . '%');
        }

        if ($request->filled('pelanggaran_ke')) {
            $query->where('pelanggaran_ke', $request->pelanggaran_ke);
        }

        // GLOBAL SEARCH
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('id_aspekpenilaian', 'like', "%$search%")
                ->orWhere('kategori', 'like', "%$search%")
                ->orWhere('uraian', 'like', "%$search%")
                ->orWhere('pelanggaran_ke', 'like', "%$search%")
                ->orWhereRaw("CAST(indikator_poin AS CHAR) LIKE ?", ["%$search%"]);
            });
        }

        $aspek_penilaian = $query->paginate(10)->appends($request->all());

        return view('wakasek.aspek_penilaian.aspek_pelanggaran.index', compact('aspek_penilaian', 'kategoriList'));
    }




    public function storePelanggaran(Request $request)
    {
        try {
        $request->validate([

            'kategori' => 'required',
            'uraian' => 'required',
            'indikator_poin' => 'required',
        ]);

        aspek_penilaian::create([

            'jenis_poin' => 'Pelanggaran',
            'kategori' => $request->kategori,
            'uraian' => $request->uraian,
            'pelanggaran_ke' => $request->pelanggaran_ke,
            'indikator_poin' => $request->indikator_poin,

        ]);


        return redirect()->route('aspek_pelanggaran.index')->with('success', 'Aspek Penilaian berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function updatePelanggaran(Request $request, string $id)
    {
        try {
        $data = $request->validate([


            'kategori' => 'required',
            'uraian' => 'required',
            'pelanggaran_ke' => 'required',
            'indikator_poin' => 'required',
        ]);

        $aspek_penilaian = aspek_penilaian::where('id_aspekpenilaian', $id)->firstOrFail();

        $aspek_penilaian->update([

            'jenis_poin' => 'Pelanggaran',
            'kategori' => $data['kategori'],
            'uraian' => $data['uraian'],
            'pelanggaran_ke' => $data['pelanggaran_ke'],
            'indikator_poin' => $data['indikator_poin'],
        ]);

        return redirect()->route('aspek_pelanggaran.index')->with('success', 'Aspek Penilaian berhasil diedit');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroyPelanggaran(string $id)
    {
        try {
        if ($pesan = $this->aspekDipakai($id)) {
            return redirect()->back()->with('error', $pesan);
        }
        aspek_penilaian::findOrFail($id)->delete();

        return redirect()->route('aspek_pelanggaran.index')->with('success', 'Aspek Penilaian berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }



    public function export_pelanggaran_pdf()
    {
        try {
        $aspek_penilaian = aspek_penilaian::where('jenis_poin', 'Pelanggaran')->get();

        $pdf = PDF::loadView('Export.aspek_pelanggaran.pdf', compact('aspek_penilaian'));
        return $pdf->download('aspek_pelanggaran.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_pelanggaran_excel()
    {
        try {
        return Excel::download(new Aspek_Pelanggaran_ExportExcel, 'aspek_pelanggaran.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function import_pelanggaran(Request $request)
    {
        try {
        $aspek_penilaian = aspek_penilaian::where('jenis_poin', 'Pelanggaran')->get();


        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',

        ]);

        Excel::import(new Aspek_Pelanggaran_Import, $request->file('file'));

        return redirect()->back()->with('success', 'Data Aspek Pelanggaran berhasil diimport!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * FK penilaian.id_aspekpenilaian bersifat ON DELETE CASCADE: menghapus aspek yang sudah
     * dipakai akan menghapus riwayat penilaian siswa tanpa mengoreksi poin_total mereka.
     */
    private function aspekDipakai($id): ?string
    {
        $jumlah = \Illuminate\Support\Facades\DB::table('penilaian')->where('id_aspekpenilaian', $id)->count();

        return $jumlah > 0
            ? "Aspek tidak dapat dihapus karena sudah dipakai pada {$jumlah} data penilaian siswa."
            : null;
    }
}