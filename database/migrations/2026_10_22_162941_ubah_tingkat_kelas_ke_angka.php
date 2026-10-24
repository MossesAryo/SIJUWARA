<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah tingkat kelas dari angka romawi ke angka:
 *   id_kelas   : X-RPL-1  -> 10-RPL-1,  XI-RPL-1 -> 11-RPL-1,  XII-RPL-1 -> 12-RPL-1
 *   nama_kelas : X PPLG 1 -> 10 PPLG 1, XI RPL 1 -> 11 RPL 1,  XII RPL 1 -> 12 RPL 1
 *
 * id_kelas dipakai di tabel siswa, walikelas, dan guru_bk_kelas, jadi semuanya
 * ikut diubah. Foreign key siswa.id_kelas tidak punya ON UPDATE CASCADE, karena
 * itu pengecekan FK dimatikan sebentar selama proses berlangsung.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ubah(['XII' => '12', 'XI' => '11', 'X' => '10']);
    }

    public function down(): void
    {
        $this->ubah(['12' => 'XII', '11' => 'XI', '10' => 'X']);
    }

    private function ubah(array $peta): void
    {
        $pola = implode('|', array_keys($peta));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::transaction(function () use ($peta, $pola) {
                foreach (DB::table('kelas')->get(['id_kelas', 'nama_kelas']) as $k) {
                    $idBaru   = $this->ganti($k->id_kelas, '-', $peta, $pola);
                    $namaBaru = $this->ganti($k->nama_kelas, ' ', $peta, $pola);

                    if ($idBaru === $k->id_kelas && $namaBaru === $k->nama_kelas) {
                        continue;
                    }

                    DB::table('kelas')->where('id_kelas', $k->id_kelas)
                        ->update(['id_kelas' => $idBaru, 'nama_kelas' => $namaBaru]);

                    DB::table('siswa')->where('id_kelas', $k->id_kelas)
                        ->update(['id_kelas' => $idBaru]);

                    DB::table('walikelas')->where('id_kelas', $k->id_kelas)
                        ->update(['id_kelas' => $idBaru]);

                    DB::table('guru_bk_kelas')->where('kelas_id', $k->id_kelas)
                        ->update(['kelas_id' => $idBaru]);
                }
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function ganti(string $teks, string $pemisah, array $peta, string $pola): string
    {
        return preg_replace_callback(
            '/^(' . $pola . ')(?=' . preg_quote($pemisah, '/') . ')/',
            fn ($m) => $peta[$m[1]],
            $teks
        );
    }
};