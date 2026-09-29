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

class Siswa_Template implements WithMultipleSheets
{
    protected $kelas;

    public function __construct($kelas)
    {
        $this->kelas = $kelas;
    }

    public function sheets(): array
    {
        return [
            new class implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize {
                public function array(): array
                {
                    return [];
                }

                public function headings(): array
                {
                    return ['No', 'NIS', 'Nama Siswa', 'Id Kelas'];
                }

                public function title(): string
                {
                    return 'Data Siswa';
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('B2:B5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                    return [1 => ['font' => ['bold' => true]]];
                }
            },

            new class($this->kelas) implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                private $kelas;

                public function __construct($kelas)
                {
                    $this->kelas = $kelas;
                }

                public function array(): array
                {
                    $rows = [
                        ['PETUNJUK PENGISIAN'],
                        ['1. Isi sheet "Data Siswa" mulai baris 2. Kolom No boleh dikosongkan.'],
                        ['2. NIS wajib angka dan tidak boleh sama dengan siswa lain dalam file.'],
                        ['3. Id Kelas harus sama dengan daftar di bawah (Nama Kelas juga diterima).'],
                        ['4. NIS yang sudah terdaftar akan diperbarui nama & kelasnya, bukan dobel.'],
                        ['5. Jangan mengubah judul kolom di baris 1.'],
                        [],
                        ['Id Kelas', 'Nama Kelas', 'Jurusan'],
                    ];

                    foreach ($this->kelas as $k) {
                        $rows[] = [$k->id_kelas, $k->nama_kelas, $k->id_jurusan];
                    }

                    return $rows;
                }

                public function title(): string
                {
                    return 'Petunjuk & Kelas';
                }

                public function columnWidths(): array
                {
                    return ['A' => 16, 'B' => 22, 'C' => 14];
                }

                public function styles(Worksheet $sheet)
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                        8 => ['font' => ['bold' => true]],
                    ];
                }
            },
        ];
    }
}