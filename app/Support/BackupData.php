<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory as ReaderFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Titik pemulihan (backup) otomatis sebelum proses yang tidak bisa dibatalkan,
 * terutama ganti tahun ajaran.
 *
 * Yang disimpan hanya kolom yang DIUBAH proses tersebut, bukan seluruh database:
 *   - tahun_ajaran          : status (aktif / nonaktif)
 *   - siswa                 : id_kelas, status, updated_at
 *   - walikelas             : id_kelas, updated_at
 *   - rencana_pindah_kelas  : seluruh baris (rencana dihapus saat tahun diganti)
 *   - student_class_histories : seluruh baris Riwayat Kelas. Saat dipulihkan, baris yang dibuat SETELAH
 *                             backup dihapus (riwayat itu mencatat perubahan kelas yang dibatalkan oleh
 *                             pemulihan), dan baris yang hilang dikembalikan. Sheet ini opsional supaya
 *                             backup lama (sebelum ada sheet ini) tetap sah dan bisa dipulihkan.
 *
 * Poin siswa, penilaian, intervensi, dan catatan sengaja tidak ikut, sehingga
 * pemulihan tidak menimpa data yang berubah setelah backup dibuat.
 *
 * Selain kolom yang dipulihkan, tiap sheet memuat kolom NAMA sebagai informasi (supaya file bisa dibaca
 * manusia dan nama tetap ada walau siswa / guru dihapus kemudian):
 *   - tahun_ajaran          : tahun_ajaran (nama tahun ajarannya, bukan hanya id)
 *   - siswa                 : nama_siswa, nama_kelas, nama_walikelas (wali kelas dari kelas siswa saat backup)
 *   - walikelas             : nama_walikelas (nama guru), nama_kelas
 *   - rencana_pindah_kelas  : nama_siswa, nama_pembuat (nama Ketua Program pembuat rencana)
 *   - student_class_histories : nama_siswa
 * Kolom nama ini TIDAK dipulihkan dan diabaikan oleh backup:pulihkan (lihat kolomInfo()).
 *
 * File berupa Excel (.xlsx) yang bisa dibuka dan dibaca manusia:
 *   - sheet "Info"                  : waktu dibuat, alasan, database, status SELESAI, jumlah baris per tabel
 *   - sheet <nama tabel>            : baris 1 = nama kolom, baris 2 dst = data
 *
 * Semua nilai ditulis sebagai TEKS (NIS / NIP 18 digit tidak boleh jadi angka ilmiah).
 * Sel kosong = NULL. Jangan ubah nama sheet dan baris judul kalau file ingin dipulihkan.
 *
 * Pemulihan memakai UPDATE / upsert, tidak memakai DELETE/TRUNCATE sehingga tidak memicu
 * ON DELETE CASCADE. Dipulihkan lewat: php artisan backup:pulihkan {nama-file}
 *
 * File dibuat di storage/app/backup/tahun-ajaran (tidak bisa diakses dari browser).
 */
class BackupData
{
    /** Isi sel "Status" di sheet Info bila file ditulis sampai selesai. */
    public const PENANDA_SELESAI = 'SELESAI';

    public const EKSTENSI = 'xlsx';

    public const SHEET_INFO = 'Info';

    /** Label tabel yang dicadangkan, urut tampil. */
    public const TABEL = [
        'tahun_ajaran'         => 'Tahun Ajaran',
        'siswa'                => 'Siswa',
        'walikelas'            => 'Wali Kelas',
        'rencana_pindah_kelas' => 'Rencana Pindah Kelas',
        'student_class_histories' => 'Riwayat Kelas',
    ];

    /**
     * Cara menyimpan & memulihkan tiap tabel.
     * mode "update": baris tidak pernah dihapus proses, cukup dikembalikan nilai kolomnya.
     * mode "insert": baris bisa dihapus proses, jadi disimpan utuh dan dimasukkan lagi.
     * mode "sinkron": seperti insert, tetapi baris yang dibuat SETELAH backup (id lebih besar dari id
     *                 terbesar di backup) juga dihapus, supaya tabel kembali persis seperti saat backup.
     * opsional: sheet boleh tidak ada (backup lama); file tetap dianggap lengkap.
     */
    public const SPEK = [
        'tahun_ajaran'         => ['kunci' => 'id',            'mode' => 'update', 'kolom' => ['status'], 'info' => ['tahun_ajaran']],
        'siswa'                => ['kunci' => 'nis',           'mode' => 'update', 'kolom' => ['id_kelas', 'status', 'updated_at'], 'info' => ['nama_siswa', 'nama_kelas', 'nama_walikelas']],
        'walikelas'            => ['kunci' => 'nip_walikelas', 'mode' => 'update', 'kolom' => ['id_kelas', 'updated_at'], 'info' => ['nama_walikelas', 'nama_kelas']],
        'rencana_pindah_kelas' => ['kunci' => 'id',            'mode' => 'insert', 'kolom' => null, 'info' => ['nama_siswa', 'nama_pembuat']],
        'student_class_histories' => ['kunci' => 'id',         'mode' => 'sinkron', 'kolom' => null, 'info' => ['nama_siswa'], 'opsional' => true],
    ];

