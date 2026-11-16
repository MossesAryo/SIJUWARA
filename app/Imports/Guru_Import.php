<?php

namespace App\Imports;

use App\Models\guru;
use App\Support\AkunStaf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import data guru. NIP baru ditambahkan, NIP yang sudah ada diperbarui namanya.
 * Format: baris 1 judul, baris 2 judul kolom (NIP, Nama Guru), data mulai baris 3.
 */
class Guru_Import implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    public int $ditambahkan = 0;
    public int $diperbarui = 0;
    public int $dilewati = 0;

    public function headingRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        // Baris Excel asli = indeks + 3 (judul, header, lalu data).
        foreach ($rows as $i => $row) {
            $barisExcel = $i + 3;

            $nip  = $this->ambilNip($row['nip'] ?? null);
            $nama = AkunStaf::rapikanNama($row['nama_guru'] ?? '');

            // Baris kosong dilewati tanpa pesan.
            if ($nip === '' && $nama === '') {
                continue;
            }

            if ($this->isContoh($nama)) {
                continue;
            }

            if ($nip === '' || !preg_match('/^\d{1,18}$/', $nip)) {
                $this->gagal($barisExcel, 'NIP kosong atau bukan angka (maksimal 18 digit)');
                continue;
            }

            if ($nama === '') {
                $this->gagal($barisExcel, "Nama guru kosong (NIP $nip)");
                continue;
            }

            if (mb_strlen($nama) > 255) {
                $this->gagal($barisExcel, "Nama guru terlalu panjang (NIP $nip)");
                continue;
            }

            $guru = guru::where('nip_guru', $nip)->first();

            if ($guru) {
                $guru->update(['nama_guru' => $nama]);
                $this->diperbarui++;
            } else {
                guru::create(['nip_guru' => $nip, 'nama_guru' => $nama]);
                $this->ditambahkan++;
            }
        }
    }

    public function hasImported(): bool
    {
        return ($this->ditambahkan + $this->diperbarui) > 0;
    }

    public function summary(): string
    {
        $bagian = [];

        if ($this->ditambahkan) {
            $bagian[] = "{$this->ditambahkan} guru ditambahkan";
        }
        if ($this->diperbarui) {
            $bagian[] = "{$this->diperbarui} guru diperbarui";
        }
        if ($this->dilewati) {
            $bagian[] = "{$this->dilewati} baris dilewati";
        }

        return $bagian ? implode(', ', $bagian) . '.' : 'Tidak ada baris data yang valid.';
    }

    private function gagal(int $baris, string $pesan): void
    {
        $this->dilewati++;
        $this->errors[] = "Baris $baris: $pesan";
    }

    /** NIP dari Excel bisa berupa angka (1.9E+17) atau teks berisi spasi/tanda hubung. */
    private function ambilNip($nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        if (is_float($nilai) || is_int($nilai)) {
            $nilai = number_format($nilai, 0, '', '');
        }

        return AkunStaf::bersihkanNip($nilai);
    }

    private function isContoh($teks): bool
    {
        return is_string($teks) && str_starts_with(mb_strtolower(trim($teks)), 'contoh');
    }
}