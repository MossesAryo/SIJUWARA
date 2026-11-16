<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\guru;
use App\Models\ketua_program;
use App\Models\jurusan;
use App\Support\AkunStaf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exports\Ketua_Program_ExportExcel;
use App\Imports\Ketua_Program_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Ketua_Program_Template;

/**
 * Kelola data ketua program.
 * Ketua program dipilih dari data guru (menu Guru), bukan diketik ulang.
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

        // Pilihan guru untuk form tambah/edit. Guru yang sudah jadi ketua program ditandai.
        $daftar_guru = guru::orderBy('nama_guru')->get();
        $nip_kaprog_terpakai = ketua_program::pluck('nip_kaprog')
            ->map(fn ($nip) => (string) $nip)
            ->all();

        return view('wakasek.kaprog.index', compact(
            'ketua_program',
            'daftar_jurusan',
            'daftar_guru',
            'nip_kaprog_terpakai'
        ));
    }

    public function store(Request $request)
    {
        // Validasi di luar try/catch supaya pesan per kolom tampil di form.
        $data = $request->validate([
            'nip_guru' => [
                'required',
                Rule::exists('guru', 'nip_guru'),
                function ($attribute, $value, \Closure $fail) {
                    if (AkunStaf::nipTerpakai((string) $value)) {
                        $fail(AkunStaf::pesanNipTerpakai((string) $value));
                    }
                },
            ],
            'id_jurusan' => ['required', Rule::exists('jurusan', 'id_jurusan')],
        ], [
            'nip_guru.required'   => 'Guru wajib dipilih.',
            'nip_guru.exists'     => 'Guru yang dipilih tidak ada di data guru.',
            'id_jurusan.required' => 'Program / kompetensi keahlian wajib dipilih.',
            'id_jurusan.exists'   => 'Program / kompetensi keahlian tidak valid.',
        ]);

        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();

        try {
            // Akun user dan data kaprog disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($guru, $data) {
                $user = AkunStaf::buatUser(
                    $guru->nama_guru,
                    $guru->nip_guru,
                    User::ROLE_KETUA_PROGRAM
                );

                ketua_program::create([
                    'nip_kaprog'         => $guru->nip_guru,
                    'nama_ketua_program' => $guru->nama_guru,
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
        $data = $request->validate([
            'nip_guru'   => ['required', Rule::exists('guru', 'nip_guru')],
            'id_jurusan' => ['required', Rule::exists('jurusan', 'id_jurusan')],
        ], [
            'nip_guru.required'   => 'Guru wajib dipilih.',
            'nip_guru.exists'     => 'Guru yang dipilih tidak ada di data guru.',
            'id_jurusan.required' => 'Program / kompetensi keahlian wajib dipilih.',
            'id_jurusan.exists'   => 'Program / kompetensi keahlian tidak valid.',
        ]);

        $kp = ketua_program::where('nip_kaprog', $nip_kaprog)->firstOrFail();
        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();
        $guruBerganti = (string) $guru->nip_guru !== (string) $kp->nip_kaprog;

        if ($guruBerganti && AkunStaf::nipTerpakai((string) $guru->nip_guru)) {
            return redirect()->back()->withInput()
                ->withErrors(['nip_guru' => AkunStaf::pesanNipTerpakai((string) $guru->nip_guru)]);
        }

        try {
            DB::transaction(function () use ($kp, $guru, $data, $guruBerganti) {
                $username = $kp->username;

                // Orangnya diganti: akun login ikut ke guru baru dan password dikembalikan ke awal,
                // supaya guru baru tidak memakai password ketua program sebelumnya.
                if ($guruBerganti) {
                    $user = User::where('username', $kp->username)->first();

                    if ($user) {
                        $user->update([
                            'username' => AkunStaf::buatUsername($guru->nama_guru, $guru->nip_guru),
                            'email'    => AkunStaf::buatEmail($guru->nama_guru, $guru->nip_guru),
                            'password' => Hash::make(AkunStaf::PASSWORD_DEFAULT),
                        ]);
                        $username = $user->username;
                    }
                }

                ketua_program::where('nip_kaprog', $kp->nip_kaprog)->update([
                    'nip_kaprog'         => $guru->nip_guru,
                    'nama_ketua_program' => $guru->nama_guru,
                    'id_jurusan'         => $data['id_jurusan'],
                    'username'           => $username,
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Ubah ketua program'));
        }

        $pesan = 'Data berhasil diperbarui.';
        if ($guruBerganti) {
            $pesan .= ' Login dengan NIP guru baru, password awal: ' . AkunStaf::PASSWORD_DEFAULT;
        }

        return redirect()->route('kaprog.index')->with('success', $pesan);
    }

    public function destroy($nip_kaprog)
    {
        try {
            $kp = ketua_program::where('nip_kaprog', $nip_kaprog)->firstOrFail();
            User::where('username', $kp->username)->delete();
            $kp->delete();

            return redirect()->route('kaprog.index')->with('success', 'Data Ketua Program berhasil dihapus. Data guru tetap ada.');
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
            ], [
                'file.required' => 'Pilih file Excel terlebih dahulu.',
                'file.mimes'    => 'Format file harus .xlsx, .xls, atau .csv.',
                'file.max'      => 'Ukuran file maksimal 10MB.',
            ]);

            $import = new Ketua_Program_Import;
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
            Log::error('Import ketua program gagal: ' . $e->getMessage());

            return redirect()->back()->with('error', AkunStaf::pesanGagal($e, 'Import ketua program'));
        }
    }
}