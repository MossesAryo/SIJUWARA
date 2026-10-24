<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * CRUD Jurusan (Manajemen Keahlian) untuk Super Admin.
 *
 * - Kode jurusan (id_jurusan) boleh diubah. Kelas, siswa (termasuk arsip), dan
 *   ketua program yang memakai kode lama ikut dipindahkan ke kode baru.
 *   FK kelas.id_jurusan tidak punya ON UPDATE CASCADE, jadi perubahan kode dilakukan
 *   dengan menyalin baris jurusan ke kode baru, memindahkan data turunannya,
 *   lalu menghapus baris lama, semuanya dalam satu transaksi.
 * - Program Keahlian (kelas 10) dan Konsentrasi Keahlian (kelas 11/12) disimpan
 *   di kolom kode_program dan konsentrasi pada tabel jurusan.
 * - Jurusan yang masih dipakai kelas, siswa (termasuk arsip), atau ketua program
 *   tidak dapat dihapus, karena FK-nya ON DELETE CASCADE.
 */
class JurusanController extends Controller
{
    /** Tabel yang menyimpan id_jurusan dan ikut dipindahkan saat kode berubah. */
    private const TABEL_TURUNAN = ['kelas', 'siswa', 'ketua_program'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $like = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $q
        ) . '%';

