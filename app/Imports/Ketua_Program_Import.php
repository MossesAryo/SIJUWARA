<?php

namespace App\Imports;

use App\Models\guru;
use App\Models\jurusan;
use App\Models\ketua_program;
use App\Models\User;
use App\Support\AkunStaf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import ketua program. Orangnya diambil dari data guru berdasarkan NIP,
 * jadi guru harus sudah ada di menu Guru (tambah manual atau import guru dulu).
 * Format: baris 1 judul, baris 2 judul kolom (NIP, Jurusan), data mulai baris 3.
 */
class Ketua_Program_Import implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    public int $ditambahkan = 0;
    public int $dilewati = 0;

    public function headingRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        $daftarJurusan = jurusan::pluck('id_jurusan')->map(fn ($id) => (string) $id)->all();

        // Baris Excel asli = indeks + 3 (judul, header, lalu data).
        foreach ($rows as $i => $row) {
            $barisExcel = $i + 3;

            $nip = $this->ambilNip($row['nip'] ?? null);
            $idJurusan = trim((string) ($row['jurusan'] ?? ''));

            // Baris kosong dilewati tanpa pesan.
            if ($nip === '' && $idJurusan === '') {
                continue;
            }

            // Baris contoh dari template dilewati.
            if ($this->isContoh($nip) || str_starts_with(mb_strtolower($idJurusan), 'contoh')) {
                continue;
            }

            if ($nip === '') {
                $this->gagal($barisExcel, 'NIP kosong');
                continue;
            }

            $guru = guru::where('nip_guru', $nip)->first();
            if (!$guru) {
                $this->gagal($barisExcel, "NIP $nip belum ada di data guru. Tambah/import guru dulu");
                continue;
            }

            if ($idJurusan === '' || !in_array($idJurusan, $daftarJurusan, true)) {
                $this->gagal($barisExcel, "Jurusan '$idJurusan' tidak dikenal (NIP $nip). Isi dengan Id Jurusan dari sheet petunjuk");
                continue;
            }

            if (AkunStaf::nipTerpakai($nip)) {
                $this->gagal($barisExcel, AkunStaf::pesanNipTerpakai($nip) . " (NIP $nip)");
                continue;
            }

            try {
                DB::transaction(function () use ($guru, $idJurusan) {
                    $user = AkunStaf::buatUser(
                        $guru->nama_guru,
                        $guru->nip_guru,
                        User::ROLE_KETUA_PROGRAM
                    );

                    ketua_program::create([
                        'nip_kaprog'         => $guru->nip_guru,
                        'username'           => $user->username,
                        'nama_ketua_program' => $guru->nama_guru,
                        'id_jurusan'         => $idJurusan,
                    ]);
                });

                $this->ditambahkan++;
            } catch (\Throwable $e) {
                $this->gagal($barisExcel, AkunStaf::pesanGagal($e, "Simpan ketua program NIP $nip"));
            }
        }
    }

    public function hasImported(): bool
    {
        return $this->ditambahkan > 0;
    }

    public function summary(): string
    {
        $bagian = [];

        if ($this->ditambahkan) {
            $bagian[] = "{$this->ditambahkan} ketua program ditambahkan (password awal: " . AkunStaf::PASSWORD_DEFAULT . ')';
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