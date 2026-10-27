<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rencana perpindahan siswa (10 -> 11, 11 -> 12, 12 -> ALUMNI) yang dibuat Ketua Program.
 * Satu siswa hanya punya satu rencana (nis unik).
 */
class RencanaPindahKelas extends Model
{
    protected $table = 'rencana_pindah_kelas';

    protected $fillable = [
        'nis',
        'id_kelas_tujuan',
        'dibuat_oleh',
    ];

    public function siswa()
    {
        return $this->belongsTo(siswa::class, 'nis', 'nis');
    }

    public function kelasTujuan()
    {
        return $this->belongsTo(kelas::class, 'id_kelas_tujuan', 'id_kelas');
    }
}