<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import Guru.
 *
 * Guru_Import memakai headingRow() = 2: baris 1 = judul,
 * baris 2 = judul kolom (nip, nama_guru), data mulai baris 3.
 */
class Guru_Template implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new class implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize {
                public function array(): array
                {
                    // Baris contoh: nama diawali "CONTOH" supaya dilewati saat import.
                    return [
                        ['196501011990031001', 'CONTOH - Budi Santoso, S.Pd'],
                    ];
                }

                public function headings(): array
                {
                    return [
                        ['Template Import Guru'],
                        ['NIP', 'Nama Guru'],
                    ];
                }

                public function title(): string
                {
                    return 'Data Guru';
                }

                public function styles(Worksheet $sheet)
                {
                    // NIP berupa angka panjang, paksa jadi teks agar tidak menjadi 1.9E+17.
                    $sheet->getStyle('A3:A5000')
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);

                    return [
                        1 => ['font' => ['bold' => true, 'size' => 14]],
                        2 => ['font' => ['bold' => true]],
                        3 => ['font' => ['italic' => true, 'color' => ['rgb' => '808080']]],
                    ];
                }
            },

            new class implements FromArray, WithTitle, WithStyles, ShouldAutoSize {
                public function array(): array
                {
                    return [
                        ['PETUNJUK PENGISIAN'],
                        ['1. Isi sheet "Data Guru" mulai baris 3. Baris 1 dan 2 jangan diubah.'],
                        ['2. NIP wajib diisi (angka, maksimal 18 digit). Kolom NIP sudah berformat Teks.'],
                        ['3. Nama Guru wajib diisi.'],
                        ['4. NIP yang sudah ada akan diperbarui namanya, NIP baru akan ditambahkan.'],
                        ['5. Baris contoh (nama diawali CONTOH) dilewati saat import. Boleh dihapus atau ditimpa.'],
                        ['6. Guru tidak punya akun login. Akun dibuat saat guru dipilih sebagai ketua program.'],
                    ];
                }

                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function styles(Worksheet $sheet)
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
        ];
    }
}