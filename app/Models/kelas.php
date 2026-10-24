<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class kelas extends Model
{
    protected $table = 'kelas';
    protected $primaryKey = 'id_kelas';
    protected $fillable = ['id_kelas', 'nama_kelas', 'id_jurusan'];
    public $incrementing = false;

    // Tingkat kelas ditulis dengan angka: 10, 11, 12 (bukan angka romawi).
    public const TINGKAT = ['10', '11', '12'];

    /*
     * Konstanta di bawah HANYA cadangan (sebelum migrasi pemetaan dijalankan)
     * dan bahan seeder. Pemetaan yang dipakai aplikasi ada di tabel jurusan
     * (kolom kode_program dan konsentrasi), bisa diubah Super Admin.
     */

    // Program Keahlian (kelas 10)
    public const PROGRAM_KEAHLIAN = [
        'AK'  => 'AKL',
        'MP'  => 'MPLB',
        'PM'  => 'PM',
        'RPL' => 'PPLG',
        'DKV' => 'DKV',
        'TKJ' => 'TJKT',
    ];

    // Konsentrasi Keahlian (kelas 11 & 12). Satu jurusan boleh lebih dari satu.
    public const KONSENTRASI_KEAHLIAN = [
        'AK'  => ['AK'],
        'MP'  => ['MP', 'MLOG'],
        'PM'  => ['BR'],
        'RPL' => ['RPL'],
        'DKV' => ['DKV'],
        'TKJ' => ['TKJ'],
    ];

    // Konsentrasi utama (pertama) tiap jurusan.
    public const KOMPETENSI_KEAHLIAN = [
        'AK'  => 'AK',
        'MP'  => 'MP',
        'PM'  => 'BR',
        'RPL' => 'RPL',
        'DKV' => 'DKV',
        'TKJ' => 'TKJ',
    ];

    /** Cache pemetaan selama satu request. */
    private static ?array $petaKeahlian = null;

    /**
     * Pemetaan semua jurusan: [id_jurusan => ['program' => ?string, 'konsentrasi' => string[]]].
     * Sumber: tabel jurusan. Jika kolomnya belum ada (migrasi belum jalan), pakai konstanta.
     */
    public static function petaKeahlian(): array
    {
        if (self::$petaKeahlian !== null) {
            return self::$petaKeahlian;
        }

        $peta = [];

        try {
            if (Schema::hasColumn('jurusan', 'kode_program') && Schema::hasColumn('jurusan', 'konsentrasi')) {
                foreach (DB::table('jurusan')->get(['id_jurusan', 'kode_program', 'konsentrasi']) as $j) {
                    $peta[$j->id_jurusan] = [
                        'program'     => $j->kode_program ?: null,
                        'konsentrasi' => self::pecahKonsentrasi($j->konsentrasi),
                    ];
                }

                return self::$petaKeahlian = $peta;
            }
        } catch (\Throwable $e) {
            // jatuh ke konstanta
        }

        foreach (self::PROGRAM_KEAHLIAN as $idJurusan => $program) {
            $peta[$idJurusan] = [
                'program'     => $program,
                'konsentrasi' => self::KONSENTRASI_KEAHLIAN[$idJurusan] ?? [],
            ];
        }

        return self::$petaKeahlian = $peta;
    }

    /** Dipanggil setelah jurusan diubah supaya pemetaan dibaca ulang. */
    public static function lupakanPeta(): void
    {
        self::$petaKeahlian = null;
    }

    /** "mp, MLOG;mp" => ['MP', 'MLOG'] */
    public static function pecahKonsentrasi(?string $teks): array
    {
        $bagian = preg_split('/[\s,;]+/', strtoupper(trim((string) $teks)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($bagian ?: []));
    }

    public static function programKeahlian(?string $idJurusan): ?string
    {
        if (!$idJurusan) {
            return null;
        }

        return self::petaKeahlian()[$idJurusan]['program'] ?? null;
    }

    public static function daftarKonsentrasi(?string $idJurusan): array
    {
        if (!$idJurusan) {
            return [];
        }

        return self::petaKeahlian()[$idJurusan]['konsentrasi'] ?? [];
    }

    /** Konsentrasi pertama jurusan. Dipakai untuk prefix id kelas otomatis. */
    public static function konsentrasiUtama(?string $idJurusan): ?string
    {
        return self::daftarKonsentrasi($idJurusan)[0] ?? null;
    }

    /** [id_jurusan => program], untuk JavaScript di halaman kelas. */
    public static function petaProgram(): array
    {
        return array_filter(array_map(fn ($p) => $p['program'], self::petaKeahlian()));
    }

    /** [id_jurusan => [konsentrasi, ...]], untuk JavaScript di halaman kelas. */
    public static function petaKonsentrasi(): array
    {
        return array_map(fn ($p) => $p['konsentrasi'], self::petaKeahlian());
    }

    /** [id_jurusan => konsentrasi pertama], untuk JavaScript di halaman kelas. */
    public static function petaKonsentrasiUtama(): array
    {
        return array_filter(array_map(fn ($p) => $p['konsentrasi'][0] ?? null, self::petaKeahlian()));
    }

    /**
     * Pasangan Program | Konsentrasi untuk satu jurusan.
     * Jika jurusan punya 2 konsentrasi, hasilnya 2 pasangan.
     *   MP  => [['MPLB', 'MP'], ['MPLB', 'MLOG']]
     *   RPL => [['PPLG', 'RPL']]
     *
     * @return array<int, array{0: ?string, 1: ?string}>
     */
    public static function pasanganKeahlian(?string $idJurusan): array
    {
        $program     = self::programKeahlian($idJurusan);
        $konsentrasi = self::daftarKonsentrasi($idJurusan);

        if (!$konsentrasi) {
            return $program ? [[$program, null]] : [];
        }

        return array_map(fn ($k) => [$program, $k], $konsentrasi);
    }

    /** "11 RPL 1" => "11" */
    public static function tingkatDariNama(?string $namaKelas): ?string
    {
        if (preg_match('/^(12|11|10)\s/', trim((string) $namaKelas), $m)) {
            return $m[1];
        }

        return null;
    }

    /** "11-RPL-1" => "11" */
    public static function tingkatDariId(?string $idKelas): ?string
    {
        if (preg_match('/^(12|11|10)-/', trim((string) $idKelas), $m)) {
            return $m[1];
        }

        return null;
    }

    /** "11 MLOG 1" => "MLOG" */
    public static function kodeDariNama(?string $namaKelas): ?string
    {
        if (preg_match('/^(?:12|11|10)\s+(\S+)\s+\d+$/', strtoupper(trim((string) $namaKelas)), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Penulisan lama dengan angka romawi di awal ("X RPL 1", "XI-RPL-1", "XIIRPL1")
     * diubah ke angka ("10 RPL 1", "11-RPL-1", "12RPL1"). Dipakai saat import Excel
     * supaya file lama tetap terbaca.
     */
    public static function romawiKeAngka($teks): string
    {
        $peta = ['X' => '10', 'XI' => '11', 'XII' => '12'];

        return (string) preg_replace_callback(
            '/^(\s*)(XII|XI|X)(?=[\s\-]|[A-Z])/i',
            fn ($m) => $m[1] . $peta[strtoupper($m[2])],
            (string) $teks
        );
    }

    public static function labelKeahlian(?string $tingkat): string
    {
        return $tingkat === '10' ? 'Program Keahlian' : 'Konsentrasi Keahlian';
    }

    /**
     * Kode keahlian untuk jurusan + tingkat.
     * - Kelas 10: kode program (mis. MPLB).
     * - Kelas 11/12: kode konsentrasi. Jika nama kelas diberikan dan cocok
     *   (mis. "11 MLOG 1") dikembalikan kode itu; jika tidak dan jurusan punya
     *   banyak konsentrasi, dikembalikan gabungan "MP / MLOG".
     */
    public static function kodeKeahlian(?string $idJurusan, ?string $tingkat, ?string $namaKelas = null): ?string
    {
        if (!$idJurusan) {
            return null;
        }

        if ($tingkat === '10') {
            return self::programKeahlian($idJurusan);
        }

        $daftar = self::daftarKonsentrasi($idJurusan);

        if (!$daftar) {
            return null;
        }

        $kodeNama = self::kodeDariNama($namaKelas);

        if ($kodeNama && in_array($kodeNama, $daftar, true)) {
            return $kodeNama;
        }

        return count($daftar) > 1 ? implode(' / ', $daftar) : $daftar[0];
    }

    /**
     * Konsentrasi untuk dipakai sebagai alias import.
     * $kodeDiId = kode yang ada di id_kelas (mis. MLOG dari "11-MLOG-1").
     */
    public static function kompetensiKelas(?string $idJurusan, ?string $kodeDiId): ?string
    {
        $daftar = self::daftarKonsentrasi($idJurusan);

        if (!$daftar) {
            return null;
        }

        $kodeDiId = strtoupper((string) $kodeDiId);

        return in_array($kodeDiId, $daftar, true) ? $kodeDiId : $daftar[0];
    }

    /**
     * Ringkasan "Program | Konsentrasi" satu jurusan.
     *   MP  => "MPLB | MP, MPLB | MLOG"
     *   RPL => "PPLG | RPL"
     */
    public static function ringkasanKode(?string $idJurusan): string
    {
        $pasangan = array_map(
            fn ($p) => ($p[0] ?? '-') . ' | ' . ($p[1] ?? '-'),
            self::pasanganKeahlian($idJurusan)
        );

        return implode(', ', $pasangan);
    }

    public static function normalisasiNama(string $nama): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $nama)));
    }

    public static function validasiNama(string $namaKelas, ?string $idJurusan): ?string
    {
        $nama = self::normalisasiNama($namaKelas);

        if (!preg_match('/^(12|11|10) (\S+) (\d+)$/', $nama, $m)) {
            return 'Format nama kelas harus "Tingkat Kode Nomor", contoh: 10 PPLG 1 atau 11 RPL 2.';
        }

        $tingkat = $m[1];
        $kode    = $m[2];

        $benar = $tingkat === '10'
            ? array_filter([self::programKeahlian($idJurusan)])
            : self::daftarKonsentrasi($idJurusan);

        if (!$benar) {
            return "Jurusan {$idJurusan} tidak tersedia untuk kelas {$tingkat}.";
        }

        if (!in_array($kode, $benar, true)) {
            return sprintf(
                'Kelas %s memakai %s %s, bukan %s.',
                $tingkat,
                strtolower(self::labelKeahlian($tingkat)),
                implode(' atau ', $benar),
                $kode
            );
        }

        return null;
    }

    public function getTingkatAttribute(): ?string
    {
        return self::tingkatDariNama($this->nama_kelas);
    }

    public function getLabelKeahlianAttribute(): string
    {
        return self::labelKeahlian($this->tingkat);
    }

    public function getKodeKeahlianAttribute(): string
    {
        return self::kodeKeahlian($this->id_jurusan, $this->tingkat, $this->nama_kelas)
            ?? $this->id_jurusan
            ?? '-';
    }

    public function siswa()
    {
        return $this->hasMany(siswa::class, 'id_kelas', 'id_kelas');
    }

    public function walikelas()
    {
        return $this->hasOne(walikelas::class, 'id_kelas', 'id_kelas');
    }

    public function jurusan()
    {
        return $this->belongsTo(jurusan::class, 'id_jurusan', 'id_jurusan');
    }

    public function guruBk()
    {
        return $this->belongsToMany(
            guru_bk::class,
            'guru_bk_kelas',
            'kelas_id',
            'guru_bk_id',
            'id_kelas',
            'nip_bk'
        );
    }
}