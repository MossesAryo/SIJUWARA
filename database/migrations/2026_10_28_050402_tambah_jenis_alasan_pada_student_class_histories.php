<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat kelas sebelumnya hanya mencatat siswa yang kelasnya BERUBAH, jadi siswa yang tidak
 * naik kelas (tetap di kelasnya) tidak meninggalkan jejak sama sekali.
 *
 * Kolom baru:
 *  - jenis        : pindah (manual) | naik | turun | lulus | tinggal_kelas (tidak naik / mengulang)
 *  - alasan       : alasan tidak naik kelas (diisi untuk jenis tinggal_kelas)
 *  - tahun_ajaran : tahun ajaran yang berakhir saat riwayat ini dicatat (mis. "2026/2027")
 *
 * Baris lama otomatis berjenis "pindah". Baris hasil kenaikan massal lama tetap berjenis
 * "pindah" karena tidak bisa dibedakan dengan pasti; baris baru akan terisi benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_class_histories', function (Blueprint $table) {
            $table->string('jenis', 20)->default('pindah')->after('nama_kelas_sesudah');
            $table->string('alasan', 255)->nullable()->after('keterangan');
            $table->string('tahun_ajaran', 20)->nullable()->after('alasan');

            $table->index('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('student_class_histories', function (Blueprint $table) {
            $table->dropIndex(['jenis']);
            $table->dropColumn(['jenis', 'alasan', 'tahun_ajaran']);
        });
    }
};