<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\guru;
use App\Models\walikelas;
use App\Models\kelas;
use App\Models\User;
use App\Support\AkunStaf;

use App\Exports\Walikelas_ExportExcel;
use App\Exports\Walikelas_Template;
use App\Imports\Walikelas_Import;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Manajemen data wali kelas.
 * Wali kelas dipilih dari data guru (menu Guru), bukan diketik ulang:
 * NIP dan nama mengikuti data guru. CRUD wali kelas dan kaitan dengan kelas.
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

        // Pilihan guru untuk form tambah/edit. Guru yang sudah punya jabatan ditandai.
        $daftar_guru = guru::orderBy('nama_guru')->get();
        $peta_jabatan = guru::petaJabatan();

        return view('wakasek.walikelas.index', compact('walikelas', 'kelas', 'daftar_guru', 'peta_jabatan'));
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
        // id_kelas boleh kosong (guru tanpa kelas, disimpan sebagai NULL).
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
            'id_kelas' => [
                'nullable',
                Rule::exists('kelas', 'id_kelas'),
                Rule::unique('walikelas', 'id_kelas'),
            ],
        ], [
            'nip_guru.required' => 'Guru wajib dipilih.',
            'nip_guru.exists'   => 'Guru yang dipilih tidak ada di data guru.',
            'id_kelas.exists'   => 'Kelas yang dipilih tidak valid.',
            'id_kelas.unique'   => 'Kelas ini sudah memiliki walikelas.',
        ]);

        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();

        try {
            // Akun user dan data walikelas disimpan bersamaan atau tidak sama sekali.
            DB::transaction(function () use ($guru, $data) {
                $user = AkunStaf::buatUser(
                    $guru->nama_guru,
                    $guru->nip_guru,
                    User::ROLE_WALIKELAS
                );

                walikelas::create([
                    'nip_walikelas'  => $guru->nip_guru,
                    'username'       => $user->username,
                    'nama_walikelas' => $guru->nama_guru,
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
            'nip_guru' => ['required', Rule::exists('guru', 'nip_guru')],
            'username' => 'required|string',
            'id_kelas' => ['nullable', 'string', Rule::exists('kelas', 'id_kelas')],
        ], [
            'nip_guru.required' => 'Guru wajib dipilih.',
            'nip_guru.exists'   => 'Guru yang dipilih tidak ada di data guru.',
            'id_kelas.exists'   => 'Kelas yang dipilih tidak valid.',
        ]);

        $guru = guru::where('nip_guru', $data['nip_guru'])->firstOrFail();

        if (AkunStaf::nipTerpakaiOlehLain($guru->nip_guru, $nip_walikelas)) {
            return redirect()->back()->withInput()
                ->withErrors(['nip_guru' => AkunStaf::pesanNipTerpakai((string) $guru->nip_guru)]);
        }

        $guruBerganti = (string) $guru->nip_guru !== (string) $nip_walikelas;

        try {
            $walikelas = Walikelas::where('nip_walikelas', $nip_walikelas)->firstOrFail();
            $user = User::where('username', $username)->first();
            $idKelas = !empty($data['id_kelas']) ? $data['id_kelas'] : null;

            $digantikan = null;

            DB::transaction(function () use ($data, $guru, $guruBerganti, $walikelas, $user, $idKelas, &$digantikan) {
                if ($user) {
                    if ($guruBerganti) {
                        // Orangnya diganti: akun login ikut ke guru baru dan password dikembalikan ke awal,
                        // supaya guru baru tidak memakai password wali kelas sebelumnya.
                        $user->update([
                            'username' => AkunStaf::buatUsername($guru->nama_guru, $guru->nip_guru),
                            'email'    => AkunStaf::buatEmail($guru->nama_guru, $guru->nip_guru),
                            'password' => Hash::make(AkunStaf::PASSWORD_DEFAULT),
                        ]);
                    } else {
                        $user->update(['username' => $data['username']]);
                    }
                }

                $walikelas->update([
                    'nip_walikelas'  => $guru->nip_guru,
                    'username'       => $user->username ?? $walikelas->username,
                    'nama_walikelas' => $guru->nama_guru,
                ]);

                // Kelas diatur terpisah: kalau sudah ada wali lain, wali itu dilepas.
                $digantikan = self::pasangkanKelas((string) $guru->nip_guru, $idKelas);
            });

            $pesan = 'Data berhasil diperbarui.';
            if ($guruBerganti) {
                $pesan .= ' Login dengan NIP guru baru, password awal: ' . AkunStaf::PASSWORD_DEFAULT . '.';
            }
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