<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class jurusan extends Model
{
    protected $table = 'jurusan';
    protected $primaryKey = 'id_jurusan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_jurusan',
        'nama_jurusan',
        'kode_program',
        'konsentrasi',
    ];

    /** Program Keahlian (kelas 10), mis. MPLB. */
    public function getKodeProgramAttribute($value): ?string
    {
        return $value ?: kelas::programKeahlian($this->id_jurusan);
    }

    /** Konsentrasi Keahlian (kelas 11 & 12) sebagai array, mis. ['MP', 'MLOG']. */
    public function getDaftarKonsentrasiAttribute(): array
    {
        return kelas::daftarKonsentrasi($this->id_jurusan);
    }

    /** Pasangan [program, konsentrasi]; 2 konsentrasi = 2 pasangan. */
    public function getPasanganKeahlianAttribute(): array
    {
        return kelas::pasanganKeahlian($this->id_jurusan);
    }

    public function getKodeKompetensiAttribute(): ?string
    {
        $daftar = $this->daftar_konsentrasi;

        return $daftar ? implode(', ', $daftar) : null;
    }

    public function getRingkasanKodeAttribute(): string
    {
        return kelas::ringkasanKode($this->id_jurusan);
    }

    public function getLabelDropdownAttribute(): string
    {
        $ringkas = $this->ringkasan_kode;

        return $ringkas !== ''
            ? "{$this->nama_jurusan} ({$ringkas})"
            : $this->nama_jurusan;
    }

    public function siswa()
    {
        return $this->hasMany(siswa::class, 'id_jurusan', 'id_jurusan');
    }

    public function ketuaProgram()
    {
        return $this->hasOne(ketua_program::class, 'id_jurusan', 'id_jurusan');
    }

    public function kelas()
    {
        return $this->hasMany(kelas::class, 'id_jurusan', 'id_jurusan');
    }
}