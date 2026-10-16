<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE activity_logs MODIFY kategori ENUM('Apresiasi','Pelanggaran','Akun','Siswa') NOT NULL");

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('actor_username')->nullable()->after('user_id');
            $table->unsignedTinyInteger('actor_role')->nullable()->after('actor_username');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        DB::table('activity_logs')->whereIn('kategori', ['Akun', 'Siswa'])->delete();

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['actor_username', 'actor_role']);
        });

        DB::statement("ALTER TABLE activity_logs MODIFY kategori ENUM('Apresiasi','Pelanggaran') NOT NULL");
    }
};