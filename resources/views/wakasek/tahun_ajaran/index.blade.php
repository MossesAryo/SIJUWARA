@extends('layouts.wakasek.app')

@php
    $preview     = $preview ?? ['kelas10_ke_11' => 0, 'kelas10_direncanakan' => 0, 'kelas11_ke_12' => 0, 'kelas11_direncanakan' => 0, 'lulus' => 0, 'lulus_direncanakan' => 0];
    $tahunAktif  = $tahunAktif ?? null;
    $rencanaMap  = $rencanaMap ?? collect();
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

    @include('wakasek.tahun_ajaran.kelas_tujuan')
    {{-- Info kelas 10 --}}
    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded text-sm flex gap-2">
        <i class="bi bi-info-circle-fill mt-0.5"></i>
        <p>
            Penempatan siswa kelas 10 ke kelas 11 direncanakan oleh Ketua Program (siswa dicampur antar rombel).
            Saat tahun ajaran naik 1 tahun, rencana itu diterapkan otomatis. Siswa kelas 10 yang belum
            direncanakan tetap di kelas 10. Kelas 11 → 12 tetap dipilih (dicentang) di sini;
            untuk kelas 11, jika Ketua Program sudah membuat rencana, siswa yang dicentang masuk ke kelas rencana tersebut,
            jika belum mereka masuk ke kelas pasangannya. Kelas 12 → Alumni juga direncanakan Ketua Program
            (siswa kelas 12 yang ditandai lulus diluluskan otomatis, yang belum ditandai tetap di kelas 12).
        </p>
    </div>

    {{-- Preview --}}
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-amber-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Kelas 10 → 11 (rencana Ketua Program)</p>
            <p class="text-3xl font-bold text-amber-700">
                {{ $preview['kelas10_direncanakan'] }}<span class="text-lg text-gray-500"> / {{ $preview['kelas10_ke_11'] }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-1">siswa kelas 10 sudah direncanakan</p>
        </div>
        <div class="bg-indigo-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa kelas 11 → 12</p>
            <p class="text-3xl font-bold text-indigo-700">{{ $preview['kelas11_ke_12'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $preview['kelas11_direncanakan'] }} sudah direncanakan Ketua Program</p>
        </div>
        <div class="bg-green-50 border rounded-lg p-4">
            <p class="text-sm text-gray-600">Siswa kelas 12 → Alumni</p>
            <p class="text-3xl font-bold text-green-700">
                {{ $preview['lulus_direncanakan'] }}<span class="text-lg text-gray-500"> / {{ $preview['lulus'] }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-1">siswa kelas 12 sudah ditandai lulus Ketua Program</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-white border rounded-xl p-6">
        <h3 class="font-semibold mb-1 text-lg">Pilih Tahun Ajaran</h3>
        <p class="text-sm text-gray-500 mb-4">
            Sistem akan menaikkan atau menurunkan kelas siswa yang dicentang sesuai selisih tahun ajaran.
            Siswa yang tidak dicentang tidak akan berubah kelasnya.
            Siswa kelas 10 dan kelas 12 tidak bisa dicentang saat naik kelas karena mengikuti rencana Ketua Program.
        </p>

        <form method="POST" action="{{ route('tahun_ajaran.update') }}" id="formTahunAjaran">
            @csrf
            <input type="hidden" name="siswa_nis" id="siswaNisInput" value="{{ old('siswa_nis') }}">
            {{-- Diisi 1 oleh JavaScript hanya jika pengguna sengaja mengganti tahun tanpa mencentang siswa. --}}
            <input type="hidden" name="tanpa_siswa" id="tanpaSiswaInput" value="0">
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
                        @php
                            $tingkatSiswa = \App\Models\kelas::tingkatDariId($item->id_kelas);
                            $rencanaSiswa = in_array($tingkatSiswa, ['10', '11', '12'], true) ? ($rencanaMap[(string) $item->nis] ?? null) : null;
                        @endphp
                        <tr class="hover:bg-gray-50 group"
                            data-nis="{{ $item->nis }}"
                            data-nama="{{ strtolower($item->nama_siswa) }}"
                            data-kelas="{{ $item->id_kelas ?? '' }}"
                            data-jurusan="{{ $item->id_jurusan ?? '' }}"
                            data-tingkat="{{ $tingkatSiswa ?? '' }}">
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
                                @if ($tingkatSiswa === '10')
                                    @if ($rencanaSiswa)
                                        <div class="text-xs text-emerald-700 mt-1">
                                            Rencana → {{ $rencanaSiswa->kelasTujuan?->nama_kelas ?? $rencanaSiswa->id_kelas_tujuan }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1">Belum direncanakan Ketua Program</div>
                                    @endif
                                    <span class="badge-kaprog hidden text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 mt-1 inline-block">
                                        Mengikuti rencana Ketua Program
                                    </span>
                                @elseif ($tingkatSiswa === '12')
                                    @if (($rencanaSiswa?->id_kelas_tujuan) === 'ALUMNI')
                                        <div class="text-xs text-emerald-700 mt-1">Rencana → Alumni (lulus)</div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1">Belum ditandai lulus Ketua Program</div>
                                    @endif
                                    <span class="badge-kaprog hidden text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 mt-1 inline-block">
                                        Mengikuti rencana Ketua Program
                                    </span>
                                @elseif ($tingkatSiswa === '11')
                                    @if ($rencanaSiswa)
                                        <div class="text-xs text-emerald-700 mt-1">
                                            Rencana → {{ $rencanaSiswa->kelasTujuan?->nama_kelas ?? $rencanaSiswa->id_kelas_tujuan }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1">Belum direncanakan, naik ke kelas pasangan</div>
                                    @endif
                                @endif
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
            <p id="modalKaprog" class="text-amber-700"></p>
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
    const TINGKAT = ['10', '11', '12'];

    // Rencana penempatan kelas 10 -> 11 dari Ketua Program.
    const JUMLAH_KELAS10    = {{ (int) $preview['kelas10_ke_11'] }};
    const JUMLAH_DIRENCANAKAN = {{ (int) $preview['kelas10_direncanakan'] }};
    // Rencana kelulusan kelas 12 -> Alumni dari Ketua Program.
    const JUMLAH_KELAS12      = {{ (int) $preview['lulus'] }};
    const JUMLAH_LULUS_RENCANA = {{ (int) $preview['lulus_direncanakan'] }};

    const form        = document.getElementById('formTahunAjaran');
    const select      = document.getElementById('selectTahun');
    const hint        = document.getElementById('hintSelisih');
    const infoDipilih = document.getElementById('infoDipilih');
    const nisInput    = document.getElementById('siswaNisInput');
    const tanpaSiswaInput = document.getElementById('tanpaSiswaInput');
    const btnSubmit   = document.getElementById('btnSubmitTahun');

    const modal       = document.getElementById('modalKonfirmasi');
    const modalDesc   = document.getElementById('modalDesc');
    const modalJumlah = document.getElementById('modalJumlah');
    const modalKaprog = document.getElementById('modalKaprog');
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
                // Kelas 10 mengikuti rencana Ketua Program, hanya jika naik tepat 1 tahun.
                if (t === '10') {
                    return selisih === 1
                        ? '10 → 11 (sesuai rencana Ketua Program)'
                        : '10 (tidak diproses, rencana Ketua Program ditahan)';
                }

                // Kelas 12 lulus hanya jika sudah ditandai Ketua Program, hanya jika naik tepat 1 tahun.
                if (t === '12') {
                    return selisih === 1
                        ? '12 → Alumni (sesuai rencana Ketua Program)'
                        : '12 (tidak diproses, rencana Ketua Program ditahan)';
                }

                return target >= TINGKAT.length
                    ? `${t} → Alumni`
                    : `${t} → ${TINGKAT[target]}`;
            }

            // Turun: tidak pernah di bawah 10.
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

    /* ---------- Kunci siswa kelas 10 dan 12 saat naik kelas (mengikuti rencana Ketua Program) ---------- */

    function kunciKelas10() {
        const naik = !!activeId && selisihTerpilih() > 0;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            if (row.dataset.tingkat !== '10' && row.dataset.tingkat !== '12') return;

            const cb    = row.querySelector('input.siswa-check');
            const badge = row.querySelector('.badge-kaprog');

            if (cb) {
                cb.disabled = naik;
                if (naik) cb.checked = false;
            }
            if (badge) badge.classList.toggle('hidden', !naik);
            row.classList.toggle('opacity-60', naik);
        });

        syncSiswaNisInput();
        syncMasterCheck();
    }

    /* ---------- Pemilihan siswa ---------- */

    function getSelectedSiswaNis() {
        return Array.from(tableBody.querySelectorAll('input.siswa-check:checked:not(:disabled)'))
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

    function kotakAktifTampak() {
        return barisTampak()
            .map(row => row.querySelector('input.siswa-check'))
            .filter(cb => cb && !cb.disabled);
    }

    function syncMasterCheck() {
        const kotak = kotakAktifTampak();

        const jumlahCentang = kotak.filter(cb => cb.checked).length;
        checkSemua.checked       = kotak.length > 0 && jumlahCentang === kotak.length;
        checkSemua.indeterminate = jumlahCentang > 0 && jumlahCentang < kotak.length;
    }

    function toggleSemuaSiswa(checked) {
        kotakAktifTampak().forEach(cb => {
            cb.checked = checked;
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

    function teksKaprog(selisih) {
        if (selisih <= 0) return '';

        if (selisih > 1) {
            return 'Naik lebih dari 1 tahun: rencana Ketua Program tidak diterapkan dan siswa kelas 10 dan 12 tetap di kelasnya.';
        }

        const belum   = Math.max(0, JUMLAH_KELAS10 - JUMLAH_DIRENCANAKAN);
        const belum12 = Math.max(0, JUMLAH_KELAS12 - JUMLAH_LULUS_RENCANA);

        return `${JUMLAH_DIRENCANAKAN} siswa kelas 10 akan dipindah ke kelas 11 sesuai rencana Ketua Program. `
            + `${belum} siswa kelas 10 yang belum direncanakan tetap di kelas 10. `
            + `${JUMLAH_LULUS_RENCANA} siswa kelas 12 akan lulus menjadi alumni sesuai rencana Ketua Program. `
            + `${belum12} siswa kelas 12 yang belum ditandai lulus tetap di kelas 12.`;
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

        // Rincian siswa yang dicentang per tingkat, supaya jelas kelas 11 dan 12 ikut terkirim.
        const perTingkat = {'11': 0, '12': 0};
        daftar.forEach(nis => {
            const baris = tableBody.querySelector(`tr[data-nis="${nis}"]`);
            const t = baris ? baris.dataset.tingkat : '';
            if (perTingkat[t] !== undefined) perTingkat[t]++;
        });

        tanpaSiswaInput.value = daftar.length === 0 ? '1' : '0';

        modalJumlah.textContent = daftar.length > 0
            ? `${daftar.length} siswa yang dicentang akan diproses (kelas 11: ${perTingkat['11']}, kelas 12: ${perTingkat['12']}).`
            : (selisih > 0
                ? 'PERINGATAN: tidak ada siswa kelas 11 yang dicentang. Hanya tahun ajaran dan rencana Ketua Program (kelas 10 dan 12) yang diproses, siswa kelas 11 TIDAK naik kelas.'
                : 'PERINGATAN: tidak ada siswa kelas 11 / 12 yang dicentang. Hanya tahun ajaran yang diganti, siswa kelas 11 dan 12 TIDAK berubah kelasnya.');
        modalKaprog.textContent = teksKaprog(selisih);

        bukaModal();
    });

    btnConfirm.addEventListener('click', function () {
        // Hitung ulang daftar tepat sebelum dikirim, supaya selalu sesuai centang terakhir.
        const daftarAkhir = syncSiswaNisInput();
        tanpaSiswaInput.value = daftarAkhir.length === 0 ? '1' : '0';

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

    select.addEventListener('change', function () {
        updateHint();
        kunciKelas10();
    });
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
    kunciKelas10();
    syncSiswaNisInput();
})();
</script>
@endpush