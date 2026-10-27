<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\RencanaPindahKelas;
use App\Models\siswa;
use App\Models\tahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Manajemen tahun ajaran + kenaikan / penurunan kelas siswa.
 *
 * Aturan utama:
 * - Hanya siswa berstatus "aktif" dan yang DIPILIH (checkbox) yang dipindahkan.
 * - Kelas tujuan dihitung dari id_kelas: "11-RPL-1" -> "12-RPL-1".
 * - Kelas 10 yang NAIK tidak dipilih manual di sini. Penempatannya ke kelas 11
 *   (siswa dicampur) sudah direncanakan Ketua Program lewat PerpindahanKelasController;
 *   rencana itu DITERAPKAN otomatis saat tahun ajaran naik 1 tahun. Siswa kelas 10 yang
 *   belum direncanakan tetap di kelas 10.
 * - Kelas tujuan HARUS sudah ada di tabel kelas (foreign key). Jika ada yang belum
 *   ada, seluruh proses dibatalkan (tidak ada data setengah berubah) dan pesan
 *   menyebutkan kelas apa yang perlu dibuat dulu.
 * - Kelas 12 yang naik -> id_kelas "ALUMNI" + status "alumni".
 * - Turun kelas tidak pernah menghidupkan kembali alumni dan tidak turun di bawah 10.
 * - Jika daftar siswa kelas 11/12 yang dikirim KOSONG padahal ada siswa aktif di kelas 11/12,
 *   proses dibatalkan (kecuali pengguna sengaja mengonfirmasi lewat tanpa_siswa=1), supaya
 *   tidak terjadi kasus "hanya kelas 10 yang naik".
 */
class TahunAjaranController extends Controller
{
    private const TINGKAT = ['10', '11', '12'];
    private const TINGKAT_DIATUR_KAPROG = '10';
    private const TINGKAT_HASIL_KAPROG  = '11';
    private const ID_ALUMNI = 'ALUMNI';
    private const CHUNK = 500;

    public function index()
    {
        $tahunAjaran = tahunAjaran::orderBy('id', 'asc')->get();
        $tahunAktif  = $tahunAjaran->firstWhere('status', 'aktif');

        // Selisih tiap tahun ajaran terhadap tahun aktif, berdasarkan URUTAN (bukan selisih id),
        // supaya tetap benar walau ada id yang bolong.
        $urutanAktif = $tahunAktif
            ? $tahunAjaran->values()->search(fn ($t) => (int) $t->id === (int) $tahunAktif->id)
            : false;

        $opsiTahun = $tahunAjaran->values()->map(function ($t, $i) use ($urutanAktif) {
            return [
                'id'      => $t->id,
                'label'   => $t->tahun_ajaran,
                'aktif'   => $t->status === 'aktif',
                'selisih' => $urutanAktif === false ? 0 : $i - $urutanAktif,
            ];
        });

        $siswaList = siswa::with(['kelas.jurusan'])
            ->where('status', 'aktif')
            ->orderBy('nama_siswa')
            ->get();

        // Rencana penempatan kelas 10 -> 11 dari Ketua Program, kunci = NIS.
        $rencanaMap = RencanaPindahKelas::with('kelasTujuan')
            ->get()
            ->keyBy(fn ($r) => (string) $r->nis);

        $siswaKelas10 = $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '10');

