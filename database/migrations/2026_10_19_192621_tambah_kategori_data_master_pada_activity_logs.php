<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE activity_logs MODIFY kategori ENUM('Apresiasi','Pelanggaran','Akun','Siswa','Data Master') NOT NULL");
    }

    public function down(): void
    {
        DB::table('activity_logs')->where('kategori', 'Data Master')->delete();

        DB::statement("ALTER TABLE activity_logs MODIFY kategori ENUM('Apresiasi','Pelanggaran','Akun','Siswa') NOT NULL");
    }
};