<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master data guru, terpisah dari data wali kelas.
 * Ketua program dipilih dari tabel ini.
 *
 * Ketua program yang sudah ada disalin ke tabel guru supaya
 * form edit-nya tetap bisa menampilkan gurunya.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('guru', function (Blueprint $table) {
            $table->string('nip_guru', 18)->primary();
            $table->string('nama_guru');
            $table->timestamps();
        });

        $sekarang = now();

        foreach (DB::table('ketua_program')->get(['nip_kaprog', 'nama_ketua_program']) as $kp) {
            DB::table('guru')->insertOrIgnore([
                'nip_guru'   => (string) $kp->nip_kaprog,
                'nama_guru'  => $kp->nama_ketua_program,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guru');
    }
};