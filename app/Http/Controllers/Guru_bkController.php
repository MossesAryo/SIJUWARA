<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\guru_bk;
use App\Support\AkunStaf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Search (nama atau nip)
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
        $request->merge([
            'nip_bk'       => AkunStaf::bersihkanNip($request->input('nip_bk')),
            'nama_guru_bk' => AkunStaf::rapikanNama($request->input('nama_guru_bk')),
        ]);

        // Validasi di luar try/catch supaya pesan per kolom tampil di form.
        $data = $request->validate([
            'nip_bk' => [
                'required',
                'regex:/^\d{1,18}$/',
                function ($attribute, $value, \Closure $fail) {
                    if (AkunStaf::nipTerpakai((string) $value)) {
                        $fail('NIP sudah terdaftar pada data guru/pegawai lain.');
                    }
                },
            ],
            'nama_guru_bk' => ['required', 'string', 'max:255'],
        ], [
            'nip_bk.required'       => 'NIP wajib diisi.',
            'nip_bk.regex'          => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_guru_bk.required' => 'Nama guru BK wajib diisi.',
            'nama_guru_bk.max'      => 'Nama guru BK maksimal 255 karakter.',
        ]);

        try {
            // Akun user dan data guru BK disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($data) {
                $user = AkunStaf::buatUser(
                    $data['nama_guru_bk'],
                    $data['nip_bk'],
                    User::ROLE_GURU_BK
                );

                guru_bk::create([
                    'nip_bk'       => $data['nip_bk'],
                    'username'     => $user->username,
                    'nama_guru_bk' => $data['nama_guru_bk'],
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Tambah guru BK'));
        }

        return redirect()->route('gurubk.index')
            ->with('success', 'Guru BK berhasil ditambahkan. Login dengan NIP, password awal: ' . AkunStaf::PASSWORD_DEFAULT);
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

            // FK penilaian & intervensi ON DELETE CASCADE: jangan hapus riwayat secara tak sengaja.
            $riwayat = DB::table('penilaian')->where('nip_bk', $bk->nip_bk)->count()
                + DB::table('intervensi')->where('nip_bk', $bk->nip_bk)->count();
            if ($riwayat > 0) {
                return redirect()->back()->with('error', "Guru BK tidak dapat dihapus karena memiliki {$riwayat} riwayat penilaian/intervensi.");
            }

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