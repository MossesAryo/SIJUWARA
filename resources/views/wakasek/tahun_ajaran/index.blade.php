@extends('layouts.wakasek.app')

@php
    $preview = $preview ?? ['x_ke_xi' => 0, 'xi_ke_xii' => 0, 'lulus' => 0];
    $tahunAktif = $tahunAktif ?? null;
    $tahunDipilih = old('tahun_ajaran_id', $tahunAktif?->id);

    $jurusanList = \App\Models\jurusan::all();
    $kelasList = \App\Models\kelas::with('jurusan')->get();
    $siswaList = \App\Models\siswa::with(['kelas.jurusan'])->orderBy('nama_siswa')->get();
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
            Sistem akan otomatis menaikkan atau menurunkan kelas siswa sesuai selisih tahun ajaran.
        </p>

        <form method="POST" action="{{ route('tahun_ajaran.update') }}"
              onsubmit="return confirmGanti(event)">
            @csrf
            <input type="hidden" name="siswa_nis" id="siswaNisInput" value="{{ old('siswa_nis') }}">
            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                <select name="tahun_ajaran_id" id="selectTahun"
                        class="border rounded px-4 py-2 w-full md:w-1/2">
                    @foreach ($tahunAjaran as $ta)
                        <option value="{{ $ta->id }}"
                            data-selisih="{{ $ta->id - ($tahunAktif?->id ?? $ta->id) }}"
                            {{ $ta->id == $tahunDipilih ? 'selected' : '' }}>
                            {{ $ta->tahun_ajaran }}
                            @if ($ta->status === 'aktif') (aktif) @endif
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold">
                    Ganti Tahun Ajaran
                </button>
            </div>

            <p id="hintSelisih" class="text-sm mt-3"></p>
        </form>
    </div>

    {{-- Data Siswa --}}
    <div class="bg-white border rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Data Siswa</h3>
                    <p class="text-sm text-gray-500">{{ $siswaList->count() }} siswa pada tahun ajaran terpilih.</p>
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
                        <select id="filterJurusanTahun" name="jurusan"
                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                            <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                            @foreach ($jurusanList as $jurusan)
                                <option value="{{ $jurusan->id_jurusan }}"
                                    {{ request('jurusan') == $jurusan->id_jurusan ? 'selected' : '' }}>
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
                    <select id="filterKelasTahun" name="kelas"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Semua Kelas --</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}" data-jurusan="{{ $kelas->id_jurusan }}"
                                {{ request('kelas') == $kelas->id_kelas ? 'selected' : '' }}>
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
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-hash text-gray-400"></i>
                                No
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-hash text-gray-400"></i>
                                NIS
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-person text-gray-400"></i>
                                Nama Siswa
                            </div>
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-person text-gray-400"></i>
                                Kelas
                            </div>
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="checkSemuaSiswa" class="siswa-check-semua w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                                <label for="checkSemuaSiswa" class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Check Semua Siswa</label>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody id="tahunTableBody" class="bg-white divide-y divide-gray-100">
                    @forelse ($siswaList as $item)
                        <tr class="hover:bg-gray-50 group"
                            data-nis="{{ $item->nis }}"
                            data-nama="{{ strtolower(addslashes($item->nama_siswa)) }}"
                            data-kelas="{{ $item->kelas?->id_kelas ?? '' }}"
                            data-jurusan="{{ $item->id_jurusan ?? '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $loop->iteration }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    <span class="text-sm font-medium text-gray-900">{{ $item->nis }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ strtoupper($item->nama_siswa) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $item->kelas?->nama_kelas ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="siswa-check" data-nis="{{ $item->nis }}">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="bi bi-people text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data siswa</h3>
                                <p class="text-gray-500">Tambahkan data siswa untuk memulai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal --}}
