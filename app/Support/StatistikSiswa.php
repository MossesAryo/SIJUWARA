<?php

namespace App\Support;

use App\Models\siswa_penghargaan;
use App\Models\siswa_sp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rumus tunggal statistik siswa berprestasi / berpelanggaran.
 * Dipakai dashboard Super Admin dan Wakasek agar angkanya selalu sinkron.
 *
 * - berprestasi() / berpelanggaran(): jumlah SISWA unik yang punya jejak.
 * - kejadian(): jumlah KEJADIAN (baris log aktivitas), sama dengan angka
 *   pada chip filter di halaman Log Aktivitas.
 */
class StatistikSiswa
{
    /**
     * Jumlah siswa unik yang pernah berprestasi.
     *
     * @param  Builder  $siswa  Query siswa (boleh sudah difilter kelas/jurusan).
     */
    public static function berprestasi(Builder $siswa): int
    {
        return (clone $siswa)->where(function ($q) {
            $q->where('poin_apresiasi', '>', 0)
                ->orWhereIn('nis', siswa_penghargaan::select('nis'))
                ->orWhereIn('nis', self::nisDariPenilaian('Apresiasi'))
                ->orWhereIn('nis', self::nisDariLog('Apresiasi'));
        })->count();
    }

    /**
     * Jumlah siswa unik yang pernah melanggar.
     */
    public static function berpelanggaran(Builder $siswa): int
    {
        return (clone $siswa)->where(function ($q) {
            $q->where('poin_pelanggaran', '>', 0)
                ->orWhereIn('nis', siswa_sp::select('nis'))
                ->orWhereIn('nis', self::nisDariPenilaian('Pelanggaran'))
                ->orWhereIn('nis', self::nisDariLog('Pelanggaran'));
        })->count();
    }

    /**
     * Jumlah kejadian (baris log aktivitas) per kategori.
     * Jika $siswa diberikan, hanya log milik siswa dalam cakupan query itu
     * yang dihitung (untuk Kaprog, Wali Kelas, Guru BK).
     * Jika null, semua log kategori tersebut dihitung (Super Admin / Wakasek),
     * sehingga angkanya sama dengan chip di Log Aktivitas.
     *
     * @param  string        $kategori  'Apresiasi' atau 'Pelanggaran'
     * @param  Builder|null  $siswa
     */
    public static function kejadian(string $kategori, ?Builder $siswa = null): int
    {
        return DB::table('activity_logs')
            ->where('kategori', $kategori)
            ->when($siswa, function ($q) use ($siswa) {
                $q->whereIn('nis', (clone $siswa)->select('nis'));
            })
            ->count();
    }

    private static function nisDariPenilaian(string $jenisPoin)
    {
        return DB::table('penilaian as p')
            ->join('aspek_penilaian as a', 'a.id_aspekpenilaian', '=', 'p.id_aspekpenilaian')
            ->where('a.jenis_poin', $jenisPoin)
            ->select('p.nis');
    }

    private static function nisDariLog(string $kategori)
    {
        return DB::table('activity_logs')
            ->where('kategori', $kategori)
            ->whereNotNull('nis')
            ->select('nis');
    }
}