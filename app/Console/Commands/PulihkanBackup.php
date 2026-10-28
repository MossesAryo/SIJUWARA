<?php

namespace App\Console\Commands;

use App\Support\BackupData;
use App\Support\PulihkanData;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulihkan titik pemulihan (file Excel) yang dibuat otomatis sebelum ganti tahun ajaran.
 *
 *   php artisan backup:pulihkan                  -> daftar backup
 *   php artisan backup:pulihkan NAMA_FILE.xlsx   -> pulihkan file itu
 */
class PulihkanBackup extends Command
{
    protected $signature = 'backup:pulihkan
                            {file? : Nama file backup .xlsx (lihat daftar dengan menjalankan tanpa argumen)}
                            {--force : Lewati pertanyaan konfirmasi}';

    protected $description = 'Daftar dan pulihkan backup (file Excel) otomatis sebelum ganti tahun ajaran';

    public function handle(): int
    {
        $nama = $this->argument('file');

        if (!$nama) {
            return $this->tampilkanDaftar();
        }

        $nama = basename($nama);

        if (strtolower(pathinfo($nama, PATHINFO_EXTENSION)) !== BackupData::EKSTENSI) {
            $this->error('Backup sekarang berupa file Excel (.' . BackupData::EKSTENSI . '). File lain tidak bisa dipulihkan.');
            $this->line('Jalankan "php artisan backup:pulihkan" tanpa argumen untuk melihat daftar.');

            return self::FAILURE;
        }

        $path = BackupData::folder() . DIRECTORY_SEPARATOR . $nama;

        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$nama}");
            $this->line('Jalankan "php artisan backup:pulihkan" tanpa argumen untuk melihat daftar.');

            return self::FAILURE;
        }

        if (!BackupData::valid($path)) {
            $this->error('File backup tidak lengkap atau rusak (sheet Info tidak berstatus SELESAI). Pemulihan dibatalkan.');

            return self::FAILURE;
        }

        // Baca & periksa seluruh isi SEBELUM ada yang diubah.
        try {
            $data = PulihkanData::baca($path);
        } catch (Throwable $e) {
            $this->error('File backup tidak bisa dipakai: ' . $e->getMessage());
            $this->line('Pemulihan dibatalkan, tidak ada data yang berubah.');

            return self::FAILURE;
        }

        $this->newLine();
        foreach (BackupData::keterangan($path) as $baris) {
            $this->line("  {$baris}");
        }
        foreach ($data as $tabel => $rows) {
            $this->line('  ' . str_pad(BackupData::TABEL[$tabel], 22) . ': ' . count($rows) . ' baris');
        }
        $this->newLine();

        $this->warn('Yang dikembalikan: tahun ajaran aktif, kelas & status siswa, kelas walikelas, dan rencana Ketua Program.');
        if (array_key_exists('student_class_histories', $data)) {
            $this->warn('Riwayat Kelas ikut dikembalikan: riwayat yang dibuat SETELAH backup ini dihapus.');
        } else {
            $this->warn('Backup ini dibuat sebelum ada sheet Riwayat Kelas, jadi Riwayat Kelas TIDAK diubah.');
        }
        $this->warn('Data lain (poin, penilaian, intervensi, catatan) tidak diubah.');
        $this->warn('Rencana Ketua Program yang dibuat SETELAH backup tidak dihapus.');

        if (!$this->option('force') && !$this->confirm('Pulihkan sekarang?', false)) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $hasil = PulihkanData::jalankan($path, $data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Keadaan sebelum pemulihan diamankan di: ' . $hasil['pengaman']);
        $this->info("Selesai. Dipulihkan dari {$nama}:");
        foreach ($hasil['ringkas'] as $tabel => $n) {
            $baris = $n['dipulihkan'] . ' baris';
            if ($n['dihapus'] > 0) {
                $baris .= ", {$n['dihapus']} baris setelah backup dihapus";
            }
            if ($n['dilewati'] > 0) {
                $baris .= ", {$n['dilewati']} dilewati (siswa sudah dihapus permanen)";
            }
            $this->line('  ' . str_pad(BackupData::TABEL[$tabel], 22) . ": {$baris}");
        }

        return self::SUCCESS;
    }

    private function tampilkanDaftar(): int
    {
        $daftar = BackupData::daftar();

        if (empty($daftar)) {
            $this->line('Belum ada backup di ' . BackupData::folder());

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Dibuat', 'Ukuran', 'Lengkap'],
            array_map(fn ($b) => [
                $b['nama'],
                date('Y-m-d H:i:s', $b['waktu']),
                round($b['ukuran'] / 1024, 1) . ' KB',
                $b['valid'] ? 'ya' : 'TIDAK',
            ], $daftar)
        );

        $this->line('Pulihkan dengan: php artisan backup:pulihkan NAMA_FILE.xlsx');

        return self::SUCCESS;
    }
}