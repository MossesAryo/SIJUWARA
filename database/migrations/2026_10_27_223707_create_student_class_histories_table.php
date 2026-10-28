<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nis'); // NIS siswa
            $table->string('id_kelas_sebelum')->nullable(); // ID kelas sebelumnya
            $table->string('nama_kelas_sebelum')->nullable(); // Nama kelas sebelumnya (backup)
            $table->string('id_kelas_sesudah'); // ID kelas baru
            $table->string('nama_kelas_sesudah'); // Nama kelas baru
            $table->date('tanggal_mulai'); // Tanggal mulai di kelas baru
            $table->text('keterangan')->nullable(); // Alasan pindah kelas (opsional)
            $table->timestamps();

            $table->foreign('nis')->references('nis')->on('siswa')->onDelete('cascade');
            $table->index('nis');
            $table->index('tanggal_mulai');
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_class_histories');
    }
};