        $preview = [
            // Kelas 10 -> 11 diatur Ketua Program: total siswa kelas 10 dan yang sudah direncanakan.
            'kelas10_ke_11'        => $siswaKelas10->count(),
            'kelas10_direncanakan' => $siswaKelas10->filter(fn ($s) => $rencanaMap->has((string) $s->nis))->count(),
            'kelas11_ke_12'        => $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '11')->count(),
            'lulus'                => $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '12')->count(),
        ];

        $kelasList   = kelas::with('jurusan')
            ->where('id_kelas', '!=', self::ID_ALUMNI)
            ->orderBy('id_kelas')
            ->get();
        $jurusanList = jurusan::all();

        return view('wakasek.tahun_ajaran.index', [
            'preview'     => $preview,
            'tahunAjaran' => $tahunAjaran,
            'tahunAktif'  => $tahunAktif,
            'opsiTahun'   => $opsiTahun,
            'siswaList'   => $siswaList,
            'kelasList'   => $kelasList,
            'jurusanList' => $jurusanList,
            'rencanaMap'  => $rencanaMap,
        ]);
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            ], [
                'tahun_ajaran_id.required' => 'Pilih tahun ajaran terlebih dahulu.',
                'tahun_ajaran_id.exists'   => 'Tahun ajaran yang dipilih tidak ditemukan.',
            ]);

            $tahunBaru     = tahunAjaran::findOrFail($request->tahun_ajaran_id);
            $tahunSekarang = tahunAjaran::where('status', 'aktif')->orderBy('id')->first();

            // Belum ada tahun aktif -> cukup aktifkan, tidak ada kenaikan kelas.
            if (!$tahunSekarang) {
                DB::transaction(function () use ($tahunBaru) {
                    tahunAjaran::where('id', '!=', $tahunBaru->id)->update(['status' => 'nonaktif']);
                    $tahunBaru->update(['status' => 'aktif']);
                });

                ActivityLog::catat(
                    ActivityLog::KATEGORI_SISWA,
                    'Aktifkan Tahun Ajaran',
                    "Tahun ajaran {$tahunBaru->tahun_ajaran} diaktifkan."
                );

                return back()->with('success', "Tahun ajaran {$tahunBaru->tahun_ajaran} berhasil diaktifkan.");
            }

            if ((int) $tahunSekarang->id === (int) $tahunBaru->id) {
                return back()->with('success', 'Tahun ajaran ini sudah aktif.');
            }

            // Selisih dihitung dari urutan tahun ajaran.
            $urutanId    = tahunAjaran::orderBy('id', 'asc')->pluck('id')->map(fn ($v) => (int) $v)->values();
            $idxSekarang = $urutanId->search((int) $tahunSekarang->id);
            $idxBaru     = $urutanId->search((int) $tahunBaru->id);

            if ($idxSekarang === false || $idxBaru === false) {
                throw new RuntimeException('Data tahun ajaran tidak konsisten. Hubungi administrator.');
            }

            $selisih = $idxBaru - $idxSekarang;

            // Kelas tujuan harus dibuat dulu sebelum tahun ajaran naik.
            if ($selisih > 0) {
                $kurang = \App\Support\KelasTujuan::kurang();

                if ($kurang->isNotEmpty()) {
                    $contoh = $kurang->take(5)->pluck('nama_kelas')->implode(', ');
                    $sisa   = $kurang->count() > 5 ? ' dan ' . ($kurang->count() - 5) . ' lainnya' : '';

                    throw new RuntimeException(
                        "Buat kelas tujuan dulu. {$kurang->count()} kelas belum ada ({$contoh}{$sisa}). "
                        . 'Klik tombol "Buat Kelas Tujuan" di halaman ini, lalu ulangi.'
                    );
                }
            }

            $nisDipilih = $this->ambilNisTerpilih($request->input('siswa_nis'));

            // Pengaman: daftar siswa kosong padahal ada siswa kelas 11/12 yang aktif biasanya berarti
            // daftar centang tidak terkirim dari browser (bukan pilihan sengaja). Tanpa pengaman ini,
            // hanya kelas 10 (rencana Ketua Program) yang pindah dan kelas 11/12 diam-diam tertinggal.
            // Pilihan sengaja "tanpa menaikkan siswa" dikirim form lewat tanpa_siswa=1 (dari modal konfirmasi).
            $tingkatDiproses = $this->hitungSiswaKelas11dan12();

            if ($selisih !== 0 && empty($nisDipilih) && !$request->boolean('tanpa_siswa') && $tingkatDiproses > 0) {
                Log::warning('Ganti tahun ajaran dibatalkan: daftar siswa kosong', [
                    'tahun_ajaran_id' => $tahunBaru->id,
                    'siswa_nis_raw'   => is_string($request->input('siswa_nis'))
                        ? strlen($request->input('siswa_nis')) . ' karakter'
                        : gettype($request->input('siswa_nis')),
                ]);

                throw new RuntimeException(
                    "Tidak ada daftar siswa kelas 11 / 12 yang diterima server, padahal ada {$tingkatDiproses} siswa aktif di kelas 11 / 12. "
                    . 'Tahun ajaran BELUM diganti dan tidak ada data yang berubah. '
                    . 'Muat ulang halaman (Ctrl+F5), centang siswa, lalu klik Ganti Tahun Ajaran dan konfirmasi di jendela yang muncul.'
                );
            }

            // Siswa kelas 11/12 aktif yang tidak dicentang akan tetap di kelasnya.
            $tidakDicentang = max(0, $tingkatDiproses - $this->hitungDicentangAktif($nisDipilih));

            $hasil = DB::transaction(function () use ($selisih, $nisDipilih, $tahunBaru) {
                // 1) Kelas 11 -> 12 dan 12 -> Alumni (atau turun kelas) untuk siswa yang dicentang.
                $hasil = $this->pindahkanKelas($nisDipilih, $selisih);

                // 2) Naik tepat 1 tahun: terapkan rencana Ketua Program (10 -> 11).
                //    Dilakukan setelah langkah 1 supaya tidak tercampur dengan siswa kelas 11 lama.
                if ($selisih === 1) {
                    $hasil = array_merge($hasil, $this->terapkanRencanaKaprog());
                }

                tahunAjaran::where('id', '!=', $tahunBaru->id)->update(['status' => 'nonaktif']);
                $tahunBaru->update(['status' => 'aktif']);

                return $hasil;
            });

            // Siswa kelas 10 yang masih tinggal (belum direncanakan Ketua Program).
            $menungguKaprog = $selisih > 0 ? $this->hitungSisaKelas10() : 0;

            // Naik lebih dari 1 tahun: rencana tidak diterapkan, tetap tersimpan.
            $rencanaDitahan = $selisih > 1 ? RencanaPindahKelas::query()->count() : 0;

            $ringkasan = $this->susunRingkasan($selisih, $nisDipilih, $hasil, $menungguKaprog, $rencanaDitahan, $tidakDicentang);

            ActivityLog::catat(
                ActivityLog::KATEGORI_SISWA,
                $selisih > 0 ? 'Kenaikan Kelas' : 'Penurunan Kelas',
                "Tahun ajaran {$tahunSekarang->tahun_ajaran} -> {$tahunBaru->tahun_ajaran}. {$ringkasan}"
            );

            return back()->with('success', "Tahun ajaran berhasil diubah ke {$tahunBaru->tahun_ajaran}. {$ringkasan}");
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            // Pesan ini sengaja dibuat untuk pengguna (misal: kelas tujuan belum ada).
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal mengganti tahun ajaran', [
                'tahun_ajaran_id' => $request->input('tahun_ajaran_id'),
                'error'           => $e->getMessage(),
                'file'            => $e->getFile() . ':' . $e->getLine(),
            ]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat mengganti tahun ajaran. Tidak ada data yang diubah.');
        }
    }

    /**
     * Pindahkan kelas siswa terpilih sejauh $selisih tingkat.
     * $selisih > 0 = naik, $selisih < 0 = turun.
     * Siswa kelas 10 yang NAIK dilewati: penempatannya mengikuti rencana Ketua Program.
     * Wajib dipanggil di dalam DB::transaction.
     *
     * @param  array<int,string>  $nisList
     * @return array{naik:int, turun:int, lulus:int, tetap:int, dilewati:int, diserahkan:int, ditempatkan:int, rencana_basi:int}
     */
    private function pindahkanKelas(array $nisList, int $selisih): array
    {
        $hasil = [
            'naik' => 0, 'turun' => 0, 'lulus' => 0, 'tetap' => 0,
            'dilewati' => 0, 'diserahkan' => 0, 'ditempatkan' => 0, 'rencana_basi' => 0,
        ];

        if (empty($nisList) || $selisih === 0) {
            return $hasil;
        }

        // Ambil siswa aktif terpilih saja (alumni / nonaktif / terarsip otomatis terabaikan).
        $siswaTerpilih = collect();
        foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
            $siswaTerpilih = $siswaTerpilih->merge(
                siswa::query()
                    ->where('status', 'aktif')
                    ->whereIn('nis', $potongan)
                    ->lockForUpdate()
                    ->get(['nis', 'id_kelas', 'nama_siswa'])
            );
        }

        // Rencana: id kelas tujuan => daftar NIS.
        $rencana     = [];
        $jenisPerNis = [];

        foreach ($siswaTerpilih as $s) {
            $uraian = $this->uraikanIdKelas($s->id_kelas);

            if ($uraian === null) {
                $hasil['dilewati']++;
                continue;
            }

            [$tingkat, $kode, $nomor] = $uraian;

            // Kelas 10 naik ke 11 mengikuti rencana Ketua Program, bukan dihitung di sini.
            if ($selisih > 0 && $tingkat === self::TINGKAT_DIATUR_KAPROG) {
                $hasil['diserahkan']++;
                continue;
            }

            $idxSekarang = array_search($tingkat, self::TINGKAT, true);
            $idxTujuan   = $idxSekarang + $selisih;

            if ($idxTujuan >= count(self::TINGKAT)) {
                $tujuan = self::ID_ALUMNI;
                $jenis  = 'lulus';
            } else {
                $idxTujuan = max(0, $idxTujuan);

                if ($idxTujuan === $idxSekarang) {
                    $hasil['tetap']++;
                    continue;
                }

                $tujuan = self::TINGKAT[$idxTujuan] . "-{$kode}-{$nomor}";
                $jenis  = $selisih > 0 ? 'naik' : 'turun';
            }

            $rencana[$tujuan][] = (string) $s->nis;
            $jenisPerNis[(string) $s->nis] = $jenis;
        }

        if (empty($rencana)) {
            return $hasil;
        }

        // Pastikan semua kelas tujuan ada. Kalau ada yang hilang, batalkan SEMUA.
        $adaDiDb = kelas::whereIn('id_kelas', array_keys($rencana))->pluck('id_kelas')->all();
        $hilang  = array_diff(array_keys($rencana), $adaDiDb);

        if (!empty($hilang)) {
            $daftar = collect($hilang)
                ->sort()
                ->map(fn ($id) => "{$id} (" . count($rencana[$id]) . ' siswa)')
                ->implode(', ');

            throw new RuntimeException(
                "Proses dibatalkan, tidak ada data yang diubah. Kelas tujuan berikut belum ada: {$daftar}. "
                . 'Buat kelas tersebut di menu Kelas terlebih dahulu, atau hilangkan centang siswa dari kelas asalnya.'
            );
        }

        // Terapkan perubahan per kelas tujuan.
        foreach ($rencana as $tujuan => $daftarNis) {
            $nilai = ['id_kelas' => $tujuan];

            if ($tujuan === self::ID_ALUMNI) {
                $nilai['status'] = 'alumni';
            }

            foreach (array_chunk($daftarNis, self::CHUNK) as $potongan) {
                siswa::query()
                    ->where('status', 'aktif')
                    ->whereIn('nis', $potongan)
                    ->update($nilai);
            }
        }

        foreach ($jenisPerNis as $jenis) {
            $hasil[$jenis]++;
        }

        return $hasil;
    }

    /**
     * Terapkan semua rencana penempatan kelas 10 -> 11 dari Ketua Program, lalu hapus rencananya.
     *
     * Rencana dianggap basi (dibuang tanpa memindahkan siswa) bila siswanya sudah tidak aktif
     * di kelas 10, kelas tujuannya bukan kelas 11, atau kelas tujuannya sudah tidak ada.
     * Wajib dipanggil di dalam DB::transaction.
     *
     * @return array{ditempatkan:int, rencana_basi:int}
     */
    private function terapkanRencanaKaprog(): array
    {
        $hasil = ['ditempatkan' => 0, 'rencana_basi' => 0];

        $semuaRencana = RencanaPindahKelas::query()
            ->lockForUpdate()
            ->get(['id', 'nis', 'id_kelas_tujuan']);

        if ($semuaRencana->isEmpty()) {
            return $hasil;
        }

        $nisSemua = $semuaRencana->map(fn ($r) => (string) $r->nis)->all();

        // Kelas siswa saat ini (hanya yang masih aktif).
        $kelasSiswa = [];
        foreach (array_chunk($nisSemua, self::CHUNK) as $potongan) {
            $baris = siswa::query()
                ->where('status', 'aktif')
                ->whereIn('nis', $potongan)
                ->lockForUpdate()
                ->get(['nis', 'id_kelas']);

            foreach ($baris as $s) {
                $kelasSiswa[(string) $s->nis] = $s->id_kelas;
            }
        }

        $idTujuanAda = kelas::query()
            ->whereIn('id_kelas', $semuaRencana->pluck('id_kelas_tujuan')->unique()->values()->all())
            ->pluck('id_kelas')
            ->all();

        $perTujuan = [];

        foreach ($semuaRencana as $r) {
            $nis  = (string) $r->nis;
            $asal = $kelasSiswa[$nis] ?? null;

            $sah = $asal !== null
                && kelas::tingkatDariId($asal) === self::TINGKAT_DIATUR_KAPROG
                && kelas::tingkatDariId($r->id_kelas_tujuan) === self::TINGKAT_HASIL_KAPROG
                && in_array($r->id_kelas_tujuan, $idTujuanAda, true);

            if (!$sah) {
                $hasil['rencana_basi']++;
                continue;
            }

            $perTujuan[$r->id_kelas_tujuan][] = $nis;
        }

        foreach ($perTujuan as $idTujuan => $daftarNis) {
            foreach (array_chunk($daftarNis, self::CHUNK) as $potongan) {
                siswa::query()
                    ->where('status', 'aktif')
                    ->where('id_kelas', 'like', self::TINGKAT_DIATUR_KAPROG . '-%')
                    ->whereIn('nis', $potongan)
                    ->update(['id_kelas' => (string) $idTujuan]);
            }

            $hasil['ditempatkan'] += count($daftarNis);
        }

        // Semua rencana sudah diproses (diterapkan atau dibuang), hapus.
        foreach (array_chunk($nisSemua, self::CHUNK) as $potongan) {
            RencanaPindahKelas::query()->whereIn('nis', $potongan)->delete();
        }

        return $hasil;
    }

    /**
     * Jumlah siswa aktif yang masih di kelas 10 (belum dipindah ke kelas 11).
     */
    private function hitungSisaKelas10(): int
    {
        return siswa::query()
            ->where('status', 'aktif')
            ->where('id_kelas', 'like', self::TINGKAT_DIATUR_KAPROG . '-%')
            ->count();
    }

    /**
     * Jumlah siswa aktif di kelas 11 dan 12 (yang dipilih manual di halaman ini).
     */
    private function hitungSiswaKelas11dan12(): int
    {
        return siswa::query()
            ->where('status', 'aktif')
            ->where(function ($q) {
                $q->where('id_kelas', 'like', '11-%')
                  ->orWhere('id_kelas', 'like', '12-%');
            })
            ->count();
    }

    /**
     * Berapa dari NIS yang dikirim benar-benar siswa aktif kelas 11/12.
     *
     * @param  array<int,string>  $nisList
     */
    private function hitungDicentangAktif(array $nisList): int
    {
        $jumlah = 0;

        foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
            $jumlah += siswa::query()
                ->where('status', 'aktif')
                ->where(function ($q) {
                    $q->where('id_kelas', 'like', '11-%')
                      ->orWhere('id_kelas', 'like', '12-%');
                })
                ->whereIn('nis', $potongan)
                ->count();
        }

        return $jumlah;
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

    /**
     * "11-RPL-2" => ['11', 'RPL', '2']. Mengembalikan null bila formatnya tidak dikenali
     * (mis. NULL, "ALUMNI").
     */
    private function uraikanIdKelas(?string $idKelas): ?array
    {
        if (!$idKelas) {
            return null;
        }

        if (!preg_match('/^(12|11|10)-(.+)-([^-]+)$/', trim($idKelas), $m)) {
            return null;
        }

        return [$m[1], $m[2], $m[3]];
    }

    private function tingkatDariId(?string $idKelas): ?string
    {
        return $this->uraikanIdKelas($idKelas)[0] ?? null;
    }

    private function susunRingkasan(int $selisih, array $nisDipilih, array $hasil, int $menungguKaprog = 0, int $rencanaDitahan = 0, int $tidakDicentang = 0): string
    {
        $bagian = [];

        if ($hasil['naik'] > 0)        $bagian[] = "{$hasil['naik']} siswa naik kelas";
        if ($hasil['turun'] > 0)       $bagian[] = "{$hasil['turun']} siswa turun kelas";
        if ($hasil['lulus'] > 0)       $bagian[] = "{$hasil['lulus']} siswa lulus menjadi alumni";
        if ($hasil['ditempatkan'] > 0) $bagian[] = "{$hasil['ditempatkan']} siswa kelas 10 ditempatkan ke kelas 11 sesuai rencana Ketua Program";
        if ($hasil['tetap'] > 0)       $bagian[] = "{$hasil['tetap']} siswa tetap di kelas 10";
        if ($hasil['dilewati'] > 0)    $bagian[] = "{$hasil['dilewati']} siswa dilewati (kelas tidak dikenali)";

        if (!empty($bagian)) {
            $teks = ucfirst(implode(', ', $bagian)) . '.';
        } elseif (empty($nisDipilih)) {
            $teks = 'Tidak ada siswa yang dipilih, hanya tahun ajaran yang diganti.';
        } else {
            $teks = 'Tidak ada siswa aktif yang berubah kelasnya.';
        }

        if ($tidakDicentang > 0) {
            $teks .= " {$tidakDicentang} siswa kelas 11/12 tidak dicentang sehingga tetap di kelasnya.";
        }

        if ($hasil['rencana_basi'] > 0) {
            $teks .= " {$hasil['rencana_basi']} rencana Ketua Program dibuang karena siswa atau kelas tujuannya sudah berubah.";
        }

        if ($rencanaDitahan > 0) {
            $teks .= " {$rencanaDitahan} rencana Ketua Program tidak diterapkan karena tahun ajaran naik lebih dari 1 tahun.";
        }

        if ($menungguKaprog > 0) {
            $teks .= " {$menungguKaprog} siswa kelas 10 belum ditempatkan ke kelas 11 dan masih di kelas 10.";
        }

        return $teks;
    }
}