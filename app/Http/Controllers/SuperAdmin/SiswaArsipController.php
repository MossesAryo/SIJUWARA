<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\kelas;
use App\Models\siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class SiswaArsipController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $idKelas = (string) $request->query('kelas', '');
        $like = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $q
        ) . '%';

        $daftar = siswa::onlyTrashed()
            ->with('kelas:id_kelas,nama_kelas')
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('nama_siswa', 'like', $like)
                        ->orWhere('nis', 'like', $like);
                });
            })
            ->when(
                $idKelas !== '',
                fn ($query) => $query->where('id_kelas', $idKelas)
            )
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $penghapus = ActivityLog::with('user:id,username')
            ->whereIn('nis', $daftar->pluck('nis')->all())
            ->where('activity', 'Arsipkan Siswa')
            ->orderBy('id')
            ->get()
            ->keyBy('nis');

        $daftarKelas = kelas::whereIn(
            'id_kelas',
            siswa::onlyTrashed()->select('id_kelas')
        )
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas']);

        return view('superadmin.siswa_arsip.index', [
            'daftar' => $daftar,
            'penghapus' => $penghapus,
            'daftarKelas' => $daftarKelas,
            'totalArsip' => siswa::onlyTrashed()->count(),
            'q' => $q,
            'idKelas' => $idKelas,
        ]);
    }

    public function restore(string $nis)
    {
        $siswa = siswa::onlyTrashed()
            ->where('nis', $nis)
            ->first();

        if (! $siswa) {
            return redirect()
                ->route('superadmin.siswa-arsip.index')
                ->with('error', 'Siswa tidak ditemukan di tong sampah.');
        }

        try {
            $siswa->restore();
        } catch (Throwable $e) {
            Log::error('Gagal memulihkan siswa', [
                'nis' => $nis,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.siswa-arsip.index')
                ->with('error', 'Siswa gagal dipulihkan. Silakan coba lagi.');
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_SISWA,
            'Pulihkan Siswa',
            "Siswa {$siswa->nama_siswa} (NIS {$siswa->nis}) dipulihkan dari tong sampah.",
            $siswa->nis
        );

        return redirect()
            ->route('superadmin.siswa-arsip.index')
            ->with(
                'success',
                'Siswa "' . $siswa->nama_siswa . '" berhasil dipulihkan.'
            );
    }

    /**
     * Halaman konfirmasi hapus permanen siswa.
     */
    public function konfirmasiHapus(string $nis)
    {
        $siswa = siswa::onlyTrashed()
            ->with('kelas:id_kelas,nama_kelas')
            ->where('nis', $nis)
            ->first();

        if (! $siswa) {
            return redirect()
                ->route('superadmin.siswa-arsip.index')
                ->with('error', 'Siswa tidak ditemukan di tong sampah.');
        }

        return view('superadmin.siswa_arsip.delete', [
            'siswa' => $siswa,
            'dampak' => $this->hitungDampak($nis),
        ]);
    }

    /**
     * Menghapus permanen siswa yang sudah diarsipkan.
     */
    public function forceDelete(Request $request, string $nis)
    {
        $siswa = siswa::onlyTrashed()
            ->with('kelas:id_kelas,nama_kelas')
            ->where('nis', $nis)
            ->first();

        if (! $siswa) {
            return redirect()
                ->route('superadmin.siswa-arsip.index')
                ->with('error', 'Siswa tidak ditemukan di tong sampah.');
        }

        $request->validate(
            [
                'konfirmasi_nis' => [
                    'required',
                    Rule::in([(string) $siswa->nis]),
                ],
            ],
            [
                'konfirmasi_nis.required' => 'Ketik NIS siswa untuk mengonfirmasi.',
                'konfirmasi_nis.in' => 'NIS yang diketik tidak sama dengan NIS siswa.',
            ]
        );

        $nama = $siswa->nama_siswa;
        $namaKelas = $siswa->kelas->nama_kelas ?? '-';
        $dampak = $this->hitungDampak($nis);

        // Simpan path file sebelum data terkait terhapus melalui cascade.
        $fileBukti = DB::table('bukti_pembinaan')
            ->whereIn(
                'intervensi_id',
                DB::table('intervensi')
                    ->where('nis', $nis)
                    ->select('id_intervensi')
            )
            ->pluck('file')
            ->all();

        try {
            DB::transaction(function () use ($siswa) {
                $siswa->forceDelete();
            });
        } catch (Throwable $e) {
            Log::error('Gagal menghapus permanen siswa', [
                'nis' => $nis,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.siswa-arsip.index')
                ->with(
                    'error',
                    'Siswa gagal dihapus permanen. Tidak ada data yang berubah, silakan coba lagi.'
                );
        }

        foreach ($fileBukti as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable $e) {
                Log::warning('File bukti pembinaan gagal dihapus', [
                    'file' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $ringkas = collect($dampak)
            ->filter()
            ->map(fn ($jumlah, $namaData) => "{$namaData} {$jumlah}")
            ->implode(', ');

        // Tidak mengisi NIS karena data siswa sudah dihapus permanen.
        ActivityLog::catat(
            ActivityLog::KATEGORI_SISWA,
            'Hapus Permanen Siswa',
            "Siswa {$nama} (NIS {$nis}), kelas {$namaKelas}, dihapus permanen. Ikut terhapus: "
                . ($ringkas !== '' ? $ringkas : 'tidak ada data terkait') . '.'
        );

        return redirect()
            ->route('superadmin.siswa-arsip.index')
            ->with(
                'success',
                'Siswa "' . $nama . '" berhasil dihapus permanen.'
            );
    }

    /**
     * Menghitung data yang ikut terhapus bersama siswa.
     */
    private function hitungDampak(string $nis): array
    {
        return [
            'Penilaian' => DB::table('penilaian')
                ->where('nis', $nis)
                ->count(),

            'Catatan' => DB::table('catatan')
                ->where('nis', $nis)
                ->count(),

            'Intervensi' => DB::table('intervensi')
                ->where('nis', $nis)
                ->count(),

            'Bukti pembinaan' => DB::table('bukti_pembinaan')
                ->whereIn(
                    'intervensi_id',
                    DB::table('intervensi')
                        ->where('nis', $nis)
                        ->select('id_intervensi')
                )
                ->count(),

            'Penghargaan' => DB::table('siswapenghargaan')
                ->where('nis', $nis)
                ->count(),

            'Surat peringatan' => DB::table('siswaperingatan')
                ->where('nis', $nis)
                ->count(),
        ];
    }
}