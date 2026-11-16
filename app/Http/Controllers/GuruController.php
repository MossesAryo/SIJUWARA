<?php

namespace App\Http\Controllers;

use App\Exports\Guru_ExportExcel;
use App\Exports\Guru_Template;
use App\Imports\Guru_Import;
use App\Models\guru;
use App\Models\guru_bk;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\ketua_program;
use App\Models\User;
use App\Models\wakasek;
use App\Models\walikelas;
use App\Support\AkunStaf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
        $query = Guru::with(['wakasek', 'gurubk', 'kaprog.jurusan', 'walikelas.kelas']);

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
        } elseif (array_key_exists($jabatan, Guru::DAFTAR_JABATAN)) {
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

        // Pilihan untuk form jabatan di modal edit
        $daftar_kelas = kelas::with('walikelas')
            ->where('id_kelas', '!=', 'ALUMNI')
            ->orderBy('nama_kelas')
            ->get();
        $daftar_jurusan = jurusan::all();

        return view('wakasek.guru.index', compact('guru', 'daftar_kelas', 'daftar_jurusan'));
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
            Guru::create($data);
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
            'nip_guru'   => ['required', 'regex:/^\d{1,18}$/'],
            'nama_guru'  => ['required', 'string', 'max:255'],
            'jabatan'    => ['nullable', Rule::in(array_keys(Guru::DAFTAR_JABATAN))],
            'id_kelas'   => ['nullable', 'string', 'not_in:ALUMNI', Rule::exists('kelas', 'id_kelas')],
            'id_jurusan' => [
                Rule::requiredIf(fn () => $request->input('jabatan') === 'kaprog'),
                'nullable',
                Rule::exists('jurusan', 'id_jurusan'),
            ],
        ], [
            'nip_guru.required'   => 'NIP wajib diisi.',
            'nip_guru.regex'      => 'NIP harus berupa angka (maksimal 18 digit).',
            'nama_guru.required'  => 'Nama guru wajib diisi.',
            'nama_guru.max'       => 'Nama guru maksimal 255 karakter.',
            'jabatan.in'          => 'Jabatan yang dipilih tidak valid.',
            'id_kelas.exists'     => 'Kelas yang dipilih tidak valid.',
            'id_kelas.not_in'     => 'Kelas alumni tidak bisa dipilih.',
            'id_jurusan.required' => 'Program / kompetensi keahlian wajib dipilih untuk Ketua Program.',
            'id_jurusan.exists'   => 'Program / kompetensi keahlian tidak valid.',
        ]);

        $guru = Guru::where('nip_guru', $nip)->firstOrFail();
        $nipBerubah = (string) $data['nip_guru'] !== (string) $guru->nip_guru;

        if ($nipBerubah) {
            if (Guru::where('nip_guru', $data['nip_guru'])->exists()) {
                return redirect()->back()->withInput()
                    ->withErrors(['nip_guru' => 'NIP sudah terdaftar pada data guru.']);
            }

            // NIP yang sudah punya jabatan dipakai untuk login, jadi tidak diubah dari sini.
            $jabatan = Guru::petaJabatan()[(string) $guru->nip_guru] ?? null;
            if ($jabatan) {
                return redirect()->back()->withInput()
                    ->withErrors(['nip_guru' => "NIP tidak bisa diubah karena guru ini menjabat sebagai {$jabatan}. Hapus jabatannya dulu."]);
            }
        }

        // Form lama tanpa kolom jabatan: jabatan dibiarkan seperti sekarang.
        $ubahJabatan = $request->has('jabatan');
        $catatanJabatan = null;

        try {
            DB::transaction(function () use ($guru, $data, $ubahJabatan, &$catatanJabatan) {
                Guru::where('nip_guru', $guru->nip_guru)->update([
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

                if ($ubahJabatan) {
                    $catatanJabatan = $this->terapkanJabatan(
                        (string) $data['nip_guru'],
                        $data['nama_guru'],
                        (string) ($data['jabatan'] ?? ''),
                        $data['id_kelas'] ?? null,
                        $data['id_jurusan'] ?? null
                    );
                }
            });
        } catch (\DomainException $e) {
            // Aturan jabatan dilanggar (mis. masih punya riwayat): semua perubahan dibatalkan.
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', AkunStaf::pesanGagal($e, 'Ubah data guru'));
        }

        $pesan = 'Data guru berhasil diperbarui.';
        if ($catatanJabatan) {
            $pesan .= ' ' . $catatanJabatan;
        }

        return redirect()->route('guru.index')->with('success', $pesan);
    }

    public function destroy($nip)
    {
        try {
            $guru = Guru::where('nip_guru', $nip)->firstOrFail();

            $jabatan = Guru::petaJabatan()[(string) $guru->nip_guru] ?? null;
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

    /** Kode jabatan yang sedang dipegang NIP ini, atau '' kalau belum punya. */
    private function jabatanSekarang(string $nip): string
    {
        $sumber = [
            'wakasek'   => ['wakasek', 'nip_wakasek'],
            'gurubk'    => ['guru_bk', 'nip_bk'],
            'kaprog'    => ['ketua_program', 'nip_kaprog'],
            'walikelas' => ['walikelas', 'nip_walikelas'],
        ];

        foreach ($sumber as $kode => [$tabel, $kolom]) {
            if (DB::table($tabel)->where($kolom, $nip)->exists()) {
                return $kode;
            }
        }

        return '';
    }

    /**
     * Tambah, ubah, atau hapus jabatan seorang guru (dipanggil di dalam transaksi).
     * Jabatan baru membuat akun login (password awal), jabatan yang dihapus ikut menghapus akunnya.
     * Pelanggaran aturan dilempar sebagai DomainException supaya semua perubahan dibatalkan.
     *
     * @return string|null catatan untuk pesan sukses
     */
    private function terapkanJabatan(string $nip, string $nama, string $target, ?string $idKelas, ?string $idJurusan): ?string
    {
        $label = Guru::DAFTAR_JABATAN;
        $sekarang = $this->jabatanSekarang($nip);
        $catatan = [];

        // Jabatan tetap sama: hanya detailnya (kelas / program) yang bisa berubah.
        if ($sekarang === $target) {
            if ($target === 'walikelas') {
                $digantikan = WalikelasController::pasangkanKelas($nip, $idKelas ?: null);
                if ($digantikan) {
                    $catatan[] = "{$digantikan} dilepas dari kelas tersebut karena kelas hanya boleh punya satu wali.";
                }
            } elseif ($target === 'kaprog') {
                ketua_program::where('nip_kaprog', $nip)->update(['id_jurusan' => $idJurusan]);
            }

            return $catatan ? implode(' ', $catatan) : null;
        }

        // Hapus jabatan lama (kalau ada)
        if ($sekarang !== '') {
            $this->hapusJabatan($nip, $sekarang);
            $catatan[] = "Jabatan {$label[$sekarang]} dihapus dan akun loginnya ikut dihapus.";
        }

        // Tambah jabatan baru (kalau dipilih)
        if ($target !== '') {
            if (AkunStaf::nipTerpakai($nip)) {
                throw new \DomainException(AkunStaf::pesanNipTerpakai($nip));
            }

            switch ($target) {
                case 'walikelas':
                    $user = AkunStaf::buatUser($nama, $nip, User::ROLE_WALIKELAS);
                    walikelas::create([
                        'nip_walikelas'  => $nip,
                        'username'       => $user->username,
                        'nama_walikelas' => $nama,
                        'id_kelas'       => null,
                    ]);
                    if ($idKelas) {
                        $digantikan = WalikelasController::pasangkanKelas($nip, $idKelas);
                        if ($digantikan) {
                            $catatan[] = "{$digantikan} dilepas dari kelas tersebut karena kelas hanya boleh punya satu wali.";
                        }
                    }
                    break;

                case 'gurubk':
                    $user = AkunStaf::buatUser($nama, $nip, User::ROLE_GURU_BK);
                    guru_bk::create([
                        'nip_bk'       => $nip,
                        'username'     => $user->username,
                        'nama_guru_bk' => $nama,
                    ]);
                    break;

                case 'kaprog':
                    $user = AkunStaf::buatUser($nama, $nip, User::ROLE_KETUA_PROGRAM);
                    ketua_program::create([
                        'nip_kaprog'         => $nip,
                        'nama_ketua_program' => $nama,
                        'id_jurusan'         => $idJurusan,
                        'username'           => $user->username,
                    ]);
                    break;

                case 'wakasek':
                    $user = AkunStaf::buatUser($nama, $nip, User::ROLE_WAKASEK);
                    wakasek::create([
                        'nip_wakasek'  => $nip,
                        'username'     => $user->username,
                        'nama_wakasek' => $nama,
                    ]);
                    break;
            }

            $catatan[] = "Jabatan {$label[$target]} ditambahkan. Login dengan NIP, password awal: " . AkunStaf::PASSWORD_DEFAULT . '.';
        }

        return $catatan ? implode(' ', $catatan) : null;
    }

    /** Hapus jabatan beserta akun loginnya. Data guru tetap ada. */
    private function hapusJabatan(string $nip, string $kode): void
    {
        switch ($kode) {
            case 'walikelas':
                $w = walikelas::where('nip_walikelas', $nip)->firstOrFail();

                // FK penilaian, intervensi & catatan ON DELETE CASCADE: jangan hapus riwayat secara tak sengaja.
                $riwayat = DB::table('penilaian')->where('nip_walikelas', $nip)->count()
                    + DB::table('intervensi')->where('nip_walikelas', $nip)->count()
                    + DB::table('catatan')->where('nip_walikelas', $nip)->count();
                if ($riwayat > 0) {
                    throw new \DomainException("Jabatan Wali Kelas tidak dapat dihapus karena memiliki {$riwayat} riwayat penilaian/intervensi/catatan.");
                }

                User::where('username', $w->username)->delete();
                walikelas::where('nip_walikelas', $nip)->delete();
                break;

            case 'gurubk':
                $bk = guru_bk::where('nip_bk', $nip)->firstOrFail();

                $riwayat = DB::table('penilaian')->where('nip_bk', $nip)->count()
                    + DB::table('intervensi')->where('nip_bk', $nip)->count();
                if ($riwayat > 0) {
                    throw new \DomainException("Jabatan Guru BK tidak dapat dihapus karena memiliki {$riwayat} riwayat penilaian/intervensi.");
                }

                User::where('username', $bk->username)->delete();
                guru_bk::where('nip_bk', $nip)->delete();
                break;

            case 'kaprog':
                $kp = ketua_program::where('nip_kaprog', $nip)->firstOrFail();

                User::where('username', $kp->username)->delete();
                ketua_program::where('nip_kaprog', $nip)->delete();
                break;

            case 'wakasek':
                $wk = wakasek::where('nip_wakasek', $nip)->firstOrFail();

                if (Auth::user() && Auth::user()->username === $wk->username) {
                    throw new \DomainException('Jabatan Wakasek dari akun yang sedang kamu pakai tidak dapat dihapus.');
                }
                if (User::where('role', User::ROLE_WAKASEK)->count() <= 1) {
                    throw new \DomainException('Wakasek terakhir tidak dapat dihapus.');
                }

                $riwayat = DB::table('penilaian')->where('nip_wakasek', $nip)->count()
                    + DB::table('catatan')->where('nip_wakasek', $nip)->count()
                    + DB::table('intervensi')->where('nip_wakasek', $nip)->count();
                if ($riwayat > 0) {
                    throw new \DomainException("Jabatan Wakasek tidak dapat dihapus karena memiliki {$riwayat} riwayat penilaian/catatan/intervensi.");
                }

                User::where('username', $wk->username)->delete();
                wakasek::where('nip_wakasek', $nip)->delete();
                break;
        }
    }

    public function export_pdf()
    {
        try {
            $guru = Guru::orderBy('nama_guru')->get();

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