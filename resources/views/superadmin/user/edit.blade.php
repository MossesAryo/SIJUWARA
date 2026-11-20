@extends('layouts.superadmin.app')

@section('title', 'Edit Akun - Super Admin')

@section('content')
    @php
        $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
        $terkunci = 'border-gray-200 bg-gray-50 text-gray-500';
        $labelRole = $cfg['label'] ?? 'Tidak Dikenal (' . $role . ')';
        $nipProfil = ($profil && $cfg) ? $profil->{$cfg['nip']} : null;
        $namaProfil = ($profil && $cfg) ? $profil->{$cfg['nama']} : null;
        $pilihKelas = old('kelas', $kelasDipilih);
        $tugasTerpilih = old('tugas_tambahan', $tugasSekarang);
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.user.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen User
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Edit Akun {{ $labelRole }}</h2>
        <p class="text-sm text-gray-400">Username dan NIP boleh diubah, riwayat data akan ikut menyesuaikan otomatis. Tugas tambahan bisa diganti lewat dropdown (pilihannya sama dengan di halaman Data Guru).</p>
    </div>

    @unless ($profil || $role === 5)
        <div class="mb-4 flex max-w-3xl items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="bi bi-exclamation-triangle mt-0.5"></i>
            <span>Profil {{ $labelRole }} untuk akun ini tidak ditemukan, sehingga hanya username, email, dan password yang dapat diubah.</span>
        </div>
    @endunless

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.user.update', $user->id) }}" class="space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="tugas_tambahan" class="mb-1 block text-sm font-medium text-gray-700">Tugas Tambahan</label>
                    @if ($bisaGantiTugas)
                        <select id="tugas_tambahan" name="tugas_tambahan"
                            class="{{ $input }} {{ $errors->has('tugas_tambahan') ? 'border-red-400' : 'border-gray-300' }}">
                            @foreach ($daftarTugas as $kode => $label)
                                <option value="{{ $kode }}" @selected($tugasTerpilih === $kode)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tugas_tambahan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @else
                        {{-- Tetap tampil sebagai dropdown, tapi dikunci (Super Admin, akun sendiri, atau profil tidak ada). --}}
                        <select id="tugas_tambahan_terkunci" disabled class="{{ $input }} {{ $terkunci }}">
                            @if ($tugasSekarang === null)
                                <option selected>{{ $labelRole }}</option>
                            @else
                                @foreach ($daftarTugas as $kode => $label)
                                    <option @selected($kode === $tugasSekarang)>{{ $label }}</option>
                                @endforeach
                            @endif
                        </select>
                        @if ($akunSendiri)
                            <p class="mt-1 text-xs text-gray-400">Tugas tambahan akun yang sedang Anda pakai tidak dapat diganti.</p>
                        @elseif ($role === 5)
                            <p class="mt-1 text-xs text-gray-400">Tugas tambahan Super Admin tidak dapat diganti.</p>
                        @endif
                    @endif
                </div>

                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input id="username" name="username" type="text" required maxlength="255"
                        value="{{ old('username', $user->username) }}"
                        class="{{ $input }} {{ $errors->has('username') ? 'border-red-400' : 'border-gray-300' }}">
                    <p class="mt-1 text-xs text-gray-400">Huruf, angka, titik, garis bawah, dan strip. Tidak boleh angka saja.</p>
                    @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($profil)
                    <div>
                        <label for="nip" class="mb-1 block text-sm font-medium text-gray-700">NIP <span class="text-red-500">*</span></label>
                        <input id="nip" name="nip" type="text" required inputmode="numeric" maxlength="18"
                            value="{{ old('nip', $nipProfil) }}"
                            class="{{ $input }} font-mono {{ $errors->has('nip') ? 'border-red-400' : 'border-gray-300' }}">
                        <p class="mt-1 text-xs text-gray-400">Angka 5 sampai 18 digit. Dipakai untuk login.</p>
                        @error('nip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="nama" class="mb-1 block text-sm font-medium text-gray-700">Nama {{ $labelRole }} <span class="text-red-500">*</span></label>
                        <input id="nama" name="nama" type="text" required maxlength="255"
                            value="{{ old('nama', $namaProfil) }}"
                            class="{{ $input }} {{ $errors->has('nama') ? 'border-red-400' : 'border-gray-300' }}">
                        @error('nama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="{{ $profil ? '' : 'sm:col-span-2' }}">
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" required maxlength="255"
                        value="{{ old('email', $user->email) }}"
                        class="{{ $input }} {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($profil && $role === 3)
                    <div data-tugas-lama>
                        <label for="id_kelas" class="mb-1 block text-sm font-medium text-gray-700">Kelas Perwalian <span class="text-red-500">*</span></label>
                        <select id="id_kelas" name="id_kelas" required
                            class="{{ $input }} {{ $errors->has('id_kelas') ? 'border-red-400' : 'border-gray-300' }}">
                            @foreach ($daftarKelas as $k)
                                <option value="{{ $k->id_kelas }}" @selected(old('id_kelas', $profil->id_kelas) === $k->id_kelas)>
                                    {{ $k->nama_kelas }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($profil && $role === 4)
                    <div data-tugas-lama>
                        <label for="id_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Jurusan <span class="text-red-500">*</span></label>
                        <select id="id_jurusan" name="id_jurusan" required
                            class="{{ $input }} {{ $errors->has('id_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
                            @foreach ($daftarJurusan as $j)
                                <option value="{{ $j->id_jurusan }}" @selected(old('id_jurusan', $profil->id_jurusan) === $j->id_jurusan)>
                                    {{ $j->nama_jurusan }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            @if ($bisaGantiTugas)
                <div id="blokTugasBaru" class="hidden space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-start gap-3 text-sm text-amber-800">
                        <i class="bi bi-exclamation-triangle mt-0.5"></i>
                        <span>
                            Mengganti tugas tambahan menghapus akun dan profil lama, lalu membuat akun baru
                            (login memakai NIP, password awal <strong>{{ \App\Support\AkunStaf::PASSWORD_DEFAULT }}</strong>).
                            Perubahan username, email, dan password pada form ini tidak dipakai. Penggantian ditolak bila
                            akun lama masih punya riwayat penilaian, intervensi, atau catatan.
                        </span>
                    </div>

                    <div id="blokKelasBaru" class="hidden">
                        <label for="id_kelas_baru" class="mb-1 block text-sm font-medium text-gray-700">Kelas Perwalian (opsional)</label>
                        <select id="id_kelas_baru" name="id_kelas_baru"
                            class="{{ $input }} bg-white {{ $errors->has('id_kelas_baru') ? 'border-red-400' : 'border-gray-300' }}">
                            <option value="">Belum ada kelas</option>
                            @foreach ($daftarKelasBaru as $k)
                                <option value="{{ $k->id_kelas }}" @selected((string) old('id_kelas_baru') === (string) $k->id_kelas)>
                                    {{ $k->nama_kelas }}{{ $k->walikelas ? ' (wali: ' . $k->walikelas->nama_walikelas . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Satu kelas hanya punya satu wali. Jika kelas sudah punya wali, wali lamanya dilepas.</p>
                        @error('id_kelas_baru') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div id="blokJurusanBaru" class="hidden">
                        <label for="id_jurusan_baru" class="mb-1 block text-sm font-medium text-gray-700">Program / Kompetensi Keahlian <span class="text-red-500">*</span></label>
                        <select id="id_jurusan_baru" name="id_jurusan_baru"
                            class="{{ $input }} bg-white {{ $errors->has('id_jurusan_baru') ? 'border-red-400' : 'border-gray-300' }}">
                            <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                            @foreach ($daftarJurusanBaru as $j)
                                <option value="{{ $j->id_jurusan }}" @selected((string) old('id_jurusan_baru') === (string) $j->id_jurusan)>
                                    {{ $j->label_dropdown }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_jurusan_baru') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            @if ($profil && $role === 2)
                <div data-tugas-lama>
                    <p class="mb-1 text-sm font-medium text-gray-700">Kelas yang Diampu</p>
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
                    @error('kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('kelas.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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

    @if ($bisaGantiTugas)
        <script>
            (function () {
                const pilih = document.getElementById('tugas_tambahan');
                const asal = @json($tugasSekarang);
                const blokBaru = document.getElementById('blokTugasBaru');
                const blokKelas = document.getElementById('blokKelasBaru');
                const blokJurusan = document.getElementById('blokJurusanBaru');
                const jurusan = document.getElementById('id_jurusan_baru');
                const bagianLama = document.querySelectorAll('[data-tugas-lama]');

                function atur() {
                    const berubah = pilih.value !== asal;

                    blokBaru.classList.toggle('hidden', !berubah);
                    blokKelas.classList.toggle('hidden', !(berubah && pilih.value === 'walikelas'));
                    blokJurusan.classList.toggle('hidden', !(berubah && pilih.value === 'kaprog'));
                    jurusan.required = berubah && pilih.value === 'kaprog';

                    // Isian tugas lama disembunyikan dan dinonaktifkan agar tidak menghalangi simpan.
                    bagianLama.forEach(function (el) {
                        el.classList.toggle('hidden', berubah);
                        el.querySelectorAll('input, select').forEach(function (f) { f.disabled = berubah; });
                    });
                }

                pilih.addEventListener('change', atur);
                atur();
            })();
        </script>
    @endif
@endsection