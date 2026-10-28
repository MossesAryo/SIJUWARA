<?php

namespace App\Support;

use App\Models\kelas;
use App\Models\siswa;
use App\Models\StudentClassHistory;
use App\Models\tahunAjaran;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;
use Throwable;

/**
 * Kelas dan tahun ajaran siswa PADA SAAT sebuah kejadian (catatan, pelanggaran, penghargaan,
 * peringatan, pembinaan), bukan kelasnya sekarang.
 *
 * Dihitung dari garis waktu Riwayat Kelas (student_class_histories), sehingga berlaku juga untuk
 * data lama tanpa kolom tambahan di tabel kejadian:
 *   - kejadian SETELAH perubahan kelas terakhir yang sudah terjadi   -> kelas tujuan perubahan itu;
 *   - kejadian SEBELUM perubahan kelas pertama                        -> kelas asal perubahan pertama;
 *   - siswa belum pernah punya riwayat                                -> kelas sekarang (ditandai perkiraan).
 *
 * Tahun ajaran kejadian = tahun ajaran yang DIAKHIRI oleh perubahan kelas berikutnya (kolom
 * tahun_ajaran pada riwayat). Kalau belum ada perubahan setelahnya, dipakai tahun ajaran aktif.
 *
 * Pemakaian di view:
 *   KelasSaatKejadian::muatDari($daftar);                       // satu query untuk semua siswa di daftar
 *   $k = KelasSaatKejadian::pada($item->nis, $item->created_at);
 *   $k['nama_kelas'], $k['tahun_ajaran'], $k['perkiraan']
 */
class KelasSaatKejadian
{
    private const CHUNK = 500;

    /** @var array<string, array{riwayat: array<int,array<string,mixed>>, id_kelas: ?string, nama_kelas: ?string}> */
    private static array $garisWaktu = [];

    private static ?string $tahunAktif = null;
    private static bool $tahunAktifDimuat = false;

    /** Kosongkan cache (dipakai tes). */
    public static function lupakan(): void
    {
        self::$garisWaktu       = [];
        self::$tahunAktif       = null;
        self::$tahunAktifDimuat = false;
    }

    /**
     * Muat garis waktu banyak siswa sekaligus dari daftar apa pun (koleksi, array, paginator).
     *
     * @param  iterable|Paginator  $daftar
     */
    public static function muatDari($daftar, string $kolomNis = 'nis'): void
    {
        if ($daftar instanceof Paginator) {
            $daftar = $daftar->items();
        }

        $nis = [];
        foreach ($daftar as $baris) {
            $n = data_get($baris, $kolomNis);
            if ($n !== null && $n !== '') {
                $nis[(string) $n] = (string) $n;
            }
        }

        self::muat(array_values($nis));
    }

    /**
     * @param  array<int,string|int>  $daftarNis
     */
    public static function muat(array $daftarNis): void
    {
        $belum = [];
        foreach ($daftarNis as $nis) {
            $nis = (string) $nis;
            if (!isset(self::$garisWaktu[$nis])) {
                $belum[$nis] = $nis;
            }
        }

        foreach (array_chunk(array_values($belum), self::CHUNK) as $potongan) {
            $riwayat = StudentClassHistory::query()
                ->whereIn('nis', $potongan)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'id', 'nis', 'id_kelas_sebelum', 'nama_kelas_sebelum',
                    'id_kelas_sesudah', 'nama_kelas_sesudah', 'tahun_ajaran', 'tanggal_mulai', 'created_at',
                ])
                ->groupBy(fn ($h) => (string) $h->nis);

            // Nama kelas diambil lewat peta, bukan relasi: id_kelas berupa teks ("10-RPL-1").
            $namaKelas = kelas::pluck('nama_kelas', 'id_kelas');

            $sekarang = siswa::withTrashed()
                ->whereIn('nis', $potongan)
                ->get(['nis', 'id_kelas'])
                ->keyBy(fn ($s) => (string) $s->nis);

