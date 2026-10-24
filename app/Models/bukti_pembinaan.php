<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class bukti_pembinaan extends Model
{
    protected $table = 'bukti_pembinaan';
    protected $primaryKey = 'id_bukti_pembinaan';
    protected $fillable = [
        'intervensi_id',
        'file',
        'nama_file',
    ];

    public function intervensi()
    {
        return $this->belongsTo(intervensi::class, 'intervensi_id', 'id_intervensi');
    }
}