<?php

namespace App\Imports;

use App\Models\kelas;
use App\Models\User;
use App\Models\walikelas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;

class Walikelas_Import implements ToCollection
{
    private const HEADER_ALIASES = [
        'nip'      => ['nip', 'nipguru', 'nipwalikelas', 'nomorindukpegawai'],
        'nama'     => ['namaguru', 'namawalikelas', 'nama', 'namalengkap'],
        'kelas'    => ['idkelas', 'kelas', 'kodekelas', 'namakelas'], // opsional
        'username' => ['username'], // opsional, kalau kosong dipakai nama guru
    ];

    /**
     * Isi kolom kelas yang berarti "tanpa kelas" (sudah dinormalisasi:
     * huruf kecil, hanya a-z 0-9). Tanda "-" menjadi string kosong.
     */
    private const TANPA_KELAS = ['', 'tanpakelas', 'tidakada', 'tidakpunyakelas', 'kosong', 'none', 'null', 'na'];

    private const HEADER_SCAN_ROWS = 10;

    private const PASSWORD_DEFAULT = 'password123';

    public int $ditambahkan = 0;
    public int $diperbarui = 0;
    public int $dilewati = 0;

    /** @var string[] */
    public array $errors = [];

    /** Key kelas yang cocok ke lebih dari satu kelas (tidak bisa dipastikan) */
    private array $kelasAmbigu = [];

    /** Sudah ada sheet dengan header valid (sheet "Petunjuk" diabaikan setelahnya) */
    private bool $headerFound = false;

