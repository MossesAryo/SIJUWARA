<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentClassHistory extends Model
{
    public const JENIS_PINDAH        = 'pindah';        // pindah kelas manual
    public const JENIS_NAIK          = 'naik';
    public const JENIS_TURUN         = 'turun';
    public const JENIS_LULUS         = 'lulus';
    public const JENIS_TINGGAL_KELAS = 'tinggal_kelas'; // tidak naik kelas / mengulang (sengaja, ada alasan)
    // Tertinggal di kelasnya karena belum diproses (mis. belum direncanakan / ditandai lulus Ketua Program).
    // Beda dengan tinggal_kelas: tidak ada keputusan "mengulang", hanya belum ada yang memprosesnya.
    public const JENIS_BELUM_DIPROSES = 'belum_diproses';

    /** Pilihan alasan siap pakai di halaman Tahun Ajaran. "Lainnya" = tulis sendiri. */
    public const ALASAN_LAINNYA = 'Lainnya';
    public const ALASAN_PRESET  = [
        'Nilai belum memenuhi syarat kenaikan kelas',
        'Kehadiran (absensi) kurang',
        'Poin pelanggaran melebihi batas',
        'Sakit / izin berkepanjangan',
        self::ALASAN_LAINNYA,
    ];

    protected $table = 'student_class_histories';

    protected $fillable = [
        'nis',
        'id_kelas_sebelum',
        'nama_kelas_sebelum',
        'id_kelas_sesudah',
        'nama_kelas_sesudah',
        'jenis',
        'tanggal_mulai',
        'keterangan',
        'alasan',
        'tahun_ajaran',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
    ];

    public function siswa()
    {
        return $this->belongsTo(siswa::class, 'nis', 'nis');
    }

    public function kelasSebelum()
    {
        return $this->belongsTo(kelas::class, 'id_kelas_sebelum', 'id_kelas');
    }

    public function kelasSesudah()
    {
        return $this->belongsTo(kelas::class, 'id_kelas_sesudah', 'id_kelas');
    }

    /** True bila baris ini mencatat siswa yang tidak naik kelas (mengulang). */
    public function getTidakNaikAttribute(): bool
    {
        return $this->jenis === self::JENIS_TINGGAL_KELAS;
    }

    /** True bila baris ini mencatat siswa yang tertinggal karena belum diproses. */
    public function getBelumDiprosesAttribute(): bool
    {
        return $this->jenis === self::JENIS_BELUM_DIPROSES;
    }

    /**
     * Riwayat TERBARU tiap siswa aktif yang jenisnya tinggal_kelas atau belum_diproses dan siswanya
     * masih berada di kelas itu. Begitu siswa dipindah / dinaikkan / diluluskan, riwayat terbarunya
     * berganti sehingga otomatis tidak muncul lagi di sini.
     *
     * @return Collection<int, StudentClassHistory>
     */
    public static function terbaruTertinggal(): Collection
    {
        return static::query()
            ->whereIn('id', function ($q) {
                $q->from('student_class_histories')->selectRaw('MAX(id)')->groupBy('nis');
            })
            ->whereIn('jenis', [self::JENIS_TINGGAL_KELAS, self::JENIS_BELUM_DIPROSES])
            ->with('siswa.kelas')
            ->get()
            ->filter(fn ($h) => $h->siswa
                && $h->siswa->status === 'aktif'
                && (string) $h->siswa->id_kelas === (string) $h->id_kelas_sesudah)
            ->values();
    }

    /**
     * Daftar siswa yang masih "belum diproses", diurutkan menurut nama.
     *
     * @return Collection<int, StudentClassHistory>
     */
    public static function belumDiproses(?Collection $terbaru = null): Collection
    {
        return ($terbaru ?? self::terbaruTertinggal())
            ->where('jenis', self::JENIS_BELUM_DIPROSES)
            ->sortBy(fn ($h) => strtolower((string) $h->siswa->nama_siswa))
            ->values();
    }

    /**
     * Ubah siswa "belum diproses" menjadi "tidak naik kelas (mengulang)" beserta alasannya.
     * Hanya siswa yang memang masih belum diproses yang diubah; NIS lain diabaikan.
     *
     * @param  array<int,string|int>  $daftarNis
     * @return int  Jumlah siswa yang diubah
     */
    public static function tandaiMengulang(array $daftarNis, string $alasan): int
    {
        $dipilih = array_map('strval', $daftarNis);
        $diubah  = 0;

        DB::transaction(function () use ($dipilih, $alasan, &$diubah) {
            foreach (self::belumDiproses() as $h) {
                if (!in_array((string) $h->nis, $dipilih, true)) {
                    continue;
                }

                $h->update([
                    'jenis'      => self::JENIS_TINGGAL_KELAS,
                    'alasan'     => $alasan,
                    'keterangan' => 'Tidak naik kelas / mengulang (ditandai dari "Belum diproses")'
                        . ($h->tahun_ajaran ? ", tahun ajaran {$h->tahun_ajaran}" : ''),
                ]);

                $diubah++;
            }
        });

        return $diubah;
    }

    /**
     * Catat riwayat untuk banyak siswa sekaligus dengan membandingkan kelas SEBELUM dan SESUDAH.
     *
     * Dipakai saat ganti tahun ajaran (kenaikan / penurunan / kelulusan). Hanya siswa yang
     * id_kelas-nya benar-benar berubah yang dicatat; siswa yang tetap di kelasnya dicatat
     * terpisah lewat catatTertinggal(). Aman dipanggil di dalam DB::transaction.
     *
     * @param  array<string|int, string|null>  $kelasSebelum  [nis => id_kelas sebelum perubahan]
     * @param  string       $keterangan   Teks keterangan umum (mis. "Kenaikan kelas, tahun ajaran A -> B")
     * @param  string       $jenisDasar   JENIS_NAIK atau JENIS_TURUN (siswa yang jadi alumni otomatis JENIS_LULUS)
     * @param  string|null  $tahunAjaran  Tahun ajaran yang berakhir
     * @return int  Jumlah baris riwayat yang dibuat
     */
    public static function catatPerubahanMassal(
        array $kelasSebelum,
        string $keterangan,
        string $jenisDasar = self::JENIS_NAIK,
        ?string $tahunAjaran = null
    ): int {
        if (empty($kelasSebelum)) {
            return 0;
        }

        $namaKelas = kelas::pluck('nama_kelas', 'id_kelas');
        $tanggal   = now()->toDateString();
        $waktu     = now();
        $dibuat    = 0;

        foreach (array_chunk(array_keys($kelasSebelum), 500) as $potongan) {
            $sekarang = siswa::withTrashed()
                ->whereIn('nis', $potongan)
                ->pluck('id_kelas', 'nis');

            $baris = [];

            foreach ($potongan as $nis) {
                $sebelum = $kelasSebelum[$nis] ?? null;
                $sesudah = $sekarang[$nis] ?? null;

                // Tidak berubah, atau kelas tujuan kosong: tidak ada yang dicatat di sini.
                if ($sesudah === null || $sesudah === '' || (string) $sebelum === (string) $sesudah) {
                    continue;
                }

                $lulus = $sesudah === 'ALUMNI';

                $baris[] = [
                    'nis'                => $nis,
                    'id_kelas_sebelum'   => $sebelum,
                    'nama_kelas_sebelum' => $sebelum ? ($namaKelas[$sebelum] ?? $sebelum) : null,
                    'id_kelas_sesudah'   => $sesudah,
                    'nama_kelas_sesudah' => $namaKelas[$sesudah] ?? ($lulus ? 'Alumni' : $sesudah),
                    'jenis'              => $lulus ? self::JENIS_LULUS : $jenisDasar,
                    'tanggal_mulai'      => $tanggal,
                    'keterangan'         => $lulus
                        ? str_replace(['Kenaikan kelas', 'Penurunan kelas'], 'Lulus menjadi alumni', $keterangan)
                        : $keterangan,
                    'alasan'             => null,
                    'tahun_ajaran'       => $tahunAjaran,
                    'created_at'         => $waktu,
                    'updated_at'         => $waktu,
                ];
            }

            if (!empty($baris)) {
                DB::table('student_class_histories')->insert($baris);
                $dibuat += count($baris);
            }
        }

        return $dibuat;
    }

    /**
     * NIS siswa yang tadinya aktif dan SETELAH proses masih aktif di kelas yang sama
     * (tertinggal: tidak naik kelas atau belum diproses). Panggil setelah semua perpindahan kelas selesai.
     *
     * @param  array<string|int, string|null>  $kelasSebelum  [nis => id_kelas sebelum perubahan]
     * @return array<int,string>
     */
    public static function cariTidakNaik(array $kelasSebelum): array
    {
        $hasil = [];

        foreach (array_chunk(array_keys($kelasSebelum), 500) as $potongan) {
            $sekarang = siswa::query()
                ->where('status', 'aktif')
                ->whereIn('nis', $potongan)
                ->pluck('id_kelas', 'nis');

            foreach ($potongan as $nis) {
                $sebelum = $kelasSebelum[$nis] ?? null;

                if ($sebelum === null || $sebelum === '') {
                    continue;
                }

                if (isset($sekarang[$nis]) && (string) $sekarang[$nis] === (string) $sebelum) {
                    $hasil[] = (string) $nis;
                }
            }
        }

        return $hasil;
    }

    /**
     * Catat siswa yang TERTINGGAL di kelasnya (kelas sebelum = kelas sesudah). Dua jenis, supaya bisa dibedakan:
     *  - ada alasan  -> tinggal_kelas   : keputusan tidak naik / mengulang;
     *  - tanpa alasan -> belum_diproses : hanya belum diproses (mis. belum direncanakan Ketua Program).
     *
     * @param  array<int,string>               $daftarNis
     * @param  array<string|int, string|null>  $kelasSebelum  [nis => id_kelas]
     * @param  array<string|int, string>       $alasan        [nis => alasan], hanya untuk yang mengulang
     * @param  string                          $periode       mis. "tahun ajaran 2026/2027 -> 2027/2028"
     * @return array{tidak_naik:int, belum_diproses:int}
     */
    public static function catatTertinggal(array $daftarNis, array $kelasSebelum, array $alasan, ?string $tahunAjaran, string $periode): array
    {
        $hasil = ['tidak_naik' => 0, 'belum_diproses' => 0];

        if (empty($daftarNis)) {
            return $hasil;
        }

        $namaKelas = kelas::pluck('nama_kelas', 'id_kelas');
        $tanggal   = now()->toDateString();
        $waktu     = now();

        foreach (array_chunk($daftarNis, 500) as $potongan) {
            $baris = [];

            foreach ($potongan as $nis) {
                $idKelas = $kelasSebelum[$nis] ?? null;

                if ($idKelas === null || $idKelas === '') {
                    continue;
                }

                $nama     = $namaKelas[$idKelas] ?? $idKelas;
                $alasanSiswa = $alasan[$nis] ?? null;
                $mengulang = $alasanSiswa !== null && $alasanSiswa !== '';

                if ($mengulang) {
                    $jenis      = self::JENIS_TINGGAL_KELAS;
                    $keterangan = "Tidak naik kelas / mengulang, {$periode}";
                } else {
                    $penyebab = match (kelas::tingkatDariId((string) $idKelas)) {
                        '10'    => 'belum direncanakan Ketua Program',
                        '12'    => 'belum ditandai lulus Ketua Program',
                        default => 'belum ada keputusan naik kelas',
                    };
                    $jenis      = self::JENIS_BELUM_DIPROSES;
                    $keterangan = "Belum diproses ({$penyebab}), {$periode}";
                }

                $baris[] = [
                    'nis'                => $nis,
                    'id_kelas_sebelum'   => $idKelas,
                    'nama_kelas_sebelum' => $nama,
                    'id_kelas_sesudah'   => $idKelas,
                    'nama_kelas_sesudah' => $nama,
                    'jenis'              => $jenis,
                    'tanggal_mulai'      => $tanggal,
                    'keterangan'         => $keterangan,
                    'alasan'             => $mengulang ? $alasanSiswa : null,
                    'tahun_ajaran'       => $tahunAjaran,
                    'created_at'         => $waktu,
                    'updated_at'         => $waktu,
                ];

                $hasil[$mengulang ? 'tidak_naik' : 'belum_diproses']++;
            }

            if (!empty($baris)) {
                DB::table('student_class_histories')->insert($baris);
            }
        }

        return $hasil;
    }
}