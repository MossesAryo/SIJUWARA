<?php

namespace App\Imports;

use App\Models\User;
use App\Models\ketua_program;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Ketua_Program_Import implements ToModel, WithHeadingRow
{

    public function headingRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {

        \Illuminate\Support\Facades\Log::info('Processing row: ' . json_encode($row));
        if (empty($row['username']) || is_null($row['username'])) {
            \Illuminate\Support\Facades\Log::warning('Null or empty username in row: ' . json_encode($row));
            return null;
        }

        // Baris contoh dari template dilewati.
        if ($this->isContoh($row['username'] ?? null) || $this->isContoh($row['nama_ketua_program'] ?? null)) {
            return null;
        }

        $user = User::Create(
            [
                'username' => $row['username'],
                'email' => $row['username'] . '@gmail.com',
                'password' => Hash::make('password123'),
                'role' => 4,
            ],
        );

        return new ketua_program([
            'nip_kaprog' => $row['nip'],
            'username' => $user->username,
            'nama_ketua_program' => $row['nama_ketua_program'],
            'id_jurusan' => $row['jurusan'],
        ]);
    }

    private function isContoh($teks): bool
    {
        return is_string($teks) && str_starts_with(mb_strtolower(trim($teks)), 'contoh');
    }
}