<?php

namespace App\Support;

use App\Models\kelas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kelas tujuan untuk kenaikan kelas.
 *
 * Hanya kelas 11 -> 12 yang dipetakan satu-satu dari id kelasnya:
 *   11-MLOG-1 -> 12-MLOG-1 (nama "12 MLOG 1")
 *
 * Kelas 10 -> 11 TIDAK dipetakan satu-satu. Siswa kelas 10 dicampur dan ditempatkan
 * ke kelas 11 yang SUDAH ADA sesuai rencana Ketua Program (mis. 10 MPLB 5 bisa
 * masuk 11 MLOG 1), jadi kelas 10 tidak membutuhkan kelas 11 pasangan.
 *
 * Kelas tujuan harus ada sebelum tahun ajaran diganti.
 */
class KelasTujuan
{
    /**
     * Daftar kelas tujuan yang BELUM ada.
     *
     * @return Collection<int, array{id_kelas:string, nama_kelas:string, id_jurusan:?string, dari:string}>
     */
    public static function kurang(): Collection
    {
        $semua = kelas::query()
            ->where('id_kelas', '!=', 'ALUMNI')
            ->get(['id_kelas', 'nama_kelas', 'id_jurusan']);

        $ada    = $semua->pluck('id_kelas')->flip();
        $hasil  = [];

        foreach ($semua as $k) {
            // Hanya kelas 11 yang butuh pasangan kelas 12 dengan kode & nomor yang sama.
            if (!preg_match('/^(11)-(.+)-(\d+)$/', trim((string) $k->id_kelas), $m)) {
                continue;
            }

            $tingkatBaru = '12';
            $idBaru      = "{$tingkatBaru}-{$m[2]}-{$m[3]}";

            if (isset($ada[$idBaru])) {
                continue;
            }

            $hasil[$idBaru] = [
                'id_kelas'   => $idBaru,
                'nama_kelas' => "{$tingkatBaru} {$m[2]} {$m[3]}",
                'id_jurusan' => $k->id_jurusan,
                'dari'       => $k->nama_kelas,
            ];
        }

        ksort($hasil);

        return collect(array_values($hasil));
    }

    /**
     * Buat semua kelas tujuan yang belum ada. Mengembalikan jumlah kelas yang dibuat.
     */
    public static function buat(): int
    {
        $dibuat = 0;

        DB::transaction(function () use (&$dibuat) {
            $sekarang = now();

            foreach (self::kurang() as $k) {
                // Lewati kalau nama tidak cocok dengan pemetaan program/konsentrasi jurusannya.
                if (kelas::validasiNama($k['nama_kelas'], $k['id_jurusan']) !== null) {
                    continue;
                }

                $dibuat += DB::table('kelas')->insertOrIgnore([
                    'id_kelas'   => $k['id_kelas'],
                    'nama_kelas' => $k['nama_kelas'],
                    'id_jurusan' => $k['id_jurusan'],
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
        });

        return $dibuat;
    }
}