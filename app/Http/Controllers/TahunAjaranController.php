<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\RencanaPindahKelas;
use App\Models\siswa;
use App\Models\StudentClassHistory;
use App\Models\tahunAjaran;
use App\Models\walikelas;
use App\Support\BackupData;
use App\Support\StatusPerencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Manajemen tahun ajaran + kenaikan / penurunan kelas siswa.
 *
 * Aturan utama:
 * - Hanya siswa berstatus "aktif" dan yang DIPILIH (checkbox) yang dipindahkan.
 * - Kelas tujuan dihitung dari id_kelas: "11-RPL-1" -> "12-RPL-1".
 * - KENAIKAN (tahun ajaran maju) ditentukan CENTANG untuk kelas 10, 11, dan 12 dengan aturan yang sama:
 *     * dicentang       -> naik. Kelas 10 -> 11 dan kelas 11 -> 12 memakai kelas rencana Ketua Program
 *                          (PerpindahanKelasController) bila ada; kalau tidak ada, dipakai kelas pasangan
 *                          ("10-RPL-1" -> "11-RPL-1"). Kelas 12 -> Alumni (id_kelas "ALUMNI", status "alumni").
 *     * tidak dicentang -> TIDAK NAIK: tetap di kelasnya, dicatat mengulang di Riwayat Kelas dan WAJIB beralasan.
 *   Rencana Ketua Program milik siswa yang tidak dicentang dibuang (perlu dibuat ulang untuk tahun ajaran baru).
 *   Naik lebih dari 1 tahun: rencana Ketua Program tidak dipakai dan tetap tersimpan; siswa yang dicentang naik
 *   sejauh selisihnya lewat kelas pasangan.
 * - Kelas tujuan HARUS sudah ada di tabel kelas (foreign key). Jika ada yang belum
 *   ada, seluruh proses dibatalkan (tidak ada data setengah berubah) dan pesan
 *   menyebutkan kelas apa yang perlu dibuat dulu.
 * - Turun kelas tidak pernah menghidupkan kembali alumni dan tidak turun di bawah 10.
 * - Setiap kali tahun ajaran diganti (naik maupun turun), kelas semua walikelas dikosongkan
 *   (walikelas.id_kelas = NULL). Wakasek memasang walikelas ke kelas barunya lewat menu Walikelas.
 *   Akun walikelas tidak dihapus.
 * - Sebelum data diubah, titik pemulihan dibuat otomatis (BackupData). Jika backup gagal, proses
 *   dibatalkan. Dipulihkan lewat: php artisan backup:pulihkan, atau tombol "Pulihkan sekarang" di menu Data Backup
 *   (Super Admin). Backup ikut menyimpan Riwayat Kelas, dan pemulihan menghapus riwayat yang dibuat setelah backup.
 * - Saat tahun ajaran NAIK, setiap siswa aktif kelas 10 / 11 / 12 yang tetap di kelasnya dicatat di Riwayat Kelas
 *   sebagai TIDAK NAIK / mengulang (jenis tinggal_kelas) dan WAJIB beralasan.
 *   Jenis BELUM DIPROSES (belum_diproses) hanya untuk siswa yang kelasnya tidak dikenali (bukan 10 / 11 / 12) dan
 *   data lama; tampil di daftar "Siswa Belum Diproses" sampai dipindah atau ditandai mengulang lewat tandaiMengulang().
 *   Alasan dikirim sebagai JSON {nis: alasan} lewat input alasan_tidak_naik. Kalau ada siswa tidak naik yang
 *   belum punya alasan, proses dibatalkan dan tidak ada data yang berubah.
 * - SYARAT NAIK TAHUN: semua Ketua Program harus sudah selesai merencanakan penempatan kelas 10 -> 11
 *   (StatusPerencanaan, dihitung per jurusan: belum / sebagian / selesai). Selama ada jurusan yang belum
 *   selesai, ganti tahun ke tahun yang lebih baru DITOLAK di server (dan tombolnya ditahan di halaman),
 *   sebelum backup dibuat dan sebelum ada data yang berubah.
 * - TAHUN DIKUNCI: tahun ajaran hanya boleh maju. Mundur ke tahun lama ditolak karena kelas siswa tidak
 *   bisa dikembalikan hanya dengan mengubah status tahun. Kesalahan ditangani lewat pemulihan backup
 *   (php artisan backup:pulihkan) oleh administrator. Kelas siswa pada saat kejadian (catatan, pelanggaran,
 *   pembinaan) tetap bisa dilihat dari Riwayat Kelas lewat App\Support\KelasSaatKejadian.
 * - Jika daftar siswa yang dikirim KOSONG padahal ada siswa aktif yang bisa dicentang (naik: kelas 10 / 11 / 12;
 *   turun: kelas 11 / 12), proses dibatalkan (kecuali pengguna sengaja mengonfirmasi lewat tanpa_siswa=1),
 *   supaya tidak terjadi kasus semua siswa tidak naik karena daftar centang tidak terkirim.
 */
