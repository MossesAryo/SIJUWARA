<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\ketua_program;
use App\Models\RencanaPindahKelas;
use App\Models\siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Rencana perpindahan kelas oleh Ketua Program (10 -> 11, 11 -> 12, dan 12 -> Alumni).
 *
 * Ketua Program hanya MENYIMPAN RENCANA. Siswa tetap di kelas asal sampai Wakasek
 * mengganti tahun ajaran; saat itu TahunAjaranController menerapkan semua rencana
 * dan menghapusnya. Siswa boleh dicampur antar rombel (manual ke satu kelas, atau
 * acak dibagi merata).
 *
 * Kelas 12 -> Alumni tidak punya kelas tujuan: rencananya hanya menandai siswa kelas 12
 * yang akan lulus (id_kelas_tujuan = "ALUMNI"). Siswa kelas 12 yang tidak ditandai tetap di kelas 12.
 *
 * Batasan:
 * - Hanya siswa aktif pada tingkat asal milik jurusan Ketua Program yang login.
 * - Kelas tujuan hanya satu tingkat di atas asal, di jurusan yang sama
 *   (jurusan MP boleh ke MP atau MLOG).
 * - Menyimpan ulang rencana siswa yang sama = mengganti tujuannya.
 */
class PerpindahanKelasController extends Controller
{
    /**
     * Pilihan perpindahan yang didukung: tingkat asal => tingkat tujuan.
     */
    private const TRANSISI = [
        '10-11'    => ['asal' => '10', 'tujuan' => '11'],
        '11-12'    => ['asal' => '11', 'tujuan' => '12'],
        '12-alumni' => ['asal' => '12', 'tujuan' => 'alumni'],
    ];

    private const TRANSISI_DEFAULT = '10-11';
    private const ID_ALUMNI        = 'ALUMNI';
    private const CHUNK            = 500;

    public function index()
    {
        $ketua    = $this->ketuaLogin();
        $jurusan  = jurusan::find($ketua->id_jurusan);
        $transisi = $this->transisiDipilih(request()->query('transisi'));

        $untukAlumni = $transisi['tujuan'] === 'alumni';

        $kelasAsal   = $this->kelasPerTingkat($ketua->id_jurusan, $transisi['asal']);
        // Alumni bukan kelas biasa, jadi tidak ada daftar kelas tujuan.
        $kelasTujuan = $untukAlumni ? collect() : $this->kelasPerTingkat($ketua->id_jurusan, $transisi['tujuan']);

        $siswaList = siswa::with('kelas')
            ->where('status', 'aktif')
            ->whereIn('id_kelas', $kelasAsal->pluck('id_kelas')->all())
            ->orderBy('id_kelas')
            ->orderBy('nama_siswa')
            ->get();

        $idTujuan = $untukAlumni ? [self::ID_ALUMNI] : $kelasTujuan->pluck('id_kelas')->all();

        // Rencana milik siswa-siswa ini: [nis => id_kelas_tujuan].
        $rencanaMap = [];
        $nisList    = $siswaList->map(fn ($s) => (string) $s->nis)->all();

        foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
            $baris = RencanaPindahKelas::query()
                ->whereIn('nis', $potongan)
                ->get(['nis', 'id_kelas_tujuan']);

            foreach ($baris as $r) {
                if (in_array($r->id_kelas_tujuan, $idTujuan, true)) {
                    $rencanaMap[(string) $r->nis] = $r->id_kelas_tujuan;
                }
            }
        }

