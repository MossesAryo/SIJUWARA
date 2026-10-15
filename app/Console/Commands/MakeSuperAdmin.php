<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class MakeSuperAdmin extends Command
{
    protected $signature = 'make:superadmin {username? : Username Super Admin} {--email= : Email Super Admin}';

    protected $description = 'Membuat akun Super Admin (role 5); password diminta secara interaktif';

    public function handle(): int
    {
        $username = $this->argument('username') ?: $this->ask('Username');
        $email    = $this->option('email') ?: $this->ask('Email');
        $password = $this->secret('Password (minimal 8 karakter)');
        $confirm  = $this->secret('Konfirmasi password');

        $validator = Validator::make(
            [
                'username'              => $username,
                'email'                 => $email,
                'password'              => $password,
                'password_confirmation' => $confirm,
            ],
            [
                // Tidak boleh angka saja: login memeriksa NIP lebih dulu, username angka bisa bentrok.
                'username' => ['required', 'string', 'max:255', 'alpha_dash', 'not_regex:/^\d+$/', 'unique:users,username'],
                'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Password di-hash otomatis oleh cast 'hashed' pada model User.
        User::create([
            'username' => $username,
            'email'    => $email,
            'role'     => User::ROLE_SUPER_ADMIN,
            'password' => $password,
        ]);

        $this->info("Akun Super Admin '{$username}' berhasil dibuat.");
        $this->line('Login di halaman biasa: isi kolom NIP dengan username tersebut.');

        return self::SUCCESS;
    }
}