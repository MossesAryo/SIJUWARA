<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Siswa_ExportExcel extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithCustomValueBinder
{
    protected $siswa;
    private int $no = 0;

    public function __construct($siswa)
    {
        $this->siswa = $siswa;
    }

    public function collection()
    {
        return $this->siswa;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIS',
            'Nama Siswa',
            'Id Kelas',
            'Nama Kelas',
            'Poin Apresiasi',
            'Poin Pelanggaran',
            'Poin Total',
        ];
    }

    public function map($item): array
    {
        return [
            ++$this->no,
            (string) $item->nis,
            $item->nama_siswa,
            $item->id_kelas,
            optional($item->kelas)->nama_kelas,
            $item->poin_apresiasi ?? 0,
            $item->poin_pelanggaran ?? 0,
            $item->poin_total ?? 0,
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'B' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}