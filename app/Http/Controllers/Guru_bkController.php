<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\guru;
use App\Models\guru_bk;
use App\Support\AkunStaf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use App\Exports\Guru_Bk_ExportExcel;
use App\Imports\Guru_Bk_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BK_Template;

/**
 * Manajemen akun dan kelas guru BK.
 * Guru BK dipilih dari data guru (menu Guru), bukan diketik ulang:
 * NIP dan nama mengikuti data guru. CRUD data guru BK, impor/ekspor, serta relasi kelas.
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

        // Pilihan guru untuk form tambah/edit. Guru yang sudah punya jabatan ditandai.
        return view('wakasek.guru_bk.index', [
            'guru_bk'      => $guru_bk,
            'daftar_guru'  => guru::orderBy('nama_guru')->get(),
            'peta_jabatan' => guru::petaJabatan(),
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
            'nip_guru' => AkunStaf::bersihkanNip($request->input('nip_guru')),
        ]);

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
        ], [
            'nip_guru.required' => 'Guru wajib dipilih.',
            'nip_guru.exists'   => 'Guru yang dipilih tidak ada di data guru.',
        ]);

        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();

        try {
            // Akun user dan data guru BK disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($guru) {
                $user = AkunStaf::buatUser(
                    $guru->nama_guru,
                    $guru->nip_guru,
                    User::ROLE_GURU_BK
                );

                guru_bk::create([
                    'nip_bk'       => $guru->nip_guru,
                    'username'     => $user->username,
                    'nama_guru_bk' => $guru->nama_guru,
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
        $data = $request->validate([
            'nip_guru' => ['required', Rule::exists('guru', 'nip_guru')],
        ], [
            'nip_guru.required' => 'Guru wajib dipilih.',
            'nip_guru.exists'   => 'Guru yang dipilih tidak ada di data guru.',
        ]);

        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();

        if (AkunStaf::nipTerpakaiOlehLain($guru->nip_guru, $nip_bk)) {
            return redirect()->back()->withInput()
                ->withErrors(['nip_guru' => AkunStaf::pesanNipTerpakai((string) $guru->nip_guru)]);
        }

        $guruBerganti = (string) $guru->nip_guru !== (string) $nip_bk;

        try {
            $bk = guru_bk::where('nip_bk', $nip_bk)->firstOrFail();

            DB::transaction(function () use ($bk, $guru, $guruBerganti) {
                $username = $bk->username;

                // Orangnya diganti: akun login ikut ke guru baru dan password dikembalikan ke awal,
                // supaya guru baru tidak memakai password guru BK sebelumnya.
                if ($guruBerganti) {
                    $user = User::where('username', $bk->username)->first();

                    if ($user) {
                        $user->update([
                            'username' => AkunStaf::buatUsername($guru->nama_guru, $guru->nip_guru),
                            'email'    => AkunStaf::buatEmail($guru->nama_guru, $guru->nip_guru),
                            'password' => Hash::make(AkunStaf::PASSWORD_DEFAULT),
                        ]);
                        $username = $user->username;
                    }
                }

                guru_bk::where('nip_bk', $bk->nip_bk)->update([
                    'nip_bk'       => $guru->nip_guru,
                    'nama_guru_bk' => $guru->nama_guru,
                    'username'     => $username,
                ]);
            });

            $pesan = 'Data berhasil diperbarui.';
            if ($guruBerganti) {
                $pesan .= ' Login dengan NIP guru baru, password awal: ' . AkunStaf::PASSWORD_DEFAULT . '.';
            }

            return redirect()->route('gurubk.index')->with('success', $pesan);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', AkunStaf::pesanGagal($e, 'Ubah guru BK'));
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