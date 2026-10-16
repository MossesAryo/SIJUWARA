@extends('layouts.superadmin.app')

@section('title', 'Pemantauan Role - Super Admin')

@section('content')
    @php
        $warna = [
            'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'bar' => 'bg-blue-500'],
            'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'bar' => 'bg-purple-500'],
            'amber'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'bar' => 'bg-amber-500'],
        ];
        $badgeRole = [
            'wakasek'   => 'bg-blue-50 text-blue-700',
            'gurubk'    => 'bg-purple-50 text-purple-700',
            'walikelas' => 'bg-amber-50 text-amber-700',
        ];
        $query = array_filter(['role' => $roleDipilih, 'status' => $status]);
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Pemantauan Lintas Role</h2>
            <p class="text-sm text-gray-400">
                Ringkasan pemakaian per bulan: penilaian, catatan, dan intervensi yang dibuat tiap pengguna.
            </p>
        </div>

        {{-- Navigasi bulan --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('superadmin.pemantauan.index', $query + ['bulan' => $bulanSebelumnya]) }}"
                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-600 hover:bg-gray-50" title="Bulan sebelumnya">
                <i class="bi bi-chevron-left"></i>
            </a>
            <form method="GET" action="{{ route('superadmin.pemantauan.index') }}">
                @foreach ($query as $nama => $nilai)
                    <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                @endforeach
                <input type="month" name="bulan" value="{{ $bulan }}" max="{{ $bulanIni }}" onchange="this.form.submit()"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
            </form>
            @if ($bulan < $bulanIni)
                <a href="{{ route('superadmin.pemantauan.index', $query + ['bulan' => $bulanBerikutnya]) }}"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-600 hover:bg-gray-50" title="Bulan berikutnya">
                    <i class="bi bi-chevron-right"></i>
                </a>
            @else
                <span class="cursor-not-allowed rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-gray-300">
                    <i class="bi bi-chevron-right"></i>
                </span>
            @endif
        </div>
    </div>

    <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">{{ $labelBulan }}</p>

    {{-- Ringkasan per role --}}
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($ringkasan as $kode => $r)
            @php $c = $warna[$r['warna']]; @endphp
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $c['bg'] }}">
                        <i class="bi {{ $r['icon'] }} text-xl {{ $c['text'] }}"></i>
                    </div>
                    <span class="rounded-lg px-3 py-1 text-xs font-medium {{ $c['bg'] }} {{ $c['text'] }}">{{ $r['label'] }}</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">
                    {{ number_format($r['aktif']) }}<span class="text-base font-medium text-gray-400"> / {{ number_format($r['akun']) }} aktif</span>
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ number_format($r['penilaian']) }} penilaian
                    @if ($kode !== 'gurubk')
                        · {{ number_format($r['catatan']) }} catatan
                    @endif
                    · {{ number_format($r['intervensi']) }} intervensi
                </p>
                <div class="absolute bottom-0 left-0 right-0 h-1 {{ $c['bar'] }}"></div>
            </div>
        @endforeach
    </div>

    {{-- Filter role --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('superadmin.pemantauan.index', array_filter(['bulan' => $bulan, 'status' => $status])) }}"
            class="rounded-full border px-3 py-1.5 text-sm {{ $roleDipilih === null ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            Semua
        </a>
        @foreach ($ringkasan as $kode => $r)
            <a href="{{ route('superadmin.pemantauan.index', array_filter(['bulan' => $bulan, 'status' => $status, 'role' => $kode])) }}"
                class="rounded-full border px-3 py-1.5 text-sm {{ $roleDipilih === $kode ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $r['label'] }} <span class="ml-1 opacity-75">{{ $r['akun'] }}</span>
            </a>
        @endforeach

        <span class="mx-1 hidden h-5 w-px bg-gray-200 sm:block"></span>

        @foreach ([null => 'Semua status', 'aktif' => 'Aktif', 'belum' => 'Belum ada aktivitas'] as $nilai => $teks)
            <a href="{{ route('superadmin.pemantauan.index', array_filter(['bulan' => $bulan, 'role' => $roleDipilih, 'status' => $nilai])) }}"
                class="rounded-full border px-3 py-1.5 text-sm {{ $status === $nilai ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $teks }}
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Pengguna</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Penilaian</th>
                        <th class="px-4 py-3 text-right">Catatan</th>
                        <th class="px-4 py-3 text-right">Intervensi</th>
                        <th class="px-4 py-3">Terakhir Aktif</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tabel as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900">{{ $r['nama'] }}</span>
                                <div class="font-mono text-xs text-gray-400">{{ $r['nip'] }} · {{ $r['username'] }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badgeRole[$r['role']] }}">{{ $r['role_label'] }}</span>
                            </td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-gray-600" title="{{ $r['kelas'] }}">{{ $r['kelas'] }}</td>
                            <td class="min-w-[10rem] px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-8 font-semibold text-gray-900">{{ $r['penilaian'] }}</span>
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full bg-blue-500" style="width: {{ round($r['penilaian'] / $maksPenilaian * 100) }}%"></div>
                                    </div>
                                </div>
                                @if ($r['penilaian'] > 0)
                                    <div class="mt-0.5 text-xs text-gray-400">
                                        <span class="text-green-600">{{ $r['apresiasi'] }} apresiasi</span> ·
                                        <span class="text-red-600">{{ $r['pelanggaran'] }} pelanggaran</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                {{ $r['punya_catatan'] ? $r['catatan'] : '–' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['intervensi'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ $r['terakhir']?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($r['total'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-green-600">
                                        <i class="bi bi-check-circle"></i> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-amber-600">
                                        <i class="bi bi-dash-circle"></i> Belum ada aktivitas
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                                Tidak ada pengguna yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-400">
            Dihitung dari penilaian, catatan, dan intervensi yang dibuat pada bulan terpilih. "Catatan" hanya dimiliki Wakasek dan Wali Kelas.
        </div>
    </div>
@endsection