@extends('layouts.superadmin.app')

@section('title', 'Manajemen Keahlian - Super Admin')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Manajemen Keahlian</h2>
            <p class="text-sm text-gray-400">Kelola jurusan, program keahlian, dan konsentrasi keahlian ({{ number_format($totalJurusan) }} jurusan)</p>
        </div>
        <a href="{{ route('superadmin.jurusan.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            <i class="bi bi-plus-lg"></i> Tambah Jurusan
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.jurusan.index') }}"
            class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode atau nama jurusan"
                    class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
            @if ($q !== '')
                <a href="{{ route('superadmin.jurusan.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama Jurusan</th>
                        <th class="px-4 py-3">Program Keahlian | Konsentrasi Keahlian
                            <span class="block text-[10px] font-normal normal-case tracking-normal text-gray-400">Kelas 10 | Kelas 11 &amp; 12</span>
                        </th>
                        <th class="px-4 py-3 text-center">Kelas</th>
                        <th class="px-4 py-3 text-center">Siswa</th>
                        <th class="px-4 py-3">Ketua Program</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftarJurusan as $j)
                        @php
                            $no       = $daftarJurusan->firstItem() + $loop->index;
                            // Jika jurusan punya 2 konsentrasi, tampil 2 baris (MPLB | MP dan MPLB | MLOG).
                            $pasangan = $j->pasangan_keahlian ?: [[null, null]];
                            $jumlah   = count($pasangan);
                        @endphp

                        @foreach ($pasangan as [$program, $konsentrasi])
                            <tr class="hover:bg-gray-50">
                                @if ($loop->first)
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 align-top text-gray-500">{{ $no }}</td>
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 align-top font-mono font-medium text-gray-700">{{ $j->id_jurusan }}</td>
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 align-top font-medium text-gray-900">{{ $j->nama_jurusan }}</td>
                                @endif

                                <td class="px-4 py-3">
                                    @if ($program || $konsentrasi)
                                        <span class="rounded-lg bg-blue-50 px-2.5 py-1 font-mono text-xs font-medium text-blue-700">{{ $program ?? '-' }}</span>
                                        <span class="px-1 text-gray-400">|</span>
                                        <span class="rounded-lg bg-teal-50 px-2.5 py-1 font-mono text-xs font-medium text-teal-700">{{ $konsentrasi ?? '-' }}</span>
                                    @else
                                        <span class="rounded-lg bg-amber-50 px-2 py-1 text-xs text-amber-700">Belum dipetakan</span>
                                    @endif
                                </td>

                                @if ($loop->first)
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 text-center align-top text-gray-600">{{ number_format($j->kelas_count) }}</td>
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 text-center align-top text-gray-600">{{ number_format($j->siswa_count) }}</td>
                                    <td rowspan="{{ $jumlah }}" class="px-4 py-3 align-top text-gray-600">{{ $j->ketuaProgram->nama_ketua_program ?? '-' }}</td>
                                    <td rowspan="{{ $jumlah }}" class="whitespace-nowrap px-4 py-3 text-right align-top">
                                        <a href="{{ route('superadmin.jurusan.edit', $j->id_jurusan) }}"
                                            class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                        <a href="{{ route('superadmin.jurusan.hapus', $j->id_jurusan) }}"
                                            class="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Hapus"><i class="bi bi-trash"></i></a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                {{ $q !== '' ? 'Tidak ada jurusan yang cocok dengan pencarian.' : 'Belum ada data jurusan.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($daftarJurusan->hasPages())
            <div class="border-t border-gray-100 p-4">{{ $daftarJurusan->links() }}</div>
        @endif
    </div>
@endsection