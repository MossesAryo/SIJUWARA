<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wali kelas, guru BK, dan wakasek yang sudah ada disalin ke tabel guru
 * supaya muncul di dropdown form jabatan (NIP dan nama dipilih dari data guru).
 * Guru yang NIP-nya sudah ada tidak ditimpa.
 */
return new class extends Migration {
    public function up(): void
    {
        $sekarang = now();

        $sumber = [
            ['walikelas', 'nip_walikelas', 'nama_walikelas'],
            ['guru_bk', 'nip_bk', 'nama_guru_bk'],
            ['wakasek', 'nip_wakasek', 'nama_wakasek'],
        ];

        foreach ($sumber as [$tabel, $kolomNip, $kolomNama]) {
            foreach (DB::table($tabel)->get([$kolomNip, $kolomNama]) as $baris) {
                DB::table('guru')->insertOrIgnore([
                    'nip_guru'   => (string) $baris->{$kolomNip},
                    'nama_guru'  => $baris->{$kolomNama},
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data guru hasil salinan dibiarkan: tidak bisa dibedakan dari data yang diinput manual.
    }
};