<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\siswa;
use App\Models\kelas;
use App\Models\tahunAjaran;
use Illuminate\Support\Facades\DB;

/**
 * Manajemen tahun ajaran.
 * CRUD periode akademik untuk data relasi.
 */
class TahunAjaranController extends Controller
{
    public function index()
    {
        $tahunAjaran = tahunAjaran::orderBy('id', 'asc')->get();
        $tahunAktif  = tahunAjaran::where('status', 'aktif')->first();

        $preview = [
            'x_ke_xi'   => siswa::where('id_kelas', 'like', 'X-%')->where('status', 'aktif')->count(),
            'xi_ke_xii' => siswa::where('id_kelas', 'like', 'XI-%')->where('status', 'aktif')->count(),
            'lulus'     => siswa::where('id_kelas', 'like', 'XII-%')->where('status', 'aktif')->count(),
        ];

        return view('wakasek.tahun_ajaran.index', [
            'preview'     => $preview,
            'tahunAjaran' => $tahunAjaran,
            'tahunAktif'  => $tahunAktif,
        ]);
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id'
            ]);

            $tahunBaru     = tahunAjaran::find($request->tahun_ajaran_id);
            $tahunSekarang = tahunAjaran::where('status', 'aktif')->first();

            if (!$tahunSekarang) {
                $tahunBaru->update(['status' => 'aktif']);
                return back()->with('success', 'Tahun ajaran berhasil diaktifkan.');
            }

            if ($tahunSekarang->id === $tahunBaru->id) {
                return back()->with('success', 'Tahun ajaran ini sudah aktif.');
            }

            $selisih = $tahunBaru->id - $tahunSekarang->id;

            $siswaNis = $request->input('siswa_nis');
            $siswaNisTerpilih = [];

            if (is_string($siswaNis)) {
                $siswaNisTerpilih = array_values(array_filter(array_map('trim', explode(',', $siswaNis)), fn ($nis) => $nis !== ''));
            } elseif (is_array($siswaNis)) {
                $siswaNisTerpilih = array_values(array_filter($siswaNis, fn ($nis) => $nis !== ''));
            }

            $siswaNisUntukDipindahkan = $siswaNisTerpilih;

            DB::transaction(function () use ($selisih, $tahunSekarang, $tahunBaru, $siswaNisUntukDipindahkan) {
                if ($selisih > 0) {
                    for ($i = 0; $i < $selisih; $i++) {
                        $this->naikKelas($siswaNisUntukDipindahkan);
                    }
                } elseif ($selisih < 0) {
                    for ($i = 0; $i < abs($selisih); $i++) {
                        $this->turunKelas($siswaNisUntukDipindahkan);
                    }
                }

                $tahunSekarang->update(['status' => 'nonaktif']);
                $tahunBaru->update(['status' => 'aktif']);
            });

            return back()->with('success', 'Tahun ajaran berhasil diubah.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan');
        }
    }

    private function naikKelas(array $siswaNis = []): void
    {
        try {
            // Hanya siswa yang dipilih yang diproses.
            $siswaTerpilih = siswa::where('status', 'aktif')
                ->whereIn('nis', $siswaNis);

            // Proses XII dulu → alumni
            (clone $siswaTerpilih)
                ->where('id_kelas', 'like', 'XII-%')
                ->each(function ($s) {
                    [$tingkat, $jurusan, $nomor] = array_pad(explode('-', $s->id_kelas, 3), 3, null);
                    if (!$jurusan || !$nomor) return;

                    $s->id_kelas = 'ALUMNI';
                    $s->status   = 'alumni';
                    $s->save();
                });

            // Lalu XI → XII
            (clone $siswaTerpilih)
                ->where('id_kelas', 'like', 'XI-%')
                ->each(function ($s) {
                    [$tingkat, $jurusan, $nomor] = array_pad(explode('-', $s->id_kelas, 3), 3, null);
                    if (!$jurusan || !$nomor) return;

                    $s->id_kelas = "XII-{$jurusan}-{$nomor}";
                    $s->save();
                });

            // Terakhir X → XI
            (clone $siswaTerpilih)
                ->where('id_kelas', 'like', 'X-%')
                ->each(function ($s) {
                    [$tingkat, $jurusan, $nomor] = array_pad(explode('-', $s->id_kelas, 3), 3, null);
                    if (!$jurusan || !$nomor) return;

                    $s->id_kelas = "XI-{$jurusan}-{$nomor}";
                    $s->save();
                });
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function turunKelas(array $siswaNis = []): void
    {
        try {
            // Hanya siswa yang dipilih yang diproses.
            $siswaTerpilih = siswa::where('status', 'aktif')
                ->whereIn('nis', $siswaNis);

            // Proses X dulu → tidak berubah, skip
            // XI → X dulu
            (clone $siswaTerpilih)
                ->where('id_kelas', 'like', 'XI-%')
                ->each(function ($s) {
                    [$tingkat, $jurusan, $nomor] = array_pad(explode('-', $s->id_kelas, 3), 3, null);
                    if (!$jurusan || !$nomor) return;

                    $s->id_kelas = "X-{$jurusan}-{$nomor}";
                    $s->save();
                });

            // Lalu XII → XI
            (clone $siswaTerpilih)
                ->where('id_kelas', 'like', 'XII-%')
                ->each(function ($s) {
                    [$tingkat, $jurusan, $nomor] = array_pad(explode('-', $s->id_kelas, 3), 3, null);
                    if (!$jurusan || !$nomor) return;

                    $s->id_kelas = "XI-{$jurusan}-{$nomor}";
                    $s->save();
                });
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
