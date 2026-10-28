<?php

namespace App\Support;

use Carbon\Carbon;
use Generator;

/**
 * Pembaca file backup (hanya baca) untuk halaman Super Admin.
 *
 * Membaca file Excel (.xlsx) buatan BackupData menjadi array biasa, tanpa mengubah database.
 */
class BackupReader
{
    /** Tabel yang ada di file backup, urut tampil. */
    public const TABEL = BackupData::TABEL;

    /** Nama file yang sah: huruf/angka/titik/strip/garis bawah, berakhiran .xlsx. */
    public static function namaValid(string $nama): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]+\.xlsx$/', $nama) && !str_contains($nama, '..');
    }

    /** Path lengkap file, atau null bila nama tidak sah / file tidak ada. */
    public static function path(string $nama): ?string
    {
        if (!self::namaValid($nama)) {
            return null;
        }

        $path = BackupData::folder() . DIRECTORY_SEPARATOR . $nama;

        return is_file($path) ? $path : null;
    }

    /**
     * Daftar backup beserta keterangannya, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(): array
    {
        return array_map(fn ($b) => self::lengkapi($b), BackupData::daftar());
    }

    /** Satu backup (null bila tidak ada). */
    public static function cari(string $nama): ?array
    {
        $path = self::path($nama);

        if ($path === null) {
            return null;
        }

        return self::lengkapi([
            'nama'   => basename($path),
            'path'   => $path,
            'ukuran' => (int) filesize($path),
            'waktu'  => (int) filemtime($path),
            'valid'  => BackupData::valid($path),
        ]);
    }

    /** Tambahkan alasan, waktu dibuat, jenis, dan jumlah baris per tabel. */
    private static function lengkapi(array $b): array
    {
        $info = BackupData::info($b['path']) ?? [];

        $b['alasan']   = $info['alasan'] ?? '-';
        $b['database'] = $info['database'] ?? '-';
        $b['dibuat']   = self::waktuDibuat($info['dibuat'] ?? null, $b['waktu']);
        $b['jumlah']   = $b['valid'] ? ($info['jumlah'] ?? []) : [];
        $b['jenis']    = self::jenis($b['nama']);

        return $b;
    }

    private static function waktuDibuat(?string $teks, int $cadangan): Carbon
    {
        if ($teks) {
            try {
                return Carbon::parse($teks);
            } catch (\Throwable $e) {
                // jatuh ke waktu file
            }
        }

        return Carbon::createFromTimestamp($cadangan);
    }

    /** Jenis backup dilihat dari awalan nama file. */
    public static function jenis(string $nama): array
    {
        if (str_starts_with($nama, 'pengaman-sebelum-pulih')) {
            return ['kode' => 'pengaman', 'label' => 'Pengaman pemulihan', 'warna' => 'bg-amber-50 text-amber-700'];
        }

        if (str_starts_with($nama, 'sebelum-ganti-tahun')) {
            return ['kode' => 'tahun', 'label' => 'Ganti tahun ajaran', 'warna' => 'bg-blue-50 text-blue-700'];
        }

        return ['kode' => 'lain', 'label' => 'Lainnya', 'warna' => 'bg-gray-100 text-gray-600'];
    }

    /**
     * Baca semua baris satu tabel dari file backup.
     *
     * Hasil tiap baris: kolom => nilai (teks / null), termasuk kunci utama (mis. nis / nip_walikelas / id).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public static function baris(string $path, string $tabel): Generator
    {
        if (!array_key_exists($tabel, self::TABEL)) {
            return;
        }

        try {
            $rows = BackupData::bacaTabel($path, $tabel);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $row) {
            yield $row;
        }
    }
}