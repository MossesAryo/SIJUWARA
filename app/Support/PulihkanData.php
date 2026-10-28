<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Logika memulihkan file backup (Excel) buatan BackupData.
 *
 * Dipakai bersama oleh perintah `php artisan backup:pulihkan` dan tombol pulihkan di halaman
 * Super Admin, supaya keduanya memulihkan dengan cara yang persis sama.
 *
 *  1. baca()      : baca & periksa SELURUH isi file sebelum ada data yang berubah.
 *  2. jalankan()  : amankan keadaan sekarang (backup pengaman), lalu pulihkan dalam satu transaksi.
 *
 * Tabel mode "update" hanya mengembalikan kolom tertentu; mode "insert" dan "sinkron" memasukkan baris
 * yang hilang; mode "sinkron" (Riwayat Kelas) juga menghapus baris yang dibuat SETELAH backup, karena
 * baris itu mencatat perubahan kelas yang dibatalkan oleh pemulihan. Tidak ada tabel yang di-TRUNCATE.
 */
class PulihkanData
{
    private const CHUNK = 200;

    /**
     * Baca semua sheet dan pastikan strukturnya cocok dengan database dan catatan di sheet Info.
     * Tabel opsional yang tidak ada di file (backup lama) dilewati, bukan dianggap rusak.
     *
     * @return array<string, array<int, array<string, string|null>>>
     */
    public static function baca(string $path): array
    {
        $info = BackupData::info($path);

        if ($info === null || !$info['selesai']) {
            throw new RuntimeException('File backup tidak lengkap atau rusak (sheet Info tidak berstatus SELESAI).');
        }

        $data = [];

        foreach (BackupData::SPEK as $tabel => $spec) {
            if (!array_key_exists($tabel, $info['jumlah'])) {
                if (empty($spec['opsional'])) {
                    throw new RuntimeException("Sheet {$tabel} tidak tercatat di sheet Info.");
                }

                continue; // backup lama: sheet ini memang belum ada
            }

            $rows = BackupData::bacaTabel($path, $tabel);

            if (count($rows) !== $info['jumlah'][$tabel]) {
                throw new RuntimeException(
                    "Sheet {$tabel} berisi " . count($rows) . ' baris, padahal sheet Info mencatat '
                    . $info['jumlah'][$tabel] . '. File mungkin sudah diubah.'
                );
            }

            // Kolom nama (siswa / guru / kelas) hanya informasi di file; bukan untuk dipulihkan.
            $kolomInfo = BackupData::kolomInfo($tabel);
            $rows      = array_map(fn ($r) => Arr::except($r, array_diff($kolomInfo, $spec['kolom'] ?? [], [$spec['kunci']])), $rows);

            if (!empty($rows)) {
                $kolomFile = array_keys($rows[0]);
                $kolomDb   = Schema::getColumnListing($tabel);
                $wajib     = $spec['mode'] === 'update'
                    ? array_merge([$spec['kunci']], $spec['kolom'])
                    : [$spec['kunci']];

                $hilang = array_diff($wajib, $kolomFile);
                if ($hilang) {
                    throw new RuntimeException("Sheet {$tabel} kehilangan kolom: " . implode(', ', $hilang) . '.');
                }

                if ($spec['mode'] === 'update') {
                    // Kolom informasi yang sama dengan nama kolom asli (mis. tahun_ajaran) tidak dipulihkan,
                    // tetapi tetap boleh ada di file.
                    $rows = array_map(fn ($r) => Arr::only($r, array_merge([$spec['kunci']], $spec['kolom'])), $rows);
                    $kolomFile = array_keys($rows[0]);
                }

                $asing = array_diff($kolomFile, $kolomDb);
                if ($asing) {
                    throw new RuntimeException("Sheet {$tabel} punya kolom yang tidak ada di database: " . implode(', ', $asing) . '.');
                }

                foreach ($rows as $i => $row) {
                    if (($row[$spec['kunci']] ?? null) === null || $row[$spec['kunci']] === '') {
                        throw new RuntimeException("Sheet {$tabel} baris data ke-" . ($i + 1) . " tidak punya {$spec['kunci']}.");
                    }
                }
            }

            $data[$tabel] = $rows;
        }

        return $data;
    }

    /**
     * Pulihkan. Backup pengaman dibuat dulu (gagal = proses berhenti, tidak ada data berubah).
     *
     * @param  array<string, array<int, array<string, string|null>>>|null  $data  hasil baca() bila sudah dibaca
     * @return array{pengaman:string, ringkas:array<string, array{dipulihkan:int, dihapus:int, dilewati:int}>}
     *
     * @throws RuntimeException pesan siap tampil
     */
    public static function jalankan(string $path, ?array $data = null): array
    {
        $nama = basename($path);
        $data ??= self::baca($path);

        // Keadaan sekarang diamankan dulu, supaya pemulihan pun bisa dibatalkan.
        $pengaman = BackupData::buat("Pengaman sebelum memulihkan {$nama}", 'pengaman-sebelum-pulih');

        $ringkas = [];

        try {
            DB::transaction(function () use ($data, &$ringkas) {
                foreach ($data as $tabel => $rows) {
                    $ringkas[$tabel] = self::pulihkanTabel($tabel, BackupData::SPEK[$tabel], $rows);
                }
            });
        } catch (\Throwable $e) {
            throw new RuntimeException('Pemulihan gagal, tidak ada data yang berubah: ' . $e->getMessage(), 0, $e);
        }

        return ['pengaman' => basename($pengaman), 'ringkas' => $ringkas];
    }

    /**
     * Kembalikan satu tabel. Wajib dipanggil di dalam DB::transaction.
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{dipulihkan:int, dihapus:int, dilewati:int}
     */
    private static function pulihkanTabel(string $tabel, array $spec, array $rows): array
    {
        $hasil = ['dipulihkan' => 0, 'dihapus' => 0, 'dilewati' => 0];

        if ($spec['mode'] === 'sinkron') {
            // Id terbesar di backup (sebelum baris disaring). Baris dengan id lebih besar dibuat SETELAH backup.
            $idTerbesar = 0;
            foreach ($rows as $row) {
                $idTerbesar = max($idTerbesar, (int) $row[$spec['kunci']]);
            }

            // Riwayat siswa yang sudah dihapus permanen tidak bisa dimasukkan lagi (foreign key ke siswa).
            $nisAda = DB::table('siswa')->pluck('nis')->map(fn ($n) => (string) $n)->flip();
            $layak  = [];

            foreach ($rows as $row) {
                if (isset($nisAda[(string) ($row['nis'] ?? '')])) {
                    $layak[] = $row;
                } else {
                    $hasil['dilewati']++;
                }
            }

            $hasil['dihapus'] = DB::table($tabel)->where($spec['kunci'], '>', $idTerbesar)->delete();
            $rows             = $layak;
        }

        if (empty($rows)) {
            return $hasil;
        }

        if ($spec['mode'] === 'update') {
            foreach ($rows as $row) {
                DB::table($tabel)
                    ->where($spec['kunci'], $row[$spec['kunci']])
                    ->update(Arr::only($row, $spec['kolom']));
            }

            $hasil['dipulihkan'] = count($rows);

            return $hasil;
        }

        $kolom = array_keys($rows[0]);

        foreach (array_chunk($rows, self::CHUNK) as $potong) {
            DB::table($tabel)->upsert($potong, [$spec['kunci']], $kolom);
        }

        $hasil['dipulihkan'] = count($rows);

        return $hasil;
    }
}