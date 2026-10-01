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
 * Template import Guru BK.
 *
 * Penting: Guru_Bk_Import memakai headingRow() = 2, jadi
 * baris 1 = judul, baris 2 = judul kolom, data mulai baris 3.
 * Judul kolom di-slug oleh Maatwebsite: nip, username, nama_guru_bk.
 */
class BK_Template implements WithMultipleSheets
{
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
                        ['Template Import Guru BK'],
                        ['NIP', 'Username', 'Nama Guru Bk'],
                    ];
                }

                public function title(): string
                {
                    return 'Data Guru BK';
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

            new class implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                private const PETUNJUK = [
                    'PETUNJUK PENGISIAN',
                    '1. Isi sheet "Data Guru BK" mulai baris 3. Baris 1 dan 2 jangan diubah.',
                    '2. NIP wajib diisi dan tidak boleh sama dengan guru BK lain.',
                    '3. Username wajib diisi dan harus unik. Baris tanpa username dilewati.',
                    '4. Akun login dibuat otomatis dari Username dengan password default.',
                    '5. Kolom NIP sudah diformat sebagai teks, jadi digit panjang tidak berubah jadi scientific notation.',
                ];

                public function array(): array
                {
                    $rows = [];

                    foreach (self::PETUNJUK as $baris) {
                        $rows[] = [$baris];
                    }

                    $rows[] = [];
                    $rows[] = ['Kolom', 'Keterangan'];

                    $rows[] = ['NIP', 'Nomor Induk Pegawai. Wajib diisi, tanpa spasi.'];
                    $rows[] = ['Username', 'Username untuk login. Wajib diisi dan harus unik.'];
                    $rows[] = ['Nama Guru Bk', 'Nama lengkap guru BK sesuai dokumen resmi.'];

                    return $rows;
                }

                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function columnWidths(): array
                {
                    return ['A' => 18, 'B' => 70];
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
