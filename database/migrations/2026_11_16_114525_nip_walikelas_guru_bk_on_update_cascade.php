<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIP Wali Kelas dan Guru BK boleh diubah dari Manajemen User.
 * Foreign key riwayat dibuat ON UPDATE CASCADE supaya ikut berubah otomatis.
 * (Username semua role dan guru_bk_kelas sudah cascade; Wakasek ada di migration sebelumnya.)
 */
return new class extends Migration {
    /** [tabel riwayat, kolom FK, tabel induk] */
    private array $relasi = [
        ['penilaian', 'nip_walikelas', 'walikelas'],
        ['catatan', 'nip_walikelas', 'walikelas'],
        ['intervensi', 'nip_walikelas', 'walikelas'],
        ['penilaian', 'nip_bk', 'guru_bk'],
        ['intervensi', 'nip_bk', 'guru_bk'],
    ];

    public function up(): void
    {
        foreach ($this->relasi as [$tabel, $kolom, $induk]) {
            Schema::table($tabel, function (Blueprint $table) use ($kolom, $induk) {
                $table->dropForeign([$kolom]);
                $table->foreign($kolom)->references($kolom)->on($induk)
                    ->onDelete('cascade')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->relasi as [$tabel, $kolom, $induk]) {
            Schema::table($tabel, function (Blueprint $table) use ($kolom, $induk) {
                $table->dropForeign([$kolom]);
                $table->foreign($kolom)->references($kolom)->on($induk)
                    ->onDelete('cascade');
            });
        }
    }
};