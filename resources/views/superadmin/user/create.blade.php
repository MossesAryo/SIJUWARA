@extends('layouts.superadmin.app')

@section('title', 'Tambah Akun - Super Admin')

@section('content')
    @php
        $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
        $roleTerpilih = (string) old('role', $roleAwal ?? '');
        $pilihKelas = old('kelas', []);
        $pilihKelas = is_array($pilihKelas) ? $pilihKelas : [];
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.user.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen User
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Tambah Akun</h2>
        <p class="text-sm text-gray-400">Pilih role, lalu isi data akun. Pengguna login memakai NIP (Super Admin memakai username) dan password.</p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.user.store') }}" class="space-y-5" autocomplete="off">
            @csrf

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- Role --}}
                <div class="sm:col-span-2">
                    <label for="role" class="mb-1 block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                    <select id="role" name="role" required
                        class="{{ $input }} {{ $errors->has('role') ? 'border-red-400' : 'border-gray-300' }}">
                        <option value="">-- Pilih role --</option>
                        @foreach ($daftarRole as $r)
                            <option value="{{ $r['kode'] }}" @selected((string) $r['kode'] === $roleTerpilih)>{{ $r['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400" id="roleHint">Pilih role untuk menampilkan isian yang sesuai.</p>
                    @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Kolom umum --}}
                <div data-role-section="1 2 3 4">
                    <label for="nip" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
                    <input id="nip" name="nip" type="text" inputmode="numeric" required maxlength="18"
                        value="{{ old('nip') }}"
                        class="{{ $input }} font-mono {{ $errors->has('nip') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Angka 5 sampai 18 digit. NIP tidak dapat diubah setelah akun dibuat.</p>
                    @error('nip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div data-role-section="1 2 3 4">
                    <label for="nama" class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input id="nama" name="nama" type="text" required maxlength="255"
                        value="{{ old('nama') }}"
                        class="{{ $input }} {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('nama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input id="username" name="username" type="text" required maxlength="255"
                        value="{{ old('username') }}"
                        class="{{ $input }} {{ $errors->has('username') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, atau strip. Tanpa spasi, tidak boleh angka saja. Terisi otomatis dari nama, boleh diubah.</p>
                    @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" required maxlength="255"
                        value="{{ old('email') }}"
                        class="{{ $input }} {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Wali Kelas: pilih kelas --}}
                <div data-role-section="3" class="sm:col-span-2 {{ $roleTerpilih === '3' ? '' : 'hidden' }}">
                    <label for="id_kelas" class="mb-1 block text-sm font-medium text-gray-700">Kelas Perwalian <span class="text-red-500">*</span></label>
                    <select id="id_kelas" name="id_kelas" required
                        class="{{ $input }} {{ $errors->has('id_kelas') ? 'border-red-400' : 'border-gray-300' }}">
                        <option value="">-- Pilih kelas --</option>
                        @foreach ($daftarKelas as $k)
                            @php $terisi = in_array($k->id_kelas, $kelasTerisi, true); @endphp
                            <option value="{{ $k->id_kelas }}"
                                @selected(old('id_kelas') === $k->id_kelas)
                                @disabled($terisi)>
                                {{ $k->nama_kelas }}{{ $terisi ? ' (sudah ada wali kelas)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Satu kelas hanya dapat memiliki satu wali kelas.</p>
                    @error('id_kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Ketua Program: pilih jurusan --}}
                <div data-role-section="4" class="sm:col-span-2 {{ $roleTerpilih === '4' ? '' : 'hidden' }}">
                    <label for="id_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Jurusan <span class="text-red-500">*</span></label>
                    <select id="id_jurusan" name="id_jurusan" required
                        class="{{ $input }} {{ $errors->has('id_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
                        <option value="">-- Pilih jurusan --</option>
                        @foreach ($daftarJurusan as $j)
                            @php $terisi = in_array($j->id_jurusan, $jurusanTerisi, true); @endphp
                            <option value="{{ $j->id_jurusan }}"
                                @selected(old('id_jurusan') === $j->id_jurusan)
                                @disabled($terisi)>
                                {{ $j->nama_jurusan }}{{ $terisi ? ' (sudah ada ketua program)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Satu jurusan hanya dapat memiliki satu ketua program.</p>
                    @error('id_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Guru BK: pilih kelas binaan --}}
            <div data-role-section="2" class="{{ $roleTerpilih === '2' ? '' : 'hidden' }}">
                <p class="mb-1 text-sm font-medium text-gray-700">Kelas Binaan <span class="text-xs font-normal text-gray-400">(boleh dikosongkan)</span></p>
                <div class="grid max-h-64 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-200 p-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($daftarKelas as $k)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="kelas[]" value="{{ $k->id_kelas }}"
                                @checked(in_array($k->id_kelas, $pilihKelas, true))
                                class="rounded border-gray-300">
                            <span>{{ $k->nama_kelas }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-400">Kelas binaan dapat diatur kapan saja lewat menu Edit akun.</p>
                @error('kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('kelas.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
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
            const roleSelect = document.getElementById('role');
            const bagian = document.querySelectorAll('[data-role-section]');
            const hint = document.getElementById('roleHint');
            const passwordInput = document.getElementById('password');
            const passwordWajib = document.getElementById('passwordWajib');
            const passwordHint = document.getElementById('passwordHint');
            const passwordHintAwal = passwordHint.innerHTML;

            const keteranganRole = {
                '1': 'Wakasek: mengelola data sekolah. Login memakai NIP.',
                '2': 'Guru BK: dapat dipasangkan ke beberapa kelas binaan. Login memakai NIP.',
                '3': 'Wali Kelas: satu kelas hanya dapat memiliki satu wali kelas. Login memakai NIP.',
                '4': 'Ketua Program: satu jurusan hanya dapat memiliki satu ketua program. Login memakai NIP.',
                '5': 'Super Admin: tanpa NIP dan tanpa profil. Login memakai username pada kolom NIP, password wajib diisi.'
            };

            // Tampilkan hanya bagian sesuai role; bagian tersembunyi dinonaktifkan
            // supaya tidak ikut terkirim dan tidak memicu validasi "required".
            function tampilkanBagian() {
                const nilai = roleSelect.value;

                bagian.forEach(function (el) {
                    const aktif = el.dataset.roleSection.split(' ').indexOf(nilai) !== -1;
                    el.classList.toggle('hidden', !aktif);
                    el.querySelectorAll('input, select').forEach(function (f) {
                        f.disabled = !aktif;
                    });
                });

                hint.textContent = keteranganRole[nilai] || 'Pilih role untuk menampilkan isian yang sesuai.';

                const superAdmin = nilai === '5';
                passwordInput.required = superAdmin;
                passwordWajib.classList.toggle('hidden', !superAdmin);
                passwordHint.innerHTML = superAdmin
                    ? 'Password wajib diisi untuk akun Super Admin (minimal 8 karakter).'
                    : passwordHintAwal;
            }

            roleSelect.addEventListener('change', tampilkanBagian);
            tampilkanBagian();

            // Username otomatis dari nama, selama username belum diisi/diubah manual.
            const nama = document.getElementById('nama');
            const username = document.getElementById('username');
            let usernameManual = username.value !== '';

            function buatUsername(teks) {
                return teks
                    .split(',')[0]
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '.')
                    .replace(/^\.+|\.+$/g, '')
                    .slice(0, 60);
            }

            username.addEventListener('input', function () {
                usernameManual = username.value !== '';
            });

            nama.addEventListener('input', function () {
                if (!usernameManual) {
                    username.value = buatUsername(nama.value);
                }
            });
        })();
    </script>
@endpush