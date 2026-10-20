<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ketua_program;
use App\Models\jurusan;
use App\Support\AkunStaf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Exports\Ketua_Program_ExportExcel;
use App\Imports\Ketua_Program_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Ketua_Program_Template;

/**
 * Kelola data ketua program.
 * CRUD akun ketua program dan keterkaitan jurusan.
 */
class KetuaProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = ketua_program::with('jurusan');

        if ($request->filled('jurusan')) {
            $query->where('id_jurusan', $request->jurusan);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_ketua_program', 'like', "%{$request->search}%")
                  ->orWhere('nip_kaprog', 'like', "%{$request->search}%");
            });
        }

        $ketua_program = $query->orderBy('id_jurusan')
                              ->orderBy('nama_ketua_program')
                              ->paginate(10)
                              ->appends($request->only(['jurusan', 'search']));
        $daftar_jurusan = jurusan::all();

        return view('wakasek.kaprog.index', compact('ketua_program', 'daftar_jurusan'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'nip_kaprog'         => AkunStaf::bersihkanNip($request->input('nip_kaprog')),
            'nama_ketua_program' => AkunStaf::rapikanNama($request->input('nama_ketua_program')),
        ]);

        // Validasi di luar try/catch supaya pesan per kolom tampil di form.
        $data = $request->validate([
            'nip_kaprog' => [
                'required',
                'regex:/^\d{1,18}$/',
                function ($attribute, $value, \Closure $fail) {
                    if (AkunStaf::nipTerpakai((string) $value)) {
                        $fail('NIP sudah terdaftar pada data guru/pegawai lain.');
                    }
                },
            ],
            'nama_ketua_program' => ['required', 'string', 'max:255'],
            'id_jurusan'         => ['required', Rule::exists('jurusan', 'id_jurusan')],
        ], [
            'nip_kaprog.required'         => 'NIP wajib diisi.',
            'nip_kaprog.regex'            => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_ketua_program.required' => 'Nama ketua program wajib diisi.',
            'nama_ketua_program.max'      => 'Nama ketua program maksimal 255 karakter.',
            'id_jurusan.required'         => 'Program / kompetensi keahlian wajib dipilih.',
            'id_jurusan.exists'           => 'Program / kompetensi keahlian tidak valid.',
        ]);

        try {
            // Akun user dan data kaprog disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($data) {
                $user = AkunStaf::buatUser(
                    $data['nama_ketua_program'],
                    $data['nip_kaprog'],
                    User::ROLE_KETUA_PROGRAM
                );

                ketua_program::create([
                    'nip_kaprog'         => $data['nip_kaprog'],
                    'nama_ketua_program' => $data['nama_ketua_program'],
                    'id_jurusan'         => $data['id_jurusan'],
                    'username'           => $user->username,
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Tambah ketua program'));
        }

        return redirect()->route('kaprog.index')
            ->with('success', 'Data Ketua Program berhasil disimpan. Login dengan NIP, password awal: ' . AkunStaf::PASSWORD_DEFAULT);
    }

    public function edit($nip_kaprog)
    {
        $kp = ketua_program::where('nip_kaprog', $nip_kaprog)->firstOrFail();
        $users = User::all();
        $daftar_jurusan = jurusan::pluck('nama_jurusan', 'id_jurusan');

        return view('wakasek.kaprog.edit', compact('kp', 'users', 'daftar_jurusan'));
    }

    public function update(Request $request, $nip_kaprog)
    {
        try {
            $request->validate([
                'nip_kaprog' => 'required|unique:ketua_program,nip_kaprog,' . $nip_kaprog . ',nip_kaprog',
                'nama_ketua_program' => 'required|string|max:255',
                'id_jurusan' => 'required',
            ]);

            $kp = ketua_program::where('nip_kaprog', $nip_kaprog)->firstOrFail();

            $kp->update([
                'nip_kaprog' => $request->nip_kaprog,
                'nama_ketua_program' => $request->nama_ketua_program,
                'id_jurusan' => $request->id_jurusan,
            ]);

            return redirect()->route('kaprog.index')->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function destroy($nip_kaprog)
    {
        try {
            $kp = ketua_program::where('nip_kaprog', $nip_kaprog)->firstOrFail();
            User::where('username', $kp->username)->delete();
            $kp->delete();

            return redirect()->route('kaprog.index')->with('success', 'Data Ketua Program berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_pdf()
    {
        try {
            $ketua_program = ketua_program::with('jurusan')->get();

            $pdf = Pdf::loadView('Export.ketua_program.pdf', compact('ketua_program'));
            return $pdf->download('ketuaprogram.pdf');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function export_excel()
    {
        try {
            return Excel::download(new Ketua_Program_ExportExcel, 'ketuaprogram.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    public function template()
    {
        $jurusan = jurusan::orderBy('id_jurusan')->get();

        return Excel::download(new Ketua_Program_Template($jurusan), 'Template_Import_Ketua_Program.xlsx');
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv|max:10240',
            ]);

            Excel::import(new Ketua_Program_Import, $request->file('file'));

            return redirect()->back()->with('success', 'Data Ketua Program berhasil diimport!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }
}