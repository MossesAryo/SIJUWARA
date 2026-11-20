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

    /** Kode jabatan yang dipegang guru ini (dari relasi), mis. ['gurubk', 'walikelas']. */
    public function getJabatanListAttribute(): array
    {
        return array_values(array_filter(
            array_keys(self::DAFTAR_JABATAN),
            fn ($relasi) => (bool) $this->$relasi
        ));
    }

    /** Nama jabatan guru ini ("Guru BK, Wali Kelas"), atau null kalau belum dipetakan. */
    public function getJabatanAttribute(): ?string
    {
        $label = array_map(fn ($kode) => self::DAFTAR_JABATAN[$kode], $this->jabatan_list);

        return $label ? implode(', ', $label) : null;
    }

    /** Kode jabatan pertama guru ini (kompatibilitas kode lama), atau null kalau belum dipetakan. */
    public function getJabatanKodeAttribute(): ?string
    {
        return $this->jabatan_list[0] ?? null;
    }

    /** Keterangan tambahan jabatan (kelas untuk wali kelas, jurusan untuk ketua program). */
    public function getJabatanDetailAttribute(): ?string
    {
        $detail = [];

        if ($this->walikelas) {
            $detail[] = optional($this->walikelas->kelas)->nama_kelas ?: 'Tanpa Kelas';
        }

        if ($this->kaprog && optional($this->kaprog->jurusan)->nama_jurusan) {
            $detail[] = $this->kaprog->jurusan->nama_jurusan;
        }

        return $detail ? implode(' · ', $detail) : null;
    }

    /** Sumber data tiap jabatan: kode => [tabel, kolom NIP]. */
    public const SUMBER_JABATAN = [
        'wakasek'   => ['wakasek', 'nip_wakasek'],
        'gurubk'    => ['guru_bk', 'nip_bk'],
        'kaprog'    => ['ketua_program', 'nip_kaprog'],
        'walikelas' => ['walikelas', 'nip_walikelas'],
    ];

    /** Kode semua jabatan yang dipegang NIP ini, dibaca dari tabel jabatan. */
    public static function kodeJabatan(string $nip): array
    {
        $ada = [];

        foreach (self::SUMBER_JABATAN as $kode => [$tabel, $kolom]) {
            if (DB::table($tabel)->where($kolom, $nip)->exists()) {
                $ada[] = $kode;
            }
        }

        return $ada;
    }

    /**
     * Peta [nip => label jabatan] untuk semua guru yang sudah dipetakan.
     * Guru dengan lebih dari satu jabatan: labelnya digabung ("Guru BK, Wali Kelas"),
     * jadi pemanggil lama yang memperlakukannya sebagai string tetap jalan.
     */
    public static function petaJabatan(): array
    {
        $peta = [];

        foreach (self::SUMBER_JABATAN as $kode => [$tabel, $kolom]) {
            foreach (DB::table($tabel)->pluck($kolom) as $nip) {
                $peta[(string) $nip][] = self::DAFTAR_JABATAN[$kode];
            }
        }

        return array_map(fn ($daftar) => implode(', ', $daftar), $peta);
    }
}
