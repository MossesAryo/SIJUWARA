<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model wakasek.
 * Data wakil kepala sekolah dan relasi penilaian siswa.
 */
class wakasek extends Model
{
    protected $table = 'wakasek';
    protected $primaryKey = 'nip_wakasek';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'nip_wakasek',
        'username',
        'nama_wakasek',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}