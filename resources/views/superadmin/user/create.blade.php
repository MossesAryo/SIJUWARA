@extends('layouts.superadmin.app')

@section('title', 'Tambah Akun - Super Admin')

@section('content')
    @php
        $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
        $roleTerpilih = collect(old('role', $roleAwal ? [$roleAwal] : []))
            ->map(fn ($v) => (string) $v)
            ->all();
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.user.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen User
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Tambah Akun</h2>
        <p class="text-sm text-gray-400">Satu orang satu akun. Centang satu atau lebih tugas tambahan; bila lebih dari satu, pengguna memilih tugas yang dipakai setelah login. Login memakai NIP (Super Admin memakai username) dan password.</p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.user.store') }}" class="space-y-5" autocomplete="off">
            @csrf

            {{-- Tugas tambahan (boleh lebih dari satu) --}}
            <div>
                <p class="mb-2 text-sm font-medium text-gray-700">Tugas Tambahan <span class="text-red-500">*</span></p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($daftarRole as $r)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                            <input type="checkbox" name="role[]" value="{{ $r['kode'] }}"
                                @checked(in_array((string) $r['kode'], $roleTerpilih, true))
                                class="rounded border-gray-300">
                            <span>{{ $r['label'] }}</span>
                            @if ((int) $r['kode'] === 5)
                                <span class="text-xs text-gray-400">(tidak bisa digabung)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-400" id="roleHint">Centang tugas tambahan untuk menampilkan isian yang sesuai.</p>
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('role.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Data orang (NIP & nama; tidak dipakai Super Admin) --}}
            <div data-umum class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="nip" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
                    <input id="nip" name="nip" type="text" inputmode="numeric" required maxlength="18"
                        value="{{ old('nip') }}"
                        class="{{ $input }} font-mono {{ $errors->has('nip') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Angka 5 sampai 18 digit. Dipakai untuk login.</p>
                    @error('nip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nama" class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input id="nama" name="nama" type="text" required maxlength="255"
                        value="{{ old('nama') }}"
                        class="{{ $input }} {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('nama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Satu akun: username & email --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input id="username" name="username" type="text" required maxlength="255"
                        value="{{ old('username') }}"
                        class="{{ $input }} {{ $errors->has('username') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, dan strip. Tidak boleh angka saja.</p>
                    @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" required maxlength="255"
                        value="{{ old('email') }}"
                        class="{{ $input }} {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Isian khusus per tugas (kelas perwalian / jurusan / kelas binaan) --}}
            <div class="space-y-4">
                @foreach ($daftarRole as $r)
                    @include('superadmin.user._detail_tugas', [
                        'kode' => $r['kode'],
                        'label' => $r['label'],
                        'aktif' => in_array((string) $r['kode'], $roleTerpilih, true),
                        'input' => $input,
                        'daftarKelas' => $daftarKelas,
                        'kelasTerisi' => $kelasTerisi,
                        'daftarJurusan' => $daftarJurusan,
                        'jurusanTerisi' => $jurusanTerisi,
                    ])
                @endforeach
            </div>

            {{-- Password (satu untuk akun ini) --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-800">Password</p>
                <p id="passwordHint" class="mb-3 text-xs text-gray-500">
                    Kosongkan untuk memakai password awal
                    <code class="rounded bg-white px-1.5 py-0.5 font-mono font-semibold text-blue-900">{{ $passwordAwal }}</code>.
                    Password dapat diganti kapan saja lewat menu Edit akun.
                </p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700">
                            Password <span id="passwordWajib" class="hidden text-red-500">*</span>
                        </label>
                        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password"
                            class="{{ $input }} bg-white {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }}">
                        <p class="mt-1 text-xs text-gray-400">Minimal 8 karakter.</p>
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"
                            class="{{ $input }} border-gray-300 bg-white">
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.user.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
                    Buat Akun
                </button>
            </div>
        </form>
    </div>
@endsection

@push('js')
    <script>
        (function () {
            const cekRole = Array.from(document.querySelectorAll('input[name="role[]"]'));
            const detail = document.querySelectorAll('[data-detail]');
            const umum = document.querySelectorAll('[data-umum]');
            const hint = document.getElementById('roleHint');
            const passwordInput = document.getElementById('password');
            const passwordWajib = document.getElementById('passwordWajib');
            const passwordHint = document.getElementById('passwordHint');
            const passwordHintAwal = passwordHint.innerHTML;
            const nama = document.getElementById('nama');
            const username = document.getElementById('username');

            const keteranganRole = {
                '1': 'Wakasek: mengelola data sekolah.',
                '2': 'Guru BK: dapat dipasangkan ke beberapa kelas binaan.',
                '3': 'Wali Kelas: satu kelas hanya dapat memiliki satu wali kelas.',
                '4': 'Ketua Program: satu jurusan hanya dapat memiliki satu ketua program.',
                '5': 'Super Admin: tanpa NIP dan tanpa profil. Login memakai username, password wajib diisi.'
            };

            function terpilih() {
                return cekRole.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            }

            // Bagian yang tersembunyi dinonaktifkan supaya tidak terkirim dan tidak memicu validasi "required".
            function tampil(el, aktif) {
                el.classList.toggle('hidden', !aktif);
                el.querySelectorAll('input, select').forEach(function (f) { f.disabled = !aktif; });
            }

            // Username otomatis dari nama, selama belum diisi/diubah manual.
            let manual = username.value !== '';
            username.addEventListener('input', function () { manual = username.value !== ''; });

            function buatUsername(teks) {
                return teks
                    .split(',')[0]
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '.')
                    .replace(/^\.+|\.+$/g, '')
                    .slice(0, 50);
            }

            function isiUsername() {
                if (manual) { return; }
                username.value = buatUsername(nama.value);
            }

            function atur(sumber) {
                // Super Admin tidak bisa digabung dengan tugas lain.
                if (sumber && sumber.checked) {
                    cekRole.forEach(function (c) {
                        if (c === sumber) { return; }
                        if (sumber.value === '5' || c.value === '5') { c.checked = false; }
                    });
                }

                const pilih = terpilih();
                const adaProfil = pilih.some(function (v) { return v !== '5'; });
                const superAdmin = pilih.indexOf('5') !== -1;

                detail.forEach(function (el) { tampil(el, pilih.indexOf(el.dataset.detail) !== -1); });
                umum.forEach(function (el) { tampil(el, adaProfil); });

                hint.textContent = pilih.length
                    ? pilih.map(function (v) { return keteranganRole[v]; }).join(' ')
                    : 'Centang tugas tambahan untuk menampilkan isian yang sesuai.';

                passwordInput.required = superAdmin;
                passwordWajib.classList.toggle('hidden', !superAdmin);
                passwordHint.innerHTML = superAdmin
                    ? 'Password wajib diisi untuk akun Super Admin (minimal 8 karakter).'
                    : passwordHintAwal;

                cekRole[0].setCustomValidity(pilih.length ? '' : 'Pilih minimal satu tugas tambahan.');
            }

            cekRole.forEach(function (c) { c.addEventListener('change', function () { atur(c); }); });
            nama.addEventListener('input', isiUsername);
            atur(null);
        })();
    </script>
@endpush