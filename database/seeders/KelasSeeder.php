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
     * Kelas 10      => Program Keahlian    (AKL, MPLB, PM, PPLG, DKV, TJKT)
     * Kelas 11 / 12 => Kompetensi Keahlian (AK, MP, MLOG, RPL, TKJ, BR, DKV)
     *
     * id_kelas memakai kode kompetensi di semua tingkat (10-RPL-1, 11-RPL-1, ...)
     * supaya proses naik kelas (ganti awalan tingkat) tetap jalan.
     * nama_kelas memakai kode sesuai tingkat (10 PPLG 1, 11 RPL 1, ...).
     */
    public function run(): void
    {
        // [id_jurusan, kode konsentrasi, jumlah rombel kelas 10, 11, 12]
        $daftar = [
            ['RPL', 'RPL',  2, 2, 2],
            ['PM',  'BR',   3, 3, 3],
            ['AK',  'AK',   4, 4, 4],
            ['TKJ', 'TKJ',  2, 2, 2],
            ['DKV', 'DKV',  2, 2, 2],
            ['MP',  'MP',   4, 4, 4],
            ['MP',  'MLOG', 0, 3, 3], // konsentrasi MLOG di jurusan MP, belum ada kelas 10
        ];

        $now  = now();
        $rows = [];

        foreach ($daftar as [$idJurusan, $kodeKonsentrasi, $jml10, $jml11, $jml12]) {
            // Pakai daftar pasangan (bukan array ber-key angka) supaya tingkat tetap string.
            foreach ([['10', $jml10], ['11', $jml11], ['12', $jml12]] as [$tingkat, $jumlah]) {
                $kodeNama = $tingkat === '10'
                    ? kelas::PROGRAM_KEAHLIAN[$idJurusan]   // PPLG / MPLB / dst
                    : $kodeKonsentrasi;                      // RPL / MLOG / BR / dst

                for ($n = 1; $n <= $jumlah; $n++) {
                    $rows[] = [
                        'id_kelas'   => "{$tingkat}-{$kodeKonsentrasi}-{$n}",
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