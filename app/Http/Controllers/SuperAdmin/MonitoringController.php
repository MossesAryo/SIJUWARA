<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pemantauan pemakaian per role (Wakasek, Guru BK, Wali Kelas) per bulan.
 * Ukuran aktivitas: penilaian (apresiasi/pelanggaran), catatan, dan intervensi yang dibuat.
 */
class MonitoringController extends Controller
{
    private const ROLE = [
        'wakasek' => [
            'label' => 'Wakasek', 'tabel' => 'wakasek', 'nip' => 'nip_wakasek', 'nama' => 'nama_wakasek',
            'catatan' => true, 'icon' => 'bi-person-badge', 'warna' => 'blue',
        ],
        'gurubk' => [
            'label' => 'Guru BK', 'tabel' => 'guru_bk', 'nip' => 'nip_bk', 'nama' => 'nama_guru_bk',
            'catatan' => false, 'icon' => 'bi-chat-heart', 'warna' => 'purple',
        ],
        'walikelas' => [
            'label' => 'Wali Kelas', 'tabel' => 'walikelas', 'nip' => 'nip_walikelas', 'nama' => 'nama_walikelas',
            'catatan' => true, 'icon' => 'bi-person-workspace', 'warna' => 'amber',
        ],
    ];

    public function index(Request $request)
    {
        $bulan = (string) $request->query('bulan', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }

        $awal  = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        $roleDipilih = (string) $request->query('role', '');
        $roleDipilih = array_key_exists($roleDipilih, self::ROLE) ? $roleDipilih : null;

        $status = $request->query('status');
        $status = in_array($status, ['aktif', 'belum'], true) ? $status : null;

        $semua = collect();
        $ringkasan = [];

        foreach (self::ROLE as $kode => $cfg) {
            $baris = $this->susunRole($kode, $cfg, $awal, $akhir);

            $ringkasan[$kode] = [
                'label'      => $cfg['label'],
                'icon'       => $cfg['icon'],
                'warna'      => $cfg['warna'],
                'akun'       => $baris->count(),
                'aktif'      => $baris->where('total', '>', 0)->count(),
                'penilaian'  => (int) $baris->sum('penilaian'),
                'catatan'    => (int) $baris->sum('catatan'),
                'intervensi' => (int) $baris->sum('intervensi'),
            ];

            $semua = $semua->merge($baris);
        }

        $tabel = $semua
            ->when($roleDipilih !== null, fn ($c) => $c->where('role', $roleDipilih))
            ->when($status === 'aktif', fn ($c) => $c->where('total', '>', 0))
            ->when($status === 'belum', fn ($c) => $c->where('total', 0))
            ->sortBy([['total', 'desc'], ['nama', 'asc']])
            ->values();

        return view('superadmin.monitoring.index', [
            'tabel'           => $tabel,
            'ringkasan'       => $ringkasan,
            'maksPenilaian'   => max(1, (int) $tabel->max('penilaian')),
            'bulan'           => $bulan,
            'bulanIni'        => now()->format('Y-m'),
            'bulanSebelumnya' => $awal->copy()->subMonth()->format('Y-m'),
            'bulanBerikutnya' => $awal->copy()->addMonth()->format('Y-m'),
            'labelBulan'      => $awal->copy()->locale('id')->translatedFormat('F Y'),
            'roleDipilih'     => $roleDipilih,
            'status'          => $status,
        ]);
    }

    /**
     * Susun baris pemantauan untuk satu role. Jumlah dihitung dengan query agregat (bukan per orang).
     */
    private function susunRole(string $kode, array $cfg, Carbon $awal, Carbon $akhir): Collection
    {
        $kolom = $cfg['nip']; // dari konstanta, aman dipakai di raw query

        $profil = $kode === 'walikelas'
            ? DB::table('walikelas as w')
                ->leftJoin('kelas as k', 'k.id_kelas', '=', 'w.id_kelas')
                ->select('w.*', 'k.nama_kelas as kelas_label')
                ->get()
            : DB::table($cfg['tabel'])->get();

        $kelasBk = $kode === 'gurubk'
            ? DB::table('guru_bk_kelas as gk')
                ->join('kelas as k', 'k.id_kelas', '=', 'gk.kelas_id')
                ->orderBy('k.nama_kelas')
                ->get(['gk.guru_bk_id', 'k.nama_kelas'])
                ->groupBy('guru_bk_id')
            : collect();

        $penilaian = DB::table('penilaian as p')
            ->join('aspek_penilaian as a', 'a.id_aspekpenilaian', '=', 'p.id_aspekpenilaian')
            ->whereNotNull("p.{$kolom}")
            ->whereBetween('p.created_at', [$awal, $akhir])
            ->selectRaw("p.{$kolom} as nip, COUNT(*) as total,
                SUM(a.jenis_poin = 'Apresiasi') as apresiasi,
                SUM(a.jenis_poin = 'Pelanggaran') as pelanggaran,
                MAX(p.created_at) as terakhir")
            ->groupBy("p.{$kolom}")
            ->get()
            ->keyBy('nip');

        $catatan = $cfg['catatan']
            ? DB::table('catatan')
                ->whereNotNull($kolom)
                ->whereBetween('created_at', [$awal, $akhir])
                ->selectRaw("{$kolom} as nip, COUNT(*) as total, MAX(created_at) as terakhir")
                ->groupBy($kolom)
                ->get()
                ->keyBy('nip')
            : collect();

        $intervensi = DB::table('intervensi')
            ->whereNotNull($kolom)
            ->whereBetween('created_at', [$awal, $akhir])
            ->selectRaw("{$kolom} as nip, COUNT(*) as total, MAX(created_at) as terakhir")
            ->groupBy($kolom)
            ->get()
            ->keyBy('nip');

        return $profil->map(function ($p) use ($kode, $cfg, $kelasBk, $penilaian, $catatan, $intervensi) {
            $nip = $p->{$cfg['nip']};
            $n = $penilaian->get($nip);
            $c = $catatan->get($nip);
            $i = $intervensi->get($nip);

            $jmlPenilaian  = (int) ($n->total ?? 0);
            $jmlCatatan    = (int) ($c->total ?? 0);
            $jmlIntervensi = (int) ($i->total ?? 0);

            $terakhir = collect([$n->terakhir ?? null, $c->terakhir ?? null, $i->terakhir ?? null])
                ->filter()
                ->max();

            $kelas = match ($kode) {
                'walikelas' => $p->kelas_label ?? '-',
                'gurubk'    => ($kelasBk->get($nip)?->pluck('nama_kelas')->implode(', ')) ?: '-',
                default     => '-',
            };

            return [
                'role'         => $kode,
                'role_label'   => $cfg['label'],
                'nip'          => $nip,
                'nama'         => $p->{$cfg['nama']},
                'username'     => $p->username,
                'kelas'        => $kelas,
                'punya_catatan' => $cfg['catatan'],
                'penilaian'    => $jmlPenilaian,
                'apresiasi'    => (int) ($n->apresiasi ?? 0),
                'pelanggaran'  => (int) ($n->pelanggaran ?? 0),
                'catatan'      => $jmlCatatan,
                'intervensi'   => $jmlIntervensi,
                'total'        => $jmlPenilaian + $jmlCatatan + $jmlIntervensi,
                'terakhir'     => $terakhir ? Carbon::parse($terakhir) : null,
            ];
        });
    }
}