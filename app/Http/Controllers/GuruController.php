<?php

namespace App\Http\Controllers;

use App\Exports\Guru_ExportExcel;
use App\Exports\Guru_Template;
use App\Imports\Guru_Import;
use App\Models\guru;
use App\Models\guru_bk;
use App\Models\ketua_program;
use App\Models\wakasek;
use App\Models\walikelas;
use App\Support\AkunStaf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Data guru dan staf TU (master data, guru_staf) untuk Wakasek.
 * Terpisah dari data jabatan. Guru tidak punya akun login.
 *
 * Alur: import data guru/staf, lalu petakan jabatannya di menu Wali Kelas,
 * Guru BK, atau Ketua Program. NIP dan nama di form jabatan dipilih dari data ini.
 */
class GuruController extends Controller
{
    public function index(Request $request)
    {
        $query = guru::with(['wakasek', 'gurubk', 'kaprog.jurusan', 'walikelas.kelas']);

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

        // ───── Filter jabatan ─────
        // Nilai: wakasek | gurubk | kaprog | walikelas | belum (belum dipetakan)
        $jabatan = (string) $request->query('jabatan', '');
        if ($jabatan === 'belum') {
            $query->whereDoesntHave('wakasek')
                  ->whereDoesntHave('gurubk')
                  ->whereDoesntHave('kaprog')
                  ->whereDoesntHave('walikelas');
        } elseif (array_key_exists($jabatan, guru::DAFTAR_JABATAN)) {
            $query->whereHas($jabatan);
        } else {
            $jabatan = '';
        }

        // ───── Sortir ─────
        $sort = $request->query('sort');
        if (!in_array($sort, ['nama', 'nip', 'jabatan'], true)) {
            $sort = 'nama';
        }
        $dir = strtolower((string) $request->query('dir')) === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'nip':
                // Urut sebagai angka: panjang dulu, baru isi (NIP disimpan sebagai string).
                $query->orderByRaw('LENGTH(nip_guru) ' . $dir)
                      ->orderBy('nip_guru', $dir);
                break;

            case 'jabatan':
                $rank = "CASE
                    WHEN EXISTS (SELECT 1 FROM wakasek WHERE wakasek.nip_wakasek = guru.nip_guru) THEN 1
                    WHEN EXISTS (SELECT 1 FROM guru_bk WHERE guru_bk.nip_bk = guru.nip_guru) THEN 2
                    WHEN EXISTS (SELECT 1 FROM ketua_program WHERE ketua_program.nip_kaprog = guru.nip_guru) THEN 3
                    WHEN EXISTS (SELECT 1 FROM walikelas WHERE walikelas.nip_walikelas = guru.nip_guru) THEN 4
                    ELSE 5
                END";
                // "Belum dipetakan" selalu di paling bawah, baik asc maupun desc.
                $query->orderByRaw("($rank) = 5 asc")
                      ->orderByRaw("($rank) $dir")
                      ->orderBy('nama_guru');
                break;

            default: // nama
                $query->orderBy('nama_guru', $dir);
                break;
        }

        // Pagination mempertahankan search, jabatan, sort, dan dir
        $guru = $query->paginate(10)
            ->appends($request->only(['search', 'jabatan', 'sort', 'dir']));

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

            // NIP yang sudah punya jabatan dipakai untuk login, jadi tidak diubah dari sini.
            $jabatan = guru::petaJabatan()[(string) $guru->nip_guru] ?? null;
            if ($jabatan) {
                return redirect()->back()->withInput()
                    ->withErrors(['nip_guru' => "NIP tidak bisa diubah karena guru ini menjabat sebagai {$jabatan}."]);
            }
        }

        try {
            DB::transaction(function () use ($guru, $data) {
                guru::where('nip_guru', $guru->nip_guru)->update([
                    'nip_guru'   => $data['nip_guru'],
                    'nama_guru'  => $data['nama_guru'],
                    'updated_at' => now(),
                ]);

                // Nama pada data jabatan ikut diperbarui.
                ketua_program::where('nip_kaprog', $data['nip_guru'])
                    ->update(['nama_ketua_program' => $data['nama_guru']]);
                walikelas::where('nip_walikelas', $data['nip_guru'])
                    ->update(['nama_walikelas' => $data['nama_guru']]);
                guru_bk::where('nip_bk', $data['nip_guru'])
                    ->update(['nama_guru_bk' => $data['nama_guru']]);
                wakasek::where('nip_wakasek', $data['nip_guru'])
                    ->update(['nama_wakasek' => $data['nama_guru']]);
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

            $jabatan = guru::petaJabatan()[(string) $guru->nip_guru] ?? null;
            if ($jabatan) {
                return redirect()->back()
                    ->with('error', "Guru tidak dapat dihapus karena menjabat sebagai {$jabatan}. Hapus data {$jabatan} terlebih dahulu.");
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