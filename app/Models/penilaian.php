<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model penilaian siswa.
 * Relasi nilai ke wakasek, wali, BK, aspek penilaian, dan siswa.
 */
class penilaian extends Model
{
    protected $table = 'penilaian';
    protected $primaryKey = 'id_penilaian';
    public $timestamps = false;

    protected $fillable = [
        'id_penilaian',
        'nip_wakasek',
        'nip_walikelas',
        'nip_bk',
        'id_aspekpenilaian',
        'nis',
        'created_at',
        
    ];

    public function wakasek()
    {
        return $this->belongsTo(wakasek::class, 'nip_wakasek', 'nip_wakasek');
    }
    public function walikelas()
    {
        return $this->belongsTo(walikelas::class, 'nip_walikelas');
    }

    public function aspek_penilaian()
    {
        return $this->belongsTo(aspek_penilaian::class, 'id_aspekpenilaian', 'id_aspekpenilaian');
    }


    public function siswa()
    {
        return $this->belongsTo(siswa::class, 'nis', 'nis');
    }

    public function guruBk()
    {
        return $this->belongsTo(guru_bk::class, 'nip_bk', 'nip_bk');
    }

     public function aspekApresiasi()
    {
        return $this->belongsTo(aspek_penilaian::class, 'id_aspek_penilaian', 'id_aspek_penilaian')
                    ->where('jenis_poin', 'Apresiasi');
    }
    public function aspekPelanggaran()
    {
        return $this->belongsTo(aspek_penilaian::class, 'id_aspek_penilaian', 'id_aspek_penilaian')
                    ->where('jenis_poin', 'Pelanggaran');
    }
}