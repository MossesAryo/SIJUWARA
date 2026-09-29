<?php

namespace App\Imports;

use App\Models\kelas;
use App\Models\siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class Siswa_Import implements ToCollection
{
    private const HEADER_ALIASES = [
        'nis'   => ['nis', 'nomorinduksiswa', 'nomorinduk'],
        'nama'  => ['namasiswa', 'nama', 'namalengkap'],
        'kelas' => ['idkelas', 'kelas', 'kodekelas'],
    ];

    private const HEADER_SCAN_ROWS = 10;

    public int $ditambahkan = 0;
    public int $diperbarui = 0;
    public int $dipulihkan = 0;
    public int $dilewati = 0;

    /** @var string[] */
    public array $errors = [];

    public function collection(Collection $rows)
    {
        [$map, $startIndex] = $this->detectColumns($rows);

        $kelasMap = $this->buildKelasMap();

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
            $kelasRaw = $row[$map['kelas']] ?? null;

            if ($this->isBlank($nisRaw) && $this->isBlank($namaRaw) && $this->isBlank($kelasRaw)) {
                continue;
            }

            foreach ([$nisRaw, $namaRaw, $kelasRaw] as $cell) {
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

            $kelasKey = mb_strtolower(trim((string) $kelasRaw));
            if ($kelasKey === '') {
                $this->fail($barisExcel, "Kelas kosong (NIS $nis)");
                continue;
            }
            $kelas = $kelasMap[$kelasKey] ?? null;
            if (!$kelas) {
                $this->fail($barisExcel, "Kelas '" . trim((string) $kelasRaw) . "' tidak ditemukan (NIS $nis)");
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
                'id_kelas'   => $kelas->id_kelas,
                'id_jurusan' => $kelas->id_jurusan,
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

        return $parts ? implode(', ', $parts) . '.' : 'Tidak ada data yang berubah.';
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

            if (isset($map['nis'], $map['nama'], $map['kelas'])) {
                return [$map, $i + 1];
            }
        }

        return [['nis' => 1, 'nama' => 2, 'kelas' => 3], 2];
    }

    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));
    }

    private function buildKelasMap(): array
    {
        $all = kelas::all();
        $map = [];

        foreach ($all as $k) {
            $map[mb_strtolower(trim($k->nama_kelas))] = $k;
        }
        foreach ($all as $k) {
            $map[mb_strtolower(trim($k->id_kelas))] = $k;
        }

        return $map;
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