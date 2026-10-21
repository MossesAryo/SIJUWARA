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
        <a href="{{ route('superadmin.role.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Role
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Tambah Akun</h2>
        <p class="text-sm text-gray-400">Pilih role, lalu isi data akun. Pengguna login memakai NIP dan password awal default.</p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.role.store') }}" class="space-y-5" autocomplete="off">
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
                    <p class="mt-1 text-xs text-gray-400">
                        Akun Wakasek dibuat lewat menu Wakasek. Akun Super Admin dibuat lewat perintah
                        <code class="rounded bg-gray-100 px-1">php artisan make:superadmin</code>.
                    </p>
                    @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Kolom umum --}}
                <div>
                    <label for="nip" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
                    <input id="nip" name="nip" type="text" inputmode="numeric" required maxlength="18"
                        value="{{ old('nip') }}"
                        class="{{ $input }} font-mono {{ $errors->has('nip') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Angka 5 sampai 18 digit. NIP tidak dapat diubah setelah akun dibuat.</p>
                    @error('nip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
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
                    <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, atau strip. Tanpa spasi. Terisi otomatis dari nama, boleh diubah.</p>
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

            {{-- Password awal (default) --}}
            <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <i class="bi bi-key mt-0.5"></i>
                <span>
                    Password awal semua akun baru adalah
                    <code class="rounded bg-white px-1.5 py-0.5 font-mono font-semibold text-blue-900">{{ $passwordAwal }}</code>.
                    Untuk menggantinya setelah akun dibuat, gunakan menu Edit akun (Reset Password).
                </span>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.role.index') }}"
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

            // Tampilkan hanya bagian sesuai role; bagian tersembunyi dinonaktifkan
            // supaya tidak ikut terkirim dan tidak memicu validasi "required".
            function tampilkanBagian() {
                bagian.forEach(function (el) {
                    const aktif = el.dataset.roleSection === roleSelect.value;
                    el.classList.toggle('hidden', !aktif);
                    el.querySelectorAll('input, select').forEach(function (f) {
                        f.disabled = !aktif;
                    });
                });
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