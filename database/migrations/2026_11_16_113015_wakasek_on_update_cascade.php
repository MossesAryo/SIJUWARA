<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Username dan NIP Wakasek boleh diubah dari Manajemen User.
 * Supaya relasinya ikut berubah otomatis, foreign key dibuat ON UPDATE CASCADE.
 */
return new class extends Migration {
    /** tabel => kolom FK yang mengarah ke wakasek.nip_wakasek */
    private array $tabelNip = ['penilaian', 'catatan', 'intervensi'];

    public function up(): void
    {
        Schema::table('wakasek', function (Blueprint $table) {
            $table->dropForeign(['username']);
            $table->foreign('username')->references('username')->on('users')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        foreach ($this->tabelNip as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['nip_wakasek']);
                $table->foreign('nip_wakasek')->references('nip_wakasek')->on('wakasek')
                    ->onDelete('cascade')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelNip as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['nip_wakasek']);
                $table->foreign('nip_wakasek')->references('nip_wakasek')->on('wakasek')
                    ->onDelete('cascade');
            });
        }

        Schema::table('wakasek', function (Blueprint $table) {
            $table->dropForeign(['username']);
            $table->foreign('username')->references('username')->on('users')
                ->onDelete('cascade');
        });
    }
};