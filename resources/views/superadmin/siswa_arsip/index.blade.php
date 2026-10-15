@extends('layouts.superadmin.app')

@section('title', 'Tong Sampah Siswa - Super Admin')

@section('content')
    @php
        $badgeStatus = [
            'aktif'    => 'bg-green-50 text-green-700',
            'nonaktif' => 'bg-gray-100 text-gray-600',
            'alumni'   => 'bg-blue-50 text-blue-700',
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Tong Sampah Siswa</h2>
            <p class="text-sm text-gray-400">Siswa yang diarsipkan. Dapat dipulihkan atau dihapus permanen oleh Super Admin.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600">
            <i class="bi bi-archive"></i> {{ number_format($totalArsip) }} siswa di tong sampah
        </span>
    </div>

    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class="bi bi-info-circle mt-0.5"></i>
        <span>
            Memulihkan siswa mengembalikannya ke Daftar Siswa beserta seluruh riwayatnya.
            Hapus permanen juga menghapus penilaian, catatan, intervensi, penghargaan, dan surat peringatan siswa tersebut,
            dan tidak dapat dibatalkan.
        </span>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.siswa-arsip.index') }}"
            class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari NIS atau nama siswa"
                    class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
            </div>
            <select name="kelas"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                <option value="">Semua kelas</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id_kelas }}" @selected($idKelas === (string) $k->id_kelas)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
            @if ($q !== '' || $idKelas !== '')
                <a href="{{ route('superadmin.siswa-arsip.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">NIS</th>
                        <th class="px-4 py-3">Nama Siswa</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Poin Total</th>
                        <th class="px-4 py-3">Diarsipkan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftar as $s)
                        @php $log = $penghapus->get($s->nis); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $daftar->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $s->nis }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $s->nama_siswa }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $s->kelas->nama_kelas ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badgeStatus[$s->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($s->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $s->poin_total ?? 0 }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $s->deleted_at?->format('d/m/Y H:i') ?? '-' }}
                                @if ($log)
                                    <div class="text-xs text-gray-400">oleh {{ $log->nama_pelaku }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <form method="POST" action="{{ route('superadmin.siswa-arsip.restore', $s->nis) }}" class="inline"
                                    onsubmit="return confirm('Pulihkan siswa {{ e(addslashes($s->nama_siswa)) }}?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg p-2 text-green-600 hover:bg-green-50" title="Pulihkan">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                                <a href="{{ route('superadmin.siswa-arsip.hapus', $s->nis) }}"
                                    class="inline-block rounded-lg p-2 text-red-600 hover:bg-red-50" title="Hapus permanen">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                {{ ($q !== '' || $idKelas !== '') ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Tong sampah kosong.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($daftar->hasPages())
            <div class="border-t border-gray-100 p-4">{{ $daftar->links() }}</div>
        @endif
    </div>
@endsection