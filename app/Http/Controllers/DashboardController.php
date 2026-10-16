<?php

namespace App\Http\Controllers;

use App\Models\kelas;
use App\Models\siswa;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\walikelas;
use App\Models\guru_bk;
use App\Models\ketua_program;
use App\Support\StatistikSiswa;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard ringkasan performa siswa.
 * Menampilkan statistik dan aktivitas sesuai filter role pengguna.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Pastikan pengguna sudah login.
        if (!$user) {
            return redirect()->route('login');
        }

        $role = $user->role;

        // Arahkan Super Admin ke dashboard khusus.
        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        $kelasFilter = [];
        $jurusanFilter = null;

        // Filter untuk Ketua Program.
        if ($role == User::ROLE_KETUA_PROGRAM) {
            $ketua = ketua_program::where(
                'username',
                $user->username
            )->first();

            if (!$ketua) {
                abort(403, 'Data Ketua Program tidak ditemukan.');
            }

            $jurusanFilter = $ketua->id_jurusan;
        }

        // Filter untuk Guru BK.
        if ($role == User::ROLE_GURU_BK) {
            $guruBK = guru_bk::where(
                'username',
                $user->username
            )->first();

            if (!$guruBK) {
                abort(403, 'Data Guru BK tidak ditemukan.');
            }

            $kelasFilter = $guruBK->kelas
                ->pluck('id_kelas')
                ->toArray();
        }

        // Filter untuk Wali Kelas.
        if ($role == User::ROLE_WALIKELAS) {
            $wali = walikelas::where(
                'username',
                $user->username
            )->first();

            if (!$wali) {
                abort(403, 'Data Wali Kelas tidak ditemukan.');
            }

            $kelasFilter = [$wali->id_kelas];
        }

        // Query siswa dengan filter sesuai role.
        $siswaQuery = siswa::query();

        // Filter berdasarkan jurusan.
        if ($jurusanFilter) {
            $siswaQuery->where(
                'id_jurusan',
                $jurusanFilter
            );
        }

        // Filter berdasarkan kelas.
        if (!empty($kelasFilter)) {
            $siswaQuery->whereIn(
                'id_kelas',
                $kelasFilter
            );
        }

        // Statistik siswa.
        $totalSiswa = (clone $siswaQuery)->count();

        $rataSkor = (clone $siswaQuery)->avg('poin_total');

        // Apakah dashboard ini dibatasi cakupan (jurusan/kelas)?
        $terbatas = $jurusanFilter || !empty($kelasFilter);

        // Jumlah kejadian (sama dengan Log Aktivitas).
        // Wakasek (tanpa filter) = semua log; role lain = log siswa dalam cakupannya.
        $totalApresiasi   = StatistikSiswa::kejadian('Apresiasi', $terbatas ? $siswaQuery : null);
        $totalPelanggaran = StatistikSiswa::kejadian('Pelanggaran', $terbatas ? $siswaQuery : null);

        // Jumlah siswa unik (keterangan tambahan di kartu).
        $siswaBerprestasi    = StatistikSiswa::berprestasi($siswaQuery);
        $siswaBerpelanggaran = StatistikSiswa::berpelanggaran($siswaQuery);

        // Aktivitas terbaru sesuai cakupan role.
        $recentActivities = ActivityLog::with([
            'user',
            'siswa.kelas',
        ])
            ->when($jurusanFilter, function ($q) use ($jurusanFilter) {
                $q->whereHas('siswa', function ($sub) use ($jurusanFilter) {
                    $sub->where(
                        'id_jurusan',
                        $jurusanFilter
                    );
                });
            })
            ->when(!empty($kelasFilter), function ($q) use ($kelasFilter) {
                $q->whereHas('siswa', function ($sub) use ($kelasFilter) {
                    $sub->whereIn(
                        'id_kelas',
                        $kelasFilter
                    );
                });
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Data untuk view.
        $data = [
            'totalSiswa' => $totalSiswa,
            'totalApresiasi' => $totalApresiasi,
            'totalPelanggaran' => $totalPelanggaran,
            'siswaBerprestasi' => $siswaBerprestasi,
            'siswaBerpelanggaran' => $siswaBerpelanggaran,
            'rataSkor' => $rataSkor,
            'recentActivities' => $recentActivities,
        ];

        return view('wakasek.dashboard', $data);
    }
}