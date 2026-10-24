{{-- Variabel: $jurusan (hanya saat edit), $action, $method, $dampak (hanya saat edit) --}}
@php
    $edit = isset($jurusan);
    $input = 'w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
    $kodeAsli = $edit ? $jurusan->id_jurusan : '';
    $dampak = $dampak ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5" autocomplete="off">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="id_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Kode Jurusan <span class="text-red-500">*</span></label>
            <input id="id_jurusan" name="id_jurusan" type="text" maxlength="10" required
                value="{{ old('id_jurusan', $jurusan->id_jurusan ?? '') }}"
                data-asli="{{ $kodeAsli }}"
                style="text-transform: uppercase;"
                class="{{ $input }} font-mono {{ $errors->has('id_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
            <p class="mt-1 text-xs text-gray-400">Huruf dan angka, maksimal 10 karakter, contoh: RPL. Otomatis huruf kapital.</p>
            @error('id_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="nama_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Nama Jurusan <span class="text-red-500">*</span></label>
            <input id="nama_jurusan" name="nama_jurusan" type="text" required maxlength="255"
                value="{{ old('nama_jurusan', $jurusan->nama_jurusan ?? '') }}"
                class="{{ $input }} {{ $errors->has('nama_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
            @error('nama_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="kode_program" class="mb-1 block text-sm font-medium text-gray-700">
                Program Keahlian <span class="text-xs font-normal text-gray-400">(kelas 10)</span>
            </label>
            <input id="kode_program" name="kode_program" type="text" maxlength="20"
                value="{{ old('kode_program', $edit ? $jurusan->kode_program : '') }}"
                placeholder="Contoh: MPLB"
                style="text-transform: uppercase;"
                class="{{ $input }} font-mono {{ $errors->has('kode_program') ? 'border-red-400' : 'border-gray-300' }}">
            @error('kode_program') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="konsentrasi" class="mb-1 block text-sm font-medium text-gray-700">
                Konsentrasi Keahlian <span class="text-xs font-normal text-gray-400">(kelas 11 &amp; 12)</span>
            </label>
            <input id="konsentrasi" name="konsentrasi" type="text" maxlength="100"
                value="{{ old('konsentrasi', $edit ? implode(', ', $jurusan->daftar_konsentrasi) : '') }}"
                placeholder="Contoh: MP, MLOG"
                style="text-transform: uppercase;"
                class="{{ $input }} font-mono {{ $errors->has('konsentrasi') ? 'border-red-400' : 'border-gray-300' }}">
            <p class="mt-1 text-xs text-gray-400">Pisahkan dengan koma. Jika ada 2, tampil 2 baris (mis. MPLB | MP dan MPLB | MLOG).</p>
            @error('konsentrasi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    @if ($edit)
        {{-- Muncul hanya saat kode jurusan diubah --}}
        <div id="peringatanKode" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <p class="font-semibold"><i class="bi bi-exclamation-triangle-fill"></i> Kode jurusan akan diganti</p>
            <p class="mt-1">
                Semua data yang memakai kode
                <span class="font-mono font-semibold">{{ $kodeAsli }}</span>
                ikut dipindahkan ke kode baru:
            </p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($dampak as $label => $jumlah)
                    <li>{{ $label }}: <b>{{ number_format($jumlah) }}</b></li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs">
                Id kelas (mis. 10-RPL-1) tidak berubah. Proses ini satu paket: kalau gagal, tidak ada data yang berubah.
            </p>
        </div>
    @else
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            <i class="bi bi-info-circle"></i>
            Program Keahlian dan Konsentrasi Keahlian dipakai untuk membuat kelas (nama kelas 10 memakai program,
            kelas 11/12 memakai konsentrasi). Jurusan tanpa keduanya belum bisa dipakai membuat kelas.
        </div>
    @endif

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ route('superadmin.jurusan.index') }}"
            class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm text-gray-600 hover:bg-gray-50">Batal</a>
        <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
            {{ $edit ? 'Simpan Perubahan' : 'Simpan Jurusan' }}
        </button>
    </div>
</form>

@if ($edit)
    <script>
        (function() {
            const input = document.getElementById('id_jurusan');
            const peringatan = document.getElementById('peringatanKode');
            if (!input || !peringatan) return;

            function cek() {
                const berubah = input.value.trim().toUpperCase() !== (input.dataset.asli || '');
                peringatan.classList.toggle('hidden', !berubah);
            }

            input.addEventListener('input', cek);
            cek();
        })();
    </script>
@endif