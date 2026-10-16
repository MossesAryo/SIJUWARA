@extends('layouts.superadmin.app')

@section('title', 'Hapus Akun - Super Admin')

@section('content')
    @php
        $labelRole = $cfg['label'] ?? 'Tidak Dikenal (' . $role . ')';
        $nipProfil = ($profil && $cfg) ? $profil->{$cfg['nip']} : null;
        $namaProfil = ($profil && $cfg) ? $profil->{$cfg['nama']} : null;
        $adaDampak = collect($dampak)->sum() > 0;
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.role.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Role
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Hapus Akun {{ $labelRole }}</h2>
        <p class="text-sm text-gray-400">Periksa data berikut sebelum menghapus. Tindakan ini tidak dapat dibatalkan.</p>
    </div>

    <div class="max-w-3xl space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Username</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Email</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Role</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $labelRole }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">NIP / Nama Profil</dt>
                    <dd class="mt-0.5 text-gray-700">
                        {{ $nipProfil ? $nipProfil . ' / ' . $namaProfil : '-' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
            <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-800">
                <i class="bi bi-exclamation-triangle"></i> Data yang ikut terhapus
            </p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-800">
                <li>Akun login dan profil {{ $labelRole }} ini.</li>
                @foreach ($dampak as $nama => $jumlah)
                    <li>{{ $nama }}: <strong>{{ number_format($jumlah) }}</strong> data</li>
                @endforeach
            </ul>
            @if ($adaDampak)
                <p class="mt-3 text-xs text-red-700">
                    Riwayat di atas terhapus permanen. Poin siswa yang sudah tersimpan tidak ikut berubah,
                    tetapi catatan penilaian asalnya hilang.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('superadmin.role.destroy', $user->id) }}"
            class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            @csrf
            @method('DELETE')

            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="konfirmasi" value="1" required class="mt-0.5 rounded border-gray-300">
                <span>Saya paham bahwa akun <strong>{{ $user->username }}</strong> dan data terkait akan dihapus permanen.</span>
            </label>
            @error('konfirmasi') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.role.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                    <i class="bi bi-trash"></i> Hapus Akun
                </button>
            </div>
        </form>
    </div>
@endsection