            foreach ($potongan as $nis) {
                $baris = [];
                foreach ($riwayat->get($nis, collect()) as $h) {
                    $baris[] = [
                        'sebelum_id'   => $h->id_kelas_sebelum,
                        'sebelum_nama' => $h->nama_kelas_sebelum,
                        'sesudah_id'   => $h->id_kelas_sesudah,
                        'sesudah_nama' => $h->nama_kelas_sesudah,
                        'tahun_ajaran' => $h->tahun_ajaran,
                        // Waktu perubahan sebenarnya; riwayat lama tanpa created_at memakai tanggal mulai.
                        'waktu'        => ($h->created_at ?? $h->tanggal_mulai)?->copy(),
                    ];
                }

                $s = $sekarang->get($nis);

                self::$garisWaktu[$nis] = [
                    'riwayat'    => $baris,
                    'id_kelas'   => $s?->id_kelas,
                    'nama_kelas' => $s ? ($namaKelas[$s->id_kelas] ?? $s->id_kelas) : null,
                ];
            }
        }
    }

    /**
     * Kelas & tahun ajaran siswa pada $waktu.
     *
     * @param  string|int  $nis
     * @param  \DateTimeInterface|string|null  $waktu
     * @return array{id_kelas:?string, nama_kelas:?string, tahun_ajaran:?string, perkiraan:bool}
     */
    public static function pada($nis, $waktu): array
    {
        $nis = (string) $nis;
        self::muat([$nis]);

        $data  = self::$garisWaktu[$nis];
        $waktu = self::keCarbon($waktu);

        if (empty($data['riwayat'])) {
            // Tidak ada jejak perpindahan: kelas sekarang adalah perkiraan terbaik.
            return [
                'id_kelas'     => $data['id_kelas'],
                'nama_kelas'   => $data['nama_kelas'],
                'tahun_ajaran' => self::tahunAktif(),
                'perkiraan'    => true,
            ];
        }

        $berlaku = null;   // perubahan terakhir yang sudah terjadi pada $waktu
        $berikut = null;   // perubahan pertama yang terjadi SETELAH $waktu dan membawa tahun ajaran

        foreach ($data['riwayat'] as $r) {
            if ($r['waktu'] !== null && $waktu !== null && $r['waktu']->gt($waktu)) {
                if ($berikut === null && !empty($r['tahun_ajaran'])) {
                    $berikut = $r;
                }
                continue;
            }

            $berlaku = $r;
        }

        if ($berlaku !== null) {
            $id   = $berlaku['sesudah_id'];
            $nama = $berlaku['sesudah_nama'];
        } else {
            // Kejadian lebih lama dari perubahan kelas pertama: kelas asal perubahan pertama.
            $pertama = $data['riwayat'][0];
            $id      = $pertama['sebelum_id'];
            $nama    = $pertama['sebelum_nama'];
        }

        return [
            'id_kelas'     => $id,
            'nama_kelas'   => $nama ?: $id,
            'tahun_ajaran' => $berikut['tahun_ajaran'] ?? self::tahunAktif(),
            'perkiraan'    => ($nama ?: $id) === null,
        ];
    }

    /** Nama kelas saat kejadian, atau "-" bila tidak diketahui. */
    public static function nama($nis, $waktu): string
    {
        return self::pada($nis, $waktu)['nama_kelas'] ?? '-';
    }

    /**
     * Teks ringkas: "10 RPL 1 · TA 2026/2027".
     *
     * Siswa yang belum punya riwayat kelas sama sekali (data sebelum fitur Riwayat Kelas) hanya diketahui
     * kelasnya SEKARANG, jadi ditulis "(perkiraan)" tanpa tahun ajaran, supaya tidak dikira data pasti.
     */
    public static function label($nis, $waktu): string
    {
        $k = self::pada($nis, $waktu);

        $teks = $k['nama_kelas'] ?? '-';

        if ($k['perkiraan']) {
            return $teks . ' (perkiraan)';
        }

        if (!empty($k['tahun_ajaran'])) {
            $teks .= ' · TA ' . $k['tahun_ajaran'];
        }

        return $teks;
    }

    private static function tahunAktif(): ?string
    {
        if (!self::$tahunAktifDimuat) {
            try {
                self::$tahunAktif = tahunAjaran::where('status', 'aktif')->value('tahun_ajaran');
            } catch (Throwable $e) {
                self::$tahunAktif = null;
            }
            self::$tahunAktifDimuat = true;
        }

        return self::$tahunAktif;
    }

    private static function keCarbon($waktu): ?Carbon
    {
        if ($waktu === null || $waktu === '') {
            return null;
        }

        try {
            return $waktu instanceof Carbon ? $waktu : Carbon::parse($waktu);
        } catch (Throwable $e) {
            return null;
        }
    }
}