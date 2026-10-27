<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guru tanpa kelas disimpan sebagai NULL. Collation dipertahankan
     * (utf8mb4_unicode_ci) supaya tetap cocok dengan kelas.id_kelas pada foreign key.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE walikelas MODIFY id_kelas VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
        );
    }

    /**
     * Reverse the migrations.
     *
     * Hanya bisa dijalankan kalau tidak ada guru dengan id_kelas NULL.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE walikelas MODIFY id_kelas VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }
};
