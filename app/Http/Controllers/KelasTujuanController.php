<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\KelasTujuan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class KelasTujuanController extends Controller
{
    /**
     * Buat semua kelas tujuan yang belum ada (kelas 12 untuk setiap kelas 11).
     * Kelas 11 untuk siswa kelas 10 tidak dibuat otomatis: ditentukan Ketua Program.
     * Hanya Wakasek dan Super Admin (diatur di routes).
     */
    public function store(): RedirectResponse
    {
        try {
            $jumlah = KelasTujuan::buat();
        } catch (Throwable $e) {
            Log::error('Gagal membuat kelas tujuan', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            return back()->with('error', 'Kelas tujuan gagal dibuat. Tidak ada data yang berubah, silakan coba lagi.');
        }

        if ($jumlah === 0) {
            return back()->with('success', 'Semua kelas tujuan sudah tersedia.');
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_MASTER,
            'Buat Kelas Tujuan',
            "{$jumlah} kelas tujuan dibuat otomatis dari halaman Tahun Ajaran."
        );

        return back()->with(
            'success',
            "{$jumlah} kelas tujuan berhasil dibuat. Sekarang tahun ajaran bisa diganti."
        );
    }
}