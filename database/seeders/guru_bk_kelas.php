<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class guru_bk_kelas extends Seeder
{
    public function run(): void
    {
        /**
         * Mapping guru → daftar kelas yang dia pegang
         * (sesuai data dari kamu)
         */
        /**
         * Mapping guru → daftar kelas yang dia pegang
         */
        $data = [
            'Dra. Wening Wigati, S.E, M.Si' => [
                '10-AK-1', '10-AK-2', '10-AK-3',
                '10-DKV-1', '10-DKV-2', '10-TKJ-1',
                '12-MP-1', '12-MP-2', '12-MP-3',
            ],
            'Ratih Pratiwi, S.Pd' => [
                '10-BR-1', '10-BR-2', '10-BR-3',
                '10-RPL-1', '10-RPL-2',
                '11-BR-1', '11-BR-2', '11-TKJ-1',
                '12-TKJ-1',
            ],
            'Suci' => [
                '11-MP-1', '11-MP-2', '11-MP-3',
                '11-MLOG-1', '11-DKV-1', '11-DKV-2',
                '12-BR-1', '12-BR-2', '12-BR-3',
            ],
            'Evi Febry Damayanti, S.Pd' => [
                '10-MP-1', '10-MP-2', '10-MP-3', '10-MP-4',
                '11-RPL-1', '11-RPL-2',
                '12-RPL-1', '12-RPL-2',
            ],
            'Raden Roro Siti Ameliya Purnama Putri, S.Pd' => [
                '11-AK-1', '11-AK-2', '11-AK-3', '11-AK-4',
                '12-AK-1', '12-AK-2', '12-AK-3',
                '12-DKV-1', '12-DKV-2',
            ],
        ];

        foreach ($data as $guruNama => $kelasList) {
            // ambil guru BK berdasarkan nama
            $guru = DB::table('guru_bk')->where('nama_guru_bk', $guruNama)->first();

            if (!$guru) {
                echo "⚠️  Guru BK '$guruNama' tidak ditemukan di tabel guru_bk.\n";
                continue;
            }

            foreach ($kelasList as $idKelas) {
                $kelas = DB::table('kelas')->where('id_kelas', $idKelas)->first();

                if (!$kelas) {
                    echo "⚠️  Kelas '$idKelas' tidak ditemukan di tabel kelas.\n";
                    continue;
                }

                DB::table('guru_bk_kelas')->insert([
                    'guru_bk_id' => $guru->nip_bk,
                    'kelas_id'   => $kelas->id_kelas,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}