<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            if (!DB::table('jurusan')->where('id_jurusan', 'MLOG')->exists()) {
                return;
            }

            if (!DB::table('jurusan')->where('id_jurusan', 'MP')->exists()) {
                throw new RuntimeException('Jurusan MP tidak ditemukan, migrasi dibatalkan.');
            }

            // Ketua program itu satu per jurusan (hasOne), jadi jangan digabung otomatis.
            if (DB::table('ketua_program')->where('id_jurusan', 'MLOG')->exists()) {
                throw new RuntimeException('Masih ada Ketua Program jurusan MLOG. Pindahkan/hapus dulu secara manual.');
            }

            // id_kelas tetap (11-MLOG-1 dst), hanya induk jurusannya yang pindah ke MP.
            DB::table('kelas')->where('id_jurusan', 'MLOG')
                ->update(['id_jurusan' => 'MP', 'updated_at' => now()]);

            // Termasuk siswa arsip (soft delete) dan alumni.
            DB::table('siswa')->where('id_jurusan', 'MLOG')
                ->update(['id_jurusan' => 'MP']);

            // Sudah tidak dipakai, aman dihapus (tidak ada cascade ke data).
            DB::table('jurusan')->where('id_jurusan', 'MLOG')->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('jurusan')->insertOrIgnore([
                'id_jurusan'   => 'MLOG',
                'nama_jurusan' => 'Manajemen Logistik',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Hanya kelas MLOG yang bisa dikembalikan. Alumni MLOG tetap di MP
            // karena kodenya sudah tidak tercatat terpisah.
            $idKelas = DB::table('kelas')->where('id_kelas', 'like', '%-MLOG-%')->pluck('id_kelas');

            DB::table('kelas')->whereIn('id_kelas', $idKelas)->update(['id_jurusan' => 'MLOG']);
            DB::table('siswa')->whereIn('id_kelas', $idKelas)->update(['id_jurusan' => 'MLOG']);
        });
    }
};