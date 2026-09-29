<?php

namespace App\Exports;

use App\Models\kelas;
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

                private const PETUNJUK = [
                    'PETUNJUK PENGISIAN',
                    '1. Isi sheet "Data Siswa" mulai baris 2. Kolom No boleh dikosongkan.',
                    '2. NIS wajib angka dan tidak boleh sama dengan siswa lain dalam file.',
                    '3. Id Kelas harus sama dengan daftar di bawah. Nama Kelas juga diterima (contoh: X PPLG 1, XI RPL 1).',
                    '4. Kelas X memakai Program Keahlian: AKL, MPLB, PM, PPLG, DKV, TJKT.',
                    '5. Kelas XI dan XII memakai Kompetensi Keahlian: AK, MP, MLOG, RPL, TKJ, BR, DKV.',
                    '6. Penulisan lama seperti "X RPL 1" tetap diterima dan dicocokkan otomatis.',
                    '7. NIS yang sudah terdaftar akan diperbarui nama & kelasnya, bukan dobel.',
                    '8. Jangan mengubah judul kolom di baris 1.',
                ];

                public function __construct($kelas)
                {
                    $this->kelas = $kelas;
                }

                public function array(): array
                {
                    $rows = [];

                    foreach (self::PETUNJUK as $baris) {
                        $rows[] = [$baris];
                    }

                    $rows[] = [];
                    $rows[] = ['Id Kelas', 'Nama Kelas', 'Tingkat', 'Program / Kompetensi Keahlian', 'Jurusan'];

                    foreach ($this->kelas as $k) {
                        $tingkat = $k->tingkat;

                        if (!$tingkat) {
                            // ALUMNI dan kelas tanpa tingkat
                            $rows[] = [$k->id_kelas, $k->nama_kelas, '-', '-', '-'];
                            continue;
                        }

                        $rows[] = [
                            $k->id_kelas,
                            $k->nama_kelas,
                            $tingkat,
                            $k->label_keahlian . ' ' . $k->kode_keahlian,
                            optional($k->jurusan)->nama_jurusan ?? $k->id_jurusan,
                        ];
                    }

                    return $rows;
                }

                public function title(): string
                {
                    return 'Petunjuk & Kelas';
                }

                public function columnWidths(): array
                {
                    return ['A' => 16, 'B' => 22, 'C' => 10, 'D' => 36, 'E' => 32];
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