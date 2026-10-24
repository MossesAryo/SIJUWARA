<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\walikelas;
use App\Models\kelas;
use App\Models\User;
use App\Support\AkunStaf;

use App\Exports\Walikelas_ExportExcel;
use App\Exports\Walikelas_Template;
use App\Imports\Walikelas_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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

        $query = walikelas::with('kelas');

        // Search: nama, NIP, id kelas, atau nama kelas (mis. "10 RPL 1")
        if ($request->filled('search')) {
            $search = trim($request->search);

            // Escape karakter khusus LIKE (% _ \) supaya dicari apa adanya
            $like = '%' . addcslashes($search, '\\%_') . '%';

            // NIP sering diketik dengan spasi/tanda hubung: "1967 0409 2007"
            $nipBersih = preg_replace('/[\s\-\.]/', '', $search);

            $query->where(function ($q) use ($like, $nipBersih) {
                $q->where('nama_walikelas', 'like', $like)
                  ->orWhere('nip_walikelas', 'like', $like)
                  ->orWhere('id_kelas', 'like', $like)
                  ->orWhereHas('kelas', function ($k) use ($like) {
                      $k->where('nama_kelas', 'like', $like);
                  });

                if ($nipBersih !== '' && ctype_digit($nipBersih)) {
                    $q->orWhere('nip_walikelas', 'like', '%' . $nipBersih . '%');
                }
            });
        }

        // Filter kelas spesifik
        if ($request->filled('kelas')) {
            $query->where('id_kelas', $request->kelas);
        }

        // Paginate — sertakan search dan kelas supaya pagination mempertahankan keduanya
        $walikelas = $query->orderBy('nama_walikelas')->paginate(10)
                      ->appends($request->only(['search', 'kelas']));

        return view('wakasek.walikelas.index', compact('walikelas', 'kelas'));
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
            'nip_walikelas'  => AkunStaf::bersihkanNip($request->input('nip_walikelas')),
            'nama_walikelas' => AkunStaf::rapikanNama($request->input('nama_walikelas')),
        ]);

        // Validasi di luar try/catch supaya pesan per kolom tampil di form.
        $data = $request->validate([
            'nip_walikelas' => [
                'required',
                'regex:/^\d{1,18}$/',
                function ($attribute, $value, \Closure $fail) {
                    if (AkunStaf::nipTerpakai((string) $value)) {
                        $fail('NIP sudah terdaftar pada data guru/pegawai lain.');
                    }
                },
            ],
            'nama_walikelas' => ['required', 'string', 'max:255'],
            'id_kelas' => [
                'required',
                Rule::exists('kelas', 'id_kelas'),
                Rule::unique('walikelas', 'id_kelas'),
            ],
        ], [
            'nip_walikelas.required'  => 'NIP wajib diisi.',
            'nip_walikelas.regex'     => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_walikelas.required' => 'Nama walikelas wajib diisi.',
            'nama_walikelas.max'      => 'Nama walikelas maksimal 255 karakter.',
            'id_kelas.required'       => 'Kelas wajib dipilih.',
            'id_kelas.exists'         => 'Kelas yang dipilih tidak valid.',
            'id_kelas.unique'         => 'Kelas ini sudah memiliki walikelas.',
        ]);

        try {
            // Akun user dan data walikelas disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($data) {
                $user = AkunStaf::buatUser(
                    $data['nama_walikelas'],
                    $data['nip_walikelas'],
                    User::ROLE_WALIKELAS
                );

                walikelas::create([
                    'nip_walikelas'  => $data['nip_walikelas'],
                    'username'       => $user->username,
                    'nama_walikelas' => $data['nama_walikelas'],
                    'id_kelas'       => $data['id_kelas'],
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Tambah walikelas'));
        }

        return redirect()->route('walikelas.index')
            ->with('success', 'Data walikelas berhasil ditambahkan. Login dengan NIP, password awal: ' . AkunStaf::PASSWORD_DEFAULT);
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

            // FK penilaian, intervensi & catatan ON DELETE CASCADE: jangan hapus riwayat secara tak sengaja.
            $riwayat = DB::table('penilaian')->where('nip_walikelas', $walikelas->nip_walikelas)->count()
                + DB::table('intervensi')->where('nip_walikelas', $walikelas->nip_walikelas)->count()
                + DB::table('catatan')->where('nip_walikelas', $walikelas->nip_walikelas)->count();
            if ($riwayat > 0) {
                return redirect()->back()->with('error', "Wali kelas tidak dapat dihapus karena memiliki {$riwayat} riwayat penilaian/intervensi/catatan.");
            }

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