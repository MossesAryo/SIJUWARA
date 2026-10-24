<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import Guru BK.
 *
 * Guru_Bk_Import memakai headingRow() = 2, jadi:
 * baris 1 = judul, baris 2 = judul kolom, data mulai baris 3.
 * Judul kolom di-slug oleh Maatwebsite: nip, username, nama_guru_bk.
 */
class BK_Template implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function array(): array
    {
        // Baris 3 = contoh. Nama/username diawali "CONTOH" supaya dilewati saat import.
        return [
            ['196501011990031001', 'contoh.rina', 'CONTOH - Rina Marlina, S.Pd'],
        ];
    }

    public function headings(): array
    {
        return [
            ['Template Import Guru BK'],
            ['NIP', 'Username', 'Nama Guru BK'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // NIP disimpan sebagai text supaya angka panjang
        // tidak berubah menjadi scientific notation / kehilangan digit.
        $sheet->getStyle('A3:A5000')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['italic' => true, 'color' => ['rgb' => '808080']]],
        ];
    }
}