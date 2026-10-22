@extends('layouts.wakasek.app')

@php
    $preview     = $preview ?? ['x_ke_xi' => 0, 'xi_ke_xii' => 0, 'lulus' => 0];
    $tahunAktif  = $tahunAktif ?? null;
    $tahunDipilih = old('tahun_ajaran_id', $tahunAktif?->id);
@endphp

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold">Manajemen Tahun Ajaran</h1>
        <p class="text-gray-500">Kelola proses kenaikan kelas siswa sesuai tahun ajaran aktif</p>
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

    {{-- Preview --}}
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-blue-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa X → XI</p>
            <p class="text-3xl font-bold text-blue-700">{{ $preview['x_ke_xi'] }}</p>
        </div>
        <div class="bg-indigo-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa XI → XII</p>
            <p class="text-3xl font-bold text-indigo-700">{{ $preview['xi_ke_xii'] }}</p>
        </div>
        <div class="bg-green-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa XII → Alumni</p>
            <p class="text-3xl font-bold text-green-700">{{ $preview['lulus'] }}</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-white border rounded-xl p-6">
        <h3 class="font-semibold mb-1 text-lg">Pilih Tahun Ajaran</h3>
        <p class="text-sm text-gray-500 mb-4">
            Sistem akan menaikkan atau menurunkan kelas siswa yang dicentang sesuai selisih tahun ajaran.
            Siswa yang tidak dicentang tidak akan berubah kelasnya.
        </p>

        <form method="POST" action="{{ route('tahun_ajaran.update') }}" id="formTahunAjaran">
            @csrf
            <input type="hidden" name="siswa_nis" id="siswaNisInput" value="{{ old('siswa_nis') }}">
            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                <select name="tahun_ajaran_id" id="selectTahun"
                        class="border rounded px-4 py-2 w-full md:w-1/2">
                    @foreach ($opsiTahun as $opsi)
                        <option value="{{ $opsi['id'] }}"
                            data-selisih="{{ $opsi['selisih'] }}"
                            {{ (string) $opsi['id'] === (string) $tahunDipilih ? 'selected' : '' }}>
                            {{ $opsi['label'] }}@if ($opsi['aktif']) (aktif)@endif
                        </option>
                    @endforeach
                </select>

                <button type="submit" id="btnSubmitTahun"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold">
                    Ganti Tahun Ajaran
                </button>
            </div>

            <p id="hintSelisih" class="text-sm mt-3"></p>
            <p id="infoDipilih" class="text-sm text-gray-600 mt-1"></p>
        </form>
    </div>

    {{-- Data Siswa --}}
    <div class="bg-white border rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Data Siswa</h3>
                    <p class="text-sm text-gray-500">{{ $siswaList->count() }} siswa aktif. Centang siswa yang akan diproses.</p>
                </div>
                <div class="relative w-full md:w-64">
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input id="inputSearchTahun" type="text" placeholder="Cari Siswa..."
                        class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                @if (auth()->user()->role == 1 || auth()->user()->role == 2)
                    <div class="w-full md:w-64">
                        <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="bi bi-buildings text-gray-500"></i> Program / Kompetensi Keahlian
                        </label>
                        <select id="filterJurusanTahun"
                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                            <option value="">-- Semua Program / Kompetensi Keahlian --</option>
                            @foreach ($jurusanList as $jurusan)
                                <option value="{{ $jurusan->id_jurusan }}">
                                    {{ $jurusan->label_dropdown }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="w-full md:w-64">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-collection text-gray-500"></i> Kelas
                    </label>
                    <select id="filterKelasTahun"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Semua Kelas --</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}" data-jurusan="{{ $kelas->id_jurusan }}">
                                {{ $kelas->nama_kelas }}
                            </option>
                        @endforeach
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
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="checkSemuaSiswa" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                                <label for="checkSemuaSiswa" class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Pilih Semua</label>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody id="tahunTableBody" class="bg-white divide-y divide-gray-100">
                    @forelse ($siswaList as $item)
                        <tr class="hover:bg-gray-50 group"
                            data-nis="{{ $item->nis }}"
                            data-nama="{{ strtolower($item->nama_siswa) }}"
                            data-kelas="{{ $item->id_kelas ?? '' }}"
                            data-jurusan="{{ $item->id_jurusan ?? '' }}">
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
                                <input type="checkbox" class="siswa-check w-4 h-4" data-nis="{{ $item->nis }}">
                            </td>
                        </tr>
                    @empty
                        <tr id="barisKosong">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="bi bi-people text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada siswa aktif</h3>
                                <p class="text-gray-500">Tambahkan data siswa untuk memulai.</p>
                            </td>
                        </tr>
                    @endforelse

                    <tr id="barisTidakDitemukan" style="display:none">
                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
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
            <h3 class="font-semibold text-lg">Konfirmasi Ganti Tahun Ajaran</h3>
        </div>
        <div class="p-5 text-sm space-y-2">
            <p id="modalDesc" class="text-gray-700"></p>
            <p id="modalJumlah" class="text-gray-700"></p>
            <p class="text-red-600 font-semibold mt-2">Proses ini tidak bisa dibatalkan.</p>
        </div>
        <div class="p-5 border-t flex justify-end gap-2">
            <button type="button" id="btnBatal"
                    class="border px-4 py-2 rounded hover:bg-gray-50">
                Batal
            </button>
            <button type="button" id="btnConfirm"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Ya, Ganti
            </button>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    const TINGKAT = ['X', 'XI', 'XII'];

    const form        = document.getElementById('formTahunAjaran');
    const select      = document.getElementById('selectTahun');
    const hint        = document.getElementById('hintSelisih');
    const infoDipilih = document.getElementById('infoDipilih');
    const nisInput    = document.getElementById('siswaNisInput');
    const btnSubmit   = document.getElementById('btnSubmitTahun');

    const modal       = document.getElementById('modalKonfirmasi');
    const modalDesc   = document.getElementById('modalDesc');
    const modalJumlah = document.getElementById('modalJumlah');
    const btnConfirm  = document.getElementById('btnConfirm');
    const btnBatal    = document.getElementById('btnBatal');

    const tableBody        = document.getElementById('tahunTableBody');
    const inputSearch      = document.getElementById('inputSearchTahun');
    const filterJurusan    = document.getElementById('filterJurusanTahun');
    const filterKelas      = document.getElementById('filterKelasTahun');
    const checkSemua       = document.getElementById('checkSemuaSiswa');
    const barisTidakAda    = document.getElementById('barisTidakDitemukan');

    const activeId = {{ $tahunAktif?->id ?? 'null' }};

    /* ---------- Ringkasan perubahan ---------- */

    function hitungPerubahan(selisih) {
        return TINGKAT.map((t, idx) => {
            const target = idx + selisih;

            if (selisih > 0) {
                return target >= TINGKAT.length
                    ? `${t} → Alumni`
                    : `${t} → ${TINGKAT[target]}`;
            }

            // Turun: tidak pernah di bawah X.
            const tujuan = Math.max(0, target);
            return tujuan === idx
                ? `${t} (tidak berubah)`
                : `${t} → ${TINGKAT[tujuan]}`;
        }).join(', ');
    }

    function selisihTerpilih() {
        const opt = select.options[select.selectedIndex];
        return parseInt(opt?.dataset.selisih ?? '0', 10) || 0;
    }

    function updateHint() {
        const selisih = selisihTerpilih();

        if (!activeId || selisih === 0) {
            hint.textContent = '';
            hint.className   = 'text-sm mt-3';
            return;
        }

        if (selisih > 0) {
            hint.textContent = `⬆ Naik ${selisih} tingkat: ${hitungPerubahan(selisih)}`;
            hint.className   = 'text-sm text-green-700 mt-3';
        } else {
            hint.textContent = `⬇ Turun ${Math.abs(selisih)} tingkat: ${hitungPerubahan(selisih)}`;
            hint.className   = 'text-sm text-yellow-600 mt-3';
        }
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
        const query   = (inputSearch?.value || '').trim().toLowerCase();
        const jurusan = filterJurusan?.value || '';
        const kelas   = filterKelas?.value || '';

        let no = 0;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            let tampil = true;

            if (jurusan && String(row.dataset.jurusan || '') !== String(jurusan)) tampil = false;
            if (kelas && String(row.dataset.kelas || '') !== String(kelas)) tampil = false;

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

    function filterKelasByJurusan() {
        if (!filterJurusan || !filterKelas) return;

        const terpilih = String(filterJurusan.value).trim();

        Array.from(filterKelas.options).forEach(opt => {
            if (!opt.value) return;

            const optJurusan = String(opt.getAttribute('data-jurusan') || '').trim();
            const cocok = !terpilih || optJurusan === terpilih;

            opt.hidden   = !cocok;
            opt.disabled = !cocok;
        });

        const current = filterKelas.options[filterKelas.selectedIndex];
        if (current && current.value && current.hidden) {
            filterKelas.value = '';
        }
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

        const selisih = selisihTerpilih();
        const daftar  = syncSiswaNisInput();

        // Belum ada tahun aktif: cukup aktifkan tanpa kenaikan kelas.
        if (!activeId) {
            form.submit();
            return;
        }

        if (selisih === 0) {
            alert('Tahun ajaran ini sudah aktif.');
            return;
        }

        const arah = selisih > 0
            ? `Naik ${selisih} tingkat`
            : `Turun ${Math.abs(selisih)} tingkat`;

        modalDesc.textContent = `${arah}: ${hitungPerubahan(selisih)}`;
        modalJumlah.textContent = daftar.length > 0
            ? `${daftar.length} siswa yang dicentang akan diproses.`
            : 'Tidak ada siswa yang dicentang, hanya tahun ajaran yang akan diganti.';

        bukaModal();
    });

    btnConfirm.addEventListener('click', function () {
        btnConfirm.disabled = true;
        btnSubmit.disabled  = true;
        btnConfirm.textContent = 'Memproses...';
        form.submit();
    });

    btnBatal.addEventListener('click', tutupModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) tutupModal();
    });

    /* ---------- Event ---------- */

    select.addEventListener('change', updateHint);
    inputSearch?.addEventListener('input', applyFilter);
    filterKelas?.addEventListener('change', applyFilter);
    filterJurusan?.addEventListener('change', function () {
        filterKelasByJurusan();
        applyFilter();
    });
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

    updateHint();
    filterKelasByJurusan();
    applyFilter();
    syncSiswaNisInput();
})();
</script>
@endpush