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
 * Template import Guru.
 *
 * Baris 1 = judul, baris 2 = judul kolom, data mulai baris 3.
 * Walikelas_Import mendeteksi header otomatis, jadi urutan kolom tidak kaku.
 * Id Kelas boleh kosong atau "Tanpa Kelas".
 */
class Walikelas_Template implements WithMultipleSheets
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
                    // Baris 3-4 = contoh. Nama diawali "CONTOH" supaya dilewati saat import.
                    return [
                        ['196501011990031001', 'CONTOH - Siti Aminah, S.Pd', '11-RPL-1', 'siti.aminah'],
                        ['197001011995031002', 'CONTOH - Budi Santoso, S.Pd', 'Tanpa Kelas', 'budi.santoso'],
                    ];
                }

                public function headings(): array
                {
                    return [
                        ['Template Import Guru'],
                        ['NIP', 'Nama Guru', 'Id Kelas', 'Username (opsional)'],
                    ];
                }

                public function title(): string
                {
                    return 'Data Guru';
                }

                public function styles(Worksheet $sheet)
                {
                    // NIP 18 digit: paksa jadi teks agar tidak berubah jadi 1.9E+17.
                    $sheet->getStyle('A3:A5000')
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);

                    $contoh = [
                        'font' => [
                            'italic' => true,
                            'color' => ['rgb' => '808080'],
                        ],
                    ];

                    return [
                        1 => ['font' => ['bold' => true, 'size' => 14]],
                        2 => ['font' => ['bold' => true]],
                        3 => $contoh,
                        4 => $contoh,
                    ];
                }
            },

            new class($this->kelas) implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                private $kelas;

                private const PETUNJUK = [
                    'PETUNJUK PENGISIAN',
                    '1. Isi sheet "Data Guru" mulai baris 3. Baris 1 dan 2 jangan diubah.',
                    '2. NIP wajib diisi (angka) dan tidak boleh sama dengan guru lain. Kolom NIP sudah berformat Teks.',
                    '3. Nama Guru wajib diisi.',
                    '4. Id Kelas boleh dikosongkan. Kalau diisi, pakai Id Kelas dari daftar di bawah (contoh: 11-RPL-1). Nama kelas seperti "11 RPL 1" juga bisa.',
                    '5. Untuk guru yang tidak memiliki kelas, isi Id Kelas dengan "Tanpa Kelas" atau biarkan kosong (guru baru).',
                    '6. Untuk guru yang sudah ada: Id Kelas kosong = kelas tidak diubah, "Tanpa Kelas" = kelas dihapus.',
                    '7. Username boleh dikosongkan. Kalau kosong, dipakai Nama Guru.',
                    '8. Akun login dibuat otomatis dengan password default. NIP yang sudah ada hanya diperbarui nama dan kelasnya.',
                    '9. Baris contoh (nama diawali CONTOH) dilewati saat import. Boleh dihapus atau ditimpa.',
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
                    $rows[] = ['Id Kelas', 'Nama Kelas'];

                    foreach ($this->kelas as $k) {
                        $rows[] = [$k->id_kelas, $k->nama_kelas];
                    }

                    return $rows;
                }

                public function title(): string
                {
                    return 'Petunjuk & Kelas';
                }

                public function columnWidths(): array
                {
                    return ['A' => 22, 'B' => 30];
                }

                public function styles(Worksheet $sheet)
                {
                    // Baris judul tabel = jumlah petunjuk + 1 baris kosong + 1.
                    $barisHeader = count(self::PETUNJUK) + 2;

                    return [
                        1 => ['font' => ['bold' => true]],
                        $barisHeader => ['font' => ['bold' => true]],
                    ];
                }
            },
        ];
    }
}
