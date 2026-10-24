<?php

namespace App\Http\Controllers;

use App\Exports\Intervensi_ExportExcel;
use App\Models\aspek_penilaian;
use App\Models\catatan;
use App\Models\intervensi;
use App\Models\kelas;
use App\Models\ketua_program;
use App\Models\siswa;
use App\Models\walikelas;
use App\Models\bukti_pembinaan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Penanganan intervensi siswa.
 * Catat rencana, tindak lanjut, dan status intervensi.
 */
class IntervensiController extends Controller
{
    public function index(Request $request)
    {
        $user            = Auth::user();
        $kelas           = kelas::all();
        $catatan         = catatan::all();
        $selectedKelas   = null;
        $selectedJurusan = null;

        $siswaList      = siswa::where('poin_pelanggaran', '>', 0);
        $kelasWalikelas = null;

        if ($user->role == 3) {
            $walikelas = walikelas::where('username', $user->username)->first();
            if ($walikelas && $walikelas->id_kelas) {
                $kelasWalikelas  = $walikelas->id_kelas;
                $siswaList->where('id_kelas', $kelasWalikelas);
                $selectedKelas   = $kelasWalikelas;
                $kelasEntity     = kelas::where('id_kelas', $kelasWalikelas)->first();
                $selectedJurusan = $kelasEntity->id_jurusan ?? null;
                $kelas           = kelas::where('id_kelas', $kelasWalikelas)->get();
            }
        } elseif ($user->role == 4) {
            $ketua = ketua_program::where('username', $user->username)->first();
            if ($ketua && $ketua->id_jurusan) {
                $selectedJurusan = $ketua->id_jurusan;
                $kelas           = kelas::where('id_jurusan', $selectedJurusan)->get();
            }
        }

        $siswa = $siswaList->orderBy('nama_siswa')->get();

        $aspekPel = aspek_penilaian::whereIn('jenis_poin', ['Apresiasi', 'Pelanggaran'])
            ->orderBy('jenis_poin')
            ->orderBy('uraian')
            ->get();

        $query = intervensi::with(['siswa.kelas', 'bukti']);

        if ($user->role == 3 && $kelasWalikelas) {
            $query->whereHas('siswa', fn ($q) => $q->where('id_kelas', $kelasWalikelas));
        }
        if ($user->role == 4 && $selectedJurusan) {
            $query->whereHas('siswa', fn ($q) => $q->whereHas('kelas', fn ($k) => $k->where('id_jurusan', $selectedJurusan)));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nis', 'like', "%{$search}%")
                    ->orWhere('nama_siswa', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas')) {
            $query->whereHas('siswa', fn ($q) => $q->where('id_kelas', $request->kelas));
        }

        if ($request->filled('jurusan')) {
            $jur = $request->jurusan;
            $query->whereHas('siswa', fn ($q) => $q->whereHas('kelas', fn ($q2) => $q2->where('id_jurusan', $jur)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_Mulai_Perbaikan', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal_Selesai_Perbaikan', '<=', $request->tanggal_akhir);
        }

        $intervensi = $query->latest()
            ->paginate(10)
            ->appends($request->all());

        return view('wakasek.intervensi.index', compact(
            'intervensi',
            'kelas',
            'siswa',
            'catatan',
            'aspekPel',
            'selectedKelas',
            'selectedJurusan'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nis'                       => 'required',
            'file'                      => 'nullable|array',
            'file.*'                    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'nama_intervensi'           => 'required|string|max:255',
            'isi_intervensi'            => 'required|string|max:1000',
            'tanggal_Mulai_Perbaikan'   => 'required|date',
            'tanggal_Selesai_Perbaikan' => 'required|date|after_or_equal:tanggal_Mulai_Perbaikan',
            'status'                    => 'required|string|max:50',
        ]);

        $user = Auth::user();

        $penanganan = intervensi::create([
            'nis'                       => $request->nis,
            'nip_bk'                    => $user->gurubk->nip_bk ?? null,
            'nip_walikelas'             => $user->walikelas->nip_walikelas ?? null,
            'nip_wakasek'               => $user->wakasek->nip_wakasek ?? null,
            'nama_intervensi'           => $request->nama_intervensi,
            'isi_intervensi'            => $request->isi_intervensi,
            'tanggal_Mulai_Perbaikan'   => $request->tanggal_Mulai_Perbaikan,
            'tanggal_Selesai_Perbaikan' => $request->tanggal_Selesai_Perbaikan,
            'status'                    => $request->status,
            'created_at'                => now(),
        ]);

        if ($request->hasFile('file')) {
            $files = $request->file('file');

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (!$file || !$file->isValid()) continue;

                try {
                    $originalName = $file->getClientOriginalName();
                    $extension    = $file->getClientOriginalExtension();
                    $uniqueName   = time() . '_' . Str::random(8) . '.' . $extension;
                    $path         = $file->storeAs('bukti', $uniqueName, 'public');

                    bukti_pembinaan::create([
                        'intervensi_id' => $penanganan->id_intervensi,
                        'file'          => $path,
                        'nama_file'     => $originalName,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to save bukti (store): ' . $e->getMessage());
                }
            }
        }

        return redirect()->route('intervensi.index')
            ->with('success', 'Data intervensi berhasil ditambahkan.');
    }

    public function show($id_intervensi)
    {
        $intervensi = intervensi::with(['siswa', 'bukti'])->findOrFail($id_intervensi);
        $kelas      = kelas::all();
        $siswa      = siswa::all();
        $catatan    = catatan::all();

        return view('wakasek.intervensi.show', compact('intervensi', 'kelas', 'siswa', 'catatan'));
    }

    public function update(Request $request, string $id_intervensi)
    {
        // FIX 1: validasi file sebagai array, bukan single file
        // FIX 2: hapus_file.* pakai nullable agar tidak gagal saat value berupa string angka
        $request->validate([
            'nis'                          => 'required',
            'nama_intervensi'              => 'required|string|max:255',
            'isi_intervensi'               => 'required|string|max:1000',
            'file'                         => 'nullable|array',
            'file.*'                       => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'tanggal_Mulai_Perbaikan'      => 'required|date',
            'tanggal_Selesai_Perbaikan'    => 'required|date|after_or_equal:tanggal_Mulai_Perbaikan',
            'perubahan_setelah_intervensi' => 'nullable|string|max:1000',
            'status'                       => 'required|string|max:50',
            'hapus_file'                   => 'nullable|array',
            'hapus_file.*'                 => 'nullable',   // FIX: bukan 'integer' agar tidak gagal type check
        ]);

        $intervensi = intervensi::findOrFail($id_intervensi);

        $intervensi->update([
            'nis'                          => $request->nis,
            'nama_intervensi'              => $request->nama_intervensi,
            'isi_intervensi'               => $request->isi_intervensi,
            'tanggal_Mulai_Perbaikan'      => $request->tanggal_Mulai_Perbaikan,
            'tanggal_Selesai_Perbaikan'    => $request->tanggal_Selesai_Perbaikan,
            'perubahan_setelah_intervensi' => $request->perubahan_setelah_intervensi,
            'status'                       => $request->status,
            'updated_at'                   => now(),
        ]);

        // ── Hapus file yang ditandai ──────────────────────────────
        if ($request->filled('hapus_file')) {
            $hapusFiles = $request->input('hapus_file');
            if (is_array($hapusFiles)) {
                foreach ($hapusFiles as $fileId) {
                    if (!$fileId) continue;

                    $fileItem = bukti_pembinaan::find($fileId);

                    // FIX 3: gunakan == (loose) bukan === (strict) agar tidak gagal karena perbedaan tipe int vs string
                    if ($fileItem && $fileItem->intervensi_id == $intervensi->id_intervensi) {
                        Storage::disk('public')->delete($fileItem->file);
                        $fileItem->delete();
                    }
                }
            }
        }

        // ── Upload file baru ──────────────────────────────────────
        if ($request->hasFile('file')) {
            $files = $request->file('file');

            // Pastikan selalu array
            if (!is_array($files)) {
                $files = [$files];
            }
            
            \Log::info('files count: ' . count($files));

            foreach ($files as $file) {
                if (!$file || !$file->isValid()) continue;

                try {
                    $originalName = $file->getClientOriginalName();
                    $extension    = $file->getClientOriginalExtension();
                    $uniqueName   = time() . '_' . Str::random(8) . '.' . $extension;
                    $path         = $file->storeAs('bukti', $uniqueName, 'public');

                    bukti_pembinaan::create([
                        'intervensi_id' => $intervensi->id_intervensi,
                        'file'          => $path,
                        'nama_file'     => $originalName,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to save bukti (update): ' . $e->getMessage());
                }
            }
        }

        $returnTo = $request->input('return_to');
        if ($returnTo) {
            return redirect()->to($returnTo)->with('success', 'Data intervensi berhasil diperbarui.');
        }

        return redirect()->route('intervensi.index')->with('success', 'Data intervensi berhasil diperbarui.');
    }

    public function destroy(string $id_intervensi)
    {
        try {
            $intervensi = intervensi::with('bukti')->findOrFail($id_intervensi);

            if ($intervensi->bukti) {
                foreach ($intervensi->bukti as $bukti) {
                    Storage::disk('public')->delete($bukti->file);
                }
            }

            $intervensi->delete();

            return back()->with('success', 'Data intervensi berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function getFiles(string $id_intervensi)
    {
        try {
            $intervensi = intervensi::with('bukti')->findOrFail($id_intervensi);
            return response()->json([
                'success' => true,
                'files'   => $intervensi->bukti->map(fn ($b) => [
                    'id'        => $b->id_bukti_pembinaan,
                    'file'      => $b->file,
                    'nama_file' => $b->nama_file,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function exportPdf(Request $request)
    {
        $query = intervensi::with(['siswa.kelas']);
        $user  = Auth::user();

        if ($user && $user->role == 3) {
            $walikelas = walikelas::where('username', $user->username)->first();
            if ($walikelas && $walikelas->id_kelas) {
                $request->merge(['kelas' => $walikelas->id_kelas]);
                $request->request->remove('jurusan');
            }
        } elseif ($user && $user->role == 4) {
            $ketua = ketua_program::where('username', $user->username)->first();
            if ($ketua && $ketua->id_jurusan) {
                $request->merge(['jurusan' => $ketua->id_jurusan]);
                $request->request->remove('kelas');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nis', 'like', "%{$search}%")
                    ->orWhere('nama_siswa', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas')) {
            $query->whereHas('siswa', fn ($q) => $q->where('id_kelas', $request->kelas));
        }

        if ($request->filled('jurusan')) {
            $jur = $request->jurusan;
            $query->whereHas('siswa', fn ($q) => $q->whereHas('kelas.jurusan', fn ($q2) => $q2->where('id_jurusan', $jur)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_Mulai_Perbaikan', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal_Selesai_Perbaikan', '<=', $request->tanggal_akhir);
        }

        $intervensi = $query->latest()->get();
        $pdf        = Pdf::loadView('wakasek.intervensi.pdf', compact('intervensi'));

        return $pdf->download('Data_Intervensi.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = intervensi::with(['siswa.kelas']);
        $user  = Auth::user();

        if ($user && $user->role == 3) {
            $walikelas = walikelas::where('username', $user->username)->first();
            if ($walikelas && $walikelas->id_kelas) {
                $request->merge(['kelas' => $walikelas->id_kelas]);
                $request->request->remove('jurusan');
            }
        } elseif ($user && $user->role == 4) {
            $ketua = ketua_program::where('username', $user->username)->first();
            if ($ketua && $ketua->id_jurusan) {
                $request->merge(['jurusan' => $ketua->id_jurusan]);
                $request->request->remove('kelas');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nis', 'like', "%{$search}%")
                    ->orWhere('nama_siswa', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas')) {
            $query->whereHas('siswa', fn ($q) => $q->where('id_kelas', $request->kelas));
        }

        if ($request->filled('jurusan')) {
            $jur = $request->jurusan;
            $query->whereHas('siswa', fn ($q) => $q->whereHas('kelas.jurusan', fn ($q2) => $q2->where('id_jurusan', $jur)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_Mulai_Perbaikan', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal_Selesai_Perbaikan', '<=', $request->tanggal_akhir);
        }

        $intervensi = $query->latest()->get();

        return Excel::download(new Intervensi_ExportExcel($intervensi), 'Data_Intervensi.xlsx');
    }

    public function AddPenangananAPI(Request $request, string $nis)
    {
        \Log::info('AddPenangananAPI called with nis: ' . $nis . ' data: ', $request->all());
        
        $validator = \Illuminate\Support\Facades\Validator::make(
            array_merge($request->all(), ['nis' => $nis]),
            [
                'nis'                        => 'required|exists:siswa,nis',
                'nama_intervensi'            => 'required|string|max:255',
                'isi_intervensi'             => 'required|string|max:1000',
                'status'                     => 'required|in:Binaan Khusus,Dalam Binaan,Selesai',
                'tanggal_Mulai_Perbaikan'    => 'required|date',
                'tanggal_Selesai_Perbaikan'  => 'required|date|after_or_equal:tanggal_Mulai_Perbaikan',
            ],
            [
                'nis.exists'                               => 'Siswa tidak ditemukan.',
                'nama_intervensi.required'                 => 'Nama penanganan wajib diisi.',
                'isi_intervensi.required'                  => 'Isi penanganan wajib diisi.',
                'status.in'                                => 'Status tidak valid.',
                'tanggal_Mulai_Perbaikan.required'         => 'Tanggal mulai wajib diisi.',
                'tanggal_Selesai_Perbaikan.required'       => 'Tanggal selesai wajib diisi.',
                'tanggal_Selesai_Perbaikan.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $nip     = $request->query('nip', '');
        $idKelas = $request->query('id_kelas', '');

        $nipBk        = null;
        $nipWalikelas = null;
        $nipWakasek   = null;

        $walikelas = \App\Models\walikelas::where('nip_walikelas', $nip)->first();
        if ($walikelas) {
            $nipWalikelas = $walikelas->nip_walikelas;
        } else {
            $gurubk = \App\Models\guru_bk::where('nip_bk', $nip)->first();
            if ($gurubk) {
                $nipBk = $gurubk->nip_bk;
            }
        }

        if (!empty($idKelas)) {
            $siswa = \App\Models\siswa::where('nis', $nis)->first();
            if ($siswa && $siswa->id_kelas !== $idKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak berada di kelas Anda.',
                ], 403);
            }
        }

        try {
            $intervensi = \App\Models\intervensi::create([
                'nis'                       => $nis,
                'nip_bk'                    => $nipBk,
                'nip_walikelas'             => $nipWalikelas,
                'nip_wakasek'               => $nipWakasek,
                'nama_intervensi'           => $request->nama_intervensi,
                'isi_intervensi'            => $request->isi_intervensi,
                'status'                    => $request->status,
                'tanggal_Mulai_Perbaikan'   => $request->tanggal_Mulai_Perbaikan,
                'tanggal_Selesai_Perbaikan' => $request->tanggal_Selesai_Perbaikan,
                'perubahan_setelah_intervensi' => $request->perubahan_setelah_intervensi,
                'created_at'                => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Penanganan berhasil ditambahkan.',
                'data'    => [
                    'id_intervensi'             => $intervensi->id_intervensi,
                    'nis'                       => $intervensi->nis,
                    'nama_intervensi'           => $intervensi->nama_intervensi,
                    'isi_intervensi'            => $intervensi->isi_intervensi,
                    'status'                    => $intervensi->status,
                    'tanggal_Mulai_Perbaikan'   => $intervensi->tanggal_Mulai_Perbaikan,
                    'tanggal_Selesai_Perbaikan' => $intervensi->tanggal_Selesai_Perbaikan,
                    'created_at'                => $intervensi->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function GetPenangananAPI(Request $request, string $nis)
    {
        \Log::info('GetPenangananAPI called with nis: ' . $nis . ' nip: ' . $request->query('nip') . ' id_kelas: ' . $request->query('id_kelas'));
        
        $nip     = $request->query('nip', '');
        $idKelas = $request->query('id_kelas', '');

        $siswa = \App\Models\siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan.',
            ], 404);
        }

        if (!empty($idKelas) && $siswa->id_kelas !== $idKelas) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak berada di kelas Anda.',
            ], 403);
        }

        try {
            $intervensis = \App\Models\intervensi::where('nis', $nis)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $intervensis->map(fn($i) => [
                    'id'                                  => $i->id_intervensi,
                    'nis'                                 => $i->nis,
                    'nama_intervensi'                     => $i->nama_intervensi,
                    'isi_intervensi'                      => $i->isi_intervensi,
                    'status'                              => $i->status,
                    'tanggal_mulai_perbaikan'            => $i->tanggal_Mulai_Perbaikan,
                    'tanggal_selesai_perbaikan'          => $i->tanggal_Selesai_Perbaikan,
                    'perubahan_setelah_intervensi'       => $i->perubahan_setelah_intervensi,
                    'created_at'                         => $i->created_at,
                    'updated_at'                         => $i->updated_at,
                ]),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function UpdatePenangananAPI(Request $request, string $id)
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            $request->all(),
            [
                'nama_intervensi'            => 'required|string|max:255',
                'isi_intervensi'             => 'required|string|max:1000',
                'status'                     => 'required|in:Binaan Khusus,Dalam Binaan,Selesai',
                'tanggal_Mulai_Perbaikan'    => 'required|date',
                'tanggal_Selesai_Perbaikan'  => 'required|date|after_or_equal:tanggal_Mulai_Perbaikan',
                'perubahan_setelah_intervensi' => 'nullable|string|max:1000',
            ],
            [
                'nama_intervensi.required'                 => 'Nama penanganan wajib diisi.',
                'isi_intervensi.required'                  => 'Isi penanganan wajib diisi.',
                'status.in'                                => 'Status tidak valid.',
                'tanggal_Mulai_Perbaikan.required'         => 'Tanggal mulai wajib diisi.',
                'tanggal_Selesai_Perbaikan.required'       => 'Tanggal selesai wajib diisi.',
                'tanggal_Selesai_Perbaikan.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $nip     = $request->query('nip', '');
        $idKelas = $request->query('id_kelas', '');

        $intervensi = \App\Models\intervensi::find($id);
        if (!$intervensi) {
            return response()->json([
                'success' => false,
                'message' => 'Penanganan tidak ditemukan.',
            ], 404);
        }

        if (!empty($idKelas)) {
            $siswa = \App\Models\siswa::where('nis', $intervensi->nis)->first();
            if ($siswa && $siswa->id_kelas !== $idKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke data ini.',
                ], 403);
            }
        }

        try {
            $intervensi->update([
                'nama_intervensi'            => $request->nama_intervensi,
                'isi_intervensi'             => $request->isi_intervensi,
                'status'                     => $request->status,
                'tanggal_Mulai_Perbaikan'    => $request->tanggal_Mulai_Perbaikan,
                'tanggal_Selesai_Perbaikan'  => $request->tanggal_Selesai_Perbaikan,
                'perubahan_setelah_intervensi' => $request->perubahan_setelah_intervensi,
                'updated_at'                 => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Penanganan berhasil diperbarui.',
                'data'    => [
                    'id_intervensi'             => $intervensi->id_intervensi,
                    'nis'                       => $intervensi->nis,
                    'nama_intervensi'           => $intervensi->nama_intervensi,
                    'isi_intervensi'            => $intervensi->isi_intervensi,
                    'status'                    => $intervensi->status,
                    'tanggal_Mulai_Perbaikan'   => $intervensi->tanggal_Mulai_Perbaikan,
                    'tanggal_Selesai_Perbaikan' => $intervensi->tanggal_Selesai_Perbaikan,
                    'perubahan_setelah_intervensi' => $intervensi->perubahan_setelah_intervensi,
                    'updated_at'                => $intervensi->updated_at,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function DeletePenangananAPI(Request $request, string $id)
    {
        $nip     = $request->query('nip', '');
        $idKelas = $request->query('id_kelas', '');

        $intervensi = \App\Models\intervensi::find($id);
        if (!$intervensi) {
            return response()->json([
                'success' => false,
                'message' => 'Penanganan tidak ditemukan.',
            ], 404);
        }

        if (!empty($idKelas)) {
            $siswa = \App\Models\siswa::where('nis', $intervensi->nis)->first();
            if ($siswa && $siswa->id_kelas !== $idKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke data ini.',
                ], 403);
            }
        }

        try {
            $intervensi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Penanganan berhasil dihapus.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }
}