        return view('ketua_program.perpindahan_kelas.index', [
            'ketua'           => $ketua,
            'jurusan'         => $jurusan,
            'transisi'        => $transisi,
            'untukAlumni'     => $untukAlumni,
            'daftarTransisi'  => self::TRANSISI,
            'kelasAsal'       => $kelasAsal,
            'kelasTujuan'     => $kelasTujuan,
            'siswaList'       => $siswaList,
            'jumlahTujuan'    => $untukAlumni ? [] : $this->hitungIsiKelas($idTujuan),
            'rencanaMap'      => $rencanaMap,
            'rencanaPerKelas' => array_count_values($rencanaMap),
            'namaTujuan'      => $untukAlumni
                ? [self::ID_ALUMNI => 'Alumni']
                : $kelasTujuan->pluck('nama_kelas', 'id_kelas')->all(),
        ]);
    }

    /**
     * Simpan rencana penempatan (belum memindahkan siswa).
     */
    public function simpan(Request $request)
    {
        $ketua    = $this->ketuaLogin();
        $transisi = $this->transisiDipilih($request->input('transisi'));
        $asal     = $transisi['asal'];
        $tujuan   = $transisi['tujuan'];

        $untukAlumni = $tujuan === 'alumni';

        try {
            $request->validate([
                'transisi'            => ['nullable', Rule::in(array_keys(self::TRANSISI))],
                // Kelas 12 -> Alumni tidak punya pilihan cara penempatan.
                'mode'                => $untukAlumni ? ['nullable'] : ['required', Rule::in(['manual', 'acak'])],
                'kelas_tujuan'        => ['nullable', 'string'],
                'kelas_tujuan_acak'   => ['nullable', 'array'],
                'kelas_tujuan_acak.*' => ['string'],
            ], [
                'transisi.in'             => 'Jenis perpindahan kelas tidak valid.',
                'mode.required'           => 'Pilih cara penempatan terlebih dahulu.',
                'mode.in'                 => 'Cara penempatan tidak valid.',
                'kelas_tujuan_acak.array' => 'Kelas tujuan acak tidak valid.',
            ]);

            $mode    = $untukAlumni ? 'manual' : $request->input('mode');
            $nisList = $this->ambilNisTerpilih($request->input('siswa_nis'));

            if (empty($nisList)) {
                throw new RuntimeException("Pilih minimal satu siswa kelas {$asal} yang akan direncanakan.");
            }

            $kelasAsal   = $this->kelasPerTingkat($ketua->id_jurusan, $asal);
            $kelasTujuan = $untukAlumni
                ? collect()
                : $this->kelasPerTingkat($ketua->id_jurusan, $tujuan)->keyBy('id_kelas');

            if (!$untukAlumni && $kelasTujuan->isEmpty()) {
                throw new RuntimeException(
                    "Belum ada kelas {$tujuan} untuk program Anda. Minta Wakasek membuat kelas {$tujuan} di menu Kelas terlebih dahulu."
                );
            }

            if ($untukAlumni) {
                $tujuanDipilih = [self::ID_ALUMNI];
            } elseif ($mode === 'manual') {
                $tujuanDipilih = array_values(array_filter([(string) $request->input('kelas_tujuan')]));

                if (count($tujuanDipilih) !== 1) {
                    throw new RuntimeException("Pilih kelas {$tujuan} tujuan terlebih dahulu.");
                }
            } else {
                $tujuanDipilih = array_values(array_unique(
                    array_map('strval', (array) $request->input('kelas_tujuan_acak', []))
                ));

                if (count($tujuanDipilih) < 2) {
                    throw new RuntimeException("Pilih minimal 2 kelas {$tujuan} tujuan untuk pembagian acak.");
                }
            }

            if (!$untukAlumni) {
                foreach ($tujuanDipilih as $idTujuan) {
                    if (!$kelasTujuan->has($idTujuan)) {
                        throw new RuntimeException("Kelas tujuan tidak valid atau bukan kelas {$tujuan} program Anda.");
                    }
                }
            }

            $idKelasAsal = $kelasAsal->pluck('id_kelas')->all();

            $hasil = DB::transaction(
                fn () => $this->simpanRencana($ketua, $nisList, $idKelasAsal, $tujuanDipilih, $mode, $asal)
            );

            if ($untukAlumni) {
                $ringkasan = "{$hasil['disimpan']} siswa kelas {$asal} direncanakan lulus menjadi alumni.";
            } else {
                $rincian = collect($hasil['rincian'])
                    ->map(fn ($jumlah, $id) => $jumlah . ' ke ' . ($kelasTujuan[$id]->nama_kelas ?? $id))
                    ->implode(', ');

                $ringkasan = "{$hasil['disimpan']} siswa kelas {$asal} direncanakan pindah ({$rincian}).";
            }

            if ($hasil['dilewati'] > 0) {
                $ringkasan .= " {$hasil['dilewati']} siswa dilewati karena sudah tidak berstatus aktif kelas {$asal} di program Anda.";
            }

            ActivityLog::catat(
                ActivityLog::KATEGORI_SISWA,
                'Rencana Perpindahan Kelas',
                "Ketua Program {$ketua->nama_ketua_program} ({$ketua->id_jurusan}), {$asal} -> {$tujuan}, mode "
                . ($untukAlumni ? 'lulus' : ($mode === 'acak' ? 'acak merata' : 'manual')) . ': ' . $ringkasan
            );

            return redirect()->route('perpindahan_kelas.index', ['transisi' => $transisi['kunci']])->with(
                'success',
                $ringkasan . ($untukAlumni
                    ? ' Siswa baru menjadi alumni saat Wakasek mengganti tahun ajaran.'
                    : " Siswa baru pindah ke kelas {$tujuan} saat Wakasek mengganti tahun ajaran.")
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            // Pesan ini sengaja dibuat untuk pengguna.
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan rencana perpindahan kelas', [
                'ketua' => $ketua->nip_kaprog,
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan rencana. Tidak ada data yang diubah.');
        }
    }

    /**
     * Batalkan rencana penempatan siswa terpilih (siswa kembali "belum ditempatkan").
     */
    public function batal(Request $request)
    {
        $ketua    = $this->ketuaLogin();
        $transisi = $this->transisiDipilih($request->input('transisi'));
        $asal     = $transisi['asal'];

        try {
            $nisList = $this->ambilNisTerpilih($request->input('siswa_nis'));

            if (empty($nisList)) {
                throw new RuntimeException('Pilih minimal satu siswa yang rencananya akan dibatalkan.');
            }

            $idKelasAsal = $this->kelasPerTingkat($ketua->id_jurusan, $asal)
                ->pluck('id_kelas')
                ->all();

            $dihapus = DB::transaction(function () use ($nisList, $idKelasAsal) {
                $total = 0;

                foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
                    // Hanya siswa pada tingkat asal di jurusan Ketua Program ini.
                    $nisSah = siswa::query()
                        ->whereIn('id_kelas', $idKelasAsal)
                        ->whereIn('nis', $potongan)
                        ->pluck('nis')
                        ->map(fn ($n) => (string) $n)
                        ->all();

                    if (!empty($nisSah)) {
                        $total += RencanaPindahKelas::query()->whereIn('nis', $nisSah)->delete();
                    }
                }

                return $total;
            });

            if ($dihapus === 0) {
                throw new RuntimeException('Siswa yang dipilih belum punya rencana penempatan.');
            }

            $ringkasan = "Rencana penempatan {$dihapus} siswa dibatalkan.";

            ActivityLog::catat(
                ActivityLog::KATEGORI_SISWA,
                'Batal Rencana Perpindahan Kelas',
                "Ketua Program {$ketua->nama_ketua_program} ({$ketua->id_jurusan}): {$ringkasan}"
            );

            return redirect()->route('perpindahan_kelas.index', ['transisi' => $transisi['kunci']])->with('success', $ringkasan);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal membatalkan rencana perpindahan kelas', [
                'ketua' => $ketua->nip_kaprog,
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat membatalkan rencana. Tidak ada data yang diubah.');
        }
    }

    /**
     * Simpan rencana siswa terpilih. Wajib dipanggil di dalam DB::transaction.
     *
     * @param  array<int,string>  $nisList
     * @param  array<int,string>  $idKelasAsal  id kelas asal milik jurusan Ketua Program
     * @param  array<int,string>  $tujuan       id kelas tujuan (sudah divalidasi)
     * @param  string             $tingkatAsal  tingkat asal ("10" / "11" / "12") untuk pesan
     * @return array{disimpan:int, dilewati:int, rincian:array<string,int>}
     */
    private function simpanRencana(ketua_program $ketua, array $nisList, array $idKelasAsal, array $tujuan, string $mode, string $tingkatAsal): array
    {
        // Hanya siswa aktif yang benar-benar berada di kelas asal jurusan ini.
        $siswaTerpilih = collect();
        foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
            $siswaTerpilih = $siswaTerpilih->concat(
                siswa::query()
                    ->where('status', 'aktif')
                    ->whereIn('id_kelas', $idKelasAsal)
                    ->whereIn('nis', $potongan)
                    ->lockForUpdate()
                    ->get(['nis', 'id_kelas', 'nama_siswa'])
            );
        }

        $dilewati = count($nisList) - $siswaTerpilih->count();

        if ($siswaTerpilih->isEmpty()) {
            throw new RuntimeException(
                "Tidak ada siswa kelas {$tingkatAsal} aktif di program Anda yang bisa direncanakan dari pilihan tersebut."
            );
        }

        $nisValid = $siswaTerpilih->map(fn ($s) => (string) $s->nis)->all();

        // Rencana: id kelas tujuan => daftar NIS.
        $rencana = array_fill_keys($tujuan, []);

        if ($mode === 'manual') {
            $rencana[$tujuan[0]] = $nisValid;
        } else {
            // Acak: kocok siswa, lalu selalu masukkan ke kelas yang rencana masuknya paling sedikit.
            //
            // Yang dihitung HANYA rencana siswa lain di kelas tujuan tersebut. Siswa yang
            // sekarang berada di kelas tujuan TIDAK dihitung, karena saat tahun ajaran diganti
            // mereka naik ke tingkat berikutnya sehingga kelas tujuan kosong untuk angkatan baru.
            // (Rencana lama siswa yang sedang diproses tidak dihitung karena akan diganti.)
            // Hasilnya: tiap kelas tujuan mendapat jumlah siswa yang sama (selisih maksimal 1).
            $isi = array_fill_keys($tujuan, 0);

            foreach ($this->hitungRencana($tujuan, $nisValid) as $id => $jumlah) {
                $isi[$id] = (int) $jumlah;
            }

            // Urutan kelas diacak supaya kelas "sisa" tidak selalu jatuh ke kelas yang sama.
            $urutanTujuan = $tujuan;
            shuffle($urutanTujuan);

            foreach ($siswaTerpilih->shuffle() as $s) {
                $pilih = $urutanTujuan[0];

                foreach ($urutanTujuan as $id) {
                    if ($isi[$id] < $isi[$pilih]) {
                        $pilih = $id;
                    }
                }

                $rencana[$pilih][] = (string) $s->nis;
                $isi[$pilih]++;
            }
        }

        $sekarang = now();
        $rincian  = [];

        foreach ($rencana as $idTujuan => $daftarNis) {
            if (empty($daftarNis)) {
                continue;
            }

            foreach (array_chunk($daftarNis, self::CHUNK) as $potongan) {
                $baris = array_map(fn ($nis) => [
                    'nis'             => $nis,
                    'id_kelas_tujuan' => (string) $idTujuan,
                    'dibuat_oleh'     => $ketua->username,
                    'created_at'      => $sekarang,
                    'updated_at'      => $sekarang,
                ], $potongan);

                RencanaPindahKelas::upsert($baris, ['nis'], ['id_kelas_tujuan', 'dibuat_oleh', 'updated_at']);
            }

            $rincian[(string) $idTujuan] = count($daftarNis);
        }

        return [
            'disimpan' => array_sum($rincian),
            'dilewati' => $dilewati,
            'rincian'  => $rincian,
        ];
    }

    /**
     * Kelas milik satu jurusan pada satu tingkat ("10" / "11" / "12"), diurutkan berdasarkan nama.
     */
    private function kelasPerTingkat(?string $idJurusan, string $tingkat)
    {
        return kelas::where('id_jurusan', $idJurusan)
            ->orderBy('nama_kelas')
            ->get()
            ->filter(fn ($k) => kelas::tingkatDariId($k->id_kelas) === $tingkat)
            ->values();
    }

    /**
     * Jumlah siswa aktif per kelas: [id_kelas => jumlah].
     *
     * @param  array<int,string>  $idKelas
     * @return array<string,int>
     */
    private function hitungIsiKelas(array $idKelas): array
    {
        if (empty($idKelas)) {
            return [];
        }

        return siswa::query()
            ->where('status', 'aktif')
            ->whereIn('id_kelas', $idKelas)
            ->select('id_kelas', DB::raw('COUNT(*) as total'))
            ->groupBy('id_kelas')
            ->pluck('total', 'id_kelas')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Jumlah rencana per kelas tujuan: [id_kelas_tujuan => jumlah],
     * tidak menghitung rencana milik NIS di $kecualiNis.
     *
     * @param  array<int,string>  $idKelasTujuan
     * @param  array<int,string>  $kecualiNis
     * @return array<string,int>
     */
    private function hitungRencana(array $idKelasTujuan, array $kecualiNis): array
    {
        if (empty($idKelasTujuan)) {
            return [];
        }

        $query = RencanaPindahKelas::query()->whereIn('id_kelas_tujuan', $idKelasTujuan);

        foreach (array_chunk($kecualiNis, self::CHUNK) as $potongan) {
            $query->whereNotIn('nis', $potongan);
        }

        return $query
            ->select('id_kelas_tujuan', DB::raw('COUNT(*) as total'))
            ->groupBy('id_kelas_tujuan')
            ->pluck('total', 'id_kelas_tujuan')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Ubah input siswa_nis (string "1,2,3" atau array) menjadi array NIS unik berisi angka saja.
     *
     * @return array<int,string>
     */
    private function ambilNisTerpilih($input): array
    {
        if (is_string($input)) {
            $input = explode(',', $input);
        }

        if (!is_array($input)) {
            return [];
        }

        $bersih = [];
        foreach ($input as $nis) {
            $nis = trim((string) $nis);
            if ($nis !== '' && ctype_digit($nis)) {
                $bersih[$nis] = $nis;
            }
        }

        return array_values($bersih);
    }

    private function ketuaLogin(): ketua_program
    {
        $user  = Auth::user();
        $ketua = $user ? ketua_program::where('username', $user->username)->first() : null;

        if (!$ketua) {
            abort(403, 'Data Ketua Program tidak ditemukan.');
        }

        return $ketua;
    }

    /**
     * Normalisasi pilihan transisi (10-11 / 11-12 / 12-alumni) dari query atau input form.
     *
     * @return array{kunci:string, asal:string, tujuan:string} tujuan = "11", "12" atau "alumni"
     */
    private function transisiDipilih(?string $kunci): array
    {
        $kunci = (string) $kunci;

        if (!isset(self::TRANSISI[$kunci])) {
            $kunci = self::TRANSISI_DEFAULT;
        }

        return ['kunci' => $kunci] + self::TRANSISI[$kunci];
    }
}