<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\siswa;
use App\Models\User;
use App\Support\StatistikSiswa;

/**
 * Dashboard Super Admin: ringkasan jumlah data dan akun.
 * Angka apresiasi/pelanggaran = jumlah kejadian (sama dengan Log Aktivitas),
 * dengan keterangan jumlah siswa unik di bawahnya.
 */
class DashboardController extends Controller
{
    public function index()
    {
        // Satu query untuk semua jumlah akun per role.
        $perRole = User::selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        // Siswa terdaftar (yang diarsipkan/soft delete otomatis dikecualikan).
        $totalSiswa = siswa::count();

        // Jumlah kejadian (sama dengan chip Log Aktivitas).
        $kejadianApresiasi   = StatistikSiswa::kejadian('Apresiasi');
        $kejadianPelanggaran = StatistikSiswa::kejadian('Pelanggaran');

        // Jumlah siswa unik.
        $siswaBerprestasi    = StatistikSiswa::berprestasi(siswa::query());
        $siswaBerpelanggaran = StatistikSiswa::berpelanggaran(siswa::query());

        $ringkasan = [
            [
                'label' => 'Total Siswa',
                'value' => $totalSiswa,
                'icon'  => 'bi-people',
                'color' => 'blue',
                'note'  => 'Siswa terdaftar',
                'sub'   => null,
            ],
            [
                'label' => 'Total Apresiasi',
                'value' => $kejadianApresiasi,
                'icon'  => 'bi-award',
                'color' => 'green',
                'note'  => 'Apresiasi / penghargaan tercatat',
                'sub'   => 'Diterima ' . number_format($siswaBerprestasi) . ' siswa',
            ],
            [
                'label' => 'Total Pelanggaran',
                'value' => $kejadianPelanggaran,
                'icon'  => 'bi-exclamation-triangle',
                'color' => 'red',
                'note'  => 'Pelanggaran / surat peringatan tercatat',
                'sub'   => 'Dilakukan ' . number_format($siswaBerpelanggaran) . ' siswa',
            ],
        ];

        $akun = [
            ['label' => 'Total Akun',    'value' => $perRole->sum(),                         'icon' => 'bi-person-lines-fill', 'color' => 'slate',  'note' => 'Seluruh akun pengguna'],
            ['label' => 'Wakasek',       'value' => $perRole[User::ROLE_WAKASEK] ?? 0,       'icon' => 'bi-person-badge',      'color' => 'blue',   'note' => 'Akun Wakasek'],
            ['label' => 'Guru BK',       'value' => $perRole[User::ROLE_GURU_BK] ?? 0,       'icon' => 'bi-chat-heart',        'color' => 'purple', 'note' => 'Akun Guru BK'],
            ['label' => 'Wali Kelas',    'value' => $perRole[User::ROLE_WALIKELAS] ?? 0,     'icon' => 'bi-person-workspace',  'color' => 'amber',  'note' => 'Akun Wali Kelas'],
            ['label' => 'Ketua Program', 'value' => $perRole[User::ROLE_KETUA_PROGRAM] ?? 0, 'icon' => 'bi-diagram-3',         'color' => 'teal',   'note' => 'Akun Ketua Program'],
        ];

        return view('superadmin.dashboard', compact('ringkasan', 'akun'));
    }
}