<?php

namespace App\Http\Controllers;

use App\Exports\Guru_ExportExcel;
use App\Exports\Guru_Template;
use App\Imports\Guru_Import;
use App\Models\guru;
use App\Models\ketua_program;
use App\Support\AkunStaf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Data guru (master data) untuk Wakasek.
 * Terpisah dari data wali kelas. Guru tidak punya akun login;
 * ketua program dipilih dari daftar ini.
 */
class GuruController extends Controller
{
    public function index(Request $request)
    {
        $query = guru::with('kaprog');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $nipBersih = preg_replace('/[\s\-\.]/', '', $search);

            $query->where(function ($q) use ($like, $nipBersih) {
                $q->where('nama_guru', 'like', $like)
                  ->orWhere('nip_guru', 'like', $like);

                if ($nipBersih !== '' && ctype_digit($nipBersih)) {
                    $q->orWhere('nip_guru', 'like', '%' . $nipBersih . '%');
                }
            });
        }

        $guru = $query->orderBy('nama_guru')
            ->paginate(10)
            ->appends($request->only(['search']));

        return view('wakasek.guru.index', compact('guru'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'nip_guru'  => AkunStaf::bersihkanNip($request->input('nip_guru')),
            'nama_guru' => AkunStaf::rapikanNama($request->input('nama_guru')),
        ]);

        $data = $request->validate([
            'nip_guru'  => ['required', 'regex:/^\d{1,18}$/', 'unique:guru,nip_guru'],
            'nama_guru' => ['required', 'string', 'max:255'],
        ], [
            'nip_guru.required'  => 'NIP wajib diisi.',
            'nip_guru.regex'     => 'NIP harus berupa angka (maksimal 18 digit).',
            'nip_guru.unique'    => 'NIP sudah terdaftar pada data guru.',
            'nama_guru.required' => 'Nama guru wajib diisi.',
            'nama_guru.max'      => 'Nama guru maksimal 255 karakter.',
        ]);

        try {
            guru::create($data);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Tambah guru'));
        }

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil ditambahkan.');
    }

    public function update(Request $request, $nip)
    {
        $request->merge([
            'nip_guru'  => AkunStaf::bersihkanNip($request->input('nip_guru')),
            'nama_guru' => AkunStaf::rapikanNama($request->input('nama_guru')),
        ]);

        $data = $request->validate([
            'nip_guru'  => ['required', 'regex:/^\d{1,18}$/'],
            'nama_guru' => ['required', 'string', 'max:255'],
        ], [
            'nip_guru.required'  => 'NIP wajib diisi.',
            'nip_guru.regex'     => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_guru.required' => 'Nama guru wajib diisi.',
            'nama_guru.max'      => 'Nama guru maksimal 255 karakter.',
        ]);

        $guru = guru::where('nip_guru', $nip)->firstOrFail();
        $nipBerubah = (string) $data['nip_guru'] !== (string) $guru->nip_guru;

        if ($nipBerubah) {
            if (guru::where('nip_guru', $data['nip_guru'])->exists()) {
                return redirect()->back()->withInput()
                    ->withErrors(['nip_guru' => 'NIP sudah terdaftar pada data guru.']);
            }

            // NIP ketua program dipakai untuk login, jadi tidak diubah dari sini.
            if (ketua_program::where('nip_kaprog', $guru->nip_guru)->exists()) {
                return redirect()->back()->withInput()
                    ->withErrors(['nip_guru' => 'NIP tidak bisa diubah karena guru ini menjadi ketua program.']);
            }
        }

        try {
            DB::transaction(function () use ($guru, $data) {
                guru::where('nip_guru', $guru->nip_guru)->update([
                    'nip_guru'   => $data['nip_guru'],
                    'nama_guru'  => $data['nama_guru'],
                    'updated_at' => now(),
                ]);

                // Nama pada data ketua program ikut diperbarui.
                ketua_program::where('nip_kaprog', $data['nip_guru'])
                    ->update(['nama_ketua_program' => $data['nama_guru']]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Ubah data guru'));
        }

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil diperbarui.');
    }

    public function destroy($nip)
    {
        try {
            $guru = guru::where('nip_guru', $nip)->firstOrFail();

            if (ketua_program::where('nip_kaprog', $guru->nip_guru)->exists()) {
                return redirect()->back()
                    ->with('error', 'Guru tidak dapat dihapus karena menjadi ketua program. Hapus data ketua program terlebih dahulu.');
            }

            $guru->delete();

            return redirect()->route('guru.index')->with('success', 'Data guru berhasil dihapus.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', AkunStaf::pesanGagal($e, 'Hapus guru'));
        }
    }

    public function export_pdf()
    {
        try {
            $guru = guru::orderBy('nama_guru')->get();

            $pdf = Pdf::loadView('Export.guru.pdf', compact('guru'));
            return $pdf->download('guru.pdf');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', AkunStaf::pesanGagal($e, 'Export PDF guru'));
        }
    }

    public function export_excel()
    {
        try {
            return Excel::download(new Guru_ExportExcel, 'guru.xlsx');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', AkunStaf::pesanGagal($e, 'Export Excel guru'));
        }
    }

    public function template()
    {
        return Excel::download(new Guru_Template, 'Template_Import_Guru.xlsx');
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

            $import = new Guru_Import;
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
        } catch (\Throwable $e) {
            Log::error('Import guru gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }
}