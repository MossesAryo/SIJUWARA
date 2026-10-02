<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\guru_bk;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

use App\Exports\Guru_Bk_ExportExcel;
use App\Imports\Guru_Bk_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BK_Template;

/**
 * Manajemen akun dan kelas guru BK.
 * CRUD data guru BK, impor/ekspor, serta relasi kelas.
 */
class Guru_bkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         $query = guru_bk::with('kelas');


           // Search (nama atau nis)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_guru_bk', 'like', '%' . $search . '%')
                ->orWhere('nip_bk', 'like', '%' . $search . '%');
            });
        }
          // Paginate — sertakan semua query params yang relevan supaya pagination mempertahankan filter/search
    $guru_bk = $query->orderBy('nama_guru_bk')->paginate(10)
                  ->appends($request->only(['search', 'nip_bk', 'kelas']));


        return view('wakasek.guru_bk.index', [
            'guru_bk' => $guru_bk,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
        $request->validate([
            'nip_bk' => 'required',
            'nama_guru_bk' => 'required|string|max:255',
        ]);

        $user = User::create([
            'username' => $request->nama_guru_bk,
            'email' => strtolower(Str::slug($request->nama_guru_bk)) . '@gmail.com',
            'password' => bcrypt('password'),
            'role' => 2
        ]);

        guru_bk::create([
            'nip_bk' => $request->nip_bk,
            'username' => $request->nama_guru_bk,
            'nama_guru_bk' => $request->nama_guru_bk,
        ]);

        return redirect()->route('gurubk.index')->with('success', 'Guru BK berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function update(Request $request, $nip_bk)
    {
        try {
        $request->validate([
            'nip_bk' => 'required',
            'nama_guru_bk' => 'required',
        ]);

        $bk = guru_bk::where('nip_bk', $nip_bk)->firstOrFail();



        $bk->update([
            'nip_bk' => $request->nip_bk,
            'nama_guru_bk' => $request->nama_guru_bk,
        ]);

        return redirect()->route('gurubk.index')->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
    $bk = guru_bk::findOrFail($id);

    User::where('username', $bk->username)->delete();

    $bk->delete();

    return redirect()->route('gurubk.index')->with('success', 'Guru BK dan user berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_pdf()
    {
        try {
        $guru_bk = guru_bk::all();

        $pdf = PDF::loadView('Export.guru_bk.pdf', compact('guru_bk'));
        return $pdf->download('guru_bk.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_excel()
    {
        try {
        return Excel::download(new Guru_Bk_ExportExcel, 'guru_bk.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function import(Request $request)
    {
        try {
        $guru_bk = guru_bk::all();

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',

        ]);

        Excel::import(new Guru_Bk_Import, $request->file('file'));

        return redirect()->back()->with('success', 'Data Guru BK berhasil diimport!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function template()
    {
        return Excel::download(
         new BK_Template(),
        'Template_Import_Guru_BK.xlsx'
        );
    }
}
