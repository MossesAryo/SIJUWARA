@extends('layouts.wakasek.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/wakasek/kelas.css') }}">
@endpush

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold gradient-text">Data Kelas</h1>
                <p class="text-gray-600 mt-1">Kelola data Kelas</p>
            </div>
            @if (auth()->user()->role == 1)
                <button onclick="openCreateModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <i class="bi bi-plus-lg"></i>
                    Tambah Kelas
                </button>
            @endif
        </div>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <p class="text-sm font-semibold flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-green-600"></i>
                    {{ session('success') }}
                </p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <p class="text-sm font-semibold flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-red-600"></i>
                    {{ session('error') }}
                </p>
            </div>
        @endif

        <!-- Search & Filter -->
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex flex-col md:flex-row gap-2 items-center justify-between">


                <div class="w-full md:w-64 relative">
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="text" name="search" id="inputSearch" placeholder="Cari Kelas / Walikelas..." value="{{ request('search') }}"
                        class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg w-full">
                </div>
                <div class="flex gap-2">
                    @php
                        // Hitung tiap pilihan yang aktif (bukan jumlah parameter URL).
                        $filterCount = count(array_filter((array) request('tingkat', []), fn ($v) => $v !== ''))
                            + (request('sort') ? 1 : 0);

                        if (auth()->user()->role != '4') {
                            $filterCount += count(array_filter((array) request('jurusan', []), fn ($v) => $v !== ''));
                        }
                    @endphp
                    <button type="button" onclick="openFilterModal()"
                        class="px-3 py-1.5 border rounded-lg flex items-center gap-1.5 {{ $filterCount > 0 ? 'bg-blue-50 text-blue-700 border-blue-200' : 'border-gray-300 hover:bg-gray-50' }}">
                        <i class="bi {{ $filterCount > 0 ? 'bi-funnel-fill' : 'bi-funnel' }}"></i>
                        <span class="ml-1">Filter</span>
                        @if ($filterCount > 0)
                            <span
                                class="ml-2 inline-flex items-center justify-center bg-blue-600 text-white text-xs font-semibold rounded-full w-6 h-6">{{ $filterCount }}</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>

        {{-- @include('wakasek.kelas.modalExportImport') --}}

        <!-- Data Table -->
        <div class="bg-white rounded-xl shadow-sm border overflow-visible">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Daftar Kelas</h3>
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
                                    <i class="bi bi-tag text-gray-400"></i>
                                    Program / Konsentrasi Keahlian
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-tag text-gray-400"></i>
                                    Nama Kelas
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-person-badge text-gray-400"></i>
                                    Walikelas
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-people text-gray-400"></i>
                                    Jumlah Siswa
                                </div>
                            </th>
                            @if (auth()->user()->role == 1)
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-gear text-gray-400"></i>
                                        Aksi
                                    </div>
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="bg-white divide-y divide-gray-100">
                        @forelse ($kelas as $item)
                            <tr class="hover:bg-gray-50 group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-2 h-2 bg-blue-400 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        </div>
                                        <span
                                            class="text-sm font-medium text-gray-900">{{ ($kelas->firstItem() ?? 0) + $loop->iteration - 1 }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ $item->kode_keahlian }}</div>
                                    <div class="text-xs text-gray-500">{{ $item->label_keahlian }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ $item->nama_kelas }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $wali = $item->walikelas && $item->walikelas->nama_walikelas ? $item->walikelas : null;
                                        $bisaAturWali = auth()->user()->role == 1;
                                    @endphp

                                    @if ($bisaAturWali)
                                        <button type="button"
                                            data-id="{{ $item->id_kelas }}"
                                            data-nama="{{ $item->nama_kelas }}"
                                            data-nip="{{ $wali->nip_walikelas ?? '' }}"
                                            onclick="openWaliModal(this)"
                                            class="wali-btn group/wali text-left rounded-lg px-2 py-1 -mx-2 -my-1 hover:bg-blue-50 transition-colors"
                                            title="Klik untuk mengganti walikelas {{ $item->nama_kelas }}">
                                    @endif

                                    @if ($wali)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                {{ strtoupper(mb_substr($wali->nama_walikelas, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">
                                                    {{ $wali->nama_walikelas }}</div>
                                                <div class="text-xs text-gray-500">NIP
                                                    {{ $wali->nip_walikelas }}</div>
                                            </div>
                                            @if ($bisaAturWali)
                                                <i class="bi bi-pencil text-xs text-blue-500 opacity-0 group-hover/wali:opacity-100 transition-opacity"></i>
                                            @endif
                                        </div>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 text-gray-500 text-xs font-medium {{ $bisaAturWali ? 'group-hover/wali:bg-blue-100 group-hover/wali:text-blue-700' : '' }}">
                                            <i class="bi {{ $bisaAturWali ? 'bi-person-plus' : 'bi-person-x' }}"></i>
                                            {{ $bisaAturWali ? 'Pilih wali' : 'Belum ada wali' }}
                                        </span>
                                    @endif

                                    @if ($bisaAturWali)
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <button type="button"
                                        onclick="openSiswaModal('{{ $item->id_kelas }}', '{{ $item->nama_kelas }}')"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 text-sm font-semibold transition-colors"
                                        title="Lihat daftar siswa {{ $item->nama_kelas }}">
                                        <i class="bi bi-people"></i>
                                        {{ number_format($item->siswa_count) }} siswa
                                        <i class="bi bi-chevron-right text-xs"></i>
                                    </button>
                                </td>
                                @if (auth()->user()->role == 1)
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1">
                                            <button
                                                onclick="openEditModal('{{ $item->id_kelas }}', '{{ $item->nama_kelas }}', '{{ $item->id_jurusan }}')"
                                                class="action-btn inline-flex items-center justify-center w-9 h-9 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-full"
                                                title="Edit Kelas">
                                                <i class="bi bi-pencil-square text-sm"></i>
                                            </button>
                                            <button
                                                onclick="openDeleteModal('{{ $item->id_kelas }}', '{{ $item->nama_kelas }}')"
                                                class="action-btn inline-flex items-center justify-center w-9 h-9 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-full"
                                                title="Hapus Kelas">
                                                <i class="bi bi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div
                                        class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i class="bi bi-collection text-3xl text-gray-400"></i>
                                    </div>
                                    @if ($filterCount > 0 || request('search'))
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada kelas yang cocok</h3>
                                        <p class="text-gray-500">Ubah kata kunci atau filter yang dipakai.</p>
                                    @else
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data kelas</h3>
                                        <p class="text-gray-500">Tambahkan data kelas untuk memulai.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- PAGINATION -->
            <div id="pagination" class="px-6 py-4 border-t border-gray-200 bg-white">
                @include('layouts.wakasek.pagination', ['data' => $kelas])
            </div>
        </div>
    </div>

    @include('wakasek.kelas.create')
    @include('wakasek.kelas.edit')
    @if (auth()->user()->role == 1)
        @include('wakasek.kelas.walikelas')
    @endif
    @include('wakasek.kelas.delete')
    @include('wakasek.kelas.modalFilter')
    @include('wakasek.kelas.siswa')
@endsection

@push('js')
    @php
        $daftarGuruJs = $guruList->map(fn ($g) => [
            'nip'        => (string) $g->nip_walikelas,
            'nama'       => $g->nama_walikelas,
            'id_kelas'   => $g->id_kelas,
            'nama_kelas' => optional($g->kelas)->nama_kelas,
        ])->values();
    @endphp
    <script>
        // ===== Mapping keahlian (sumber: App\Models\kelas) =====
        // Kelas 10 = Program Keahlian, kelas 11/12 = Konsentrasi Keahlian
        const PROGRAM_KEAHLIAN = @json(\App\Models\kelas::petaProgram());
        const KOMPETENSI_KEAHLIAN = @json(\App\Models\kelas::petaKonsentrasiUtama());
        const KONSENTRASI_KEAHLIAN = @json(\App\Models\kelas::petaKonsentrasi());

        function tingkatDariNama(nama) {
            const m = (nama || '').trim().match(/^(12|11|10)\s/);
            return m ? m[1] : null;
        }

        function kodeKeahlian(tingkat, idJurusan) {
            if (tingkat === '10') return PROGRAM_KEAHLIAN[idJurusan] || null;
            const list = KONSENTRASI_KEAHLIAN[idJurusan] || [];
            return list.length ? list.join(' / ') : null;
        }

        function infoKeahlian(tingkat, idJurusan) {
            if (!tingkat || !idJurusan) return '';
            const label = tingkat === '10' ? 'Program Keahlian' : 'Konsentrasi Keahlian';
            const kode = kodeKeahlian(tingkat, idJurusan);
            return kode ?
                `Kelas ${tingkat}: ${label} ${kode}` :
                `Jurusan ini tidak tersedia untuk kelas ${tingkat}`;
        }

        // ===== Modal Tambah =====
        function openCreateModal() {
            const modal = document.getElementById('modal-create');
            if (!modal) return console.warn('modal-create tidak ditemukan');
            modal.classList.remove('hidden');
        }

        // Isi otomatis ID & nama kelas saat tingkat + jurusan dipilih
        function isiOtomatisCreate() {
            const tingkat = document.getElementById('create_tingkat')?.value;
            const jur = document.getElementById('id_jurusan')?.value;
            const hint = document.getElementById('create_hint');
            const idEl = document.getElementById('id_kelas');
            const namaEl = document.getElementById('nama_kelas');

            if (hint) hint.textContent = infoKeahlian(tingkat, jur);
            if (!tingkat || !jur) return;

            const kode = tingkat === '10' ?
                PROGRAM_KEAHLIAN[jur] :
                (KONSENTRASI_KEAHLIAN[jur] || [])[0];
            if (!kode) return;

            // ID pakai kode konsentrasi supaya naik kelas tetap jalan (10-RPL-1 -> 11-RPL-1)
            const prefixId = `${tingkat}-${KOMPETENSI_KEAHLIAN[jur]}-`;
            const prefixNama = `${tingkat} ${kode} `;

            if (idEl && (idEl.value === '' || idEl.dataset.auto === '1')) {
                idEl.value = prefixId;
                idEl.dataset.auto = '1';
            }
            if (namaEl && (namaEl.value === '' || namaEl.dataset.auto === '1')) {
                namaEl.value = prefixNama;
                namaEl.dataset.auto = '1';
            }
        }

        // ===== Modal Filter =====
        function openFilterModal() {
            const modal = document.getElementById('modal-filter');
            if (!modal) return console.warn('modal-filter tidak ditemukan');
            modal.classList.remove('hidden');
        }

        // ===== Modal Edit =====
        function refreshHintEdit() {
            const hint = document.getElementById('edit_hint');
            if (!hint) return;
            const nama = document.getElementById('edit_nama_kelas')?.value;
            const jur = document.getElementById('edit_jurusan')?.value;
            hint.textContent = infoKeahlian(tingkatDariNama(nama), jur);
        }

        function openEditModal(id, nama, jurusanVal) {
            const idK = document.getElementById('edit_id_kelas');
            const nm = document.getElementById('edit_nama_kelas');
            const jur = document.getElementById('edit_jurusan');
            const form = document.getElementById('form-edit');
            const mdl = document.getElementById('modal-edit');

            if (idK) idK.value = id;
            if (nm) nm.value = nama;
            if (jur) jur.value = jurusanVal ?? '';

            refreshHintEdit();

            if (form) form.action = `/kelas/${id}/update`;
            if (!mdl) return console.warn('modal-edit tidak ditemukan');

            mdl.classList.remove('hidden');
        }

        // ===== Modal Pilih Walikelas =====
        // Data guru dari server: [{nip, nama, id_kelas, nama_kelas}]
        const DAFTAR_GURU = @json($daftarGuruJs);

        let waliKelasId = null;
        let waliNipSekarang = '';

        function openWaliModal(btn) {
            const mdl = document.getElementById('modal-walikelas');
            if (!mdl) return;

            waliKelasId = btn.dataset.id;
            waliNipSekarang = btn.dataset.nip || '';

            document.getElementById('wali-nama-kelas').textContent = btn.dataset.nama;
            document.getElementById('form-walikelas').action = `/kelas/${encodeURIComponent(waliKelasId)}/walikelas`;
            document.getElementById('wali-cari').value = '';

            renderDaftarGuru(waliNipSekarang);
            mdl.classList.remove('hidden');
            document.getElementById('wali-cari').focus();
        }

        function renderDaftarGuru(pilihNip) {
            const box = document.getElementById('wali-daftar');
            const kunci = (document.getElementById('wali-cari').value || '').toLowerCase().trim();
            box.innerHTML = '';

            const item = (nip, judul, sub, ikon) => {
                const label = document.createElement('label');
                label.className = 'wali-item flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer border border-transparent hover:bg-gray-50';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'nip_walikelas';
                radio.value = nip;
                radio.className = 'w-4 h-4 text-blue-600';
                radio.checked = String(nip) === String(pilihNip);
                radio.addEventListener('change', () => perbaruiPeringatan(nip));
                const teks = document.createElement('div');
                teks.className = 'min-w-0 flex-1';
                const a = document.createElement('div');
                a.className = 'text-sm font-semibold text-gray-900 truncate';
                a.textContent = judul;
                const b = document.createElement('div');
                b.className = 'text-xs text-gray-500';
                b.textContent = sub;
                teks.append(a, b);
                label.append(radio, teks);
                return label;
            };

            // Pilihan lepas wali
            box.appendChild(item('', 'Tanpa walikelas', 'Kosongkan walikelas kelas ini', ''));

            DAFTAR_GURU
                .filter(g => !kunci || g.nama.toLowerCase().includes(kunci) || g.nip.includes(kunci))
                .forEach(g => {
                    const sub = `NIP ${g.nip} \u2022 ` + (g.id_kelas
                        ? (g.id_kelas === waliKelasId ? 'Wali kelas ini' : `Wali ${g.nama_kelas || g.id_kelas}`)
                        : 'Belum punya kelas');
                    box.appendChild(item(g.nip, g.nama, sub, ''));
                });

            if (!box.querySelector('input[type=radio]:checked')) {
                box.querySelector('input[type=radio]').checked = true;
            }
            perbaruiPeringatan(box.querySelector('input[type=radio]:checked')?.value ?? '');
        }

        // Beri tahu dampak pilihan sebelum disimpan
        function perbaruiPeringatan(nipDipilih) {
            const warn = document.getElementById('wali-peringatan');
            const pesan = [];
            const guru = DAFTAR_GURU.find(g => g.nip === String(nipDipilih));
            const waliLama = DAFTAR_GURU.find(g => g.id_kelas === waliKelasId);

            if (guru && guru.id_kelas && guru.id_kelas !== waliKelasId) {
                pesan.push(`${guru.nama} sekarang wali ${guru.nama_kelas || guru.id_kelas}; kelas itu akan jadi kosong.`);
            }
            if (waliLama && (!guru || waliLama.nip !== guru.nip)) {
                pesan.push(`${waliLama.nama} akan dilepas dari kelas ini.`);
            }

            warn.textContent = pesan.join(' ');
            warn.classList.toggle('hidden', pesan.length === 0);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('wali-cari')?.addEventListener('input', () => {
                const dipilih = document.querySelector('#wali-daftar input[type=radio]:checked')?.value ?? waliNipSekarang;
                renderDaftarGuru(dipilih);
            });
        });

        // ===== Modal Hapus =====
        function openDeleteModal(id, nama) {
            const nameEl = document.getElementById('delete-nama-kelas');
            const form = document.getElementById('form-delete');
            const mdl = document.getElementById('modal-delete');

            if (nameEl) nameEl.textContent = nama;
            if (form) form.action = `/kelas/${id}`;
            if (!mdl) return console.warn('modal-delete tidak ditemukan');
            mdl.classList.remove('hidden');
        }

        // --- CLOSERS / UX ---
        function closeAllModals() {
            document.querySelectorAll('[id^="modal-"]').forEach(m => m.classList.add('hidden'));
        }

        // ESC to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAllModals();
        });

        // Klik di overlay untuk menutup (pastikan elemen overlay adalah elemen dengan id modal-*)
        document.querySelectorAll('[id^="modal-"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.classList.add('hidden');
            });
        });

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        // Listener form tambah & edit
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('create_tingkat')?.addEventListener('change', isiOtomatisCreate);
            document.getElementById('id_jurusan')?.addEventListener('change', isiOtomatisCreate);

            // Kalau user mengetik sendiri, jangan ditimpa lagi oleh isi otomatis
            ['id_kelas', 'nama_kelas'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', e => {
                    e.target.dataset.auto = '0';
                });
            });

            document.getElementById('edit_jurusan')?.addEventListener('change', refreshHintEdit);
            document.getElementById('edit_nama_kelas')?.addEventListener('input', refreshHintEdit);
        });

        // Search functionality
        document.addEventListener("DOMContentLoaded", () => {
            const input = document.getElementById("inputSearch");
            const tableBody = document.getElementById("tableBody");
            const pagination = document.getElementById("pagination");

            let debounceTimer = null;

            // Simpan halaman terakhir sebelum search
            let lastPageUrl = window.location.href;

            function fetchData(url) {
                fetch(url, {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                    .then(res => {
                        if (!res.ok) {
                            throw new Error(`HTTP error ${res.status}`);
                        }
                        return res.text();
                    })
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, "text/html");

                        const newTableBody = doc.querySelector("#tableBody");
                        const newPagination = doc.querySelector("#pagination");

                        if (!newTableBody || !newPagination) {
                            console.error("Selector tidak ditemukan");
                            console.log(html);
                            return;
                        }

                        tableBody.innerHTML = newTableBody.innerHTML;
                        pagination.innerHTML = newPagination.innerHTML;

                        activatePaginationLinks();
                    })
                    .catch(err => console.error("ERR:", err));
            }

            function activatePaginationLinks() {
                const links = document.querySelectorAll("#pagination a");

                links.forEach(link => {
                    link.addEventListener("click", function(e) {
                        e.preventDefault();

                        // Simpan page terakhir sebelum search
                        lastPageUrl = this.href;

                        fetchData(this.href);
                    });
                });
            }

            activatePaginationLinks();

            // Auto search
            input.addEventListener("keyup", function() {
                clearTimeout(debounceTimer);

                debounceTimer = setTimeout(() => {
                    const query = input.value.trim();

                    if (query.length === 0) {
                        // User hapus search → kembali ke daftar sebelum dicari (filter tetap dipakai)
                        const params = new URLSearchParams(window.location.search);
                        params.delete('search');
                        params.delete('page');
                        const qs = params.toString();
                        fetchData(window.location.pathname + (qs ? '?' + qs : ''));
                        return;
                    }

                    // Search selalu mulai dari page 1, filter & urutan yang aktif tetap dipakai.
                    const params = new URLSearchParams(window.location.search);
                    params.delete('page');
                    params.set('search', query);
                    fetchData(`${window.location.pathname}?${params.toString()}`);

                }, 200);
            });
        });
    </script>
@endpush