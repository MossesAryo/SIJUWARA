<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BK_Template implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'No',
            'NIP',
            'Nama',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // NIP disimpan sebagai text supaya angka panjang
        // tidak berubah menjadi scientific notation / kehilangan digit.
        $sheet->getStyle('B2:B5000')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }
}