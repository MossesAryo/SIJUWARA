@extends('layouts.superadmin.app')

@section('title', 'Hapus Permanen Siswa - Super Admin')

@section('content')
    @php $adaDampak = collect($dampak)->sum() > 0; @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.siswa-arsip.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Arsip Siswa
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Hapus Permanen Siswa</h2>
        <p class="text-sm text-gray-400">Periksa data berikut sebelum menghapus. Tindakan ini tidak dapat dibatalkan.</p>
    </div>

    <div class="max-w-3xl space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">NIS</dt>
                    <dd class="mt-0.5 font-mono font-medium text-gray-900">{{ $siswa->nis }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Nama</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ $siswa->nama_siswa }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Kelas</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $siswa->kelas->nama_kelas ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Diarsipkan</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $siswa->deleted_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
            <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-800">
                <i class="bi bi-exclamation-triangle"></i> Data yang ikut terhapus
            </p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-800">
                <li>Data siswa ini.</li>
                @foreach ($dampak as $nama => $jumlah)
                    <li>{{ $nama }}: <strong>{{ number_format($jumlah) }}</strong> data</li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-red-700">
                @if ($adaDampak)
                    Seluruh riwayat di atas hilang selamanya, termasuk file bukti pembinaan.
                @endif
                Log aktivitas tetap tersimpan, tetapi tidak lagi terhubung ke siswa ini.
            </p>
        </div>

        <form method="POST" action="{{ route('superadmin.siswa-arsip.forceDelete', $siswa->nis) }}"
            class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            @csrf
            @method('DELETE')

            <label for="konfirmasi_nis" class="block text-sm text-gray-700">
                Untuk melanjutkan, ketik NIS <strong class="font-mono">{{ $siswa->nis }}</strong>
            </label>
            <input type="text" id="konfirmasi_nis" name="konfirmasi_nis" autocomplete="off" inputmode="numeric"
                placeholder="{{ $siswa->nis }}"
                class="mt-2 w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm focus:border-red-400 focus:outline-none sm:max-w-xs">
            @error('konfirmasi_nis') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.siswa-arsip.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" id="tombolHapus" disabled
                    class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="bi bi-trash"></i> Hapus Permanen
                </button>
            </div>
        </form>
    </div>
@endsection

@push('js')
    <script>
        (function () {
            var nis = @json((string) $siswa->nis);
            var input = document.getElementById('konfirmasi_nis');
            var tombol = document.getElementById('tombolHapus');
            input.addEventListener('input', function () {
                tombol.disabled = input.value.trim() !== nis;
            });
        })();
    </script>
@endpush