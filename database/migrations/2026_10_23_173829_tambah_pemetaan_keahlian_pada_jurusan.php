<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan pemetaan Program Keahlian (kelas 10) dan Konsentrasi Keahlian
 * (kelas 11/12) dari konstanta di model ke tabel jurusan, supaya kode jurusan
 * bisa diubah tanpa merusak pemetaan.
 *
 *   kode_program : mis. MPLB
 *   konsentrasi  : daftar dipisah koma, mis. MP,MLOG
 */
return new class extends Migration
{
    private const PROGRAM = [
        'AK'  => 'AKL',
        'MP'  => 'MPLB',
        'PM'  => 'PM',
        'RPL' => 'PPLG',
        'DKV' => 'DKV',
        'TKJ' => 'TJKT',
    ];

    private const KONSENTRASI = [
        'AK'  => 'AK',
        'MP'  => 'MP,MLOG',
        'PM'  => 'BR',
        'RPL' => 'RPL',
        'DKV' => 'DKV',
        'TKJ' => 'TKJ',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('jurusan', 'kode_program')) {
            Schema::table('jurusan', function (Blueprint $table) {
                $table->string('kode_program', 20)->nullable()->after('nama_jurusan');
            });
        }

        if (!Schema::hasColumn('jurusan', 'konsentrasi')) {
            Schema::table('jurusan', function (Blueprint $table) {
                $table->string('konsentrasi', 100)->nullable()->after('kode_program');
            });
        }

        // Isi dari pemetaan lama untuk jurusan yang sudah ada.
        foreach (self::PROGRAM as $idJurusan => $program) {
            DB::table('jurusan')
                ->where('id_jurusan', $idJurusan)
                ->update([
                    'kode_program' => $program,
                    'konsentrasi'  => self::KONSENTRASI[$idJurusan] ?? null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('jurusan', function (Blueprint $table) {
            if (Schema::hasColumn('jurusan', 'konsentrasi')) {
                $table->dropColumn('konsentrasi');
            }
            if (Schema::hasColumn('jurusan', 'kode_program')) {
                $table->dropColumn('kode_program');
            }
        });
    }
};