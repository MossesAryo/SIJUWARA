<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JurusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * MLOG bukan jurusan lagi: sekarang konsentrasi keahlian di jurusan MP (program MPLB).
     * kode_program  = Program Keahlian (kelas 10)
     * konsentrasi   = Konsentrasi Keahlian (kelas 11 & 12), dipisah koma
     */
    public function run(): void
    {
        $data = [
            ['id_jurusan' => 'RPL', 'nama_jurusan' => 'Rekayasa Perangkat Lunak', 'kode_program' => 'PPLG', 'konsentrasi' => 'RPL'],
            ['id_jurusan' => 'PM', 'nama_jurusan' => 'Pemasaran', 'kode_program' => 'PM', 'konsentrasi' => 'BR'],
            ['id_jurusan' => 'AK', 'nama_jurusan' => 'Akuntansi dan Keuangan Lembaga', 'kode_program' => 'AKL', 'konsentrasi' => 'AK'],
            ['id_jurusan' => 'TKJ', 'nama_jurusan' => 'Teknik Komputer dan Jaringan', 'kode_program' => 'TJKT', 'konsentrasi' => 'TKJ'],
            ['id_jurusan' => 'DKV', 'nama_jurusan' => 'Desain Komunikasi Visual', 'kode_program' => 'DKV', 'konsentrasi' => 'DKV'],
            ['id_jurusan' => 'MP', 'nama_jurusan' => 'Manajemen Perkantoran dan Layanan Bisnis', 'kode_program' => 'MPLB', 'konsentrasi' => 'MP,MLOG'],
        ];

        foreach ($data as $jurusan) {
            DB::table('jurusan')->insert(array_merge($jurusan, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}