        $daftarJurusan = jurusan::query()
            ->withCount(['kelas', 'siswa'])
            ->with('ketuaProgram:nip_kaprog,nama_ketua_program,id_jurusan')
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('id_jurusan', 'like', $like)
                        ->orWhere('nama_jurusan', 'like', $like);
                });
            })
            ->orderBy('nama_jurusan')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.jurusan.index', [
            'daftarJurusan' => $daftarJurusan,
            'q' => $q,
            'totalJurusan' => jurusan::count(),
        ]);
    }

    public function create()
    {
        return view('superadmin.jurusan.create');
    }

    public function store(Request $request)
    {
        $this->rapikanInput($request);

        $data = $request->validate(
            [
                'id_jurusan' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^[A-Z0-9]+$/',
                    'unique:jurusan,id_jurusan',
                ],
                'nama_jurusan' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:jurusan,nama_jurusan',
                ],
                'kode_program' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'konsentrasi' => ['nullable', 'string', 'max:100', 'regex:/^[A-Z0-9]+(,[A-Z0-9]+)*$/'],
            ],
            $this->pesan(),
            $this->atribut()
        );

        if ($bentrok = $this->konsentrasiBentrok($data['konsentrasi'] ?? null, null)) {
            return back()->withInput()->withErrors(['konsentrasi' => $bentrok]);
        }

        try {
            jurusan::create([
                'id_jurusan' => $data['id_jurusan'],
                'nama_jurusan' => $data['nama_jurusan'],
                'kode_program' => $data['kode_program'] ?? null,
                'konsentrasi' => $data['konsentrasi'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Gagal menambah jurusan', [
                'id_jurusan' => $data['id_jurusan'],
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Jurusan gagal disimpan. Tidak ada data yang tersimpan, silakan coba lagi.');
        }

        kelas::lupakanPeta();

        ActivityLog::catat(
            ActivityLog::KATEGORI_MASTER,
            'Tambah Jurusan',
            'Jurusan "' . $data['id_jurusan'] . '" ditambahkan dengan nama '
                . $data['nama_jurusan'] . '.'
        );

        return redirect()
            ->route('superadmin.jurusan.index', ['q' => $data['id_jurusan']])
            ->with('success', 'Jurusan "' . $data['nama_jurusan'] . '" berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $jurusan = $this->cariJurusan($id);

        return view('superadmin.jurusan.edit', [
            'jurusan' => $jurusan,
            'dampak' => $this->hitungDampak($jurusan->id_jurusan),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $jurusan = $this->cariJurusan($id);
        $lama = $jurusan->id_jurusan;

        $this->rapikanInput($request);

        $data = $request->validate(
            [
                'id_jurusan' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^[A-Z0-9]+$/',
                    Rule::unique('jurusan', 'id_jurusan')->ignore($lama, 'id_jurusan'),
                ],
                'nama_jurusan' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('jurusan', 'nama_jurusan')->ignore($lama, 'id_jurusan'),
                ],
                'kode_program' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'konsentrasi' => ['nullable', 'string', 'max:100', 'regex:/^[A-Z0-9]+(,[A-Z0-9]+)*$/'],
            ],
            $this->pesan(),
            $this->atribut()
        );

        $baru = $data['id_jurusan'];
        $namaBaru = $data['nama_jurusan'];
        $programBaru = $data['kode_program'] ?? null;
        $konsentrasiBaru = $data['konsentrasi'] ?? null;

        if ($bentrok = $this->konsentrasiBentrok($konsentrasiBaru, $lama)) {
            return back()->withInput()->withErrors(['konsentrasi' => $bentrok]);
        }

        $namaLama = $jurusan->nama_jurusan;
        $programLama = $jurusan->getRawOriginal('kode_program') ?: null;
        $konsentrasiLama = $this->rapikanDaftar($jurusan->getRawOriginal('konsentrasi'));

        $tidakBerubah = $baru === $lama
            && $namaBaru === $namaLama
            && $programBaru === $programLama
            && $konsentrasiBaru === $konsentrasiLama;

        if ($tidakBerubah) {
            return redirect()
                ->route('superadmin.jurusan.index')
                ->with('success', 'Tidak ada perubahan pada jurusan "' . $lama . '".');
        }

        $dampak = $this->hitungDampak($lama);

        try {
            DB::transaction(function () use ($lama, $baru, $namaBaru, $programBaru, $konsentrasiBaru) {
                $nilai = [
                    'nama_jurusan' => $namaBaru,
                    'kode_program' => $programBaru,
                    'konsentrasi' => $konsentrasiBaru,
                    'updated_at' => now(),
                ];

                if ($baru === $lama) {
                    DB::table('jurusan')->where('id_jurusan', $lama)->update($nilai);

                    return;
                }

                $this->gantiKode($lama, $baru, $nilai);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui jurusan', [
                'id_jurusan' => $lama,
                'kode_baru' => $baru,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Jurusan gagal diperbarui. Tidak ada perubahan yang tersimpan, silakan coba lagi.');
        }

        kelas::lupakanPeta();

        $rincian = [];

        if ($baru !== $lama) {
            $rincian[] = "kode {$lama} → {$baru} (memindahkan {$dampak['Kelas']} kelas, "
                . "{$dampak['Siswa (termasuk arsip)']} siswa, {$dampak['Ketua Program']} ketua program)";
        }
        if ($namaBaru !== $namaLama) {
            $rincian[] = "nama {$namaLama} → {$namaBaru}";
        }
        if ($programBaru !== $programLama) {
            $rincian[] = 'program ' . ($programLama ?? '-') . ' → ' . ($programBaru ?? '-');
        }
        if ($konsentrasiBaru !== $konsentrasiLama) {
            $rincian[] = 'konsentrasi ' . ($konsentrasiLama ?? '-') . ' → ' . ($konsentrasiBaru ?? '-');
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_MASTER,
            'Ubah Jurusan',
            'Jurusan "' . $lama . '": ' . implode('; ', $rincian) . '.'
        );

        return redirect()
            ->route('superadmin.jurusan.index', $baru !== $lama ? ['q' => $baru] : [])
            ->with('success', 'Jurusan "' . $baru . '" berhasil diperbarui.');
    }

    /**
     * Halaman konfirmasi hapus. Menampilkan data yang masih memakai jurusan.
     */
    public function konfirmasiHapus(string $id)
    {
        $jurusan = $this->cariJurusan($id);
        $dampak = $this->hitungDampak($jurusan->id_jurusan);

        return view('superadmin.jurusan.delete', [
            'jurusan' => $jurusan,
            'dampak' => $dampak,
            'dipakai' => array_sum($dampak) > 0,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->validate(
            ['konfirmasi' => ['accepted']],
            ['accepted' => 'Centang pernyataan persetujuan sebelum menghapus.']
        );

        $jurusan = $this->cariJurusan($id);
        $kode = $jurusan->id_jurusan;
        $nama = $jurusan->nama_jurusan;

        try {
            $terhapus = DB::transaction(function () use ($kode) {
                // Periksa ulang di dalam transaksi agar cascade tidak menghapus data lain.
                if (array_sum($this->hitungDampak($kode)) > 0) {
                    return false;
                }

                jurusan::where('id_jurusan', $kode)->delete();

                return true;
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus jurusan', [
                'id_jurusan' => $kode,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.jurusan.index')
                ->with('error', 'Jurusan gagal dihapus. Tidak ada data yang berubah, silakan coba lagi.');
        }

        if (!$terhapus) {
            return redirect()
                ->route('superadmin.jurusan.hapus', $kode)
                ->with('error', 'Jurusan "' . $kode . '" masih dipakai dan tidak dapat dihapus.');
        }

        kelas::lupakanPeta();

        ActivityLog::catat(
            ActivityLog::KATEGORI_MASTER,
            'Hapus Jurusan',
            'Jurusan "' . $kode . '" (' . $nama . ') dihapus.'
        );

        return redirect()
            ->route('superadmin.jurusan.index')
            ->with('success', 'Jurusan "' . $nama . '" berhasil dihapus.');
    }

    /**
     * Ganti kode jurusan. Wajib dipanggil di dalam DB::transaction.
     *
     * FK kelas.id_jurusan tidak punya ON UPDATE CASCADE, jadi baris jurusan disalin
     * ke kode baru, data turunan dipindahkan, lalu baris lama dihapus. Setelah
     * dipindahkan tidak ada lagi baris yang memakai kode lama, jadi ON DELETE CASCADE
     * pada baris lama tidak menghapus apa pun.
     */
    private function gantiKode(string $lama, string $baru, array $nilaiBaru): void
    {
        $baris = DB::table('jurusan')->where('id_jurusan', $lama)->lockForUpdate()->first();

        if (!$baris) {
            throw new RuntimeException('Jurusan "' . $lama . '" tidak ditemukan.');
        }

        if (DB::table('jurusan')->where('id_jurusan', $baru)->exists()) {
            throw new RuntimeException('Kode jurusan "' . $baru . '" sudah digunakan.');
        }

        $salinan = array_merge((array) $baris, $nilaiBaru, ['id_jurusan' => $baru]);

        DB::table('jurusan')->insert($salinan);

        // DB::table() tidak terkena soft delete, jadi siswa arsip dan alumni ikut pindah.
        foreach (self::TABEL_TURUNAN as $tabel) {
            DB::table($tabel)->where('id_jurusan', $lama)->update(['id_jurusan' => $baru]);
        }

        DB::table('jurusan')->where('id_jurusan', $lama)->delete();
    }

    /**
     * Konsentrasi tidak boleh dipakai dua jurusan sekaligus (kode di id kelas
     * dan nama kelas jadi ambigu). Mengembalikan pesan error atau null.
     */
    private function konsentrasiBentrok(?string $daftar, ?string $kecuali): ?string
    {
        if (!$daftar) {
            return null;
        }

        $milikSendiri = explode(',', $daftar);

        $lain = DB::table('jurusan')
            ->when($kecuali, fn ($q) => $q->where('id_jurusan', '!=', $kecuali))
            ->pluck('konsentrasi', 'id_jurusan');

        foreach ($lain as $idJurusan => $teks) {
            $irisan = array_intersect($milikSendiri, kelas::pecahKonsentrasi($teks));

            if ($irisan) {
                return 'Konsentrasi ' . implode(', ', $irisan) . ' sudah dipakai jurusan ' . $idJurusan . '.';
            }
        }

        return null;
    }

    /**
     * Cari jurusan berdasarkan kode (id_jurusan). Kolom ini bukan primary key
     * di database, jadi dicari lewat where().
     */
    private function cariJurusan(string $id): jurusan
    {
        return jurusan::where('id_jurusan', $id)->firstOrFail();
    }

    /**
     * Hitung data yang masih memakai jurusan.
     * Siswa dihitung termasuk yang sudah diarsip (soft delete).
     */
    private function hitungDampak(string $kode): array
    {
        return [
            'Kelas' => DB::table('kelas')->where('id_jurusan', $kode)->count(),
            'Siswa (termasuk arsip)' => DB::table('siswa')->where('id_jurusan', $kode)->count(),
            'Ketua Program' => DB::table('ketua_program')->where('id_jurusan', $kode)->count(),
        ];
    }

    /** Rapikan input sebelum divalidasi. */
    private function rapikanInput(Request $request): void
    {
        $request->merge([
            'id_jurusan' => $this->rapikanKode($request->input('id_jurusan')),
            'nama_jurusan' => $this->rapikanNama($request->input('nama_jurusan')),
            'kode_program' => $this->rapikanKode($request->input('kode_program')),
            'konsentrasi' => $this->rapikanDaftar($request->input('konsentrasi')),
        ]);
    }

    private function rapikanKode($kode): ?string
    {
        $kode = strtoupper(trim((string) $kode));

        return $kode === '' ? null : $kode;
    }

    /** "mp, mlog" => "MP,MLOG" */
    private function rapikanDaftar($teks): ?string
    {
        $daftar = kelas::pecahKonsentrasi($teks);

        return $daftar ? implode(',', $daftar) : null;
    }

    private function rapikanNama($nama): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $nama));
    }

    private function atribut(): array
    {
        return [
            'id_jurusan' => 'Kode jurusan',
            'nama_jurusan' => 'Nama jurusan',
            'kode_program' => 'Program keahlian',
            'konsentrasi' => 'Konsentrasi keahlian',
        ];
    }

    private function pesan(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
            'id_jurusan.unique' => 'Kode jurusan sudah digunakan.',
            'id_jurusan.regex' => 'Kode jurusan hanya boleh huruf kapital dan angka, tanpa spasi.',
            'nama_jurusan.unique' => 'Nama jurusan sudah digunakan.',
            'kode_program.regex' => 'Program keahlian hanya boleh huruf kapital dan angka, tanpa spasi.',
            'konsentrasi.regex' => 'Konsentrasi keahlian hanya boleh huruf kapital dan angka, pisahkan dengan koma. Contoh: MP, MLOG.',
        ];
    }
}