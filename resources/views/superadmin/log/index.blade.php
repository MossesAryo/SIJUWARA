@extends('layouts.superadmin.app')

@section('title', 'Log Aktivitas - Super Admin')

@section('content')
    @php
        $badge = [
            'Apresiasi'   => 'bg-green-50 text-green-700',
            'Pelanggaran' => 'bg-red-50 text-red-700',
            'Akun'        => 'bg-purple-50 text-purple-700',
            'Siswa'       => 'bg-amber-50 text-amber-700',
        ];

        $filterDasar = array_filter(
            request()->only(['user', 'dari', 'sampai', 'q']),
            fn ($v) => $v !== null && $v !== ''
        );
        $adaFilter = $filterDasar !== [] || $kategori !== '';
        $totalSemua = (int) $perKategori->sum();
    @endphp

    <div class="mb-5">
        <h2 class="text-lg font-bold text-gray-900">Log Aktivitas</h2>
        <p class="text-sm text-gray-400">
            Jejak aktivitas pengguna: siapa melakukan apa dan kapan. Log hanya dapat dibaca dan tidak dapat diubah
            atau dihapus dari halaman ini.
        </p>
    </div>

    {{-- Filter kategori --}}
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('superadmin.log.index', $filterDasar) }}"
            class="rounded-full border px-3 py-1.5 text-sm {{ $kategori === '' ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            Semua <span class="ml-1 opacity-75">{{ number_format($totalSemua) }}</span>
        </a>
        @foreach ($daftarKategori as $k)
            <a href="{{ route('superadmin.log.index', $filterDasar + ['kategori' => $k]) }}"
                class="rounded-full border px-3 py-1.5 text-sm {{ $kategori === $k ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $k }} <span class="ml-1 opacity-75">{{ number_format($perKategori[$k] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.log.index') }}"
            class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            @if ($kategori !== '')
                <input type="hidden" name="kategori" value="{{ $kategori }}">
            @endif

            <div>
                <label class="mb-1 block text-xs text-gray-500">Pengguna</label>
                <select name="user"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                    <option value="">Semua pengguna</option>
                    @foreach ($pelaku as $p)
                        <option value="{{ $p->id }}" @selected($userDipilih === (string) $p->id)>
                            {{ $p->username }} ({{ $labelRole[(int) $p->role] ?? '-' }})
                        </option>
                    @endforeach
                    @if ($pelakuDihapus->isNotEmpty())
                        <optgroup label="Akun yang sudah dihapus">
                            @foreach ($pelakuDihapus as $nama)
                                <option value="hapus:{{ $nama }}" @selected($userDipilih === 'hapus:' . $nama)>{{ $nama }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Dari tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Sampai tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs text-gray-500">Kata kunci</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Aktivitas, keterangan, pengguna, NIS, nama siswa"
                            class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
                    </div>
                    <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
                    @if ($adaFilter)
                        <a href="{{ route('superadmin.log.index') }}"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Pelaku</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Aktivitas</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3 text-right">Poin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ $log->created_at?->format('d/m/Y H:i:s') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900">{{ $log->nama_pelaku }}</span>
                                @if ($log->pelaku_dihapus)
                                    <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-xs text-red-600">akun dihapus</span>
                                @endif
                                @if ($log->label_role_pelaku)
                                    <div class="text-xs text-gray-400">{{ $log->label_role_pelaku }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badge[$log->kategori] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $log->kategori }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $log->activity }}</td>
                            <td class="max-w-md px-4 py-3 text-gray-600">{{ $log->description ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($log->siswaDenganArsip)
                                    <span class="font-mono text-xs text-gray-500">{{ $log->nis }}</span><br>
                                    {{ $log->siswaDenganArsip->nama_siswa }}
                                    @if ($log->siswaDenganArsip->trashed())
                                        <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">diarsipkan</span>
                                    @endif
                                @elseif ($log->nis)
                                    <span class="font-mono text-xs">{{ $log->nis }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium">
                                @if ($log->kategori === 'Apresiasi' && $log->point > 0)
                                    <span class="text-green-600">+{{ $log->point }}</span>
                                @elseif ($log->kategori === 'Pelanggaran' && $log->point > 0)
                                    <span class="text-red-600">-{{ $log->point }}</span>
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                Tidak ada aktivitas yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-gray-100 p-4">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection