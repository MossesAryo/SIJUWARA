<?php

namespace App\Imports;

use App\Models\kelas;
use App\Models\siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Import siswa ke SATU kelas yang dipilih lebih dulu di layar import.
 * Semua baris masuk ke kelas itu, dan jurusan siswa otomatis mengikuti
 * jurusan kelasnya (mis. kelas 10 PPLG 1 -> jurusan RPL).
 *
 * Kolom yang dibaca: NIS dan Nama Siswa. Kolom lain (No, Id Kelas) diabaikan.
 * Baris contoh dari template (nama diawali "CONTOH") dilewati.
 */
class Siswa_Import implements ToCollection
{
    private const HEADER_ALIASES = [
        'nis'  => ['nis', 'nomorinduksiswa', 'nomorinduk'],
        'nama' => ['namasiswa', 'nama', 'namalengkap'],
    ];

    private const HEADER_SCAN_ROWS = 10;

    public int $ditambahkan = 0;
    public int $diperbarui = 0;
    public int $dipulihkan = 0;
    public int $dilewati = 0;

    /** @var string[] */
    public array $errors = [];

    private kelas $kelas;

    public function __construct(kelas $kelas)
    {
        $kelas->loadMissing('jurusan');
        $this->kelas = $kelas;
    }

    public function collection(Collection $rows)
    {
        [$map, $startIndex] = $this->detectColumns($rows);

        $data = [];
        $seen = [];

        foreach ($rows->values() as $index => $row) {
            if ($index < $startIndex) {
                continue;
            }

            $barisExcel = $index + 1;
            $row = $row->toArray();

            $nisRaw  = $row[$map['nis']] ?? null;
            $namaRaw = $row[$map['nama']] ?? null;

            if ($this->isBlank($nisRaw) && $this->isBlank($namaRaw)) {
                continue;
            }

            // Baris contoh dari template.
            if ($this->isContoh($namaRaw)) {
                continue;
            }

            foreach ([$nisRaw, $namaRaw] as $cell) {
                if (is_string($cell) && preg_match('/^\s*[=+@]/', $cell)) {
                    $this->fail($barisExcel, 'berisi rumus / karakter tidak diizinkan');
                    continue 2;
                }
            }

            $nis = $this->cleanNis($nisRaw);
            if ($nis === null) {
                $this->fail($barisExcel, 'NIS kosong atau tidak valid (harus angka)');
                continue;
            }

            $nama = trim(preg_replace('/\s+/u', ' ', (string) $namaRaw));
            if ($nama === '') {
                $this->fail($barisExcel, "Nama siswa kosong (NIS $nis)");
                continue;
            }
            if (mb_strlen($nama) > 255) {
                $this->fail($barisExcel, "Nama siswa terlalu panjang (NIS $nis)");
                continue;
            }

            if (isset($seen[$nis])) {
                $this->fail($barisExcel, "NIS $nis dobel di file (sudah ada di baris {$seen[$nis]})");
                continue;
            }
            $seen[$nis] = $barisExcel;

            $data[$nis] = [
                'nis'        => $nis,
                'nama_siswa' => $nama,
                'id_kelas'   => $this->kelas->id_kelas,
                'id_jurusan' => $this->kelas->id_jurusan,
            ];
        }

        if (empty($data)) {
            return;
        }

        $existing = collect();
        foreach (array_chunk(array_keys($data), 500) as $chunk) {
            $existing = $existing->concat(
                siswa::withTrashed()->whereIn('nis', $chunk)->get()
            );
        }
        $existing = $existing->keyBy(fn ($s) => (string) $s->nis);

        DB::transaction(function () use ($data, $existing) {
            foreach ($data as $nis => $item) {
                $siswa = $existing->get((string) $nis);

                if (!$siswa) {
                    siswa::create($item);
                    $this->ditambahkan++;
                    continue;
                }

                $siswa->fill([
                    'nama_siswa' => $item['nama_siswa'],
                    'id_kelas'   => $item['id_kelas'],
                    'id_jurusan' => $item['id_jurusan'],
                ]);

                if ($siswa->trashed()) {
                    $siswa->restore();
                    $this->dipulihkan++;
                } elseif ($siswa->isDirty()) {
                    $this->diperbarui++;
                } else {
                    continue;
                }

                $siswa->save();
            }
        });
    }

    public function summary(): string
    {
        $parts = [];
        if ($this->ditambahkan) $parts[] = "{$this->ditambahkan} siswa ditambahkan";
        if ($this->diperbarui)  $parts[] = "{$this->diperbarui} diperbarui";
        if ($this->dipulihkan)  $parts[] = "{$this->dipulihkan} dipulihkan dari arsip";
        if ($this->dilewati)    $parts[] = "{$this->dilewati} baris dilewati";

        $teks = $parts ? implode(', ', $parts) . '.' : 'Tidak ada data yang berubah.';

        $jurusan = $this->kelas->jurusan->nama_jurusan ?? $this->kelas->id_jurusan;

        return $teks . " Kelas tujuan: {$this->kelas->nama_kelas} (jurusan {$jurusan}).";
    }

    public function hasImported(): bool
    {
        return ($this->ditambahkan + $this->diperbarui + $this->dipulihkan) > 0;
    }

    private function detectColumns(Collection $rows): array
    {
        foreach ($rows->values()->take(self::HEADER_SCAN_ROWS) as $i => $row) {
            $normalized = [];
            foreach ($row->toArray() as $col => $cell) {
                $normalized[$col] = $this->normalizeHeader($cell);
            }

            $map = [];
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $col = array_search($alias, $normalized, true);
                    if ($col !== false) {
                        $map[$field] = $col;
                        break;
                    }
                }
            }

            if (isset($map['nis'], $map['nama'])) {
                return [$map, $i + 1];
            }
        }

        // Header tidak ketemu: pakai urutan template (No, NIS, Nama Siswa), baris 1 = header.
        return [['nis' => 1, 'nama' => 2], 1];
    }

    /** Huruf kecil, hanya a-z dan 0-9. Dipakai untuk membaca judul kolom. */
    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));
    }

    private function isContoh($nama): bool
    {
        return is_string($nama)
            && str_starts_with(mb_strtolower(trim($nama)), 'contoh');
    }

    private function cleanNis($value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            $value = sprintf('%.0f', $value);
        }

        $value = trim(ltrim(trim((string) $value), "'`"));

        if (preg_match('/^\d+(\.0+)?$/', $value)) {
            $value = explode('.', $value)[0];
        } elseif (is_numeric($value)) {
            $value = sprintf('%.0f', (float) $value);
        }

        return preg_match('/^\d{1,18}$/', $value) ? $value : null;
    }

    private function isBlank($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function fail(int $barisExcel, string $pesan): void
    {
        $this->dilewati++;
        $this->errors[] = "Baris $barisExcel: $pesan";
    }
}