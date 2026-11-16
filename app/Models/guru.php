<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Model guru_staf (master data guru dan staf TU).
 * Berbeda dengan wali kelas: guru tidak punya akun login dan tidak terikat kelas.
 *
 * Alur: data guru/staf di-import lebih dulu, lalu setiap orang dipetakan ke jabatannya
 * (Wakasek, Guru BK, Ketua Program, Wali Kelas). NIP dan nama pada form jabatan
 * dipilih dari data ini lewat dropdown, bukan diketik ulang.
 */
class Guru extends Model
{
    protected $table = 'guru';
    protected $primaryKey = 'nip_guru';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['nip_guru', 'nama_guru'];

    /** Jabatan yang bisa dipetakan: relasi => label. NIP unik lintas jabatan (dipakai untuk login). */
    public const DAFTAR_JABATAN = [
        'wakasek'   => 'Wakasek',
        'gurubk'    => 'Guru BK',
        'kaprog'    => 'Ketua Program',
        'walikelas' => 'Wali Kelas',
    ];

    public function wakasek()
    {
        return $this->hasOne(wakasek::class, 'nip_wakasek', 'nip_guru');
    }

    public function gurubk()
    {
        return $this->hasOne(guru_bk::class, 'nip_bk', 'nip_guru');
    }

    /** Dipakai sebagai ketua program (kalau ada). */
    public function kaprog()
    {
        return $this->hasOne(ketua_program::class, 'nip_kaprog', 'nip_guru');
    }

    public function walikelas()
    {
        return $this->hasOne(walikelas::class, 'nip_walikelas', 'nip_guru');
    }

    /** Nama jabatan guru ini, atau null kalau belum dipetakan. */
    public function getJabatanAttribute(): ?string
    {
        foreach (self::DAFTAR_JABATAN as $relasi => $label) {
            if ($this->$relasi) {
                return $label;
            }
        }

        return null;
    }

    /** Kode jabatan guru ini (wakasek, gurubk, kaprog, walikelas), atau null kalau belum dipetakan. */
    public function getJabatanKodeAttribute(): ?string
    {
        foreach (self::DAFTAR_JABATAN as $relasi => $label) {
            if ($this->$relasi) {
                return $relasi;
            }
        }

        return null;
    }

    /** Keterangan tambahan jabatan (kelas untuk wali kelas, jurusan untuk ketua program). */
    public function getJabatanDetailAttribute(): ?string
    {
        if ($this->walikelas) {
            return optional($this->walikelas->kelas)->nama_kelas ?: 'Tanpa Kelas';
        }

        if ($this->kaprog) {
            return optional($this->kaprog->jurusan)->nama_jurusan;
        }

        return null;
    }

    /**
     * Peta [nip => label jabatan] untuk semua guru yang sudah dipetakan.
     * Dipakai dropdown supaya guru yang sudah punya jabatan ditandai dan tidak bisa dipilih ganda.
     */
    public static function petaJabatan(): array
    {
        $sumber = [
            'Wakasek'       => ['wakasek', 'nip_wakasek'],
            'Guru BK'       => ['guru_bk', 'nip_bk'],
            'Ketua Program' => ['ketua_program', 'nip_kaprog'],
            'Wali Kelas'    => ['walikelas', 'nip_walikelas'],
        ];

        $peta = [];

        foreach ($sumber as $label => [$tabel, $kolom]) {
            foreach (DB::table($tabel)->pluck($kolom) as $nip) {
                $peta[(string) $nip] = $label;
            }
        }

        return $peta;
    }
}