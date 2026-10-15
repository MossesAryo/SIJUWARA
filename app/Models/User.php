<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model pengguna sistem.
 * Autentikasi, peran, dan relasi ke entitas guru BK/wali/ketua/wakasek.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Konstanta role pengguna.
     */
    public const ROLE_WAKASEK = 1;
    public const ROLE_GURU_BK = 2;
    public const ROLE_WALIKELAS = 3;
    public const ROLE_KETUA_PROGRAM = 4;
    public const ROLE_SUPER_ADMIN = 5;

    /**
     * Memeriksa apakah pengguna adalah Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi ke data Guru BK.
     */
    public function gurubk()
    {
        return $this->hasOne(guru_bk::class, 'username', 'username');
    }

    /**
     * Relasi ke data Wali Kelas.
     */
    public function walikelas()
    {
        return $this->hasOne(walikelas::class, 'username', 'username');
    }

    /**
     * Relasi ke data Wakasek.
     */
    public function wakasek()
    {
        return $this->hasOne(wakasek::class, 'username', 'username');
    }

    /**
     * Relasi ke data Ketua Program.
     */
    public function ketua_program()
    {
        return $this->hasOne(ketua_program::class, 'username', 'username');
    }

    /**
     * Memeriksa apakah pengguna memiliki salah satu role tertentu.
     *
     * @param int|array $roles
     */
    public function hasRole($roles): bool
    {
        return in_array((int) $this->role, array_map('intval', (array) $roles), true);
    }
}
