{{-- Variabel: $wakasek (null saat tambah), $action, $method --}}
@php
    $edit = isset($wakasek);
    $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5" autocomplete="off">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="nip_wakasek" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
            <input id="nip_wakasek" name="nip_wakasek" type="text" inputmode="numeric"
                value="{{ old('nip_wakasek', $wakasek->nip_wakasek ?? '') }}"
                {{ $edit ? 'readonly' : 'required' }}
                class="{{ $input }} {{ $edit ? 'border-gray-200 bg-gray-50 text-gray-500' : ($errors->has('nip_wakasek') ? 'border-red-400' : 'border-gray-300') }}">
            @if ($edit)
                <p class="mt-1 text-xs text-gray-400">NIP tidak dapat diubah karena terhubung dengan riwayat data.</p>
            @else
                @error('nip_wakasek') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @endif
        </div>

        <div>
            <label for="nama_wakasek" class="mb-1 block text-sm font-medium text-gray-700">Nama Wakasek <span class="text-red-500">*</span></label>
            <input id="nama_wakasek" name="nama_wakasek" type="text" required maxlength="255"
                value="{{ old('nama_wakasek', $wakasek->nama_wakasek ?? '') }}"
                class="{{ $input }} {{ $errors->has('nama_wakasek') ? 'border-red-400' : 'border-gray-300' }}">
            @error('nama_wakasek') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="username" class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
            <input id="username" name="username" type="text" maxlength="255"
                value="{{ old('username', $wakasek->username ?? '') }}"
                {{ $edit ? 'readonly' : 'required' }}
                class="{{ $input }} {{ $edit ? 'border-gray-200 bg-gray-50 text-gray-500' : ($errors->has('username') ? 'border-red-400' : 'border-gray-300') }}">
            @if ($edit)
                <p class="mt-1 text-xs text-gray-400">Username tidak dapat diubah.</p>
            @else
                <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, atau strip. Tanpa spasi.</p>
                @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @endif
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
            <input id="email" name="email" type="email" required maxlength="255"
                value="{{ old('email', $wakasek->user->email ?? '') }}"
                class="{{ $input }} {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        @unless ($edit)
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password Awal <span class="text-red-500">*</span></label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                    class="{{ $input }} {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }}">
                <p class="mt-1 text-xs text-gray-400">Minimal 8 karakter.</p>
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                    class="{{ $input }} border-gray-300">
            </div>
        @endunless
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ $edit ? route('superadmin.wakasek.show', $wakasek->nip_wakasek) : route('superadmin.wakasek.index') }}"
            class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
        <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            {{ $edit ? 'Simpan Perubahan' : 'Buat Akun Wakasek' }}
        </button>
    </div>
</form>