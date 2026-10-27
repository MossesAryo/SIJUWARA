@extends('layouts.wakasek.app')

@php
    $modeDipilih   = old('mode', 'manual');
    $tujuanManual  = old('kelas_tujuan', '');
    $tujuanAcakOld = (array) old('kelas_tujuan_acak', []);
    $totalSiswa    = $siswaList->count();
    $totalRencana  = count($rencanaMap);
    $bisaProses    = $totalSiswa > 0 && $kelasTujuan->isNotEmpty();
    $asal          = $transisi['asal'];
    $tujuan        = $transisi['tujuan'];
@endphp

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold">Perpindahan Kelas {{ $asal }} → {{ $tujuan }}</h1>
        <p class="text-gray-500">
            Rencanakan penempatan siswa kelas {{ $asal }} ke kelas {{ $tujuan }}
            @if ($jurusan)
                program {{ $jurusan->nama_jurusan }}
            @endif
            . Siswa dari berbagai rombel boleh dicampur.
        </p>
    </div>

    {{-- Pilihan jenis perpindahan --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($daftarTransisi as $kunci => $t)
            <a href="{{ route('perpindahan_kelas.index', ['transisi' => $kunci]) }}"
               class="px-4 py-2 rounded-lg border text-sm font-semibold
                      {{ $kunci === $transisi['kunci'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                Kelas {{ $t['asal'] }} → {{ $t['tujuan'] }}
            </a>
        @endforeach
    </div>

    {{-- Alert --}}
    @if (session('success'))
        <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Info --}}
    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded text-sm flex gap-2">
        <i class="bi bi-info-circle-fill mt-0.5"></i>
        <p>
            Yang Anda simpan di sini hanya <b>rencana</b>. Siswa tetap di kelas {{ $asal }} dan baru pindah ke kelas {{ $tujuan }}
            saat Wakasek mengganti tahun ajaran. Selama belum diganti, rencana boleh diubah atau dibatalkan.
            @if ($asal === '11')
                Untuk kelas 11 → 12, rencana ini hanya menentukan <b>kelas tujuan</b>; siswa yang benar-benar naik
                tetap dicentang oleh Wakasek. Siswa kelas 11 tanpa rencana masuk ke kelas pasangannya (mis. 11 RPL 1 → 12 RPL 1).
            @endif
        </p>
    </div>

    {{-- Ringkasan --}}
    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-amber-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa kelas {{ $asal }} aktif</p>
            <p class="text-3xl font-bold text-amber-700">{{ $totalSiswa }}</p>
        </div>
        <div class="bg-emerald-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Sudah direncanakan</p>
            <p class="text-3xl font-bold text-emerald-700">{{ $totalRencana }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $totalSiswa - $totalRencana }} belum ditempatkan</p>
        </div>
        <div class="bg-blue-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa dipilih</p>
            <p class="text-3xl font-bold text-blue-700" id="jumlahDipilih">0</p>
        </div>
        <div class="bg-green-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Kelas {{ $tujuan }} tujuan</p>
            <p class="text-3xl font-bold text-green-700">{{ $kelasTujuan->count() }}</p>
        </div>
    </div>

    {{-- Isi kelas 11 --}}
    <div class="bg-white border rounded-xl p-6">
        <h3 class="font-semibold text-lg mb-1">Kelas {{ $tujuan }} Program Anda</h3>
        <p class="text-sm text-gray-500 mb-4">
            @if ($tujuan === '11')
                Siswa kelas 11 saat ini akan naik ke kelas 12 saat pergantian tahun ajaran.
            @else
                Siswa kelas 12 saat ini akan lulus menjadi alumni saat pergantian tahun ajaran, sehingga kelas 12 kosong untuk angkatan baru.
            @endif
            Pembagian acak dihitung dari rencana masuk saja, sehingga tiap kelas mendapat jumlah yang sama.
        </p>

        @if ($kelasTujuan->isEmpty())
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-3 rounded text-sm">
                Belum ada kelas {{ $tujuan }} untuk program Anda. Minta Wakasek membuat kelas {{ $tujuan }} di menu Kelas terlebih dahulu.
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($kelasTujuan as $k)
                    <div class="border rounded-lg p-3">
                        <p class="text-sm font-semibold text-gray-900">{{ $k->nama_kelas }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $jumlahTujuan[$k->id_kelas] ?? 0 }} siswa sekarang
                        </p>
                        <p class="text-xs text-emerald-700">
                            +{{ $rencanaPerKelas[$k->id_kelas] ?? 0 }} rencana masuk
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Form --}}
    <div class="bg-white border rounded-xl p-6">
        <h3 class="font-semibold mb-1 text-lg">Rencanakan Penempatan</h3>
        <p class="text-sm text-gray-500 mb-4">
            Centang siswa pada tabel di bawah, pilih cara penempatan, lalu simpan rencana.
        </p>

        <form method="POST" action="{{ route('perpindahan_kelas.simpan') }}" id="formPerpindahan"
              data-url-simpan="{{ route('perpindahan_kelas.simpan') }}"
              data-url-batal="{{ route('perpindahan_kelas.batal') }}">
            @csrf
            <input type="hidden" name="transisi" value="{{ $transisi['kunci'] }}">
            <input type="hidden" name="siswa_nis" id="siswaNisInput" value="{{ old('siswa_nis') }}">

            <div class="grid md:grid-cols-2 gap-3 mb-4">
                <label class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="mode" value="manual" class="mt-1"
                        {{ $modeDipilih === 'manual' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">Ke satu kelas tujuan</span>
                        <span class="block text-xs text-gray-500">
                            Semua siswa yang dicentang direncanakan masuk ke satu kelas {{ $tujuan }}. Ulangi untuk kelas lainnya.
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="mode" value="acak" class="mt-1"
                        {{ $modeDipilih === 'acak' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">Acak dan bagi merata</span>
                        <span class="block text-xs text-gray-500">
                            Siswa dikocok lalu dibagi rata ke beberapa kelas {{ $tujuan }} yang Anda pilih.
                        </span>
                    </span>
                </label>
            </div>

            {{-- Panel manual --}}
            <div id="panelManual" class="{{ $modeDipilih === 'manual' ? '' : 'hidden' }}">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Kelas {{ $tujuan }} tujuan</label>
                <select name="kelas_tujuan" id="selectKelasTujuan"
                    class="border rounded px-4 py-2 w-full md:w-1/2">
                    <option value="">-- Pilih kelas {{ $tujuan }} --</option>
                    @foreach ($kelasTujuan as $k)
                        <option value="{{ $k->id_kelas }}" data-nama="{{ $k->nama_kelas }}"
                            {{ (string) $tujuanManual === (string) $k->id_kelas ? 'selected' : '' }}>
                            {{ $k->nama_kelas }}
                            ({{ $jumlahTujuan[$k->id_kelas] ?? 0 }} siswa, +{{ $rencanaPerKelas[$k->id_kelas] ?? 0 }} rencana)
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Panel acak --}}
            <div id="panelAcak" class="{{ $modeDipilih === 'acak' ? '' : 'hidden' }}">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Kelas {{ $tujuan }} tujuan (pilih minimal 2)
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach ($kelasTujuan as $k)
                        <label class="flex items-center gap-2 border rounded-lg p-2 cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" name="kelas_tujuan_acak[]" value="{{ $k->id_kelas }}"
                                class="tujuan-acak w-4 h-4" data-nama="{{ $k->nama_kelas }}"
                                {{ in_array($k->id_kelas, $tujuanAcakOld, true) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-900">{{ $k->nama_kelas }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex flex-col md:flex-row md:items-center gap-3">
                <button type="submit" id="btnSubmit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $bisaProses ? '' : 'disabled' }}>
                    Simpan Rencana
                </button>
                <button type="button" id="btnBatalRencana"
                    class="border border-red-300 text-red-700 hover:bg-red-50 px-5 py-2.5 rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $totalRencana > 0 ? '' : 'disabled' }}>
                    Batalkan Rencana Siswa Terpilih
                </button>
                <p id="infoDipilih" class="text-sm text-gray-600"></p>
            </div>
        </form>
    </div>

    {{-- Data Siswa --}}
    <div class="bg-white border rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Siswa Kelas {{ $asal }}</h3>
                    <p class="text-sm text-gray-500">{{ $totalSiswa }} siswa aktif. Centang siswa yang akan direncanakan.</p>
                </div>
                <div class="relative w-full md:w-64">
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input id="inputSearchSiswa" type="text" placeholder="Cari Siswa..."
                        class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="w-full md:w-64">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-collection text-gray-500"></i> Kelas Asal
                    </label>
                    <select id="filterKelasAsal"
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Semua Kelas {{ $asal }} --</option>
                        @foreach ($kelasAsal as $k)
                            <option value="{{ $k->id_kelas }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full md:w-64">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-diagram-3 text-gray-500"></i> Status Rencana
                    </label>
                    <select id="filterStatusRencana"
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Semua --</option>
                        <option value="belum">Belum ditempatkan</option>
                        <option value="sudah">Sudah direncanakan</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIS</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas Asal</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rencana Kelas {{ $tujuan }}</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="checkSemuaSiswa" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                                <label for="checkSemuaSiswa" class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Pilih Semua</label>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody id="siswaTableBody" class="bg-white divide-y divide-gray-100">
                    @forelse ($siswaList as $item)
                        @php
                            $idRencana = $rencanaMap[(string) $item->nis] ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50"
                            data-nis="{{ $item->nis }}"
                            data-nama="{{ strtolower($item->nama_siswa) }}"
                            data-kelas="{{ $item->id_kelas ?? '' }}"
                            data-rencana="{{ $idRencana ? 'sudah' : 'belum' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900 nomor-urut">{{ $loop->iteration }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">{{ $item->nis }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ strtoupper($item->nama_siswa) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $item->kelas?->nama_kelas ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($idRencana)
                                    <span class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-1">
                                        → {{ $namaTujuan[$idRencana] ?? $idRencana }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-500 bg-gray-100 border border-gray-200 rounded px-2 py-1">
                                        Belum ditempatkan
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="siswa-check w-4 h-4" data-nis="{{ $item->nis }}">
                            </td>
                        </tr>
                    @empty
                        <tr id="barisKosong">
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="bi bi-people text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada siswa kelas {{ $asal }} aktif</h3>
                                <p class="text-gray-500">Belum ada siswa kelas {{ $asal }} yang aktif di program Anda.</p>
                            </td>
                        </tr>
                    @endforelse

                    <tr id="barisTidakDitemukan" style="display:none">
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                            Tidak ada siswa yang cocok dengan filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal konfirmasi --}}
<div id="modalKonfirmasi"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-md shadow-lg">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-lg">Konfirmasi Rencana Penempatan</h3>
        </div>
        <div class="p-5 text-sm space-y-2">
            <p id="modalDesc" class="text-gray-700"></p>
            <p class="text-gray-500">
                Siswa belum pindah. Mereka baru masuk ke kelas {{ $tujuan }} saat Wakasek mengganti tahun ajaran.
                Rencana siswa yang sama akan diganti.
            </p>
        </div>
        <div class="p-5 border-t flex justify-end gap-2">
            <button type="button" id="btnBatal"
                    class="border px-4 py-2 rounded hover:bg-gray-50">
                Batal
            </button>
            <button type="button" id="btnConfirm"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Ya, Simpan Rencana
            </button>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    const form          = document.getElementById('formPerpindahan');
    const nisInput      = document.getElementById('siswaNisInput');
    const infoDipilih   = document.getElementById('infoDipilih');
    const jumlahDipilih = document.getElementById('jumlahDipilih');
    const btnSubmit     = document.getElementById('btnSubmit');
    const btnBatalRencana = document.getElementById('btnBatalRencana');

    const panelManual   = document.getElementById('panelManual');
    const panelAcak     = document.getElementById('panelAcak');
    const selectTujuan  = document.getElementById('selectKelasTujuan');
    const radioMode     = document.querySelectorAll('input[name="mode"]');

    const modal         = document.getElementById('modalKonfirmasi');
    const modalDesc     = document.getElementById('modalDesc');
    const btnConfirm    = document.getElementById('btnConfirm');
    const btnBatal      = document.getElementById('btnBatal');

    const tableBody     = document.getElementById('siswaTableBody');
    const inputSearch   = document.getElementById('inputSearchSiswa');
    const filterKelas   = document.getElementById('filterKelasAsal');
    const filterStatus  = document.getElementById('filterStatusRencana');
    const checkSemua    = document.getElementById('checkSemuaSiswa');
    const barisTidakAda = document.getElementById('barisTidakDitemukan');

    /* ---------- Mode ---------- */

    function modeAktif() {
        const dipilih = document.querySelector('input[name="mode"]:checked');
        return dipilih ? dipilih.value : 'manual';
    }

    function toggleMode() {
        const mode = modeAktif();
        panelManual.classList.toggle('hidden', mode !== 'manual');
        panelAcak.classList.toggle('hidden', mode !== 'acak');
    }

    /* ---------- Pemilihan siswa ---------- */

    function getSelectedSiswaNis() {
        return Array.from(tableBody.querySelectorAll('input.siswa-check:checked'))
            .map(cb => cb.dataset.nis)
            .filter(Boolean);
    }

    function syncSiswaNisInput() {
        const daftar = getSelectedSiswaNis();
        nisInput.value = daftar.join(',');
        jumlahDipilih.textContent = daftar.length;
        infoDipilih.textContent = daftar.length > 0
            ? `${daftar.length} siswa dipilih.`
            : 'Belum ada siswa yang dipilih.';
        return daftar;
    }

    function barisTampak() {
        return Array.from(tableBody.querySelectorAll('tr[data-nis]'))
            .filter(row => row.style.display !== 'none');
    }

    function syncMasterCheck() {
        const kotak = barisTampak()
            .map(row => row.querySelector('input.siswa-check'))
            .filter(Boolean);

        const jumlahCentang = kotak.filter(cb => cb.checked).length;
        checkSemua.checked       = kotak.length > 0 && jumlahCentang === kotak.length;
        checkSemua.indeterminate = jumlahCentang > 0 && jumlahCentang < kotak.length;
    }

    function toggleSemuaSiswa(checked) {
        barisTampak().forEach(row => {
            const cb = row.querySelector('input.siswa-check');
            if (cb) cb.checked = checked;
        });
        syncSiswaNisInput();
        syncMasterCheck();
    }

    /* ---------- Filter ---------- */

    function applyFilter() {
        const query  = (inputSearch?.value || '').trim().toLowerCase();
        const kelas  = filterKelas?.value || '';
        const status = filterStatus?.value || '';

        let no = 0;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            let tampil = true;

            if (kelas && String(row.dataset.kelas || '') !== String(kelas)) tampil = false;
            if (status && String(row.dataset.rencana || '') !== String(status)) tampil = false;

            if (query) {
                const nis  = String(row.dataset.nis || '').toLowerCase();
                const nama = String(row.dataset.nama || '').toLowerCase();
                if (!nis.includes(query) && !nama.includes(query)) tampil = false;
            }

            if (tampil) {
                no++;
                const nomor = row.querySelector('.nomor-urut');
                if (nomor) nomor.textContent = no;
            }

            row.style.display = tampil ? '' : 'none';
        });

        const adaBaris = tableBody.querySelector('tr[data-nis]') !== null;
        if (barisTidakAda) {
            barisTidakAda.style.display = (adaBaris && no === 0) ? '' : 'none';
        }

        syncMasterCheck();
    }

    /* ---------- Modal & submit ---------- */

    function bukaModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function tutupModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const daftar = syncSiswaNisInput();

        if (daftar.length === 0) {
            alert('Pilih minimal satu siswa kelas {{ $asal }}.');
            return;
        }

        if (modeAktif() === 'manual') {
            if (!selectTujuan || !selectTujuan.value) {
                alert('Pilih kelas {{ $tujuan }} tujuan terlebih dahulu.');
                return;
            }

            const nama = selectTujuan.options[selectTujuan.selectedIndex].dataset.nama;
            modalDesc.textContent = `${daftar.length} siswa akan direncanakan masuk ke ${nama}.`;
        } else {
            const dipilih = Array.from(document.querySelectorAll('input.tujuan-acak:checked'));

            if (dipilih.length < 2) {
                alert('Pilih minimal 2 kelas {{ $tujuan }} tujuan untuk pembagian acak.');
                return;
            }

            const namaKelas = dipilih.map(cb => cb.dataset.nama).join(', ');
            modalDesc.textContent = `${daftar.length} siswa akan diacak dan direncanakan dibagi merata ke: ${namaKelas}.`;
        }

        form.action = form.dataset.urlSimpan;
        bukaModal();
    });

    btnConfirm.addEventListener('click', function () {
        btnConfirm.disabled = true;
        btnSubmit.disabled  = true;
        btnConfirm.textContent = 'Menyimpan...';
        form.submit();
    });

    btnBatal.addEventListener('click', tutupModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) tutupModal();
    });

    /* ---------- Batalkan rencana ---------- */

    btnBatalRencana?.addEventListener('click', function () {
        const daftar = syncSiswaNisInput();

        if (daftar.length === 0) {
            alert('Pilih minimal satu siswa yang rencananya akan dibatalkan.');
            return;
        }

        if (!confirm(`Batalkan rencana penempatan untuk ${daftar.length} siswa terpilih?`)) {
            return;
        }

        btnBatalRencana.disabled = true;
        btnSubmit.disabled = true;
        form.action = form.dataset.urlBatal;
        form.submit();
    });

    /* ---------- Event ---------- */

    radioMode.forEach(r => r.addEventListener('change', toggleMode));
    inputSearch?.addEventListener('input', applyFilter);
    filterKelas?.addEventListener('change', applyFilter);
    filterStatus?.addEventListener('change', applyFilter);
    checkSemua?.addEventListener('change', () => toggleSemuaSiswa(checkSemua.checked));

    tableBody.addEventListener('change', function (event) {
        if (event.target?.classList?.contains('siswa-check')) {
            syncSiswaNisInput();
            syncMasterCheck();
        }
    });

    /* ---------- Init ---------- */

    // Pulihkan centang setelah redirect karena error (old input).
    const sebelumnya = (nisInput.value || '').split(',').map(v => v.trim()).filter(Boolean);
    if (sebelumnya.length > 0) {
        tableBody.querySelectorAll('input.siswa-check').forEach(cb => {
            cb.checked = sebelumnya.includes(cb.dataset.nis);
        });
    }

    toggleMode();
    applyFilter();
    syncSiswaNisInput();
})();
</script>
@endpush