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
    @endphp

    <div class="mb-5">
        <a href="{{ route('superadmin.role.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Role
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Edit Akun {{ $labelRole }}</h2>
        <p class="text-sm text-gray-400">Role, NIP, dan username dikunci karena menjadi kunci relasi data.</p>
    </div>

    @unless ($profil || $role === 5)
        <div class="mb-4 flex max-w-3xl items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="bi bi-exclamation-triangle mt-0.5"></i>
            <span>Profil {{ $labelRole }} untuk akun ini tidak ditemukan, sehingga hanya email dan password yang dapat diubah.</span>
        </div>
    @endunless

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <form method="POST" action="{{ route('superadmin.role.update', $user->id) }}" class="space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                    <input type="text" value="{{ $labelRole }}" readonly class="{{ $input }} {{ $terkunci }}">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" value="{{ $user->username }}" readonly class="{{ $input }} {{ $terkunci }}">
                    <p class="mt-1 text-xs text-gray-400">Username tidak dapat diubah.</p>
                </div>

                @if ($profil)
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">NIP</label>
                        <input type="text" value="{{ $nipProfil }}" readonly class="{{ $input }} {{ $terkunci }} font-mono">
                        <p class="mt-1 text-xs text-gray-400">NIP tidak dapat diubah karena terhubung dengan riwayat data.</p>
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
                    <div>
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
                    <div>
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

            @if ($profil && $role === 2)
                <div>
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
                <a href="{{ route('superadmin.role.index') }}"
                    class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection