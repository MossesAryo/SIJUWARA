{{-- Variabel: $jurusan (hanya saat edit), $action, $method --}}
@php
    $edit = isset($jurusan);
    $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5" autocomplete="off">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="id_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Kode Jurusan <span class="text-red-500">*</span></label>
            <input id="id_jurusan" name="id_jurusan" type="text" maxlength="10"
                value="{{ old('id_jurusan', $jurusan->id_jurusan ?? '') }}"
                {{ $edit ? 'readonly' : 'required' }}
                style="text-transform: uppercase;"
                class="{{ $input }} font-mono {{ $edit ? 'border-gray-200 bg-gray-50 text-gray-500' : ($errors->has('id_jurusan') ? 'border-red-400' : 'border-gray-300') }}">
            @if ($edit)
                <p class="mt-1 text-xs text-gray-400">Kode tidak dapat diubah karena terhubung dengan kelas, siswa, dan ketua program.</p>
            @else
                <p class="mt-1 text-xs text-gray-400">Huruf dan angka, maksimal 10 karakter, contoh: RPL. Otomatis huruf kapital.</p>
                @error('id_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @endif
        </div>

        <div>
            <label for="nama_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Nama Jurusan <span class="text-red-500">*</span></label>
            <input id="nama_jurusan" name="nama_jurusan" type="text" required maxlength="255"
                value="{{ old('nama_jurusan', $jurusan->nama_jurusan ?? '') }}"
                class="{{ $input }} {{ $errors->has('nama_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
            @error('nama_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    @unless ($edit)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <i class="bi bi-info-circle"></i>
            Kode jurusan baru belum memiliki pemetaan kode Program Keahlian (kelas X) dan Kompetensi Keahlian
            (kelas XI/XII). Pemetaan itu berada di model <code>kelas</code>, sehingga jurusan baru belum dapat
            dipakai membuat kelas sebelum pemetaannya ditambahkan.
        </div>
    @endunless

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ route('superadmin.jurusan.index') }}"
            class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
        <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            {{ $edit ? 'Simpan Perubahan' : 'Simpan Jurusan' }}
        </button>
    </div>
</form>