    /** Tabel yang WAJIB ada di file agar dianggap lengkap (sisanya opsional, mis. sheet baru). */
    public static function tabelWajib(): array
    {
        return array_keys(array_filter(self::SPEK, fn ($s) => empty($s['opsional'])));
    }

    /**
     * Kolom informasi (nama siswa / guru) pada sheet satu tabel. Ikut ditulis ke file tetapi tidak pernah
     * dipulihkan ke database.
     *
     * @return array<int,string>
     */
    public static function kolomInfo(string $tabel): array
    {
        return self::SPEK[$tabel]['info'] ?? [];
    }

    /** @var array<string, array<string, mixed>|null> cache hasil baca sheet Info per file */
    private static array $cacheInfo = [];

    /** Pesan galat terakhir saat membaca file backup (untuk diagnosa). */
    private static ?string $galatTerakhir = null;

    /** Pesan galat terakhir saat membaca file backup, atau null. */
    public static function galatTerakhir(): ?string
    {
        return self::$galatTerakhir;
    }

    /**
     * Filter baca: hanya sel dari satu sheet (dan paling banyak $maksBaris baris pertama bila diisi).
     * Dipakai supaya membaca sheet Info / satu tabel tidak memuat seluruh file.
     */
    private static function filterBaca(string $sheet, ?int $maksBaris = null): IReadFilter
    {
        return new class($sheet, $maksBaris) implements IReadFilter {
            public function __construct(private string $sheet, private ?int $maksBaris)
            {
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $worksheetName === $this->sheet
                    && ($this->maksBaris === null || $row <= $this->maksBaris);
            }
        };
    }

