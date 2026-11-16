<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model guru (master data).
 * Berbeda dengan wali kelas: guru tidak punya akun login dan tidak terikat kelas.
 * Ketua program dipilih dari data ini.
 */
class guru extends Model
{
    protected $table = 'guru';
    protected $primaryKey = 'nip_guru';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['nip_guru', 'nama_guru'];

    /** Dipakai sebagai ketua program (kalau ada). */
    public function kaprog()
    {
        return $this->hasOne(ketua_program::class, 'nip_kaprog', 'nip_guru');
    }
}