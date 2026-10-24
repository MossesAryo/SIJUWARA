<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot guru BK dan kelas.
 * Menghubungkan guru BK dengan rombel yang dibimbing.
 */
class GuruBkKelas extends Model
{
    protected $table = 'guru_bk_kelas';
    protected $fillable = ['guru_bk_id', 'kelas_id'];

    public function guruBk()
    {
        return $this->belongsTo(guru_bk::class, 'guru_bk_id');
    }
    public function kelas()
    {
        return $this->belongsTo(kelas::class, 'kelas_id');
    }
}