<div id="modalKonfirmasi"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 flex">
    <div class="bg-white rounded-xl w-full max-w-md shadow-lg">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-lg">Konfirmasi Ganti Tahun Ajaran</h3>
        </div>
        <div class="p-5 text-sm space-y-2">
            <p id="modalDesc" class="text-gray-700"></p>
            <p class="text-red-600 font-semibold mt-2">Proses ini tidak bisa dibatalkan.</p>
        </div>
        <div class="p-5 border-t flex justify-end gap-2">
            <button onclick="closeModal()"
                    class="border px-4 py-2 rounded hover:bg-gray-50">
                Batal
            </button>
            <button id="btnConfirm"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Ya, Ganti
            </button>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    const select   = document.getElementById('selectTahun');
    const hint     = document.getElementById('hintSelisih');
    const activeId = {{ $tahunAktif?->id ?? 'null' }};

    function hitungPerubahan(selisih) {
        const tingkats = ['X', 'XI', 'XII'];
        return tingkats.map(t => {
            const targetIdx = tingkats.indexOf(t) + selisih;
            if (selisih > 0) {
                return targetIdx >= tingkats.length
                    ? `${t} → Alumni`
                    : `${t} → ${tingkats[targetIdx]}`;
            } else {
                return targetIdx < 0
                    ? `${t} → (tidak berubah)`
                    : `${t} → ${tingkats[targetIdx]}`;
            }
        }).join(', ');
    }

    function updateHint() {
        const opt     = select.options[select.selectedIndex];
        const selisih = parseInt(opt.dataset.selisih ?? 0);

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

    select.addEventListener('change', updateHint);
    updateHint();

    function confirmGanti(e) {
        e.preventDefault();

        const opt     = select.options[select.selectedIndex];
        const selisih = parseInt(opt.dataset.selisih ?? 0);

        if (selisih === 0) {
            alert('Tahun ajaran ini sudah aktif.');
            return false;
        }

        syncSiswaNisInput();

        const arah   = selisih > 0
            ? `Naik ${selisih} tingkat`
            : `Turun ${Math.abs(selisih)} tingkat`;

        document.getElementById('modalDesc').textContent = `${arah}: ${hitungPerubahan(selisih)}`;
        document.getElementById('modalKonfirmasi').classList.remove('hidden');
        document.getElementById('btnConfirm').onclick = () => e.target.submit();

        return false;
    }

    function closeModal() {
        document.getElementById('modalKonfirmasi').classList.add('hidden');
    }

    const tableBody = document.getElementById('tahunTableBody');
    const inputSearchTahun = document.getElementById('inputSearchTahun');
    const filterJurusanTahun = document.getElementById('filterJurusanTahun');
    const filterKelasTahun = document.getElementById('filterKelasTahun');
    const checkSemuaSiswa = document.getElementById('checkSemuaSiswa');

    function getSelectedSiswaNis() {
        if (!tableBody) return [];

        return Array.from(tableBody.querySelectorAll('input.siswa-check:checked'))
            .map(checkbox => checkbox.dataset.nis)
            .filter(Boolean);
    }

    function syncSiswaNisInput() {
        const input = document.getElementById('siswaNisInput');
        if (input) input.value = getSelectedSiswaNis().join(',');
    }

    function applyTahunFilter() {
        const query = (inputSearchTahun?.value || '').trim().toLowerCase();
        const jurusan = filterJurusanTahun?.value || '';
        const kelas = filterKelasTahun?.value || '';

        if (!tableBody) return;

        let no = 0;
        let visibleCount = 0;

        tableBody.querySelectorAll('tr').forEach(row => {
            if (!row.dataset.nis) {
                row.style.display = visibleCount === 0 ? '' : 'none';
                return;
            }

            let show = true;

            if (jurusan && String(row.dataset.jurusan || '') !== String(jurusan)) {
                show = false;
            }

            if (kelas && String(row.dataset.kelas || '') !== String(kelas)) {
                show = false;
            }

            if (query) {
                const nis = String(row.dataset.nis || '').toLowerCase();
                const nama = String(row.dataset.nama || '').toLowerCase();
                if (!nis.includes(query) && !nama.includes(query)) {
                    show = false;
                }
            }

            if (show) {
                no++;
                visibleCount++;
                const valueDiv = row.querySelector('td')?.querySelector('div');
                if (valueDiv) valueDiv.textContent = no;
            }

            row.style.display = show ? '' : 'none';
        });
    }

    function filterKelasByJurusan() {
        const jurusanEl = document.getElementById('filterJurusanTahun');
        const kelasEl = document.getElementById('filterKelasTahun');

        if (!jurusanEl || !kelasEl) return;

        const selected = String(jurusanEl.value).trim();

        Array.from(kelasEl.options).forEach(opt => {
            if (!opt.value) return;

            const optJurusan = String(opt.getAttribute('data-jurusan') || '').trim();
            const match = !selected || optJurusan === selected;

            opt.hidden = !match;
            opt.disabled = !match;
        });

        const current = kelasEl.options[kelasEl.selectedIndex];
        if (current && current.value && current.hidden) {
            kelasEl.value = '';
        }
    }

    function toggleSemuaSiswa(checked) {
        if (!tableBody) return;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            if (row.style.display === 'none') return;

            const checkbox = row.querySelector('input.siswa-check');
            if (checkbox) checkbox.checked = checked;
        });

        syncSiswaNisInput();
    }

    function syncMasterCheck() {
        if (!tableBody || !checkSemuaSiswa) return;

        const rows = Array.from(tableBody.querySelectorAll('tr[data-nis]'));
        const visibleRows = rows.filter(row => row.style.display !== 'none');
        const checkboxes = visibleRows
            .map(row => row.querySelector('input.siswa-check'))
            .filter(Boolean);

        const allChecked = checkboxes.length > 0 && checkboxes.every(checkbox => checkbox.checked);
        checkSemuaSiswa.checked = allChecked;
    }

    document.getElementById('filterJurusanTahun')?.addEventListener('change', () => {
        filterKelasByJurusan();
        applyTahunFilter();
    });

    inputSearchTahun?.addEventListener('input', applyTahunFilter);
    filterKelasTahun?.addEventListener('change', applyTahunFilter);
    checkSemuaSiswa?.addEventListener('change', () => toggleSemuaSiswa(checkSemuaSiswa.checked));

    if (tableBody) {
        tableBody.addEventListener('change', (event) => {
            if (event.target?.classList?.contains('siswa-check')) {
                syncMasterCheck();
                syncSiswaNisInput();
            }
        });
    }

    filterKelasByJurusan();
    applyTahunFilter();
</script>
@endpush