<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * CRUD Jurusan untuk Super Admin.
 *
 * - Kode jurusan (id_jurusan) tidak dapat diubah setelah dibuat.
 * - Jurusan yang masih dipakai kelas, siswa (termasuk arsip), atau
 *   ketua program tidak dapat dihapus, karena FK-nya ON DELETE CASCADE.
 */
class JurusanController extends Controller
{
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
        // Rapikan input sebelum divalidasi.
        $request->merge([
            'id_jurusan' => strtoupper(trim((string) $request->input('id_jurusan'))),
            'nama_jurusan' => $this->rapikanNama($request->input('nama_jurusan')),
        ]);

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
            ],
            $this->pesan(),
            $this->atribut()
        );

        try {
            jurusan::create([
                'id_jurusan' => $data['id_jurusan'],
                'nama_jurusan' => $data['nama_jurusan'],
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
        return view('superadmin.jurusan.edit', [
            'jurusan' => $this->cariJurusan($id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $jurusan = $this->cariJurusan($id);

        $request->merge([
            'nama_jurusan' => $this->rapikanNama($request->input('nama_jurusan')),
        ]);

        $data = $request->validate(
            [
                'nama_jurusan' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('jurusan', 'nama_jurusan')
                        ->ignore($jurusan->id_jurusan, 'id_jurusan'),
                ],
            ],
            $this->pesan(),
            $this->atribut()
        );

        $namaLama = $jurusan->nama_jurusan;

        if ($namaLama === $data['nama_jurusan']) {
            return redirect()
                ->route('superadmin.jurusan.index')
                ->with('success', 'Tidak ada perubahan pada jurusan "' . $jurusan->id_jurusan . '".');
        }

        try {
            jurusan::where('id_jurusan', $jurusan->id_jurusan)
                ->update(['nama_jurusan' => $data['nama_jurusan']]);
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui jurusan', [
                'id_jurusan' => $jurusan->id_jurusan,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Jurusan gagal diperbarui. Tidak ada perubahan yang tersimpan, silakan coba lagi.');
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_MASTER,
            'Ubah Jurusan',
            'Jurusan "' . $jurusan->id_jurusan . '": nama ' . $namaLama
                . ' → ' . $data['nama_jurusan'] . '.'
        );

        return redirect()
            ->route('superadmin.jurusan.index')
            ->with('success', 'Jurusan "' . $jurusan->id_jurusan . '" berhasil diperbarui.');
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

    private function rapikanNama($nama): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $nama));
    }

    private function atribut(): array
    {
        return [
            'id_jurusan' => 'Kode jurusan',
            'nama_jurusan' => 'Nama jurusan',
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
        ];
    }
}