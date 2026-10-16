<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Halaman log aktivitas (audit trail) untuk Super Admin. Hanya baca.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user'     => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'in:' . implode(',', ActivityLog::DAFTAR_KATEGORI)],
            'dari'     => ['nullable', 'date'],
            'sampai'   => ['nullable', 'date'],
            'q'        => ['nullable', 'string', 'max:100'],
        ], [
            'date' => 'Format :attribute tidak valid.',
            'in'   => 'Kategori tidak valid.',
            'max'  => ':attribute maksimal :max karakter.',
        ], [
            'dari'   => 'Tanggal awal',
            'sampai' => 'Tanggal akhir',
            'q'      => 'Kata kunci',
        ]);

        $filter   = $this->ambilFilter($request);
        $kategori = (string) $request->query('kategori', '');

        $basis = $this->basis($filter);

        // Jumlah per kategori mengikuti filter lain (kecuali kategori) untuk chip filter.
        $perKategori = (clone $basis)
            ->selectRaw('kategori, COUNT(*) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori');

        $logs = (clone $basis)
            ->with([
                'user:id,username,role',
                'siswaDenganArsip:nis,nama_siswa,deleted_at',
            ])
            ->when($kategori !== '', fn ($q) => $q->where('kategori', $kategori))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Pilihan filter pengguna: akun yang pernah tercatat di log + akun yang sudah dihapus.
        $pelaku = User::query()
            ->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('username')
            ->get(['id', 'username', 'role']);

        $pelakuDihapus = ActivityLog::query()
            ->whereNull('user_id')
            ->whereNotNull('actor_username')
            ->distinct()
            ->orderBy('actor_username')
            ->pluck('actor_username');

        return view('superadmin.log.index', [
            'logs'           => $logs,
            'perKategori'    => $perKategori,
            'daftarKategori' => ActivityLog::DAFTAR_KATEGORI,
            'kategori'       => $kategori,
            'pelaku'         => $pelaku,
            'pelakuDihapus'  => $pelakuDihapus,
            'labelRole'      => ActivityLog::LABEL_ROLE,
            'userDipilih'    => $filter['user'],
            'dari'           => $filter['dari'],
            'sampai'         => $filter['sampai'],
            'q'              => $filter['q'],
        ]);
    }

    /**
     * Ambil dan rapikan filter. Jika rentang tanggal terbalik, keduanya ditukar.
     */
    private function ambilFilter(Request $request): array
    {
        $dari   = $request->query('dari') ?: null;
        $sampai = $request->query('sampai') ?: null;

        if ($dari && $sampai && Carbon::parse($sampai)->lt(Carbon::parse($dari))) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [
            'user'   => (string) $request->query('user', ''),
            'dari'   => $dari,
            'sampai' => $sampai,
            'q'      => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * Query dasar (semua filter kecuali kategori).
     */
    private function basis(array $f): Builder
    {
        return ActivityLog::query()
            ->when($f['user'] !== '', function ($query) use ($f) {
                if (str_starts_with($f['user'], 'hapus:')) {
                    $query->whereNull('user_id')->where('actor_username', substr($f['user'], 6));
                } elseif (ctype_digit($f['user'])) {
                    $query->where('user_id', (int) $f['user']);
                }
            })
            ->when($f['dari'], fn ($query) => $query->where('created_at', '>=', Carbon::parse($f['dari'])->startOfDay()))
            ->when($f['sampai'], fn ($query) => $query->where('created_at', '<=', Carbon::parse($f['sampai'])->endOfDay()))
            ->when($f['q'] !== '', function ($query) use ($f) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';

                $query->where(function ($w) use ($like) {
                    $w->where('activity', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('actor_username', 'like', $like)
                        ->orWhere('nis', 'like', $like)
                        ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like))
                        ->orWhereIn('nis', siswa::withTrashed()->where('nama_siswa', 'like', $like)->select('nis'));
                });
            });
    }
}