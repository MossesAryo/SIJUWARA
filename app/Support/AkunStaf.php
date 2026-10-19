<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Helper bersama untuk menambah akun staf (walikelas, guru BK, ketua program).
 * Memastikan NIP bersih/unik dan username + email tidak bentrok.
 */
class AkunStaf
{
    public const PASSWORD_DEFAULT = 'password123';

    /** Buang spasi, tanda hubung, titik, dan apostrof dari NIP. */
    public static function bersihkanNip($nip): string
    {
        return preg_replace('/[\s\-\.\'`]/', '', trim((string) $nip));
    }

    /** Rapikan spasi ganda pada nama. */
    public static function rapikanNama($nama): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $nama));
    }

    /**
     * NIP dipakai untuk login dan dicari berurutan di 4 tabel,
     * jadi harus unik lintas tabel.
     */
    public static function nipTerpakai(string $nip): bool
    {
        return DB::table('wakasek')->where('nip_wakasek', $nip)->exists()
            || DB::table('guru_bk')->where('nip_bk', $nip)->exists()
            || DB::table('ketua_program')->where('nip_kaprog', $nip)->exists()
            || DB::table('walikelas')->where('nip_walikelas', $nip)->exists();
    }

    /** Username = nama. Kalau sudah dipakai, ditambah NIP. */
    public static function buatUsername(string $nama, string $nip): string
    {
        if (!User::where('username', $nama)->exists()) {
            return $nama;
        }

        return Str::limit($nama, 230, '') . ' ' . $nip;
    }

    /** Email dari slug nama. Kalau sudah dipakai, pakai NIP. */
    public static function buatEmail(string $nama, string $nip): string
    {
        $slug = Str::limit(Str::slug($nama), 60, '');
        $email = ($slug !== '' ? $slug : $nip) . '@gmail.com';

        if (User::where('email', $email)->exists()) {
            $email = $nip . '@gmail.com';
        }

        if (User::where('email', $email)->exists()) {
            $email = $nip . '-' . Str::lower(Str::random(4)) . '@gmail.com';
        }

        return $email;
    }

    /** Buat akun login. Panggil di dalam DB::transaction. */
    public static function buatUser(string $nama, string $nip, int $role): User
    {
        return User::create([
            'username' => self::buatUsername($nama, $nip),
            'email'    => self::buatEmail($nama, $nip),
            'password' => Hash::make(self::PASSWORD_DEFAULT),
            'role'     => $role,
        ]);
    }

    /** Catat error ke log. Detail hanya tampil saat APP_DEBUG=true. */
    public static function pesanGagal(\Throwable $e, string $aksi): string
    {
        Log::error($aksi . ' gagal: ' . $e->getMessage());

        return config('app.debug')
            ? $aksi . ' gagal: ' . $e->getMessage()
            : $aksi . ' gagal. Silakan coba lagi atau hubungi admin.';
    }
}