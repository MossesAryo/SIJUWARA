<?php

namespace App\Support;

use App\Models\jurusan;
use App\Models\kelas;
use App\Models\RencanaPindahKelas;
use App\Models\siswa;
use Illuminate\Support\Collection;

/**
 * Status perencanaan kenaikan kelas oleh Ketua Program, per jurusan.
 *
 * Syarat ganti tahun ajaran (naik): siswa kelas 10 yang DICENTANG naik harus sudah direncanakan Ketua Program
 * (lihat pesanTahanDicentang). Siswa kelas 10 yang tidak dicentang (tidak naik / mengulang) tidak perlu
 * rencana, jadi mereka tidak menahan ganti tahun. Tabel status per jurusan di bawah ini hanya informasi
 * tentang berapa siswa kelas 10 yang sudah direncanakan. Satu jurusan dianggap:
 *   - "selesai"   : semua siswa aktif kelas 10 jurusan itu sudah punya rencana yang valid
 *                   (atau jurusan itu memang tidak punya siswa kelas 10),
 *   - "sebagian"  : sebagian siswa kelas 10 sudah direncanakan,
 *   - "belum"     : belum ada satu pun siswa kelas 10 yang direncanakan.
 *
 * Rencana kelas 10 dianggap valid bila kelas tujuannya kelas 11 yang ada di tabel kelas
 * (sama dengan aturan TahunAjaranController saat menerapkan rencana).
 *
 * Kelas 12 -> Alumni hanya ditampilkan sebagai informasi. Siswa kelas 12 yang tidak ditandai
 * lulus memang boleh tetap di kelas 12, jadi tidak menahan ganti tahun. Ubah WAJIB_KELAS12
 * menjadi true bila kelas 12 juga harus ditandai semua sebelum tahun ajaran boleh naik.
 */
class StatusPerencanaan
{
    public const WAJIB_KELAS12 = false;

    private const CHUNK     = 500;
    private const ID_ALUMNI = 'ALUMNI';

    /**
     * @return array{jurusan: Collection<int, array<string,mixed>>, lengkap: bool, tertahan: Collection<int, array<string,mixed>>}
     */
    public static function hitung(): array
    {
        $kelas10 = self::siswaAktifPerTingkat('10');
        $kelas12 = self::siswaAktifPerTingkat('12');

        $nisSemua = $kelas10->pluck('nis')->merge($kelas12->pluck('nis'))
            ->map(fn ($n) => (string) $n)->unique()->values()->all();

        // [nis => id_kelas_tujuan]
        $rencana = [];
        foreach (array_chunk($nisSemua, self::CHUNK) as $potongan) {
            foreach (RencanaPindahKelas::query()->whereIn('nis', $potongan)->get(['nis', 'id_kelas_tujuan']) as $r) {
                $rencana[(string) $r->nis] = (string) $r->id_kelas_tujuan;
            }
        }

        $kelas11Ada = kelas::query()
            ->where('id_kelas', 'like', '11-%')
            ->pluck('id_kelas')
            ->flip();

        $perJurusan10 = $kelas10->groupBy(fn ($s) => (string) ($s->id_jurusan ?? ''));
        $perJurusan12 = $kelas12->groupBy(fn ($s) => (string) ($s->id_jurusan ?? ''));

        $daftarJurusan = jurusan::with('ketuaProgram')->orderBy('nama_jurusan')->get();
        $baris         = collect();
        $idDikenal     = [];

        foreach ($daftarJurusan as $j) {
            $idDikenal[] = (string) $j->id_jurusan;

            $baris->push(self::susunBaris(
                (string) $j->id_jurusan,
                $j->nama_jurusan,
                $j->ketuaProgram?->nama_ketua_program,
                $j->ketuaProgram !== null,
                $perJurusan10->get((string) $j->id_jurusan, collect()),
                $perJurusan12->get((string) $j->id_jurusan, collect()),
                $rencana,
                $kelas11Ada
            ));
        }

        // Siswa kelas 10/12 yang kelasnya tidak punya jurusan dikenal: tidak ada Ketua Program yang bisa
        // merencanakan mereka, jadi tetap dihitung supaya tidak tertinggal diam-diam.
        $yatim10 = $perJurusan10->reject(fn ($v, $k) => in_array((string) $k, $idDikenal, true));
        $yatim12 = $perJurusan12->reject(fn ($v, $k) => in_array((string) $k, $idDikenal, true));

        if ($yatim10->isNotEmpty() || $yatim12->isNotEmpty()) {
            $baris->push(self::susunBaris(
                '',
                'Tanpa jurusan',
                null,
                false,
                $yatim10->collapse(),
                $yatim12->collapse(),
                $rencana,
                $kelas11Ada
            ));
        }

        $tertahan = $baris->filter(fn ($b) => !$b['selesai'])->values();

        return [
            'jurusan'  => $baris->values(),
            'lengkap'  => $tertahan->isEmpty(),
            'tertahan' => $tertahan,
        ];
    }