    public function collection(Collection $rows)
    {
        [$map, $startIndex] = $this->detectColumns($rows);

        if ($map === null) {
            // Sheet lain (mis. "Petunjuk & Kelas") memang tidak punya header data.
            // Error hanya dicatat kalau belum ada satu pun sheet yang headernya valid.
            $pesan = 'Header tidak ditemukan. Kolom wajib: NIP, Nama Guru '
                . '(kolom Id Kelas dan Username boleh ditambahkan, tidak wajib).';

            if (!$this->headerFound && !in_array($pesan, $this->errors, true)) {
                $this->errors[] = $pesan;
            }

            return;
        }

        $this->headerFound = true;

        $kelasMap = $this->buildKelasMap();

        $data = [];
        $seenNip = [];
        $seenUsername = [];

        foreach ($rows->values() as $index => $row) {
            if ($index < $startIndex) {
                continue;
            }

            $barisExcel = $index + 1;
            $row = $row->toArray();

            $nipRaw      = $row[$map['nip']] ?? null;
            $namaRaw     = $row[$map['nama']] ?? null;
            $kelasRaw    = isset($map['kelas']) ? ($row[$map['kelas']] ?? null) : null;
            $usernameRaw = isset($map['username']) ? ($row[$map['username']] ?? null) : null;

            if ($this->isBlank($nipRaw) && $this->isBlank($namaRaw) && $this->isBlank($kelasRaw)) {
                continue;
            }

            // Baris contoh dari template (nama diawali "CONTOH") dilewati.
            if (is_string($namaRaw) && str_starts_with(mb_strtolower(trim($namaRaw)), 'contoh')) {
                continue;
            }

            foreach ([$nipRaw, $namaRaw, $kelasRaw, $usernameRaw] as $cell) {
                if (is_string($cell) && preg_match('/^\s*[=+@]/', $cell)) {
                    $this->fail($barisExcel, 'berisi rumus / karakter tidak diizinkan');
                    continue 2;
                }
            }

            // Angka Excel hanya akurat sampai 15 digit; NIP 18 digit harus disimpan sebagai Teks.
            if ((is_float($nipRaw) || is_int($nipRaw)) && abs($nipRaw) >= 1.0e15) {
                $this->fail(
                    $barisExcel,
                    'NIP terlalu panjang untuk format angka Excel (digit belakang bisa berubah). '
                    . 'Ubah format kolom NIP menjadi Teks lalu isi ulang'
                );
                continue;
            }

            $nip = $this->cleanNip($nipRaw);
            if ($nip === null) {
                $this->fail($barisExcel, 'NIP kosong atau tidak valid (harus angka)');
                continue;
            }

            $nama = trim(preg_replace('/\s+/u', ' ', (string) $namaRaw));
            if ($nama === '') {
                $this->fail($barisExcel, "Nama guru kosong (NIP $nip)");
                continue;
            }
            if (mb_strlen($nama) > 255) {
                $this->fail($barisExcel, "Nama guru terlalu panjang (NIP $nip)");
                continue;
            }

            $username = trim(preg_replace('/\s+/u', ' ', (string) $usernameRaw));
            if ($username === '') {
                $username = $nama;
            }
            if (mb_strlen($username) > 255) {
                $this->fail($barisExcel, "Username terlalu panjang (NIP $nip)");
                continue;
            }

            // === Kelas (opsional) ===
            //  - sel kosong / kolom tidak ada : guru baru = tanpa kelas, guru lama = kelas tidak diubah
            //  - "Tanpa Kelas" atau "-"       : guru dijadikan tanpa kelas (juga untuk guru lama)
            //  - isi kelas                    : dicocokkan ke daftar kelas
            $idKelas  = null;
            $setKelas = false;

            if (!$this->isBlank($kelasRaw)) {
                // Spasi, tanda hubung, dan huruf besar/kecil diabaikan:
                // "10 PPLG 1" == "10-pplg-1" == "10PPLG1"
                $kelasKey = $this->normalizeHeader(kelas::romawiKeAngka($kelasRaw));

                if (in_array($kelasKey, self::TANPA_KELAS, true)) {
                    $setKelas = true; // id_kelas tetap null
                } else {
                    $kelasTeks = trim((string) $kelasRaw);
                    $kelas = $kelasMap[$kelasKey] ?? null;

                    if (!$kelas) {
                        if (isset($this->kelasAmbigu[$kelasKey])) {
                            $this->fail(
                                $barisExcel,
                                "Kelas '$kelasTeks' cocok dengan lebih dari satu kelas, pakai Id Kelas (NIP $nip)"
                            );
                        } else {
                            $this->fail(
                                $barisExcel,
                                "Kelas '$kelasTeks' tidak ditemukan, pakai Id Kelas seperti "
                                . "'10-RPL-1', nama seperti '10 PPLG 1', atau 'Tanpa Kelas' (NIP $nip)"
                            );
                        }
                        continue;
                    }

                    $idKelas  = $kelas->id_kelas;
                    $setKelas = true;
                }
            }

            if (isset($seenNip[$nip])) {
                $this->fail($barisExcel, "NIP $nip dobel di file (sudah ada di baris {$seenNip[$nip]})");
                continue;
            }

            $usernameKey = mb_strtolower($username);
            if (isset($seenUsername[$usernameKey])) {
                $this->fail(
                    $barisExcel,
                    "Username '$username' dobel di file (sudah ada di baris {$seenUsername[$usernameKey]})"
                );
                continue;
            }

            $seenNip[$nip] = $barisExcel;
            $seenUsername[$usernameKey] = $barisExcel;

            $data[$nip] = [
                'nip_walikelas'  => $nip,
                'username'       => $username,
                'nama_walikelas' => $nama,
                'id_kelas'       => $idKelas,
                'set_kelas'      => $setKelas,
                'baris'          => $barisExcel,
            ];
        }

        if (empty($data)) {
            return;
        }

        // Data yang sudah ada di database
        $waliByNip = collect();
        foreach (array_chunk(array_keys($data), 500) as $chunk) {
            $waliByNip = $waliByNip->concat(walikelas::whereIn('nip_walikelas', $chunk)->get());
        }
        $waliByNip = $waliByNip->keyBy(fn ($w) => (string) $w->nip_walikelas);

        $usernames = array_column($data, 'username');

        $users = collect();
        $waliByUser = collect();
        foreach (array_chunk($usernames, 500) as $chunk) {
            $users = $users->concat(User::whereIn('username', $chunk)->get());
            $waliByUser = $waliByUser->concat(walikelas::whereIn('username', $chunk)->get());
        }
        $users = $users->keyBy(fn ($u) => mb_strtolower($u->username));
        $waliByUser = $waliByUser->keyBy(fn ($w) => mb_strtolower($w->username));

        // Tentukan aksi tiap baris sebelum menyentuh database
        $plan = [];
        foreach ($data as $nip => $item) {
            $wali = $waliByNip->get((string) $nip);

            if ($wali) {
                $plan[] = ['type' => 'update', 'wali' => $wali, 'item' => $item];
                continue;
            }

            $key = mb_strtolower($item['username']);
            $user = $users->get($key);

            if ($user) {
                $waliLain = $waliByUser->get($key);
                if ($waliLain) {
                    $this->fail(
                        $item['baris'],
                        "Username '{$item['username']}' sudah dipakai guru lain (NIP {$waliLain->nip_walikelas})"
                    );
                    continue;
                }

                if ((int) $user->role !== 3) {
                    $this->fail(
                        $item['baris'],
                        "Username '{$item['username']}' sudah dipakai akun lain yang bukan guru"
                    );
                    continue;
                }
            }

            $plan[] = ['type' => 'create', 'user' => $user, 'item' => $item];
        }

        if (empty($plan)) {
            return;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $p) {
                $item = $p['item'];

                if ($p['type'] === 'update') {
                    // Akun (username) tidak diubah. Nama selalu diperbarui,
                    // kelas hanya kalau kolom kelas terisi (termasuk "Tanpa Kelas").
                    $wali = $p['wali'];
                    $wali->nama_walikelas = $item['nama_walikelas'];

                    if ($item['set_kelas']) {
                        $wali->id_kelas = $item['id_kelas'];
                    }

                    if ($wali->isDirty()) {
                        $wali->save();
                        $this->diperbarui++;
                    }

                    continue;
                }

                $user = $p['user'] ?? $this->buatUser($item);

                walikelas::create([
                    'nip_walikelas'  => $item['nip_walikelas'],
                    'username'       => $user->username,
                    'nama_walikelas' => $item['nama_walikelas'],
                    'id_kelas'       => $item['id_kelas'], // null = tanpa kelas
                ]);

                $this->ditambahkan++;
            }
        });
    }

    public function summary(): string
    {
        $parts = [];
        if ($this->ditambahkan) $parts[] = "{$this->ditambahkan} guru ditambahkan";
        if ($this->diperbarui)  $parts[] = "{$this->diperbarui} diperbarui";
        if ($this->dilewati)    $parts[] = "{$this->dilewati} baris dilewati";

        return $parts ? implode(', ', $parts) . '.' : 'Tidak ada data yang berubah.';
    }

    public function hasImported(): bool
    {
        return ($this->ditambahkan + $this->diperbarui) > 0;
    }

    private function buatUser(array $item): User
    {
        $email = $item['username'] . '@gmail.com';

        if (User::where('email', $email)->exists()) {
            $email = $item['nip_walikelas'] . '@gmail.com';
        }

        return User::create([
            'username' => $item['username'],
            'email'    => $email,
            'password' => Hash::make(self::PASSWORD_DEFAULT),
            'role'     => 3,
        ]);
    }

    /**
     * Cari baris header dalam 10 baris pertama.
     * Wajib: NIP dan Nama. Kelas dan Username opsional.
     * Mengembalikan [map, indexBarisPertamaData] atau [null, 0] kalau header tidak ketemu.
     */
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

            if (isset($map['nip'], $map['nama'])) {
                return [$map, $i + 1];
            }
        }

        return [null, 0];
    }

    /** Huruf kecil, hanya a-z dan 0-9. Dipakai untuk header dan nama kelas. */
    private function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));
    }

    /**
     * Peta key => kelas. Prioritas:
     *  1. id_kelas persis        (10-RPL-1)
     *  2. nama_kelas persis      (10 PPLG 1)
     *  3. alias tingkat+kode+no  (10 RPL 1, 10 PPLG 1, 11 RPL 1, ...)
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
     * Alias dari id_kelas "10-RPL-1" + jurusan:
     * tingkat + (kode di id, kode program, kode kompetensi) + nomor.
     */
    private function aliasKelas($k): array
    {
        if (!preg_match('/^(12|11|10)-([^-]+)-(\d+)$/', trim((string) $k->id_kelas), $m)) {
            return [];
        }

        $tingkat = $m[1];
        $nomor   = $m[3];

        $kodeList = array_unique(array_filter([
            strtoupper($m[2]),
            kelas::programKeahlian($k->id_jurusan),
            kelas::kompetensiKelas($k->id_jurusan, $m[2]),
        ]));

        return array_map(
            fn ($kode) => $this->normalizeHeader($tingkat . $kode . $nomor),
            $kodeList
        );
    }

    private function cleanNip($value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            $value = sprintf('%.0f', $value);
        }

        $value = trim(ltrim(trim((string) $value), "'`"));

        // NIP sering ditulis "1967 0409 2007 01 2011" atau "1967-0409-..."
        $value = preg_replace('/[\s\-]/', '', $value);

        if (preg_match('/^\d+(\.0+)?$/', $value)) {
            $value = explode('.', $value)[0];
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
