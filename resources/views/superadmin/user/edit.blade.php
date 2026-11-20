@extends('layouts.superadmin.app')

@section('title', 'Edit Akun - Super Admin')

@section('content')
    {{--
        Variabel dari UserController@edit:
          $user                 akun yang diedit
          $akunSendiri          bool, true bila akun ini yang sedang dipakai login
          $adaProfil            bool, false untuk Super Admin (tanpa NIP/nama/tugas)
          $nip, $nama           data orang (dari profil mana pun yang dipegang)
          $daftarRole           [['kode' => 1, 'label' => 'Wakasek'], ... ] (kode 1-4, tanpa Super Admin)
          $tugasDipegang        [kode => label] tugas yang sekarang dipegang akun ini
          $detail               [kode => ['id_kelas' => .., 'id_jurusan' => .., 'kelas' => [..]]]
          $daftarKelas, $kelasTerisi, $daftarJurusan, $jurusanTerisi
                                ($kelasTerisi / $jurusanTerisi tidak boleh memuat milik akun ini sendiri)
    --}}
    @php
        $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
        $roleTerpilih = collect(old('role', array_keys($tugasDipegang)))
            ->map(fn ($v) => (string) $v)
            ->all();
        $labelAkun = $adaProfil ? implode(', ', $tugasDipegang) : 'Super Admin';
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.user.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen User
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Edit Akun {{ $labelAkun }}</h2>
        <p class="text-sm text-gray-400">Username dan NIP boleh diubah, riwayat data ikut menyesuaikan otomatis. Tugas tambahan ditambah atau dilepas lewat centang di bawah; bila akun memegang lebih dari satu tugas, pengguna memilih tugas yang dipakai setelah login.</p>
    </div>

    @unless ($adaProfil)
        <div class="mb-4 flex max-w-3xl items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <i class="bi bi-info-circle mt-0.5"></i>
            <span>
                @if ($user->isSuperAdmin())
                    Akun Super Admin tidak punya NIP, nama, maupun tugas tambahan. Yang dapat diubah hanya username, email, dan password.
                @else
                    Profil tugas untuk akun ini tidak ditemukan, sehingga hanya username, email, dan password yang dapat diubah.
                @endif
            </span>
        </div>
    @endunless

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.user.update', $user->id) }}" class="space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            @if ($adaProfil)
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
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        Menghilangkan centang melepas tugas tersebut dari akun ini (akun dan tugas lain tetap ada).
                        Ditolak bila tugas itu masih punya riwayat penilaian, intervensi, atau catatan.
                    </p>
                    @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('role.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input id="username" name="username" type="text" required maxlength="255"
                        value="{{ old('username', $user->username) }}"
                        class="{{ $input }} {{ $errors->has('username') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, dan strip. Tidak boleh angka saja.</p>
                    @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="{{ $adaProfil ? '' : 'sm:col-span-1' }}">
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" required maxlength="255"
                        value="{{ old('email', $user->email) }}"
                        class="{{ $input }} {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($adaProfil)
                    <div data-umum>
                        <label for="nip" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
                        <input id="nip" name="nip" type="text" required inputmode="numeric" maxlength="18"
                            value="{{ old('nip', $nip) }}"
                            class="{{ $input }} font-mono {{ $errors->has('nip') ? 'border-red-400' : 'border-gray-300' }}">
                        <p class="mt-1 text-xs text-gray-400">Angka 5 sampai 18 digit. Dipakai untuk login. Perubahan berlaku untuk semua tugas akun ini.</p>
                        @error('nip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div data-umum>
                        <label for="nama" class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input id="nama" name="nama" type="text" required maxlength="255"
                            value="{{ old('nama', $nama) }}"
                            class="{{ $input }} {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-300' }}">
                        @error('nama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            @if ($adaProfil)
                {{-- Isian khusus per tugas, terisi dari data yang sekarang --}}
                <div class="space-y-4">
                    @foreach ($daftarRole as $r)
                        @include('superadmin.user._detail_tugas', [
                            'kode' => $r['kode'],
                            'label' => $r['label'],
                            'aktif' => in_array((string) $r['kode'], $roleTerpilih, true),
                            'nilai' => $detail[$r['kode']] ?? [],
                            'input' => $input,
                            'daftarKelas' => $daftarKelas,
                            'kelasTerisi' => $kelasTerisi,
                            'daftarJurusan' => $daftarJurusan,
                            'jurusanTerisi' => $jurusanTerisi,
                        ])
                    @endforeach
                </div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-800">Reset Password</p>
                <p class="mb-3 text-xs text-gray-500">Kosongkan jika tidak ingin mengubah password.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password Baru</label>
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
                @unless ($akunSendiri)
                    <p class="mt-3 text-xs text-gray-500">Jika password diganti, pengguna ini harus login ulang.</p>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('superadmin.user.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    @if ($adaProfil)
        <script>
            (function () {
                const cekRole = Array.from(document.querySelectorAll('input[name="role[]"]'));
                const detail = document.querySelectorAll('[data-detail]');

                // Bagian yang tersembunyi dinonaktifkan supaya tidak terkirim dan tidak memicu validasi "required".
                function atur() {
                    const pilih = cekRole.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });

                    detail.forEach(function (el) {
                        const aktif = pilih.indexOf(el.dataset.detail) !== -1;
                        el.classList.toggle('hidden', !aktif);
                        el.querySelectorAll('input, select').forEach(function (f) { f.disabled = !aktif; });
                    });

                    cekRole[0].setCustomValidity(pilih.length ? '' : 'Akun harus memegang minimal satu tugas tambahan.');
                }

                cekRole.forEach(function (c) { c.addEventListener('change', atur); });
                atur();
            })();
        </script>
    @endif
@endsection