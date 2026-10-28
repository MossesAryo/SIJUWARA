@extends('layouts.superadmin.app')

@section('title', 'Data Backup - Super Admin')

@section('content')
    @php
        $ukuranTeks = function (int $byte) {
            if ($byte >= 1048576) return round($byte / 1048576, 2) . ' MB';
            return round($byte / 1024, 1) . ' KB';
        };
        $adaFilter = $q !== '' || $jenis !== '';
    @endphp

    <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Data Backup</h2>
            <p class="text-sm text-gray-400">Titik pemulihan otomatis (file Excel) yang dibuat sebelum ganti tahun ajaran. Halaman ini hanya untuk melihat dan mengunduh.</p>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-400">Total backup</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($ringkasan['total']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-400">Lengkap / rusak</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">
                <span class="text-green-600">{{ $ringkasan['valid'] }}</span>
                <span class="text-gray-300">/</span>
                <span class="{{ $ringkasan['rusak'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $ringkasan['rusak'] }}</span>
            </p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-400">Total ukuran</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $ukuranTeks($ringkasan['ukuran']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-400">Backup terbaru</p>
            <p class="mt-1 text-base font-bold text-gray-900">
                {{ $ringkasan['terbaru'] ? $ringkasan['terbaru']->format('d/m/Y H:i') : '-' }}
            </p>
        </div>
    </div>

    <div class="mb-4 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <i class="bi bi-info-circle mt-0.5"></i>
        <span>
            Backup berupa file Excel (.xlsx) yang menyimpan tahun ajaran aktif, kelas &amp; status siswa, kelas wali kelas, rencana Ketua Program, dan Riwayat Kelas.
            Untuk memulihkan, buka salah satu backup lalu klik <strong>Pulihkan sekarang</strong>, atau jalankan di server:
            <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs">php artisan backup:pulihkan NAMA_FILE.xlsx</code>.
        </span>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.backup.index') }}"
            class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama file atau alasan"
                    class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
            </div>
            <select name="jenis"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                <option value="">Semua jenis</option>
                <option value="tahun" @selected($jenis === 'tahun')>Ganti tahun ajaran</option>
                <option value="pengaman" @selected($jenis === 'pengaman')>Pengaman pemulihan</option>
                <option value="lain" @selected($jenis === 'lain')>Lainnya</option>
            </select>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
            @if ($adaFilter)
                <a href="{{ route('superadmin.backup.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Backup</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Dibuat</th>
                        <th class="px-4 py-3">Isi</th>
                        <th class="px-4 py-3 text-right">Ukuran</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftar as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $daftar->firstItem() + $loop->index }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $b['alasan'] }}</div>
                                <div class="break-all font-mono text-xs text-gray-400">{{ $b['nama'] }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium {{ $b['jenis']['warna'] }}">
                                    {{ $b['jenis']['label'] }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ $b['dibuat']->format('d/m/Y H:i') }}
                                <div class="text-xs text-gray-400">{{ $b['dibuat']->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @forelse ($b['jumlah'] as $t => $n)
                                    <div>{{ \App\Support\BackupReader::TABEL[$t] ?? $t }}: <span class="font-semibold">{{ number_format($n) }}</span></div>
                                @empty
                                    <span class="text-gray-400">-</span>
                                @endforelse
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-gray-600">{{ $ukuranTeks($b['ukuran']) }}</td>
                            <td class="px-4 py-3">
                                @if ($b['valid'])
                                    <span class="whitespace-nowrap rounded-lg bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                        <i class="bi bi-check-circle"></i> Lengkap
                                    </span>
                                @else
                                    <span class="whitespace-nowrap rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                        <i class="bi bi-exclamation-triangle"></i> Rusak
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('superadmin.backup.show', $b['nama']) }}"
                                    class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm text-blue-600 hover:bg-blue-50"
                                    title="Lihat isi backup">
                                    <i class="bi bi-eye"></i> Lihat
                                </a>
                                <a href="{{ route('superadmin.backup.unduh', $b['nama']) }}"
                                    class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm text-green-700 hover:bg-green-50"
                                    title="Unduh file Excel">
                                    <i class="bi bi-file-earmark-excel"></i> Unduh
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                {{ $adaFilter ? 'Tidak ada backup yang cocok dengan pencarian.' : 'Belum ada backup. Backup dibuat otomatis saat tahun ajaran diganti.' }}
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

    <p class="mt-3 text-xs text-gray-400">
        Lokasi file di server: <span class="break-all font-mono">{{ $folder }}</span>
    </p>
@endsection