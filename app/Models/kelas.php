<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class kelas extends Model
{
    protected $table = 'kelas';
    protected $primaryKey = 'id_kelas';
    protected $fillable = ['id_kelas', 'nama_kelas', 'id_jurusan'];
    public $incrementing = false;

    public const PROGRAM_KEAHLIAN = [
        'AK'  => 'AKL',
        'MP'  => 'MPLB',
        'PM'  => 'PM',
        'RPL' => 'PPLG',
        'DKV' => 'DKV',
        'TKJ' => 'TJKT',
    ];

    public const KOMPETENSI_KEAHLIAN = [
        'AK'   => 'AK',
        'MP'   => 'MP',
        'MLOG' => 'MLOG',
        'RPL'  => 'RPL',
        'TKJ'  => 'TKJ',
        'PM'   => 'BR',
        'DKV'  => 'DKV',
    ];

    public static function tingkatDariNama(?string $namaKelas): ?string
    {
        if (preg_match('/^(XII|XI|X)\s/i', trim((string) $namaKelas), $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    public static function labelKeahlian(?string $tingkat): string
    {
        return $tingkat === 'X' ? 'Program Keahlian' : 'Kompetensi Keahlian';
    }

    public static function kodeKeahlian(?string $idJurusan, ?string $tingkat): ?string
    {
        if (!$idJurusan) {
            return null;
        }

        $map = $tingkat === 'X' ? self::PROGRAM_KEAHLIAN : self::KOMPETENSI_KEAHLIAN;

        return $map[$idJurusan] ?? null;
    }

    public static function ringkasanKode(?string $idJurusan): string
    {
        $x  = self::PROGRAM_KEAHLIAN[$idJurusan] ?? null;
        $xi = self::KOMPETENSI_KEAHLIAN[$idJurusan] ?? null;

        if ($x && $xi && $x === $xi) {
            return $x;
        }

        $bagian = [];
        if ($x) {
            $bagian[] = "X: {$x}";
        }
        if ($xi) {
            $bagian[] = "XI/XII: {$xi}";
        }

        return implode(' · ', $bagian);
    }

    public static function normalisasiNama(string $nama): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $nama)));
    }

    public static function validasiNama(string $namaKelas, ?string $idJurusan): ?string
    {
        $nama = self::normalisasiNama($namaKelas);

        if (!preg_match('/^(XII|XI|X) (\S+) (\d+)$/', $nama, $m)) {
            return 'Format nama kelas harus "Tingkat Kode Nomor", contoh: X PPLG 1 atau XI RPL 2.';
        }

        $tingkat = $m[1];
        $kode    = $m[2];
        $benar   = self::kodeKeahlian($idJurusan, $tingkat);

        if ($benar === null) {
            return "Jurusan {$idJurusan} tidak tersedia untuk kelas {$tingkat}.";
        }

        if ($kode !== $benar) {
            return sprintf(
                'Kelas %s memakai %s %s, bukan %s.',
                $tingkat,
                strtolower(self::labelKeahlian($tingkat)),
                $benar,
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
        return self::kodeKeahlian($this->id_jurusan, $this->tingkat)
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