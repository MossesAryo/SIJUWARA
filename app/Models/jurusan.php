<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model jurusan/program keahlian.
 * Relasi ke kelas, ketua program, dan siswa.
 *
 * Kelas X memakai Program Keahlian, kelas XI/XII memakai Kompetensi Keahlian.
 * Mapping kodenya ada di konstanta model kelas.
 */
class jurusan extends Model
{
    protected $table = 'jurusan';
    protected $primaryKey = 'id_jurusan';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id_jurusan', 'nama_jurusan'];

    // Kode kelas X, contoh: RPL => PPLG
    public function getKodeProgramAttribute(): ?string
    {
        return kelas::PROGRAM_KEAHLIAN[$this->id_jurusan] ?? null;
    }

    // Kode kelas XI/XII, contoh: PM => BR
    public function getKodeKompetensiAttribute(): ?string
    {
        return kelas::KOMPETENSI_KEAHLIAN[$this->id_jurusan] ?? null;
    }

    // Contoh: "X: PPLG · XI/XII: RPL"
    public function getRingkasanKodeAttribute(): string
    {
        return kelas::ringkasanKode($this->id_jurusan);
    }

    // Contoh: "Rekayasa Perangkat Lunak (X: PPLG · XI/XII: RPL)"
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
        return $this->hasOne(ketuaProgram::class, 'id_jurusan', 'id_jurusan');
    }

    public function kelas()
    {
        return $this->hasMany(kelas::class, 'id_jurusan', 'id_jurusan');
    }
}