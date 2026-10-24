<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana penempatan kelas 10 -> 11 yang dibuat Ketua Program.
 * Siswa baru benar-benar pindah saat Wakasek mengganti tahun ajaran,
 * setelah itu barisnya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rencana_pindah_kelas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nis')->unique();
            $table->string('id_kelas_tujuan');
            $table->string('dibuat_oleh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_pindah_kelas');
    }
};