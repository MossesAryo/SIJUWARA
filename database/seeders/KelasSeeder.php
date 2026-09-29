<?php

namespace Database\Seeders;

use App\Models\kelas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KelasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Kelas X      => Program Keahlian    (AKL, MPLB, PM, PPLG, DKV, TJKT)
     * Kelas XI/XII => Kompetensi Keahlian (AK, MP, MLOG, RPL, TKJ, BR, DKV)
     *
     * id_kelas memakai kode kompetensi di semua tingkat (X-RPL-1, XI-RPL-1, ...)
     * supaya proses naik kelas (ganti awalan tingkat) tetap jalan.
     * nama_kelas memakai kode sesuai tingkat (X PPLG 1, XI RPL 1, ...).
     */
    public function run(): void
    {
        // [id_jurusan, jumlah rombel X, XI, XII]
        $daftar = [
            ['RPL',  2, 2, 2],
            ['PM',   3, 3, 3],
            ['AK',   4, 4, 4],
            ['TKJ',  2, 2, 2],
            ['DKV',  2, 2, 2],
            ['MLOG', 0, 3, 3], // belum ada kelas X MLOG
            ['MP',   4, 4, 4],
        ];

        $now  = now();
        $rows = [];

        foreach ($daftar as [$idJurusan, $jmlX, $jmlXI, $jmlXII]) {
            foreach (['X' => $jmlX, 'XI' => $jmlXI, 'XII' => $jmlXII] as $tingkat => $jumlah) {
                $kodeNama = kelas::kodeKeahlian($idJurusan, $tingkat);   // PPLG / RPL / dst
                $kodeId   = kelas::kodeKeahlian($idJurusan, 'XI');       // RPL / BR / dst

                for ($n = 1; $n <= $jumlah; $n++) {
                    $rows[] = [
                        'id_kelas'   => "{$tingkat}-{$kodeId}-{$n}",
                        'nama_kelas' => "{$tingkat} {$kodeNama} {$n}",
                        'id_jurusan' => $idJurusan,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $rows[] = [
            'id_kelas'   => 'ALUMNI',
            'nama_kelas' => 'ALUMNI',
            'id_jurusan' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('kelas')->insert($rows);
    }
}