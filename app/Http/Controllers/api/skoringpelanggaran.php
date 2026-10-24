<?php

namespace App\Http\Controllers\api;

use App\Models\siswa;
use App\Models\penilaian;
use Illuminate\Http\Request;
use App\Models\aspek_penilaian;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class skoringpelanggaran extends Controller
{
    public function index()
    {
        $penilaian = penilaian::whereHas('aspek_penilaian', function ($q) {
            $q->where('jenis_poin', 'Pelanggaran');
        })->paginate(10);

        $siswa    = siswa::all();
        $aspekPel = aspek_penilaian::where('jenis_poin', 'Pelanggaran')->get();

        return response()->json([
            'penilaian' => $penilaian,
            'siswa'     => $siswa,
            'aspekPel'  => $aspekPel
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_penilaian' => 'required|unique:penilaian,id_penilaian',
            'nis' => 'required',
            'id_aspekpenilaian' => 'required',
        ]);

        try {
            $user = $request->user() ?? (object) [
                'gurubk' => (object) ['nip_bk' => null],
                'walikelas' => (object) ['nip_walikelas' => null],
                'wakasek' => (object) ['nip_wakasek' => null],
                'id' => null,
            ];

            $aspek = aspek_penilaian::findOrFail($request->id_aspekpenilaian);
            $skor = (int) $aspek->indikator_poin;
            $uraian = $aspek->uraian;

            $siswa = siswa::where('nis', $request->nis)->first();
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa dengan NIS ' . $request->nis . ' tidak ditemukan.',
                ], 404);
            }

            // Penilaian, poin siswa, dan log disimpan atomik: gagal sebagian = batal semua.
            $penilaian = DB::transaction(function () use ($request, $user, $siswa, $skor, $uraian) {
                $penilaian = penilaian::create([
                    'id_penilaian' => $request->id_penilaian,
                    'nis' => $request->nis,
                    'id_aspekpenilaian' => $request->id_aspekpenilaian,
                    'nip_bk' => $user->gurubk->nip_bk ?? null,
                    'nip_walikelas' => $user->walikelas->nip_walikelas ?? null,
                    'nip_wakasek' => $user->wakasek->nip_wakasek ?? null,
                    'created_at' => now(),
                ]);


                $siswa->poin_pelanggaran += $skor;
                $siswa->poin_total -= $skor;
                $siswa->save();

                DB::table('activity_logs')->insert([
                    'user_id' => $user->id ?? null,
                    'nis' => $siswa->nis,
                    'kategori' => 'Pelanggaran',
                    'activity' => 'Tambah Pelanggaran',
                    'description' => $uraian,
                    'point' => $skor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $penilaian;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data pelanggaran berhasil ditambahkan.',
                'penilaian' => $penilaian,
                'siswa' => $siswa,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Aspek penilaian tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Gagal menyimpan skoring API', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pelanggaran',
            ], 500);
        }
    }
}