    /**
     * Pesan penahan untuk siswa kelas 10 yang DICENTANG naik tetapi belum punya rencana yang valid
     * (kelas tujuan kelas 11 yang ada). Null bila semua yang dicentang sudah direncanakan.
     *
     * Siswa kelas 10 yang tidak dicentang tidak diperiksa: mereka tidak naik, jadi tidak perlu rencana.
     *
     * @param  array<int,string|int>  $nisDipilih  NIS yang dicentang
     */
    public static function pesanTahanDicentang(array $nisDipilih): ?string
    {
        $dipilih = array_flip(array_map('strval', $nisDipilih));

        $kelas10 = self::siswaAktifPerTingkat('10')
            ->filter(fn ($s) => isset($dipilih[(string) $s->nis]));

        if ($kelas10->isEmpty()) {
            return null;
        }

        $rencana = [];
        foreach (array_chunk($kelas10->pluck('nis')->map(fn ($n) => (string) $n)->all(), self::CHUNK) as $potongan) {
            foreach (RencanaPindahKelas::query()->whereIn('nis', $potongan)->get(['nis', 'id_kelas_tujuan']) as $r) {
                $rencana[(string) $r->nis] = (string) $r->id_kelas_tujuan;
            }
        }

        $kelas11Ada = kelas::query()->where('id_kelas', 'like', '11-%')->pluck('id_kelas')->flip();

        $belum = $kelas10->filter(function ($s) use ($rencana, $kelas11Ada) {
            $tujuan = $rencana[(string) $s->nis] ?? null;

            return $tujuan === null || !$kelas11Ada->has($tujuan);
        });

        if ($belum->isEmpty()) {
            return null;
        }

        $nama    = siswa::query()->whereIn('nis', $belum->pluck('nis')->take(5)->all())->pluck('nama_siswa')->implode(', ');
        $jurusan = jurusan::query()
            ->whereIn('id_jurusan', $belum->pluck('id_jurusan')->unique()->all())
            ->pluck('nama_jurusan')
            ->implode(', ');
        $sisa    = $belum->count() > 5 ? ' dan ' . ($belum->count() - 5) . ' lainnya' : '';

        return "Ganti tahun ajaran ditahan: {$belum->count()} siswa kelas 10 dicentang naik tetapi belum direncanakan Ketua Program "
            . "({$nama}{$sisa}; jurusan: {$jurusan}). Minta Ketua Program merencanakan di menu Perpindahan Kelas, "
            . 'atau hilangkan centang siswa itu bila memang tidak naik kelas. Tidak ada data yang diubah.';
    }

    /**
     * Kalimat untuk pesan error saat ganti tahun ditahan. Null bila semua sudah selesai.
     * (Tidak lagi dipakai untuk menahan; centang siswa yang menentukan, lihat pesanTahanDicentang.)
     */
    public static function pesanTahan(?array $status = null): ?string
    {
        $status ??= self::hitung();

        if ($status['lengkap']) {
            return null;
        }

        $daftar = $status['tertahan']->map(function ($b) {
            $alasan = $b['selesai_kelas10']
                ? "kelas 12 {$b['direncanakan12']}/{$b['total12']} ditandai lulus"
                : "kelas 10 {$b['direncanakan10']}/{$b['total10']} direncanakan";

            $catatan = $b['ada_kaprog'] ? '' : ', belum ada akun Ketua Program';

            return "{$b['nama']} ({$alasan}{$catatan})";
        })->implode('; ');

        return "Ganti tahun ajaran ditahan: semua Ketua Program harus selesai merencanakan dulu. Belum selesai: {$daftar}. "
            . 'Tidak ada data yang diubah.';
    }

    /**
     * @param  Collection  $siswa10
     * @param  Collection  $siswa12
     * @param  array<string,string>  $rencana
     * @param  Collection  $kelas11Ada
     * @return array<string,mixed>
     */
    private static function susunBaris(
        string $idJurusan,
        ?string $nama,
        ?string $namaKaprog,
        bool $adaKaprog,
        $siswa10,
        $siswa12,
        array $rencana,
        $kelas11Ada
    ): array {
        $total10 = $siswa10->count();
        $ok10    = $siswa10->filter(function ($s) use ($rencana, $kelas11Ada) {
            $tujuan = $rencana[(string) $s->nis] ?? null;

            return $tujuan !== null && $kelas11Ada->has($tujuan);
        })->count();

        $total12 = $siswa12->count();
        $ok12    = $siswa12->filter(fn ($s) => ($rencana[(string) $s->nis] ?? null) === self::ID_ALUMNI)->count();

        $selesai10 = $total10 === 0 || $ok10 >= $total10;
        $selesai12 = !self::WAJIB_KELAS12 || $total12 === 0 || $ok12 >= $total12;

        // Status ditentukan dari kelas 10 (syarat utama); kelas 12 ikut hanya bila diwajibkan.
        if ($selesai10 && $selesai12) {
            $status = 'selesai';
        } elseif ($ok10 === 0 && (!self::WAJIB_KELAS12 || $ok12 === 0)) {
            $status = 'belum';
        } else {
            $status = 'sebagian';
        }

        return [
            'id_jurusan'      => $idJurusan,
            'nama'            => $nama ?: $idJurusan,
            'kaprog'          => $namaKaprog,
            'ada_kaprog'      => $adaKaprog,
            'total10'         => $total10,
            'direncanakan10'  => $ok10,
            'total12'         => $total12,
            'direncanakan12'  => $ok12,
            'selesai_kelas10' => $selesai10,
            'selesai'         => $selesai10 && $selesai12,
            'status'          => $status,
        ];
    }

    /**
     * Siswa aktif di satu tingkat beserta id_jurusan dari KELASNYA (sama dengan cara Ketua Program
     * melihat siswa di PerpindahanKelasController).
     */
    private static function siswaAktifPerTingkat(string $tingkat): Collection
    {
        return siswa::query()
            ->join('kelas', 'kelas.id_kelas', '=', 'siswa.id_kelas')
            ->where('siswa.status', 'aktif')
            ->where('siswa.id_kelas', 'like', $tingkat . '-%')
            ->get(['siswa.nis', 'kelas.id_jurusan']);
    }
}