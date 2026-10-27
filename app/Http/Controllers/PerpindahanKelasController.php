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
 * Rencana perpindahan kelas 10 -> 11 oleh Ketua Program.
 *
 * Ketua Program hanya MENYIMPAN RENCANA. Siswa tetap di kelas 10 sampai Wakasek
 * mengganti tahun ajaran; saat itu TahunAjaranController menerapkan semua rencana
 * dan menghapusnya. Siswa boleh dicampur antar rombel (manual ke satu kelas, atau
 * acak dibagi merata).
 *
 * Batasan:
 * - Hanya siswa aktif kelas 10 milik jurusan Ketua Program yang login.
 * - Kelas tujuan hanya kelas 11 di jurusan yang sama (jurusan MP boleh ke MP atau MLOG).
 * - Menyimpan ulang rencana siswa yang sama = mengganti tujuannya.
 */
class PerpindahanKelasController extends Controller
{
    private const TINGKAT_ASAL   = '10';
    private const TINGKAT_TUJUAN = '11';
    private const CHUNK          = 500;

    public function index()
    {
        $ketua   = $this->ketuaLogin();
        $jurusan = jurusan::find($ketua->id_jurusan);

        $kelasAsal   = $this->kelasPerTingkat($ketua->id_jurusan, self::TINGKAT_ASAL);
        $kelasTujuan = $this->kelasPerTingkat($ketua->id_jurusan, self::TINGKAT_TUJUAN);

        $siswaList = siswa::with('kelas')
            ->where('status', 'aktif')
            ->whereIn('id_kelas', $kelasAsal->pluck('id_kelas')->all())
            ->orderBy('id_kelas')
            ->orderBy('nama_siswa')
            ->get();

        $idTujuan = $kelasTujuan->pluck('id_kelas')->all();

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
            'kelasAsal'       => $kelasAsal,
            'kelasTujuan'     => $kelasTujuan,
            'siswaList'       => $siswaList,
            'jumlahTujuan'    => $this->hitungIsiKelas($idTujuan),
            'rencanaMap'      => $rencanaMap,
            'rencanaPerKelas' => array_count_values($rencanaMap),
            'namaTujuan'      => $kelasTujuan->pluck('nama_kelas', 'id_kelas')->all(),
        ]);
    }

    /**
     * Simpan rencana penempatan (belum memindahkan siswa).
     */
    public function simpan(Request $request)
    {
        $ketua = $this->ketuaLogin();

        try {
            $request->validate([
                'mode'                => ['required', Rule::in(['manual', 'acak'])],
                'kelas_tujuan'        => ['nullable', 'string'],
                'kelas_tujuan_acak'   => ['nullable', 'array'],
                'kelas_tujuan_acak.*' => ['string'],
            ], [
                'mode.required'           => 'Pilih cara penempatan terlebih dahulu.',
                'mode.in'                 => 'Cara penempatan tidak valid.',
                'kelas_tujuan_acak.array' => 'Kelas tujuan acak tidak valid.',
            ]);

            $mode    = $request->input('mode');
            $nisList = $this->ambilNisTerpilih($request->input('siswa_nis'));

            if (empty($nisList)) {
                throw new RuntimeException('Pilih minimal satu siswa kelas 10 yang akan direncanakan.');
            }

            $kelasAsal   = $this->kelasPerTingkat($ketua->id_jurusan, self::TINGKAT_ASAL);
            $kelasTujuan = $this->kelasPerTingkat($ketua->id_jurusan, self::TINGKAT_TUJUAN)->keyBy('id_kelas');

            if ($kelasTujuan->isEmpty()) {
                throw new RuntimeException(
                    'Belum ada kelas 11 untuk program Anda. Minta Wakasek membuat kelas 11 di menu Kelas terlebih dahulu.'
                );
            }

            if ($mode === 'manual') {
                $tujuan = array_values(array_filter([(string) $request->input('kelas_tujuan')]));

                if (count($tujuan) !== 1) {
                    throw new RuntimeException('Pilih kelas 11 tujuan terlebih dahulu.');
                }
            } else {
                $tujuan = array_values(array_unique(
                    array_map('strval', (array) $request->input('kelas_tujuan_acak', []))
                ));

                if (count($tujuan) < 2) {
                    throw new RuntimeException('Pilih minimal 2 kelas 11 tujuan untuk pembagian acak.');
                }
            }

            foreach ($tujuan as $idTujuan) {
                if (!$kelasTujuan->has($idTujuan)) {
                    throw new RuntimeException('Kelas tujuan tidak valid atau bukan kelas 11 program Anda.');
                }
            }

            $idKelasAsal = $kelasAsal->pluck('id_kelas')->all();

            $hasil = DB::transaction(
                fn () => $this->simpanRencana($ketua, $nisList, $idKelasAsal, $tujuan, $mode)
            );

            $rincian = collect($hasil['rincian'])
                ->map(fn ($jumlah, $id) => $jumlah . ' ke ' . ($kelasTujuan[$id]->nama_kelas ?? $id))
                ->implode(', ');

            $ringkasan = "{$hasil['disimpan']} siswa kelas 10 direncanakan pindah ({$rincian}).";

            if ($hasil['dilewati'] > 0) {
                $ringkasan .= " {$hasil['dilewati']} siswa dilewati karena sudah tidak berstatus aktif kelas 10 di program Anda.";
            }

            ActivityLog::catat(
                ActivityLog::KATEGORI_SISWA,
                'Rencana Perpindahan Kelas',
                "Ketua Program {$ketua->nama_ketua_program} ({$ketua->id_jurusan}), mode "
                . ($mode === 'acak' ? 'acak merata' : 'manual') . ': ' . $ringkasan
            );

            return redirect()->route('perpindahan_kelas.index')->with(
                'success',
                $ringkasan . ' Siswa baru pindah ke kelas 11 saat Wakasek mengganti tahun ajaran.'
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
        $ketua = $this->ketuaLogin();

        try {
            $nisList = $this->ambilNisTerpilih($request->input('siswa_nis'));

            if (empty($nisList)) {
                throw new RuntimeException('Pilih minimal satu siswa yang rencananya akan dibatalkan.');
            }

            $idKelasAsal = $this->kelasPerTingkat($ketua->id_jurusan, self::TINGKAT_ASAL)
                ->pluck('id_kelas')
                ->all();

            $dihapus = DB::transaction(function () use ($nisList, $idKelasAsal) {
                $total = 0;

                foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
                    // Hanya siswa kelas 10 di jurusan Ketua Program ini.
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

            return redirect()->route('perpindahan_kelas.index')->with('success', $ringkasan);
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
     * @param  array<int,string>  $idKelasAsal  id kelas 10 milik jurusan Ketua Program
     * @param  array<int,string>  $tujuan       id kelas 11 tujuan (sudah divalidasi)
     * @return array{disimpan:int, dilewati:int, rincian:array<string,int>}
     */
    private function simpanRencana(ketua_program $ketua, array $nisList, array $idKelasAsal, array $tujuan, string $mode): array
    {
        // Hanya siswa aktif yang benar-benar berada di kelas 10 jurusan ini.
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
                'Tidak ada siswa kelas 10 aktif di program Anda yang bisa direncanakan dari pilihan tersebut.'
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
            // sekarang berada di kelas 11 TIDAK dihitung, karena saat tahun ajaran diganti
            // mereka naik ke kelas 12 sehingga kelas 11 kosong untuk angkatan baru.
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
     * Kelas milik satu jurusan pada satu tingkat ("10" / "11"), diurutkan berdasarkan nama.
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
}