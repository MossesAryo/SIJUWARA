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
                    // Baris 2 = contoh. Nama diawali "CONTOH" supaya dilewati saat import.
                    return [
                        [1, '2510010001', 'CONTOH - Budi Santoso'],
                    ];
                }

                public function headings(): array
                {
                    return ['No', 'NIS', 'Nama Siswa'];
                }

                public function title(): string
                {
                    return 'Data Siswa';
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('B2:B5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                    return [
                        1 => ['font' => ['bold' => true]],
                        2 => ['font' => ['italic' => true, 'color' => ['rgb' => '808080']]],
                    ];
                }
            },

            new class($this->kelas) implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                private $kelas;

                private const PETUNJUK = [
                    'PETUNJUK PENGISIAN',
                    '1. Pilih kelas lebih dulu di layar Import, lalu upload file ini. Semua siswa di file masuk ke kelas itu.',
                    '2. Jurusan siswa otomatis mengikuti kelas yang dipilih (contoh: kelas 10 PPLG 1 masuk ke jurusan Rekayasa Perangkat Lunak).',
                    '3. Isi sheet "Data Siswa" mulai baris 2. Kolom No boleh dikosongkan.',
                    '4. NIS wajib angka dan tidak boleh sama dengan siswa lain dalam file.',
                    '5. Baris contoh (nama diawali CONTOH) dilewati saat import. Boleh dihapus atau ditimpa.',
                    '6. Kelas 10 memakai Program Keahlian: AKL, MPLB, PM, PPLG, TJKT, DKV.',
                    '7. Kelas 11 dan 12 memakai Konsentrasi Keahlian: AK, MP, MLOG, BR, TKJ, RPL, DKV.',
                    '8. NIS yang sudah terdaftar akan diperbarui nama & kelasnya, bukan dobel.',
                    '9. Jangan mengubah judul kolom di baris 1.',
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
                    $rows[] = ['Nama Kelas', 'Tingkat', 'Program | Konsentrasi Keahlian', 'Jurusan'];

                    foreach ($this->kelas as $k) {
                        $tingkat = $k->tingkat;

                        // ALUMNI dan kelas tanpa tingkat tidak bisa dipilih untuk import.
                        if (!$tingkat) {
                            continue;
                        }

                        $rows[] = [
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
                    return ['A' => 22, 'B' => 10, 'C' => 40, 'D' => 42];
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