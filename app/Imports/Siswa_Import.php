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
        'kelas' => ['idkelas', 'kelas', 'kodekelas', 'namakelas'],
    ];

    private const HEADER_SCAN_ROWS = 10;

    public int $ditambahkan = 0;
    public int $diperbarui = 0;
    public int $dipulihkan = 0;
    public int $dilewati = 0;

    /** @var string[] */
    public array $errors = [];

    /** Key kelas yang cocok ke lebih dari satu kelas (tidak bisa dipastikan) */
    private array $kelasAmbigu = [];

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

            $nisRaw   = $row[$map['nis']] ?? null;
            $namaRaw  = $row[$map['nama']] ?? null;
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

            // Spasi, tanda hubung, dan huruf besar/kecil diabaikan:
            // "X PPLG 1" == "x-pplg-1" == "XPPLG1"
            $kelasKey = $this->normalizeHeader($kelasRaw);
            if ($kelasKey === '') {
                $this->fail($barisExcel, "Kelas kosong (NIS $nis)");
                continue;
            }

            $kelasTeks = trim((string) $kelasRaw);
            $kelas = $kelasMap[$kelasKey] ?? null;

            if (!$kelas) {
                if (isset($this->kelasAmbigu[$kelasKey])) {
                    $this->fail(
                        $barisExcel,
                        "Kelas '$kelasTeks' cocok dengan lebih dari satu kelas, pakai Id Kelas (NIS $nis)"
                    );
                } else {
                    $this->fail(
                        $barisExcel,
                        "Kelas '$kelasTeks' tidak ditemukan, pakai Id Kelas atau nama seperti "
                        . "'X PPLG 1' / 'XI RPL 1' (NIS $nis)"
                    );
                }
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

    /** Huruf kecil, hanya a-z dan 0-9. Dipakai untuk header dan nama kelas. */
    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));
    }

    /**
     * Peta key => kelas. Prioritas:
     *  1. id_kelas persis        (X-RPL-1)
     *  2. nama_kelas persis      (X PPLG 1)
     *  3. alias tingkat+kode+no  (X RPL 1 lama, X PPLG 1, XI RPL 1, XI PPLG 1, ...)
     * Key yang ambigu di satu tingkat prioritas dilewati dan dicatat di $kelasAmbigu.
     */
    private function buildKelasMap(): array
    {
        $byId = $byNama = $byAlias = [];
        $ambNama = $ambAlias = [];

        foreach (kelas::all() as $k) {
            $byId[$this->normalizeHeader($k->id_kelas)] = $k;

            $this->daftarkan($byNama, $ambNama, $this->normalizeHeader($k->nama_kelas), $k);

            foreach ($this->aliasKelas($k) as $alias) {
                $this->daftarkan($byAlias, $ambAlias, $alias, $k);
            }
        }

        $final = $byId;

        foreach ([[$byNama, $ambNama], [$byAlias, $ambAlias]] as [$tier, $amb]) {
            foreach ($tier as $key => $k) {
                if (!isset($final[$key]) && !isset($amb[$key])) {
                    $final[$key] = $k;
                }
            }
        }

        $this->kelasAmbigu = [];
        foreach ($ambNama + $ambAlias as $key => $_) {
            if (!isset($final[$key])) {
                $this->kelasAmbigu[$key] = true;
            }
        }

        return $final;
    }

    private function daftarkan(array &$tier, array &$ambigu, string $key, $k): void
    {
        if ($key === '') {
            return;
        }

        if (isset($tier[$key]) && $tier[$key]->id_kelas !== $k->id_kelas) {
            $ambigu[$key] = true;
            return;
        }

        $tier[$key] = $k;
    }

    /**
     * Alias dari id_kelas "X-RPL-1" + jurusan:
     * tingkat + (kode di id, kode program, kode kompetensi) + nomor.
     */
    private function aliasKelas($k): array
    {
        if (!preg_match('/^(XII|XI|X)-([^-]+)-(\d+)$/i', trim((string) $k->id_kelas), $m)) {
            return [];
        }

        $tingkat = strtoupper($m[1]);
        $nomor   = $m[3];

        $kodeList = array_unique(array_filter([
            strtoupper($m[2]),
            kelas::PROGRAM_KEAHLIAN[$k->id_jurusan] ?? null,
            kelas::KOMPETENSI_KEAHLIAN[$k->id_jurusan] ?? null,
        ]));

        return array_map(
            fn ($kode) => $this->normalizeHeader($tingkat . $kode . $nomor),
            $kodeList
        );
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