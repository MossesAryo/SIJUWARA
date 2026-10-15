@extends('layouts.superadmin.app')

@section('title', 'Manajemen Wakasek - Super Admin')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Manajemen Wakasek</h2>
            <p class="text-sm text-gray-400">Kelola akun Wakil Kepala Sekolah</p>
        </div>
        <a href="{{ route('superadmin.wakasek.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            <i class="bi bi-plus-lg"></i> Tambah Wakasek
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.wakasek.index') }}"
            class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari NIP, nama, username, atau email"
                    class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
            @if ($q !== '')
                <a href="{{ route('superadmin.wakasek.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">NIP</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftarWakasek as $w)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $daftarWakasek->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $w->nip_wakasek }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $w->nama_wakasek }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $w->username }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $w->user->email ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('superadmin.wakasek.show', $w->nip_wakasek) }}"
                                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('superadmin.wakasek.edit', $w->nip_wakasek) }}"
                                    class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit"><i class="bi bi-pencil-square"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                {{ $q !== '' ? 'Tidak ada Wakasek yang cocok dengan pencarian.' : 'Belum ada data Wakasek.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($daftarWakasek->hasPages())
            <div class="border-t border-gray-100 p-4">{{ $daftarWakasek->links() }}</div>
        @endif
    </div>
@endsection