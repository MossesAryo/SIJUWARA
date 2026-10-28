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
        // walikelas dimuat supaya form edit bisa menandai kelas yang sudah punya wali
        $kelas = kelas::with('walikelas')->get();

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
        // id_kelas boleh kosong (guru tanpa kelas, disimpan sebagai NULL).
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
                'nullable',
                Rule::exists('kelas', 'id_kelas'),
                Rule::unique('walikelas', 'id_kelas'),
            ],
        ], [
            'nip_walikelas.required'  => 'NIP wajib diisi.',
            'nip_walikelas.regex'     => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_walikelas.required' => 'Nama walikelas wajib diisi.',
            'nama_walikelas.max'      => 'Nama walikelas maksimal 255 karakter.',
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
                    'id_kelas'       => $data['id_kelas'] ?? null,
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
     * Pasangkan seorang wali dengan kelas (atau lepas kelasnya kalau $idKelas null).
     * Satu kelas hanya boleh punya satu wali: wali lama di kelas itu dilepas (id_kelas = NULL).
     * Dipakai form edit di menu Guru dan pilihan wali di halaman Kelas.
     *
     * @return string|null nama wali lama yang digantikan, atau null kalau tidak ada
     */
    public static function pasangkanKelas(string $nip, ?string $idKelas): ?string
    {
        $digantikan = null;

        DB::transaction(function () use ($nip, $idKelas, &$digantikan) {
            if ($idKelas) {
                $lama = walikelas::where('id_kelas', $idKelas)
                    ->where('nip_walikelas', '!=', $nip)
                    ->lockForUpdate()
                    ->get();

                foreach ($lama as $w) {
                    $digantikan = $w->nama_walikelas;
                    walikelas::where('nip_walikelas', $w->nip_walikelas)->update(['id_kelas' => null]);
                }
            }

            walikelas::where('nip_walikelas', $nip)->update(['id_kelas' => $idKelas]);
        });

        return $digantikan;
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
        // Validasi di luar try/catch supaya pesan per kolom tampil, bukan "Terjadi kesalahan".
        $data = $request->validate([
            'nip_walikelas'  => 'required|integer',
            'username'       => 'required|string',
            'id_kelas'       => ['nullable', 'string', Rule::exists('kelas', 'id_kelas')],
            'nama_walikelas' => 'required|string|max:255',
        ], [
            'id_kelas.exists' => 'Kelas yang dipilih tidak valid.',
        ]);

        try {
            $walikelas = Walikelas::where('nip_walikelas', $nip_walikelas)->firstOrFail();
            $user = User::where('username', $username)->first();
            $idKelas = !empty($data['id_kelas']) ? $data['id_kelas'] : null;

            $digantikan = null;

            DB::transaction(function () use ($data, $walikelas, $user, $idKelas, &$digantikan) {
                if ($user) {
                    $user->update([
                        'username' => $data['username'],
                        'email'    => $data['nama_walikelas'] . '@gmail.com',
                    ]);
                }

                $walikelas->update([
                    'nip_walikelas'  => $data['nip_walikelas'],
                    'username'       => $user->username ?? $walikelas->username,
                    'nama_walikelas' => $data['nama_walikelas'],
                ]);

                // Kelas diatur terpisah: kalau sudah ada wali lain, wali itu dilepas.
                $digantikan = self::pasangkanKelas((string) $data['nip_walikelas'], $idKelas);
            });

            $pesan = 'Data berhasil diperbarui.';
            if ($digantikan) {
                $pesan .= " {$digantikan} dilepas dari kelas tersebut karena kelas hanya boleh punya satu wali.";
            }

            return redirect()->route('walikelas.index')->with('success', $pesan);
        } catch (\Exception $e) {
            Log::error('Update walikelas gagal: ' . $e->getMessage());

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