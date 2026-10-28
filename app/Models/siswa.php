<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model siswa.
 * Data identitas siswa, jurusan, kelas, dan perhitungan poin.
 */
class siswa extends Model
{
    use SoftDeletes;

    protected $table = 'siswa';
    protected $primaryKey = 'nis';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'nis',
        'id_kelas',
        'id_jurusan',   
        'status',
        'id_aspekpenilaian',
        'nama_siswa',
        'poin_apresiasi',
        'poin_pelanggaran',
        'poin_total'
    ];

    public function kelas()
    {
        return $this->belongsTo(kelas::class, 'id_kelas', 'id_kelas');
    }

    public function aspekPenilaian()
    {
        return $this->belongsTo(aspek_penilaian::class, 'id_aspekpenilaian', 'id_aspekpenilaian');
    }
    public function activitylog()
    {
        return $this->hasMany(ActivityLog::class, 'nis', 'nis');
    }
    public function siswaPenghargaan()
    {
        return $this->hasMany(siswa_penghargaan::class, 'nis', 'nis');
    }
    public function siswaSp()
    {
        return $this->hasMany(siswa_sp::class, 'nis', 'nis');
    }
     public function jurusan()
    {
        return $this->belongsTo(jurusan::class, 'id_jurusan', 'id_jurusan');
    }
    public function classHistories()
    {
        return $this->hasMany(StudentClassHistory::class, 'nis', 'nis')
            ->orderBy('tanggal_mulai', 'desc')
            ->orderBy('id', 'desc');
    }
}