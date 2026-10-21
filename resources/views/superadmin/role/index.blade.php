@extends('layouts.superadmin.app')

@section('title', 'Manajemen Role - Super Admin')

@section('content')
    @php
        $badge = [
            1 => 'bg-blue-50 text-blue-700',
            2 => 'bg-purple-50 text-purple-700',
            3 => 'bg-amber-50 text-amber-700',
            4 => 'bg-teal-50 text-teal-700',
            5 => 'bg-slate-800 text-white',
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Manajemen Role</h2>
            <p class="text-sm text-gray-400">Daftar akun pengguna beserta role-nya. Super Admin dapat menambah, mengubah, dan menghapus akun.</p>
        </div>
        <a href="{{ route('superadmin.role.create', in_array($roleDipilih, [2, 3, 4], true) ? ['role' => $roleDipilih] : []) }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            <i class="bi bi-plus-lg"></i> Tambah Akun
        </a>
    </div>

    <div class="mb-4 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <i class="bi bi-info-circle mt-0.5"></i>
        <span>
            Role tidak diubah dari halaman ini karena ditentukan oleh data profil pengguna
            (Wakasek, Guru BK, Wali Kelas, Ketua Program). NIP dan username juga dikunci.
            Akun Wali Kelas, Guru BK, dan Ketua Program ditambahkan lewat tombol Tambah Akun; akun Wakasek lewat menu Wakasek.
            Menghapus akun akan ikut menghapus riwayat kerjanya, jadi periksa dulu di halaman konfirmasi.
        </span>
    </div>

    {{-- Filter role --}}
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('superadmin.role.index', array_filter(['q' => $q])) }}"
            class="rounded-full border px-3 py-1.5 text-sm {{ $roleDipilih === null ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            Semua <span class="ml-1 opacity-75">{{ number_format($totalSemua) }}</span>
        </a>
        @foreach ($daftarRole as $r)
            <a href="{{ route('superadmin.role.index', array_filter(['q' => $q, 'role' => $r['kode']], fn ($v) => $v !== '' && $v !== null)) }}"
                class="rounded-full border px-3 py-1.5 text-sm {{ $roleDipilih === $r['kode'] ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $r['label'] }} <span class="ml-1 opacity-75">{{ number_format($r['total']) }}</span>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white">
        <form method="GET" action="{{ route('superadmin.role.index') }}"
            class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row">
            @if ($roleDipilih !== null)
                <input type="hidden" name="role" value="{{ $roleDipilih }}">
            @endif
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari username, email, NIP, atau nama"
                    class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
            @if ($q !== '' || $roleDipilih !== null)
                <a href="{{ route('superadmin.role.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">NIP</th>
                        <th class="px-4 py-3">Nama Profil</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Data Profil</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pengguna as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $pengguna->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $p['username'] }}
                                @if ($p['id'] === $idSaya)
                                    <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal text-gray-500">Anda</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p['email'] }}</td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $p['nip'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $p['nama'] ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badge[$p['role']] ?? 'bg-red-50 text-red-600' }}">
                                    {{ $p['role_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($p['sesuai'])
                                    <span class="inline-flex items-center gap-1 text-xs text-green-600">
                                        <i class="bi bi-check-circle"></i> {{ $p['catatan'] }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-amber-600">
                                        <i class="bi bi-exclamation-triangle"></i> {{ $p['catatan'] }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('superadmin.role.edit', $p['id']) }}"
                                    class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                @if ($p['id'] === $idSaya)
                                    <span class="inline-block cursor-not-allowed rounded-lg p-2 text-gray-300"
                                        title="Akun yang sedang dipakai tidak dapat dihapus">
                                        <i class="bi bi-trash"></i>
                                    </span>
                                @else
                                    <a href="{{ route('superadmin.role.hapus', $p['id']) }}"
                                        class="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                Tidak ada pengguna yang cocok dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pengguna->hasPages())
            <div class="border-t border-gray-100 p-4">{{ $pengguna->links() }}</div>
        @endif
    </div>
@endsection