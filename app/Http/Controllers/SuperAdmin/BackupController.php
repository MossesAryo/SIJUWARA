<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\BackupData;
use App\Support\BackupReader;
use App\Support\PulihkanData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Halaman Super Admin untuk MELIHAT, MENGUNDUH, dan MEMULIHKAN titik pemulihan (backup) otomatis
 * berupa file Excel (.xlsx).
 *
 * Memulihkan dari web sama persis dengan perintah `php artisan backup:pulihkan NAMA_FILE.xlsx`
 * (keduanya memakai App\Support\PulihkanData). Supaya tidak terpicu tidak sengaja, pemulihan:
 *   - hanya lewat POST oleh Super Admin,
 *   - wajib mengetik kata PULIHKAN,
 *   - selalu membuat backup pengaman dulu, sehingga pemulihan pun bisa dibatalkan,
 *   - dicatat di Log Aktivitas.
 */
class BackupController extends Controller
{
    private const PER_HALAMAN_DAFTAR = 15;
    private const PER_HALAMAN_ISI    = 25;

    public function index(Request $request)
    {
        $request->validate([
            'q'     => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:tahun,pengaman,lain'],
        ]);

        $q     = trim((string) $request->query('q', ''));
        $jenis = (string) $request->query('jenis', '');

        $semua = BackupReader::daftar();

        $ringkasan = [
            'total'   => count($semua),
            'valid'   => count(array_filter($semua, fn ($b) => $b['valid'])),
            'rusak'   => count(array_filter($semua, fn ($b) => !$b['valid'])),
            'ukuran'  => array_sum(array_column($semua, 'ukuran')),
            'terbaru' => $semua[0]['dibuat'] ?? null,
        ];

        $tersaring = array_values(array_filter($semua, function ($b) use ($q, $jenis) {
            if ($jenis !== '' && $b['jenis']['kode'] !== $jenis) {
                return false;
            }

            return $q === ''
                || mb_stripos($b['nama'], $q) !== false
                || mb_stripos($b['alasan'], $q) !== false;
        }));

        $halaman = max(1, (int) $request->query('page', 1));
        $daftar  = new LengthAwarePaginator(
            array_slice($tersaring, ($halaman - 1) * self::PER_HALAMAN_DAFTAR, self::PER_HALAMAN_DAFTAR),
            count($tersaring),
            self::PER_HALAMAN_DAFTAR,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('superadmin.backup.index', [
            'daftar'    => $daftar,
            'ringkasan' => $ringkasan,
            'folder'    => BackupData::folder(),
            'q'         => $q,
            'jenis'     => $jenis,
        ]);
    }

    public function show(Request $request, string $nama)
    {
        $backup = BackupReader::cari($nama);

        if ($backup === null) {
            return redirect()
                ->route('superadmin.backup.index')
                ->with('error', 'File backup tidak ditemukan.');
        }

        $request->validate([
            'tabel'   => ['nullable', 'in:' . implode(',', array_keys(BackupReader::TABEL))],
            'q'       => ['nullable', 'string', 'max:100'],
            'berbeda' => ['nullable', 'in:1'],
        ]);

        $tabel   = (string) $request->query('tabel', 'siswa');
        $tabel   = array_key_exists($tabel, BackupReader::TABEL) ? $tabel : 'siswa';
        $q       = trim((string) $request->query('q', ''));
        $berbeda = $request->query('berbeda') === '1';

        $baris = $backup['valid']
            ? $this->perkaya($tabel, iterator_to_array(BackupReader::baris($backup['path'], $tabel), false))
            : [];

        $totalTabel   = count($baris);
        $totalBerbeda = count(array_filter($baris, fn ($b) => $b['_berbeda']));

        $tersaring = array_values(array_filter($baris, function ($b) use ($q, $berbeda) {
            if ($berbeda && !$b['_berbeda']) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            return mb_stripos($b['_cari'], $q) !== false;
        }));

        $halaman = max(1, (int) $request->query('page', 1));
        $isi     = new LengthAwarePaginator(
            array_slice($tersaring, ($halaman - 1) * self::PER_HALAMAN_ISI, self::PER_HALAMAN_ISI),
            count($tersaring),
            self::PER_HALAMAN_ISI,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('superadmin.backup.show', [
            'backup'       => $backup,
            'tabel'        => $tabel,
            'daftarTabel'  => BackupReader::TABEL,
            'isi'          => $isi,
            'totalTabel'   => $totalTabel,
            'totalBerbeda' => $totalBerbeda,
            'q'            => $q,
            'berbeda'      => $berbeda,
        ]);
    }

    /** Pulihkan dari file backup. Backup pengaman dibuat otomatis sebelum data diubah. */
    public function pulihkan(Request $request, string $nama)
    {
        $request->validate(
            ['konfirmasi' => ['required', 'in:PULIHKAN']],
            [
                'konfirmasi.required' => 'Ketik PULIHKAN untuk memastikan.',
                'konfirmasi.in'       => 'Ketik PULIHKAN (huruf besar semua) untuk memastikan.',
            ]
        );

        $path = BackupReader::path($nama);

        if ($path === null) {
            return redirect()
                ->route('superadmin.backup.index')
                ->with('error', 'File backup tidak ditemukan.');
        }

        try {
            $hasil = PulihkanData::jalankan($path);
        } catch (\Throwable $e) {
            return redirect()
                ->route('superadmin.backup.show', $nama)
                ->with('error', $e->getMessage() . ' Tidak ada data yang diubah.');
        }

        $ringkas = collect($hasil['ringkas'])
            ->map(fn ($n, $tabel) => (BackupData::TABEL[$tabel] ?? $tabel) . ' ' . $n['dipulihkan']
                . ($n['dihapus'] > 0 ? " (+{$n['dihapus']} setelah backup dihapus)" : ''))
            ->implode(', ');

        ActivityLog::catat(
            ActivityLog::KATEGORI_SISWA,
            'Pulihkan Backup',
            "Memulihkan {$nama}. {$ringkas}. Pengaman: {$hasil['pengaman']}"
        );

        return redirect()
            ->route('superadmin.backup.show', $nama)
            ->with('success', "Pemulihan dari {$nama} selesai ({$ringkas}). Keadaan sebelumnya diamankan di {$hasil['pengaman']}.");
    }

    /** Unduh file backup Excel apa adanya. */
    public function download(string $nama)
    {
        $path = BackupReader::path($nama);

        if ($path === null) {
            return redirect()
                ->route('superadmin.backup.index')
                ->with('error', 'File backup tidak ditemukan.');
        }

        return response()->download(
            $path,
            $nama,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Tambahkan nama (siswa / wali kelas / kelas) dan kondisi SEKARANG di database,
     * lalu tandai baris yang nilainya berbeda dari isi backup.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private function perkaya(string $tabel, array $baris): array
    {
        $kelas     = DB::table('kelas')->pluck('nama_kelas', 'id_kelas')->all();
        $namaKelas = fn ($id) => $id === null || $id === '' ? null : ($kelas[$id] ?? $id);

        if ($tabel === 'tahun_ajaran') {
            $sekarang = DB::table('tahun_ajaran')->get(['id', 'tahun_ajaran', 'status'])->keyBy('id');

            foreach ($baris as &$b) {
                $s = $sekarang->get($b['id']);

                $b['_label']           = $s->tahun_ajaran ?? '(sudah tidak ada)';
                $b['_status_sekarang'] = $s->status ?? null;
                $b['_berbeda']         = $s === null || $s->status !== $b['status'];
                $b['_cari']            = $b['_label'] . ' ' . $b['id'];
            }
            unset($b);

            return $baris;
        }

        if ($tabel === 'siswa') {
            $sekarang = DB::table('siswa')->get(['nis', 'nama_siswa', 'id_kelas', 'status'])->keyBy('nis');

            foreach ($baris as &$b) {
                $s = $sekarang->get($b['nis']);

                // Nama dari snapshot di file backup (tetap ada walau siswa sudah dihapus); file lama tanpa
                // kolom nama memakai nama di database sekarang.
                $b['_nama']            = $b['nama_siswa'] ?? ($s->nama_siswa ?? '(sudah tidak ada)');
                $b['_wali']            = $b['nama_walikelas'] ?? null;
                $b['_kelas']           = $namaKelas($b['id_kelas']);
                $b['_kelas_sekarang']  = $s ? $namaKelas($s->id_kelas) : null;
                $b['_status_sekarang'] = $s->status ?? null;
                $b['_berbeda']         = $s === null
                    || (string) $s->id_kelas !== (string) $b['id_kelas']
                    || $s->status !== $b['status'];
                $b['_cari']            = $b['nis'] . ' ' . $b['_nama'] . ' ' . $b['_kelas'] . ' ' . ($b['_wali'] ?? '');
            }
            unset($b);

            return $baris;
        }

        if ($tabel === 'walikelas') {
            $sekarang = DB::table('walikelas')->get(['nip_walikelas', 'nama_walikelas', 'id_kelas'])->keyBy('nip_walikelas');

            foreach ($baris as &$b) {
                $s = $sekarang->get($b['nip_walikelas']);

                $b['_nama']           = $b['nama_walikelas'] ?? ($s->nama_walikelas ?? '(sudah tidak ada)');
                $b['_kelas']          = $namaKelas($b['id_kelas']);
                $b['_kelas_sekarang'] = $s ? $namaKelas($s->id_kelas) : null;
                $b['_berbeda']        = $s === null || (string) $s->id_kelas !== (string) $b['id_kelas'];
                $b['_cari']           = $b['nip_walikelas'] . ' ' . $b['_nama'] . ' ' . $b['_kelas'];
            }
            unset($b);

            return $baris;
        }

        if ($tabel === 'student_class_histories') {
            $ada   = DB::table('student_class_histories')->pluck('id')->map(fn ($i) => (string) $i)->flip();
            $siswa = DB::table('siswa')->pluck('nama_siswa', 'nis')->all();

            foreach ($baris as &$b) {
                $b['_nama']      = $b['nama_siswa'] ?? ($siswa[$b['nis']] ?? '(sudah tidak ada)');
                $b['_masih_ada'] = $ada->has((string) $b['id']);
                $b['_berbeda']   = !$b['_masih_ada'];
                $b['_cari']      = $b['nis'] . ' ' . $b['_nama'] . ' ' . ($b['nama_kelas_sebelum'] ?? '') . ' '
                    . ($b['nama_kelas_sesudah'] ?? '') . ' ' . ($b['jenis'] ?? '') . ' ' . ($b['tahun_ajaran'] ?? '');
            }
            unset($b);

            return $baris;
        }

        // rencana_pindah_kelas: baris disimpan utuh; "berbeda" = rencana sudah tidak ada / berubah sekarang.
        $sekarang = DB::table('rencana_pindah_kelas')->get()->keyBy('id');
        $siswa    = DB::table('siswa')->pluck('nama_siswa', 'nis')->all();

        foreach ($baris as &$b) {
            $s = $sekarang->get($b['id']);

            $b['_nama']      = $b['nama_siswa'] ?? ($siswa[$b['nis']] ?? '(sudah tidak ada)');
            $b['_pembuat']   = $b['nama_pembuat'] ?? ($b['dibuat_oleh'] ?? null);
            $b['_tujuan']    = $namaKelas($b['id_kelas_tujuan']);
            $b['_masih_ada'] = $s !== null;
            $b['_berbeda']   = $s === null || (string) $s->id_kelas_tujuan !== (string) $b['id_kelas_tujuan'];
            $b['_cari']      = $b['nis'] . ' ' . $b['_nama'] . ' ' . $b['_tujuan'] . ' ' . ($b['_pembuat'] ?? '');
        }
        unset($b);

        return $baris;
    }
}