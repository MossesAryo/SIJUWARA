@extends('layouts.superadmin.app')

@section('title', 'Hapus Jurusan - Super Admin')

@section('content')
    <div class="mb-5">
        <a href="{{ route('superadmin.jurusan.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke daftar
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Hapus Jurusan</h2>
        <p class="text-sm text-gray-400">Periksa data berikut sebelum menghapus. Tindakan ini tidak dapat dibatalkan.</p>
    </div>

    <div class="max-w-3xl space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Kode Jurusan</dt>
                    <dd class="mt-0.5 font-mono font-medium text-gray-900">{{ $jurusan->id_jurusan }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-400">Nama Jurusan</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ $jurusan->nama_jurusan }}</dd>
                </div>
            </dl>
        </div>

        @if ($dipakai)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
                <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-800">
                    <i class="bi bi-slash-circle"></i> Jurusan ini tidak dapat dihapus
                </p>
                <p class="mb-2 text-sm text-red-800">Jurusan masih dipakai oleh data berikut:</p>
                <ul class="list-disc space-y-1 pl-5 text-sm text-red-800">
                    @foreach ($dampak as $nama => $jumlah)
                        @if ($jumlah > 0)
                            <li>{{ $nama }}: <strong>{{ number_format($jumlah) }}</strong> data</li>
                        @endif
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-red-700">
                    Menghapus jurusan akan ikut menghapus data di atas secara permanen. Pindahkan atau hapus data
                    tersebut terlebih dahulu, lalu coba lagi.
                </p>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('superadmin.jurusan.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Kembali</a>
            </div>
        @else
            <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <i class="bi bi-check-circle"></i>
                Jurusan ini tidak dipakai oleh kelas, siswa, maupun ketua program, sehingga aman dihapus.
            </div>

            <form method="POST" action="{{ route('superadmin.jurusan.destroy', $jurusan->id_jurusan) }}"
                class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                @csrf
                @method('DELETE')

                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" name="konfirmasi" value="1" required class="mt-0.5 rounded border-gray-300">
                    <span>Saya paham bahwa jurusan <strong>{{ $jurusan->id_jurusan }}</strong> akan dihapus permanen.</span>
                </label>
                @error('konfirmasi') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('superadmin.jurusan.index') }}"
                        class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                    <button type="submit" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        <i class="bi bi-trash"></i> Hapus Jurusan
                    </button>
                </div>
            </form>
        @endif
    </div>
@endsection