<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kolom poin siswa sebelumnya nullable tanpa default, sehingga hampir semua
 * siswa bernilai NULL. Diisi 0 dan dijadikan NOT NULL DEFAULT 0 supaya
 * sorting, filter, dan penjumlahan poin konsisten.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE siswa SET poin_apresiasi = COALESCE(poin_apresiasi, 0), poin_pelanggaran = COALESCE(poin_pelanggaran, 0)');
        DB::statement('UPDATE siswa SET poin_total = COALESCE(poin_total, poin_apresiasi - poin_pelanggaran)');

        DB::statement('ALTER TABLE siswa
            MODIFY poin_apresiasi INT NOT NULL DEFAULT 0,
            MODIFY poin_pelanggaran INT NOT NULL DEFAULT 0,
            MODIFY poin_total INT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE siswa
            MODIFY poin_apresiasi INT NULL DEFAULT NULL,
            MODIFY poin_pelanggaran INT NULL DEFAULT NULL,
            MODIFY poin_total INT NULL DEFAULT NULL');
    }
};