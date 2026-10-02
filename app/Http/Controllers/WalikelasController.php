<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\walikelas;
use App\Models\kelas;
use App\Models\User;

use App\Exports\Walikelas_ExportExcel;
use App\Exports\Walikelas_Template; // <-- Ditambahkan di sini
use App\Imports\Walikelas_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Manajemen data wali kelas.
 * CRUD wali kelas dan kaitan dengan kelas.
 */
class WalikelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $kelas = kelas::all();
        $user = User::all();

        $query = walikelas::with('kelas');

        // Search (nama atau nis)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_walikelas', 'like', '%' . $search . '%')
                  ->orWhere('nip_walikelas', 'like', '%' . $search . '%')
                  ->orWhere('id_kelas', 'like', '%' . $search . '%');
            });
        }

        // Filter kelas spesifik
        if ($request->filled('kelas')) {
            $query->where('id_kelas', $request->kelas);
        }

        // Paginate — sertakan semua query params yang relevan supaya pagination mempertahankan filter/search
        $walikelas = $query->orderBy('nama_walikelas')->paginate(10)
                      ->appends($request->only(['search', 'nip_walikelas', 'kelas', 'id_kelas']));

        return view('wakasek.walikelas.index', compact('walikelas', 'kelas', 'user'));
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
                'nip_walikelas' => 'required',
                'nama_walikelas' => 'required',
                'id_kelas' => 'required',
            ]);

            // Buat user baru
            $user = User::create([
                'username' => $request->nama_walikelas,
                'email' => $request->nama_walikelas . '@gmail.com',
                'password' => bcrypt('password'), // Gantilah dengan password yang sesuai
                'role' => 3,
            ]);

            Walikelas::create([
                'nip_walikelas' => $request->nip_walikelas,
                'username' => $user->username,
                'nama_walikelas' => $request->nama_walikelas,
                'id_kelas' => $request->id_kelas,
            ]);

            return redirect()->route('walikelas.index')->with('success', 'Data walikelas berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($nip_walikelas) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $nip_walikelas, $username)
    {
        try {
            $request->validate([
                'nip_walikelas' => 'required|integer',
                'username' => 'required|string',
                'id_kelas' => 'required|string',
                'nama_walikelas' => 'required|string',
            ]);

            $walikelas = Walikelas::where('nip_walikelas', $nip_walikelas)->firstOrFail();
            $user = User::where('username', $username)->first();

            $user->update([
                'username' => $request->username,
                'email' => $request->nama_walikelas . '@gmail.com',
            ]);

            $walikelas->update([
                'nip_walikelas' => $request->nip_walikelas,
                'username' => $user->username, // Tetap gunakan username yang sudah ada
                'nama_walikelas' => $request->nama_walikelas,
                'id_kelas' => $request->id_kelas,
            ]);

            return redirect()->route('walikelas.index')->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($nip_walikelas)
    {
        try {
            $walikelas = Walikelas::where('nip_walikelas', $nip_walikelas)->firstOrFail();
            $user = User::where('username', $walikelas->username)->first();
            
            if ($user) {
                $user->delete();
            }
            $walikelas->delete();

            return redirect()->route('walikelas.index')->with('success', 'Data Walikelas berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_pdf()
    {
        try {
            $walikelas = walikelas::all();

            $pdf = PDF::loadView('Export.walikelas.pdf', compact('walikelas'));
            return $pdf->download('walikelas.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_excel()
    {
        try {
            return Excel::download(new Walikelas_ExportExcel, 'walikelas.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    /**
     * Download template import walikelas
     */
    public function template()
    {
        $kelas = kelas::orderBy('id_kelas')->get();

        return Excel::download(new Walikelas_Template($kelas), 'Template_Import_Walikelas.xlsx');
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

            $import = new Walikelas_Import;
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
            Log::error('Import walikelas gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }
}