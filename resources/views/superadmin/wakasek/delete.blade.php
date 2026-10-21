@extends('layouts.superadmin.app')

@section('title', 'Hapus Wakasek - Super Admin')

@section('content')
    @php
        $adaDampak = collect($dampak)->sum() > 0;
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.wakasek.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke daftar
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Hapus Akun Wakasek</h2>
        <p class="text-sm text-gray-400">Periksa data berikut sebelum menghapus. Tindakan ini tidak dapat dibatalkan.</p>
    </div>

    <div class="max-w-3xl space-y-4">
        @if ($wakasekTerakhir)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                <i class="bi bi-exclamation-triangle"></i>
                Ini adalah <strong>satu-satunya akun Wakasek</strong>. Setelah dihapus, tidak ada Wakasek yang dapat
                mengelola data sekolah sampai Anda membuat akun Wakasek baru.
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">NIP</dt>
                    <dd class="mt-0.5 font-mono font-medium text-gray-900">{{ $wakasek->nip_wakasek }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Nama Wakasek</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ $wakasek->nama_wakasek }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Username</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $wakasek->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Email</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $wakasek->user->email ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
            <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-800">
                <i class="bi bi-exclamation-triangle"></i> Data yang ikut terhapus
            </p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-800">
                <li>Akun login dan profil Wakasek ini.</li>
                @foreach ($dampak as $nama => $jumlah)
                    <li>{{ $nama }}: <strong>{{ number_format($jumlah) }}</strong> data</li>
                @endforeach
            </ul>
            @if ($adaDampak)
                <p class="mt-3 text-xs text-red-700">
                    Riwayat di atas terhapus permanen, termasuk file bukti pembinaan. Poin siswa yang sudah
                    tersimpan tidak ikut berubah, tetapi catatan penilaian asalnya hilang.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('superadmin.wakasek.destroy', $wakasek->nip_wakasek) }}"
            class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            @csrf
            @method('DELETE')

            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="konfirmasi" value="1" required class="mt-0.5 rounded border-gray-300">
                <span>Saya paham bahwa akun <strong>{{ $wakasek->username }}</strong> dan data terkait akan dihapus permanen.</span>
            </label>
            @error('konfirmasi') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.wakasek.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                    <i class="bi bi-trash"></i> Hapus Akun
                </button>
            </div>
        </form>
    </div>
@endsection