<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\jurusan;
use App\Models\kelas;
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
 * - Kelas tujuan dihitung dari id_kelas: "X-RPL-1" -> "XI-RPL-1" -> "XII-RPL-1".
 * - Kelas tujuan HARUS sudah ada di tabel kelas (foreign key). Jika ada yang belum
 *   ada, seluruh proses dibatalkan (tidak ada data setengah berubah) dan pesan
 *   menyebutkan kelas apa yang perlu dibuat dulu.
 * - Kelas XII yang naik -> id_kelas "ALUMNI" + status "alumni".
 * - Turun kelas tidak pernah menghidupkan kembali alumni dan tidak turun di bawah X.
 */
class TahunAjaranController extends Controller
{
    private const TINGKAT = ['X', 'XI', 'XII'];
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

        $preview = [
            'x_ke_xi'   => $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === 'X')->count(),
            'xi_ke_xii' => $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === 'XI')->count(),
            'lulus'     => $siswaList->filter(fn ($s) => $this->tingkatDariId($s->id_kelas) === 'XII')->count(),
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
            $urutanId   = tahunAjaran::orderBy('id', 'asc')->pluck('id')->map(fn ($v) => (int) $v)->values();
            $idxSekarang = $urutanId->search((int) $tahunSekarang->id);
            $idxBaru     = $urutanId->search((int) $tahunBaru->id);

            if ($idxSekarang === false || $idxBaru === false) {
                throw new RuntimeException('Data tahun ajaran tidak konsisten. Hubungi administrator.');
            }

            $selisih  = $idxBaru - $idxSekarang;
            $nisDipilih = $this->ambilNisTerpilih($request->input('siswa_nis'));

            $hasil = DB::transaction(function () use ($selisih, $nisDipilih, $tahunBaru) {
                $hasil = $this->pindahkanKelas($nisDipilih, $selisih);

                tahunAjaran::where('id', '!=', $tahunBaru->id)->update(['status' => 'nonaktif']);
                $tahunBaru->update(['status' => 'aktif']);

                return $hasil;
            });

            $ringkasan = $this->susunRingkasan($selisih, $nisDipilih, $hasil);

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
     * Wajib dipanggil di dalam DB::transaction.
     *
     * @param  array<int,string>  $nisList
     * @return array{naik:int, turun:int, lulus:int, tetap:int, dilewati:int}
     */
    private function pindahkanKelas(array $nisList, int $selisih): array
    {
        $hasil = ['naik' => 0, 'turun' => 0, 'lulus' => 0, 'tetap' => 0, 'dilewati' => 0];

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
        $rencana = [];
        $jenisPerNis = [];

        foreach ($siswaTerpilih as $s) {
            $uraian = $this->uraikanIdKelas($s->id_kelas);

            if ($uraian === null) {
                $hasil['dilewati']++;
                continue;
            }

            [$tingkat, $kode, $nomor] = $uraian;
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
     * "XI-RPL-2" => ['XI', 'RPL', '2']. Mengembalikan null bila formatnya tidak dikenali
     * (mis. NULL, "ALUMNI").
     */
    private function uraikanIdKelas(?string $idKelas): ?array
    {
        if (!$idKelas) {
            return null;
        }

        if (!preg_match('/^(XII|XI|X)-(.+)-([^-]+)$/', trim($idKelas), $m)) {
            return null;
        }

        return [$m[1], $m[2], $m[3]];
    }

    private function tingkatDariId(?string $idKelas): ?string
    {
        return $this->uraikanIdKelas($idKelas)[0] ?? null;
    }

    private function susunRingkasan(int $selisih, array $nisDipilih, array $hasil): string
    {
        if (empty($nisDipilih)) {
            return 'Tidak ada siswa yang dipilih, hanya tahun ajaran yang diganti.';
        }

        $bagian = [];

        if ($hasil['naik'] > 0)  $bagian[] = "{$hasil['naik']} siswa naik kelas";
        if ($hasil['turun'] > 0) $bagian[] = "{$hasil['turun']} siswa turun kelas";
        if ($hasil['lulus'] > 0) $bagian[] = "{$hasil['lulus']} siswa lulus menjadi alumni";
        if ($hasil['tetap'] > 0) $bagian[] = "{$hasil['tetap']} siswa tetap di kelas X";
        if ($hasil['dilewati'] > 0) $bagian[] = "{$hasil['dilewati']} siswa dilewati (kelas tidak dikenali)";

        return empty($bagian)
            ? 'Tidak ada siswa aktif yang berubah kelasnya.'
            : ucfirst(implode(', ', $bagian)) . '.';
    }
}