    public static function folder(): string
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'tahun-ajaran');
    }

    /**
     * Buat file backup Excel. Mengembalikan path lengkap file.
     * Melempar RuntimeException (pesan siap tampil) bila gagal; file setengah jadi dibuang.
     */
    public static function buat(string $alasan, string $label = 'backup'): string
    {
        $folder = self::folder();

        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new RuntimeException("Folder backup tidak bisa dibuat: {$folder}");
        }

        $nama   = Str::slug($label, '-') . '_' . date('Ymd_His') . '.' . self::EKSTENSI;
        $path   = $folder . DIRECTORY_SEPARATOR . $nama;
        $sentra = $path . '.tmp';

        $spreadsheet = null;

        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('SIJUWARA')
                ->setTitle('Titik pemulihan SIJUWARA')
                ->setDescription(self::satuBaris($alasan));

            // Sheet pertama bawaan dipakai sebagai Info; isinya diisi setelah jumlah baris diketahui.
            $info = $spreadsheet->getActiveSheet();
            $info->setTitle(self::SHEET_INFO);

            $jumlah = [];
            foreach (self::SPEK as $tabel => $spec) {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($tabel);
                $jumlah[$tabel] = self::tulisTabel($sheet, $tabel, $spec);
            }

            self::tulisInfo($info, $alasan, $jumlah);
            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save($sentra);

            $spreadsheet->disconnectWorksheets();
            $spreadsheet = null;

            if (!@rename($sentra, $path)) {
                throw new RuntimeException('File backup tidak bisa difinalisasi.');
            }

            if (!self::valid($path)) {
                throw new RuntimeException(
                    'File backup gagal diverifikasi' . (self::$galatTerakhir ? ': ' . self::$galatTerakhir : '.')
                );
            }

            // Verifikasi isi: jumlah baris tiap sheet harus sama dengan yang dicatat di sheet Info.
            foreach ($jumlah as $tabel => $n) {
                if (count(self::bacaTabel($path, $tabel)) !== $n) {
                    throw new RuntimeException("Jumlah baris sheet {$tabel} tidak sama dengan database.");
                }
            }
        } catch (Throwable $e) {
            if ($spreadsheet instanceof Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($sentra);
            @unlink($path);

            throw new RuntimeException(
                'Backup otomatis gagal dibuat (' . $e->getMessage() . '). '
                . 'Proses dibatalkan dan tidak ada data yang berubah.',
                0,
                $e
            );
        }

        return $path;
    }

    /**
     * Sudah lengkap? File Excel bisa dibuka, ada sheet Info berstatus SELESAI,
     * dan semua sheet tabel ada.
     */
    public static function valid(string $path): bool
    {
        $info = self::info($path);

        return $info !== null && $info['selesai'] === true;
    }

    /**
     * Isi sheet Info, atau null bila file bukan backup Excel yang sah / tidak bisa dibuka.
     *
     * @return array{dibuat:?string, alasan:?string, database:?string, selesai:bool, jumlah:array<string,int>}|null
     */
    public static function info(string $path): ?array
    {
        clearstatcache(true, $path);

        if (!is_file($path) || filesize($path) === 0) {
            self::$galatTerakhir = 'file tidak ada atau kosong';

            return null;
        }

        $kunci = $path . '|' . filemtime($path) . '|' . filesize($path);

        if (array_key_exists($kunci, self::$cacheInfo)) {
            return self::$cacheInfo[$kunci];
        }

        $hasil = null;

        try {
            $reader = ReaderFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(self::filterBaca(self::SHEET_INFO, 50));

            $spreadsheet = $reader->load($path);
            $sheet       = $spreadsheet->getSheetByName(self::SHEET_INFO);

            if ($sheet === null) {
                self::$galatTerakhir = 'sheet "' . self::SHEET_INFO . '" tidak ditemukan';
            }

            if ($sheet !== null) {
                $hasil = [
                    'dibuat'   => null,
                    'alasan'   => null,
                    'database' => null,
                    'selesai'  => false,
                    'jumlah'   => [],
                ];

                foreach ($sheet->toArray(null, false, false, false) as $baris) {
                    $label = trim((string) ($baris[0] ?? ''));
                    $isi   = $baris[1] ?? null;

                    if ($label === 'Dibuat') {
                        $hasil['dibuat'] = $isi === null ? null : (string) $isi;
                    } elseif ($label === 'Alasan') {
                        $hasil['alasan'] = $isi === null ? null : (string) $isi;
                    } elseif ($label === 'Database') {
                        $hasil['database'] = $isi === null ? null : (string) $isi;
                    } elseif ($label === 'Status') {
                        $hasil['selesai'] = trim((string) $isi) === self::PENANDA_SELESAI;
                    } elseif (array_key_exists($label, self::SPEK) && is_numeric($isi)) {
                        $hasil['jumlah'][$label] = (int) $isi;
                    }
                }

                // Semua tabel WAJIB harus tercatat; kalau ada yang hilang, file dianggap tidak lengkap.
                // Tabel opsional (mis. Riwayat Kelas) boleh tidak ada di backup lama.
                if (array_diff(self::tabelWajib(), array_keys($hasil['jumlah']))) {
                    $hasil['selesai'] = false;
                    self::$galatTerakhir = 'jumlah baris tabel di sheet Info tidak lengkap';
                } elseif (!$hasil['selesai']) {
                    self::$galatTerakhir = 'status di sheet Info bukan ' . self::PENANDA_SELESAI;
                }
            }

            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            self::$galatTerakhir = $e->getMessage();
            $hasil               = null;
        }

        return self::$cacheInfo[$kunci] = $hasil;
    }

    /**
     * Daftar backup, terbaru dulu.
     *
     * @return array<int, array{nama:string, path:string, ukuran:int, waktu:int, valid:bool}>
     */
    public static function daftar(): array
    {
        $file = glob(self::folder() . DIRECTORY_SEPARATOR . '*.' . self::EKSTENSI) ?: [];

        $baris = array_map(fn ($p) => [
            'nama'   => basename($p),
            'path'   => $p,
            'ukuran' => (int) filesize($p),
            'waktu'  => (int) filemtime($p),
            'valid'  => self::valid($p),
        ], $file);

        usort($baris, fn ($a, $b) => $b['waktu'] <=> $a['waktu'] ?: strcmp($b['nama'], $a['nama']));

        return $baris;
    }

    /** Keterangan singkat (waktu, alasan, database) untuk ditampilkan sebelum pemulihan. */
    public static function keterangan(string $path): array
    {
        $info = self::info($path);

        if ($info === null) {
            return [];
        }

        return array_values(array_filter([
            $info['dibuat'] ? 'Dibuat   : ' . $info['dibuat'] : null,
            $info['alasan'] ? 'Alasan   : ' . $info['alasan'] : null,
            $info['database'] ? 'Database : ' . $info['database'] : null,
        ]));
    }

    /**
     * Baca semua baris satu sheet tabel. Tiap baris: nama kolom => nilai teks (atau null bila sel kosong).
     * Baris yang seluruh selnya kosong dilewati.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function bacaTabel(string $path, string $tabel): array
    {
        if (!array_key_exists($tabel, self::SPEK)) {
            return [];
        }

        $reader = ReaderFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(self::filterBaca($tabel));

        $spreadsheet = $reader->load($path);
        $sheet       = $spreadsheet->getSheetByName($tabel);

        if ($sheet === null) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException("Sheet \"{$tabel}\" tidak ada di file backup.");
        }

        $rows = $sheet->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        $judul = array_map(fn ($j) => trim((string) $j), array_shift($rows) ?? []);
        $hasil = [];

        foreach ($rows as $baris) {
            $data  = [];
            $kosong = true;

            foreach ($judul as $i => $nama) {
                if ($nama === '') {
                    continue;
                }

                $nilai = self::normalisasi($baris[$i] ?? null);
                if ($nilai !== null) {
                    $kosong = false;
                }

                $data[$nama] = $nilai;
            }

            if (!$kosong) {
                $hasil[] = $data;
            }
        }

        return $hasil;
    }

    /**
     * Tulis semua baris satu tabel ke sheet. Mengembalikan jumlah baris data.
     */
    private static function tulisTabel($sheet, string $tabel, array $spec): int
    {
        $kunci = $spec['kunci'];

        $info = $spec['info'] ?? [];

        if ($spec['mode'] === 'update') {
            // Kunci, lalu nama (supaya mudah dibaca), lalu kolom yang dipulihkan.
            // Kolom info yang sama dengan kunci / kolom pulih (mis. tahun_ajaran) tidak ditulis dua kali.
            $kolom = array_values(array_unique(array_merge([$kunci], $info, $spec['kolom'])));
        } else {
            $kolom = array_values(array_unique(array_merge(Schema::getColumnListing($tabel), $info)));
        }

        $peta = self::petaNama($tabel);

        foreach ($kolom as $i => $nama) {
            $sheet->setCellValueExplicit(self::sel($i + 1, 1), $nama, DataType::TYPE_STRING);
        }

        $total = 0;
        $baris = 1;

        foreach (DB::table($tabel)->orderBy($kunci)->cursor() as $row) {
            $row = (array) $row;
            $row = array_merge($row, self::nilaiNama($tabel, $row, $peta));
            $baris++;

            foreach ($kolom as $i => $nama) {
                $nilai = $row[$nama] ?? null;

                if ($nilai === null) {
                    continue; // sel kosong = NULL
                }

                if (is_bool($nilai)) {
                    $nilai = $nilai ? '1' : '0';
                }

                $sheet->setCellValueExplicit(self::sel($i + 1, $baris), (string) $nilai, DataType::TYPE_STRING);
            }

            $total++;
        }

        // Tampilan: judul tebal, baris judul dibekukan, semua kolom berformat teks dan lebar otomatis.
        $akhirKolom = Coordinate::stringFromColumnIndex(count($kolom));
        $akhirBaris = max(1, $baris);

        $sheet->getStyle("A1:{$akhirKolom}{$akhirBaris}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("A1:{$akhirKolom}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$akhirKolom}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');

        for ($i = 1; $i <= count($kolom); $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        return $total;
    }

    /**
     * Peta pencarian nama yang dibutuhkan kolom informasi satu tabel (dimuat sekali per sheet).
     *
     * @return array<string, array<string,string>>
     */
    private static function petaNama(string $tabel): array
    {
        if ($tabel === 'siswa') {
            // Wali kelas per kelas; bila satu kelas punya beberapa wali, namanya digabung.
            $wali = DB::table('walikelas')
                ->whereNotNull('id_kelas')
                ->orderBy('nama_walikelas')
                ->get(['id_kelas', 'nama_walikelas'])
                ->groupBy('id_kelas')
                ->map(fn ($g) => $g->pluck('nama_walikelas')->implode(', '))
                ->all();

            return [
                'wali'  => $wali,
                'kelas' => DB::table('kelas')->pluck('nama_kelas', 'id_kelas')->all(),
            ];
        }

        if ($tabel === 'walikelas') {
            return ['kelas' => DB::table('kelas')->pluck('nama_kelas', 'id_kelas')->all()];
        }

        if ($tabel === 'student_class_histories') {
            return ['siswa' => DB::table('siswa')->pluck('nama_siswa', 'nis')->all()];
        }

        if ($tabel === 'rencana_pindah_kelas') {
            return [
                'siswa'  => DB::table('siswa')->pluck('nama_siswa', 'nis')->all(),
                'kaprog' => DB::table('ketua_program')->pluck('nama_ketua_program', 'username')->all(),
            ];
        }

        return [];
    }

    /**
     * Nilai kolom nama yang BUKAN kolom asli tabelnya (kolom asli, mis. siswa.nama_siswa dan
     * walikelas.nama_walikelas, sudah ada di $row).
     *
     * @param  array<string,mixed>  $row
     * @param  array<string, array<string,string>>  $peta
     * @return array<string,string|null>
     */
    private static function nilaiNama(string $tabel, array $row, array $peta): array
    {
        if ($tabel === 'siswa') {
            return [
                'nama_kelas'     => $peta['kelas'][$row['id_kelas'] ?? ''] ?? ($row['id_kelas'] ?? null),
                'nama_walikelas' => $peta['wali'][$row['id_kelas'] ?? ''] ?? null,
            ];
        }

        if ($tabel === 'walikelas') {
            return ['nama_kelas' => $peta['kelas'][$row['id_kelas'] ?? ''] ?? ($row['id_kelas'] ?? null)];
        }

        if ($tabel === 'student_class_histories') {
            return ['nama_siswa' => $peta['siswa'][$row['nis'] ?? ''] ?? null];
        }

        if ($tabel === 'rencana_pindah_kelas') {
            $pembuat = $row['dibuat_oleh'] ?? null;

            return [
                'nama_siswa'   => $peta['siswa'][$row['nis'] ?? ''] ?? null,
                // Tidak ketemu di ketua_program (mis. dibuat oleh admin): tampilkan username apa adanya.
                'nama_pembuat' => $pembuat === null ? null : ($peta['kaprog'][$pembuat] ?? $pembuat),
            ];
        }

        return [];
    }

    /** @param  array<string,int>  $jumlah */
    private static function tulisInfo($sheet, string $alasan, array $jumlah): void
    {
        $baris = [
            ['SIJUWARA titik pemulihan', null],
            ['Dibuat', date('Y-m-d H:i:s')],
            ['Alasan', self::satuBaris($alasan)],
            ['Database', DB::connection()->getDatabaseName()],
            ['Status', self::PENANDA_SELESAI],
            [null, null],
            ['Tabel', 'Jumlah baris'],
        ];

        foreach ($jumlah as $tabel => $n) {
            $baris[] = [$tabel, (string) $n];
        }

        $baris[] = [null, null];
        $baris[] = ['Catatan', 'Jangan ubah nama sheet dan baris judul (baris 1) bila file ini akan dipulihkan. Sel kosong berarti NULL.'];

        foreach ($baris as $r => $isi) {
            foreach ($isi as $c => $nilai) {
                if ($nilai !== null) {
                    $sheet->setCellValueExplicit(self::sel($c + 1, $r + 1), (string) $nilai, DataType::TYPE_STRING);
                }
            }
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A5')->getFont()->setBold(true);
        $sheet->getStyle('A7:B7')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setWidth(70);
    }

    /** Nilai sel jadi teks; angka dari Excel (bila file diedit manual) dijaga agar NIP tidak berbentuk ilmiah. */
    private static function normalisasi($nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        if (is_float($nilai)) {
            return floor($nilai) === $nilai && abs($nilai) < 1e18
                ? sprintf('%.0f', $nilai)
                : (string) $nilai;
        }

        return (string) $nilai;
    }

    /** Koordinat sel gaya Excel dari nomor kolom & baris (mulai 1), mis. (2, 5) -> "B5". */
    private static function sel(int $kolom, int $baris): string
    {
        return Coordinate::stringFromColumnIndex($kolom) . $baris;
    }

    private static function satuBaris(string $teks): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $teks));
    }
}