class TahunAjaranController extends Controller
{
    private const TINGKAT = ['10', '11', '12'];
    private const TINGKAT_DIATUR_KAPROG = '10';
    private const TINGKAT_HASIL_KAPROG  = '11';
    // Rencana Ketua Program 11 -> 12: hanya menentukan KELAS TUJUAN, siapa yang naik tetap dipilih lewat centang.
    private const TINGKAT_ASAL_RENCANA_12   = '11';
    private const TINGKAT_TUJUAN_RENCANA_12 = '12';
    // Rencana Ketua Program 12 -> Alumni: menandai siswa kelas 12 yang lulus.
    private const TINGKAT_DILULUSKAN_KAPROG = '12';
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

        // Rencana penempatan kelas 10 -> 11, 11 -> 12 dan 12 -> Alumni dari Ketua Program, kunci = NIS.
        $rencanaMap = RencanaPindahKelas::with('kelasTujuan')
            ->get()
            ->keyBy(fn ($r) => (string) $r->nis);

        $siswaKelas10 = $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '10');
        $siswaKelas11 = $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '11');
        $siswaKelas12 = $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === '12');

        $preview = [
            // Kelas 10 -> 11 diatur Ketua Program: total siswa kelas 10 dan yang sudah direncanakan.
            'kelas10_ke_11'        => $siswaKelas10->count(),
            'kelas10_direncanakan' => $siswaKelas10->filter(fn ($s) => $rencanaMap->has((string) $s->nis))->count(),
            'kelas11_ke_12'        => $siswaKelas11->count(),
            'kelas11_direncanakan' => $siswaKelas11->filter(fn ($s) => $rencanaMap->has((string) $s->nis))->count(),
            'lulus'                => $siswaKelas12->count(),
            'lulus_direncanakan'   => $siswaKelas12->filter(
                fn ($s) => ($rencanaMap[(string) $s->nis]->id_kelas_tujuan ?? null) === self::ID_ALUMNI
            )->count(),
        ];

        $kelasList   = kelas::with('jurusan')
            ->where('id_kelas', '!=', self::ID_ALUMNI)
            ->orderBy('id_kelas')
            ->get();
        $jurusanList = jurusan::all();

        $terbaruTertinggal = StudentClassHistory::terbaruTertinggal();

        // Status perencanaan Ketua Program per jurusan (syarat naik tahun ajaran).
        $statusPerencanaan = StatusPerencanaan::hitung();

        return view('wakasek.tahun_ajaran.index', [
            'preview'     => $preview,
            'tahunAjaran' => $tahunAjaran,
            'tahunAktif'  => $tahunAktif,
            'opsiTahun'   => $opsiTahun,
            'siswaList'   => $siswaList,
            'kelasList'   => $kelasList,
            'jurusanList' => $jurusanList,
            'rencanaMap'  => $rencanaMap,
            'alasanPreset' => StudentClassHistory::ALASAN_PRESET,
            'statusJurusan'      => $statusPerencanaan['jurusan'],
            'perencanaanLengkap' => $statusPerencanaan['lengkap'],
            // Penanda tiap siswa aktif yang tertinggal: [nis => riwayat terbaru], dan daftar yang belum diproses.
            'penandaMap'    => $terbaruTertinggal->keyBy(fn ($h) => (string) $h->nis),
            'belumDiproses' => StudentClassHistory::belumDiproses($terbaruTertinggal),
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

            // Tahun ajaran dikunci: tidak bisa mundur. Mengubah status tahun saja tidak mengembalikan kelas
            // siswa, riwayat kelas, maupun rencana Ketua Program, sehingga data akan tidak cocok dengan tahunnya.
            if ($selisih < 0) {
                throw new RuntimeException(
                    "Tahun ajaran tidak bisa dimundurkan ke {$tahunBaru->tahun_ajaran}. "
                    . 'Kelas siswa tidak ikut kembali, jadi data akan tidak cocok dengan tahunnya. '
                    . 'Kalau ganti tahun salah, minta administrator memulihkan backup otomatis '
                    . '(menu Data Backup Super Admin, atau php artisan backup:pulihkan). Tidak ada data yang diubah.'
                );
            }

            // Syarat naik tahun: semua Ketua Program harus selesai merencanakan dulu.
            if ($selisih > 0) {
                $pesanTahan = StatusPerencanaan::pesanTahan();

                if ($pesanTahan !== null) {
                    throw new RuntimeException($pesanTahan);
                }
            }

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

            // Alasan tidak naik kelas per siswa: JSON {"nis":"alasan"} dari jendela konfirmasi.
            $alasanTidakNaik = $this->ambilAlasanTidakNaik($request->input('alasan_tidak_naik'));

            // Pengaman: daftar siswa kosong padahal ada siswa kelas 11/12 yang aktif biasanya berarti
            // daftar centang tidak terkirim dari browser (bukan pilihan sengaja). Tanpa pengaman ini,
            // hanya kelas 10 (rencana Ketua Program) yang pindah dan kelas 11/12 diam-diam tertinggal.
            // Pilihan sengaja "tanpa menaikkan siswa" dikirim form lewat tanpa_siswa=1 (dari modal konfirmasi).
            // Saat naik, kelas 10 / 11 / 12 semuanya ditentukan centang; saat turun hanya kelas 11 / 12.
            $tingkatDiproses = $this->hitungSiswaKelas11dan12($selisih > 0);

            $labelTingkat = $selisih > 0 ? 'kelas 10 / 11 / 12' : 'kelas 11 / 12';

            if ($selisih !== 0 && empty($nisDipilih) && !$request->boolean('tanpa_siswa') && $tingkatDiproses > 0) {
                Log::warning('Ganti tahun ajaran dibatalkan: daftar siswa kosong', [
                    'tahun_ajaran_id' => $tahunBaru->id,
                    'siswa_nis_raw'   => is_string($request->input('siswa_nis'))
                        ? strlen($request->input('siswa_nis')) . ' karakter'
                        : gettype($request->input('siswa_nis')),
                ]);

                throw new RuntimeException(
                    "Tidak ada daftar siswa {$labelTingkat} yang diterima server, padahal ada {$tingkatDiproses} siswa aktif di {$labelTingkat}. "
                    . 'Tahun ajaran BELUM diganti dan tidak ada data yang berubah. '
                    . 'Muat ulang halaman (Ctrl+F5), centang siswa, lalu klik Ganti Tahun Ajaran dan konfirmasi di jendela yang muncul.'
                );
            }

            // Siswa aktif yang tidak dicentang akan tetap di kelasnya.
            $tidakDicentang = max(0, $tingkatDiproses - $this->hitungDicentangAktif($nisDipilih, $selisih > 0));

            // Backup otomatis SEBELUM data diubah. Kalau gagal dibuat, RuntimeException dilempar
            // dan proses berhenti di sini tanpa mengubah apa pun.
            $fileBackup = basename(BackupData::buat(
                "Ganti tahun ajaran {$tahunSekarang->tahun_ajaran} -> {$tahunBaru->tahun_ajaran}",
                "sebelum-ganti-tahun_{$tahunSekarang->tahun_ajaran}_ke_{$tahunBaru->tahun_ajaran}"
            ));

            $hasil = DB::transaction(function () use ($selisih, $nisDipilih, $tahunBaru, $tahunSekarang, $alasanTidakNaik) {
                // 0) Foto kelas semua siswa aktif SEBELUM ada yang berubah. Dipakai di akhir untuk
                //    mencatat Riwayat Kelas (kelas sebelumnya) di halaman detail siswa.
                $kelasSebelum = siswa::query()
                    ->where('status', 'aktif')
                    ->pluck('id_kelas', 'nis')
                    ->all();

                // 1) Kelas 11 -> 12 dan 12 -> Alumni (atau turun kelas) untuk siswa yang dicentang.
                $hasil = $this->pindahkanKelas($nisDipilih, $selisih);

                // 2) Naik tepat 1 tahun: rencana Ketua Program milik siswa yang dipakai sudah dihapus di langkah 1.
                //    Yang tersisa milik siswa yang tidak dicentang (tidak naik), jadi dibuang.
                if ($selisih === 1) {
                    $hasil = array_merge($hasil, $this->buangSisaRencana());
                }

                // 3) Catat Riwayat Kelas: bandingkan kelas sebelum vs sesudah untuk semua siswa aktif tadi.
                $jenis = $selisih > 0 ? 'Kenaikan kelas' : 'Penurunan kelas';
                $keteranganRiwayat = "{$jenis}, tahun ajaran {$tahunSekarang->tahun_ajaran} -> {$tahunBaru->tahun_ajaran}";

                $hasil['riwayat_dicatat'] = StudentClassHistory::catatPerubahanMassal(
                    $kelasSebelum,
                    $keteranganRiwayat,
                    $selisih > 0 ? StudentClassHistory::JENIS_NAIK : StudentClassHistory::JENIS_TURUN,
                    $tahunSekarang->tahun_ajaran
                );

                // 3b) Naik tahun: siswa aktif yang TETAP di kelasnya = tidak naik kelas (mengulang).
                //     Dicatat lengkap dengan alasan supaya tidak hilang tanpa jejak.
                $hasil['tidak_naik']     = 0;
                $hasil['belum_diproses'] = 0;

                if ($selisih > 0) {
                    $tertinggal = StudentClassHistory::cariTidakNaik($kelasSebelum);

                    // Kelas 10 / 11 / 12 yang tidak dicentang = keputusan tidak naik, alasan WAJIB.
                    // Kelas lain yang tidak dikenali tanpa alasan = hanya belum diproses.
                    $tanpaAlasan = array_values(array_filter(
                        $tertinggal,
                        fn ($nis) => empty($alasanTidakNaik[$nis])
                            && in_array(kelas::tingkatDariId((string) ($kelasSebelum[$nis] ?? '')), self::TINGKAT, true)
                    ));

                    if (!empty($tanpaAlasan)) {
                        $nama = siswa::query()
                            ->whereIn('nis', array_slice($tanpaAlasan, 0, 5))
                            ->pluck('nama_siswa')
                            ->implode(', ');
                        $sisa = count($tanpaAlasan) > 5 ? ' dan ' . (count($tanpaAlasan) - 5) . ' lainnya' : '';

                        throw new RuntimeException(
                            count($tanpaAlasan) . " siswa tidak naik kelas belum diberi alasan ({$nama}{$sisa}). "
                            . 'Tahun ajaran BELUM diganti dan tidak ada data yang berubah. '
                            . 'Klik Ganti Tahun Ajaran lagi, lalu isi alasan untuk setiap siswa di jendela konfirmasi.'
                        );
                    }

                    $catat = StudentClassHistory::catatTertinggal(
                        $tertinggal,
                        $kelasSebelum,
                        $alasanTidakNaik,
                        $tahunSekarang->tahun_ajaran,
                        "tahun ajaran {$tahunSekarang->tahun_ajaran} -> {$tahunBaru->tahun_ajaran}"
                    );

                    $hasil['tidak_naik']     = $catat['tidak_naik'];
                    $hasil['belum_diproses'] = $catat['belum_diproses'];
                }

                // 4) Tahun ajaran berganti: semua walikelas dikosongkan dari kelasnya.
                $hasil['walikelas_dikosongkan'] = $this->kosongkanWalikelas();

                tahunAjaran::where('id', '!=', $tahunBaru->id)->update(['status' => 'nonaktif']);
                $tahunBaru->update(['status' => 'aktif']);

                return $hasil;
            });

            // Siswa yang tidak naik sudah dihitung di $hasil['tidak_naik'] (semua tingkat), bukan "menunggu Kaprog".
            $menungguKaprog        = 0;
            $hasil['sisa_kelas12'] = 0;

            // Naik lebih dari 1 tahun: rencana tidak diterapkan, tetap tersimpan.
            $rencanaDitahan = $selisih > 1 ? RencanaPindahKelas::query()->count() : 0;

            $ringkasan = $this->susunRingkasan($selisih, $nisDipilih, $hasil, $menungguKaprog, $rencanaDitahan, $tidakDicentang);

            ActivityLog::catat(
                ActivityLog::KATEGORI_SISWA,
                $selisih > 0 ? 'Kenaikan Kelas' : 'Penurunan Kelas',
                "Tahun ajaran {$tahunSekarang->tahun_ajaran} -> {$tahunBaru->tahun_ajaran}. {$ringkasan} Backup: {$fileBackup}"
            );

            return back()->with(
                'success',
                "Tahun ajaran berhasil diubah ke {$tahunBaru->tahun_ajaran}. {$ringkasan} "
                . "Backup otomatis tersimpan: {$fileBackup} (pulihkan lewat menu Data Backup Super Admin atau perintah php artisan backup:pulihkan)."
            );
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
     * Hanya siswa yang dicentang (kelas 10 / 11 / 12 sama saja). Kelas 10 -> 11 dan 11 -> 12 memakai kelas rencana
     * Ketua Program bila ada, selain itu kelas pasangan; kelas 12 naik -> Alumni.
     * Wajib dipanggil di dalam DB::transaction.
     *
     * @param  array<int,string>  $nisList
     * @return array{naik:int, turun:int, lulus:int, tetap:int, dilewati:int, diserahkan:int, ditempatkan:int, rencana_basi:int, ditempatkan12:int, rencana12_basi:int, rencana12_dibuang:int, diluluskan:int, sisa_kelas12:int}
     */
    private function pindahkanKelas(array $nisList, int $selisih): array
    {
        $hasil = [
            'naik' => 0, 'turun' => 0, 'lulus' => 0, 'tetap' => 0,
            'dilewati' => 0, 'diserahkan' => 0, 'ditempatkan' => 0, 'rencana_basi' => 0,
            'ditempatkan12' => 0, 'rencana12_basi' => 0, 'rencana12_dibuang' => 0,
            'diluluskan' => 0, 'sisa_kelas12' => 0,
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

        // Rencana Ketua Program 10 -> 11 dan 11 -> 12 (hanya dipakai saat naik tepat 1 tahun).
        $rencanaKelas = [];
        if ($selisih === 1) {
            $cek          = $this->ambilRencanaTujuan($siswaTerpilih);
            $rencanaKelas = $cek['valid'];
            $hasil['rencana12_basi'] = $cek['basi'];
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

            // Kelas 10 -> 11 dan 11 -> 12: pakai kelas rencana Ketua Program bila ada.
            if ($selisih === 1
                && in_array($tingkat, [self::TINGKAT_DIATUR_KAPROG, self::TINGKAT_ASAL_RENCANA_12], true)
                && isset($rencanaKelas[(string) $s->nis])
            ) {
                $tujuanRencana = $rencanaKelas[(string) $s->nis];

                $rencana[$tujuanRencana][]     = (string) $s->nis;
                $jenisPerNis[(string) $s->nis] = 'naik';

                if ($tingkat === self::TINGKAT_DIATUR_KAPROG) {
                    $hasil['ditempatkan']++;
                } else {
                    $hasil['ditempatkan12']++;
                }

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

        // Siswa yang kelasnya baru berubah tidak boleh meninggalkan rencana lama (rencana yang dipakai
        // maupun yang tidak valid), supaya tidak diterapkan salah di langkah berikutnya.
        foreach (array_chunk(array_map('strval', array_keys($jenisPerNis)), self::CHUNK) as $potongan) {
            RencanaPindahKelas::query()->whereIn('nis', $potongan)->delete();
        }

        return $hasil;
    }

    /**
     * Rencana Ketua Program untuk siswa kelas 10 (-> 11) dan kelas 11 (-> 12) yang dicentang.
     * Rencana valid = tujuannya satu tingkat di atas kelas asal siswa dan kelas itu ada di tabel kelas.
     * Rencana yang tidak valid tidak dipakai (siswa mengikuti kelas pasangannya).
     *
     * @param  \Illuminate\Support\Collection  $siswaTerpilih
     * @return array{valid:array<string,string>, basi:int}
     */
    private function ambilRencanaTujuan($siswaTerpilih): array
    {
        $tingkatAsal = [];
        foreach ($siswaTerpilih as $s) {
            $t = $this->tingkatDariId($s->id_kelas);

            if (in_array($t, [self::TINGKAT_DIATUR_KAPROG, self::TINGKAT_ASAL_RENCANA_12], true)) {
                $tingkatAsal[(string) $s->nis] = $t;
            }
        }

        $hasil = ['valid' => [], 'basi' => 0];

        if (empty($tingkatAsal)) {
            return $hasil;
        }

        $baris = collect();
        foreach (array_chunk(array_keys($tingkatAsal), self::CHUNK) as $potongan) {
            $baris = $baris->concat(
                RencanaPindahKelas::query()
                    ->whereIn('nis', array_map('strval', $potongan))
                    ->get(['nis', 'id_kelas_tujuan'])
            );
        }

        if ($baris->isEmpty()) {
            return $hasil;
        }

        $idAda = kelas::query()
            ->whereIn('id_kelas', $baris->pluck('id_kelas_tujuan')->unique()->values()->all())
            ->pluck('id_kelas')
            ->all();

        foreach ($baris as $r) {
            $tujuan  = (string) $r->id_kelas_tujuan;
            $asal    = $tingkatAsal[(string) $r->nis] ?? null;
            $harusnya = $asal === self::TINGKAT_DIATUR_KAPROG ? self::TINGKAT_HASIL_KAPROG : self::TINGKAT_TUJUAN_RENCANA_12;

            if (kelas::tingkatDariId($tujuan) === $harusnya && in_array($tujuan, $idAda, true)) {
                $hasil['valid'][(string) $r->nis] = $tujuan;
            } else {
                $hasil['basi']++;
            }
        }

        return $hasil;
    }

    /**
     * Buang semua rencana Ketua Program yang tersisa setelah kenaikan kelas.
     * Rencana siswa yang naik sudah dihapus saat dipindahkan, jadi yang tersisa milik siswa yang
     * tidak dicentang (tidak naik) atau sudah tidak berlaku. Wajib dipanggil di dalam DB::transaction.
     *
     * @return array{rencana_dibuang:int}
     */
    private function buangSisaRencana(): array
    {
        $jumlah = RencanaPindahKelas::query()->count();

        RencanaPindahKelas::query()->delete();

        return ['rencana_dibuang' => $jumlah];
    }

    /**
     * Kosongkan kelas semua walikelas (id_kelas = NULL). Akun dan data walikelas tetap ada.
     * Mengembalikan jumlah walikelas yang tadinya memegang kelas.
     * Wajib dipanggil di dalam DB::transaction.
     */
    private function kosongkanWalikelas(): int
    {
        return walikelas::query()
            ->whereNotNull('id_kelas')
            ->update(['id_kelas' => null]);
    }

    /**
     * Jumlah siswa aktif yang bisa dicentang di halaman ini: kelas 10 / 11 / 12 saat $naik,
     * kelas 11 / 12 saat turun.
     */
    private function hitungSiswaKelas11dan12(bool $naik = false): int
    {
        return siswa::query()
            ->where('status', 'aktif')
            ->where(function ($q) use ($naik) {
                $q->where('id_kelas', 'like', '11-%')->orWhere('id_kelas', 'like', '12-%');

                if ($naik) {
                    $q->orWhere('id_kelas', 'like', '10-%');
                }
            })
            ->count();
    }

    /**
     * Berapa dari NIS yang dikirim benar-benar siswa aktif yang bisa dicentang (lihat hitungSiswaKelas11dan12).
     *
     * @param  array<int,string>  $nisList
     */
    private function hitungDicentangAktif(array $nisList, bool $naik = false): int
    {
        $jumlah = 0;

        foreach (array_chunk($nisList, self::CHUNK) as $potongan) {
            $jumlah += siswa::query()
                ->where('status', 'aktif')
                ->where(function ($q) use ($naik) {
                    $q->where('id_kelas', 'like', '11-%')->orWhere('id_kelas', 'like', '12-%');

                    if ($naik) {
                        $q->orWhere('id_kelas', 'like', '10-%');
                    }
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
     * Ubah siswa yang masih "belum diproses" menjadi "tidak naik kelas (mengulang)" beserta alasannya.
     * Hanya Wakasek / Super Admin (dibatasi di route).
     */
    public function tandaiMengulang(Request $request)
    {
        $request->validate([
            'nis'          => 'required|array|min:1',
            'nis.*'        => 'required|digits_between:1,20',
            'alasan_pilih' => ['required', 'string', Rule::in(StudentClassHistory::ALASAN_PRESET)],
            'alasan_teks'  => 'nullable|string|max:255',
        ], [
            'nis.required'          => 'Centang minimal satu siswa yang akan ditandai.',
            'nis.min'               => 'Centang minimal satu siswa yang akan ditandai.',
            'alasan_pilih.required' => 'Pilih alasan tidak naik kelas.',
            'alasan_pilih.in'       => 'Alasan yang dipilih tidak dikenal.',
        ]);

        $alasan = $request->input('alasan_pilih') === StudentClassHistory::ALASAN_LAINNYA
            ? trim((string) $request->input('alasan_teks'))
            : trim((string) $request->input('alasan_pilih'));

        if ($alasan === '') {
            return back()->withInput()->with('error', 'Tulis alasan tidak naik kelas terlebih dahulu.');
        }

        $diubah = StudentClassHistory::tandaiMengulang($request->input('nis', []), $alasan);

        if ($diubah === 0) {
            return back()->with('error', 'Tidak ada siswa yang diubah. Siswa yang dipilih sudah tidak berstatus "Belum diproses".');
        }

        ActivityLog::catat(
            ActivityLog::KATEGORI_SISWA,
            'Tandai Tidak Naik Kelas',
            "{$diubah} siswa berstatus \"Belum diproses\" ditandai tidak naik kelas (mengulang). Alasan: {$alasan}"
        );

        return back()->with('success', "{$diubah} siswa ditandai tidak naik kelas (mengulang). Alasan tersimpan di Riwayat Kelas.");
    }

    /**
     * Ubah input alasan_tidak_naik (JSON {"nis":"alasan"} atau array) menjadi [nis => alasan]
     * yang sudah dibersihkan: NIS angka saja, alasan di-trim dan dipotong 255 karakter,
     * alasan kosong dibuang.
     *
     * @return array<string,string>
     */
    private function ambilAlasanTidakNaik($input): array
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }

        if (!is_array($input)) {
            return [];
        }

        $bersih = [];
        foreach ($input as $nis => $alasan) {
            $nis = trim((string) $nis);

            if ($nis === '' || !ctype_digit($nis) || !is_scalar($alasan)) {
                continue;
            }

            $alasan = trim(preg_replace('/\s+/u', ' ', (string) $alasan));

            if ($alasan !== '') {
                $bersih[$nis] = mb_substr($alasan, 0, 255);
            }
        }

        return $bersih;
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
        if ($hasil['diluluskan'] > 0)  $bagian[] = "{$hasil['diluluskan']} siswa kelas 12 diluluskan menjadi alumni sesuai rencana Ketua Program";
        if ($hasil['ditempatkan12'] > 0) $bagian[] = "{$hasil['ditempatkan12']} siswa kelas 11 ditempatkan ke kelas 12 sesuai rencana Ketua Program";
        if ($hasil['tetap'] > 0)       $bagian[] = "{$hasil['tetap']} siswa tetap di kelas 10";
        if ($hasil['dilewati'] > 0)    $bagian[] = "{$hasil['dilewati']} siswa dilewati (kelas tidak dikenali)";

        if (!empty($bagian)) {
            $teks = ucfirst(implode(', ', $bagian)) . '.';
        } elseif (empty($nisDipilih)) {
            $teks = 'Tidak ada siswa yang dipilih, hanya tahun ajaran yang diganti.';
        } else {
            $teks = 'Tidak ada siswa aktif yang berubah kelasnya.';
        }

        if (($hasil['tidak_naik'] ?? 0) > 0) {
            $teks .= " {$hasil['tidak_naik']} siswa tidak naik kelas (tetap di kelasnya) dan dicatat mengulang di Riwayat Kelas beserta alasannya.";
        } elseif ($tidakDicentang > 0 && ($hasil['belum_diproses'] ?? 0) === 0) {
            $teks .= " {$tidakDicentang} siswa tidak dicentang sehingga tetap di kelasnya.";
        }

        if (($hasil['belum_diproses'] ?? 0) > 0) {
            $teks .= " {$hasil['belum_diproses']} siswa ditandai \"Belum diproses\" di Riwayat Kelas (dibedakan dari yang mengulang); daftarnya ada di halaman ini.";
        }

        if ($hasil['rencana_basi'] > 0) {
            $teks .= " {$hasil['rencana_basi']} rencana Ketua Program dibuang karena siswa atau kelas tujuannya sudah berubah.";
        }

        if (($hasil['rencana_dibuang'] ?? 0) > 0) {
            $teks .= " {$hasil['rencana_dibuang']} rencana Ketua Program dibuang karena siswanya tidak dicentang naik kelas; buat ulang untuk tahun ajaran baru.";
        }

        if ($hasil['rencana12_basi'] > 0) {
            $teks .= " {$hasil['rencana12_basi']} rencana Ketua Program tidak dipakai karena kelas tujuannya tidak valid, siswa dipindah ke kelas pasangannya.";
        }

        if ($rencanaDitahan > 0) {
            $teks .= " {$rencanaDitahan} rencana Ketua Program tidak diterapkan karena tahun ajaran naik lebih dari 1 tahun.";
        }

        if (($hasil['walikelas_dikosongkan'] ?? 0) > 0) {
            $teks .= " {$hasil['walikelas_dikosongkan']} walikelas dikosongkan dari kelasnya, pasang ulang di menu Walikelas.";
        }

        if ($hasil['sisa_kelas12'] > 0) {
            $teks .= " {$hasil['sisa_kelas12']} siswa kelas 12 belum ditandai lulus oleh Ketua Program dan masih di kelas 12.";
        }

        if ($menungguKaprog > 0) {
            $teks .= " {$menungguKaprog} siswa kelas 10 belum ditempatkan ke kelas 11 dan masih di kelas 10.";
        }

        return $teks;
    }
}