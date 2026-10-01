<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import Ketua Program.
 *
 * Penting: Ketua_Program_Import memakai headingRow() = 2, jadi
 * baris 1 = judul, baris 2 = judul kolom, data mulai baris 3.
 * Judul kolom di-slug oleh Maatwebsite: nip, username, nama_ketua_program, jurusan.
 */
class Ketua_Program_Template implements WithMultipleSheets
{
    protected $jurusan;

    public function __construct($jurusan)
    {
        $this->jurusan = $jurusan;
    }

    public function sheets(): array
    {
        return [
            new class implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize {
                public function array(): array
                {
                    // Sengaja kosong: baris contoh bisa ikut ter-import tanpa sengaja.
                    return [];
                }

                public function headings(): array
                {
                    return [
                        ['Template Import Ketua Program'],
                        ['NIP', 'Username', 'Nama Ketua Program', 'Jurusan'],
                    ];
                }

                public function title(): string
                {
                    return 'Data Ketua Program';
                }

                public function styles(Worksheet $sheet)
                {
                    // NIP berupa angka panjang, paksa jadi teks agar tidak jadi 1.9E+17
                    $sheet->getStyle('A3:A5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                    return [
                        1 => ['font' => ['bold' => true, 'size' => 14]],
                        2 => ['font' => ['bold' => true]],
                    ];
                }
            },

            new class($this->jurusan) implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                private $jurusan;

                private const PETUNJUK = [
                    'PETUNJUK PENGISIAN',
                    '1. Isi sheet "Data Ketua Program" mulai baris 3. Baris 1 dan 2 jangan diubah.',
                    '2. NIP wajib diisi dan tidak boleh sama dengan ketua program lain.',
                    '3. Username wajib diisi dan harus unik. Baris tanpa username dilewati.',
                    '4. Kolom Jurusan diisi dengan Id Jurusan dari daftar di bawah (contoh: RPL).',
                    '5. Akun login dibuat otomatis dari Username dengan password default.',
                ];

                public function __construct($jurusan)
                {
                    $this->jurusan = $jurusan;
                }

                public function array(): array
                {
                    $rows = [];

                    foreach (self::PETUNJUK as $baris) {
                        $rows[] = [$baris];
                    }

                    $rows[] = [];
                    $rows[] = ['Id Jurusan', 'Nama Jurusan', 'Kode Program / Kompetensi'];

                    foreach ($this->jurusan as $j) {
                        $rows[] = [
                            $j->id_jurusan,
                            $j->nama_jurusan,
                            $j->ringkasan_kode,
                        ];
                    }

                    return $rows;
                }

                public function title(): string
                {
                    return 'Petunjuk & Jurusan';
                }

                public function columnWidths(): array
                {
                    return ['A' => 16, 'B' => 36, 'C' => 32];
                }

                public function styles(Worksheet $sheet)
                {
                    // baris judul tabel = jumlah petunjuk + 1 baris kosong + 1
                    $barisHeader = count(self::PETUNJUK) + 2;

                    return [
                        1            => ['font' => ['bold' => true]],
                        $barisHeader => ['font' => ['bold' => true]],
                    ];
                }
            },
        ];
    }
}
