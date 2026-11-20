<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $sumber = [
        'wakasek'   => ['wakasek', 'nip_wakasek'],
        'gurubk'    => ['guru_bk', 'nip_bk'],
        'kaprog'    => ['ketua_program', 'nip_kaprog'],
        'walikelas' => ['walikelas', 'nip_walikelas'],
    ];

    public function up(): void
    {
        // SET = boleh berisi lebih dari satu nilai (ENUM hanya satu).
        DB::statement("ALTER TABLE guru
            ADD COLUMN jabatan SET('wakasek','gurubk','kaprog','walikelas') NULL DEFAULT NULL AFTER nama_guru");

        // Isi dari data jabatan yang sudah ada.
        foreach ($this->sumber as $kode => [$tabel, $kolom]) {
            DB::table('guru')
                ->whereIn('nip_guru', DB::table($tabel)->select($kolom))
                ->update(['jabatan' => DB::raw("CONCAT_WS(',', jabatan, '{$kode}')")]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE guru DROP COLUMN jabatan');
    }
};
