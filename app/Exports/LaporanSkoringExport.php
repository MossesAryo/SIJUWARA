<?php

namespace App\Exports;

use App\Models\penilaian;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;
use App\Support\KelasSaatKejadian;

class LaporanSkoringExport implements FromCollection, WithHeadings, WithMapping
{
    protected $type;
    protected $kelas;
    protected $jurusan;
    protected $startDate;
    protected $endDate;

    public function __construct($type, $kelas, $jurusan, $startDate = null, $endDate = null)
    {
        $this->type = $type;
        $this->kelas = $kelas;
        $this->jurusan = $jurusan;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = penilaian::with(['siswa.kelas', 'aspek_penilaian'])
            ->whereHas('aspek_penilaian', function ($q) {
                $q->where('jenis_poin', $this->type === 'pelanggaran' ? 'Pelanggaran' : 'Apresiasi');
            });

        if ($this->kelas) {
            $query->whereHas('siswa.kelas', function ($q) {
                $q->where('id_kelas', $this->kelas);
            });
        }

        if ($this->jurusan) {
            $query->whereHas('siswa.kelas', function ($q) {
                $q->where('id_jurusan', $this->jurusan);
            });
        }

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);
        }

        $data = $query->get();

        // Kolom Kelas = kelas siswa saat kejadian (Riwayat Kelas), bukan kelas sekarang.
        KelasSaatKejadian::muatDari($data);

        return $data;
    }

    public function headings(): array
    {
        return [
            'NIS',
            'Nama Siswa',
            'Kelas',
            $this->type === 'pelanggaran' ? 'Jenis Pelanggaran' : 'Jenis Penghargaan',
            'Skor',
            'Tanggal',
        ];
    }

    public function map($item): array
    {
        return [
            $item->siswa->nis ?? '-',
            $item->siswa->nama_siswa ?? '-',
            KelasSaatKejadian::nama($item->nis, $item->created_at),
            $item->aspek_penilaian->kategori ?? '-',
            $item->aspek_penilaian->indikator_poin ?? 0,
            Carbon::parse($item->created_at)->format('Y-m-d'),
        ];
    }
}