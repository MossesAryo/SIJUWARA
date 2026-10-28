@extends('layouts.wakasek.app')

@push('css')
    <style>
        .table-hover tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); transition: all 0.2s; }
        .action-btn:hover { transform: scale(1.1); }
        body.modal-open { overflow: hidden; }
    </style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold gradient-text">Data Penanganan</h1>
            <p class="text-gray-600 mt-1">Kelola Penanganan</p>
        </div>
        <button onclick="openCreateModal()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg flex items-center gap-2 shadow-md transition-all duration-200 hover:shadow-lg">
            <i class="bi bi-plus-lg"></i>
            Tambah Penanganan
        </button>
    </div>

    <!-- Alert -->
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
            <i class="bi bi-check-circle-fill text-green-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-red-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Search + Filter -->
    <div class="bg-white p-6 rounded-xl shadow-sm border">
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between mb-4">
            <div class="relative w-full md:w-64">
                <i class="bi bi-search absolute left-3 top-3 text-gray-400"></i>
                <input id="inputSearch" type="text" placeholder="Cari Nama Siswa..." value="{{ request('search') }}"
                    class="pl-10 pr-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            </div>
            <div class="flex gap-3">
                @php
                    $filterCount = collect(request()->except(['page','search','_token','_method']))->filter(function($v){ return $v !== null && $v !== ''; })->count();
                @endphp
                <button type="button" onclick="openFilterModal()"
                    class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-blue-50 flex items-center gap-2 transition">
                    <i class="bi bi-funnel"></i> Filter
                    @if($filterCount > 0)
                        <span class="ml-2 inline-flex items-center justify-center bg-blue-600 text-white text-xs font-semibold rounded-full w-6 h-6">{{ $filterCount }}</span>
                    @endif
                </button>
                @if (auth()->user()->role == 1)
                <button id="exportImportBtn"
                    class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-blue-50 flex items-center gap-2 transition">
                    <i class="bi bi-download"></i> Export
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border overflow-visible">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Daftar Penanganan</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-hover">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal Mulai</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal Selesai</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Penanganan</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="bg-white divide-y divide-gray-100">
                    @php \App\Support\KelasSaatKejadian::muatDari($intervensi); @endphp
                    @forelse($intervensi as $item)
                        <tr class="hover:bg-blue-50 transition">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                {{ $loop->iteration + ($intervensi->currentPage()-1)*$intervensi->perPage() }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->siswa->nama_siswa }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700" title="Kelas siswa saat penanganan ini dibuat">
                                {{ \App\Support\KelasSaatKejadian::nama($item->nis, $item->created_at) }}
                                <span class="block text-xs text-gray-400">TA {{ \App\Support\KelasSaatKejadian::pada($item->nis, $item->created_at)['tahun_ajaran'] ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ \Carbon\Carbon::parse($item->tanggal_Mulai_Perbaikan)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ \Carbon\Carbon::parse($item->tanggal_Selesai_Perbaikan)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $item->nama_intervensi }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColor = match($item->status) {
                                        'Binaan Khusus' => 'bg-yellow-100 text-yellow-800',
                                        'Dalam Binaan'  => 'bg-orange-100 text-orange-800',
                                        'Selesai'       => 'bg-green-100 text-green-800',
                                        default         => 'bg-gray-100 text-gray-800'
                                    };
                                @endphp
                                <span class="px-3 py-1.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('intervensi.show', $item->id_intervensi) }}"
                                        class="text-yellow-600 hover:text-yellow-800 p-2 rounded-full hover:bg-yellow-50 transition">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    {{--
                                        FIX: Gunakan id_bukti_pembinaan (bukan id) agar nilai tidak null.
                                        Pastikan nama PK ini sesuai dengan kolom di tabel bukti_pembinaan Anda.
                                    --}}
                                    <button onclick='openEditModalIndex(
                                            "{{ $item->id_intervensi }}",
                                            "{{ $item->nis }}",
                                            {{ json_encode($item->nama_intervensi) }},
                                            {{ json_encode($item->isi_intervensi) }},
                                            "{{ $item->status }}",
                                            "{{ $item->tanggal_Mulai_Perbaikan }}",
                                            "{{ $item->tanggal_Selesai_Perbaikan }}",
                                            {{ json_encode($item->perubahan_setelah_intervensi ?? '') }},
                                            {{ $item->bukti->map(fn($b) => [
                                                "id"        => $b->id_bukti_pembinaan,
                                                "path"      => $b->file,
                                                "nama_file" => $b->nama_file
                                            ])->toJson() }}
                                        )'
                                        class="text-blue-600 hover:text-blue-800 p-2 rounded-full hover:bg-blue-50 transition">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button onclick="openDeleteModal('{{ $item->id_intervensi }}','{{ $item->siswa->nama_siswa }}')"
                                        class="text-red-600 hover:text-red-800 p-2 rounded-full hover:bg-red-50 transition">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="bi bi-clipboard-check text-5xl text-gray-600"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data penanganan</h3>
                                <p class="text-gray-500">Tambahkan data penanganan untuk memulai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="pagination" class="px-6 py-4 border-t border-gray-200 bg-white">
            @include('layouts.wakasek.pagination', ['data' => $intervensi])
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div id="modal-filter" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-visible">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-funnel-fill text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800">Filter Data Penanganan</h3>
            </div>
            <button onclick="closeModal('modal-filter')" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-200 rounded-full transition">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <form method="GET" action="{{ route('intervensi.index') }}">
            <div class="p-6 space-y-6">
                @if (auth()->user()->role == 1 || auth()->user()->role == 2 || auth()->user()->role == 3)
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-grid-3x3-gap-fill text-blue-600"></i> Kelas
                    </label>
                    <select name="kelas" class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                        <option value="">Semua Kelas</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id_kelas }}" {{ request('kelas') == $k->id_kelas ? 'selected' : '' }}>{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-check-circle-fill text-blue-600"></i> Status
                    </label>
                    <select name="status" class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                        <option value="">Semua Status</option>
                        <option value="Binaan Khusus" {{ request('status') == 'Binaan Khusus' ? 'selected' : '' }}>Binaan Khusus</option>
                        <option value="Dalam Binaan"  {{ request('status') == 'Dalam Binaan'  ? 'selected' : '' }}>Dalam Binaan</option>
                        <option value="Selesai"       {{ request('status') == 'Selesai'       ? 'selected' : '' }}>Selesai</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="bi bi-calendar-event text-blue-600"></i> Tanggal Mulai
                        </label>
                        <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="bi bi-calendar-check text-blue-600"></i> Tanggal Akhir
                        </label>
                        <input type="date" name="tanggal_akhir" value="{{ request('tanggal_akhir') }}"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 p-6 border-t bg-gray-50">
                <a href="{{ route('intervensi.index') }}"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl hover:bg-gray-100 transition flex items-center gap-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl shadow-lg hover:from-blue-700 hover:to-indigo-700 transition flex items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i> Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

@include('wakasek.intervensi.create')
@include('wakasek.intervensi.modalExportImport')
@include('wakasek.intervensi.edit')
@include('wakasek.intervensi.delete')
@endsection

@push('js')
<script>
    function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-open'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-open'); }
    function openCreateModal() { openModal('modal-create'); }
    function openFilterModal() { openModal('modal-filter'); }

    /**
     * Buka modal edit dan isi semua field + tampilkan file tersimpan.
     *
     * FIX utama:
     *  1. Gunakan json_encode di blade (bukan addslashes) agar karakter khusus aman.
     *  2. Set window.existingFilesData sebelum memanggil renderExistingFiles().
     *  3. Panggil openEditModal() dari edit.blade.php agar logika terpusat di satu tempat.
     */
    function openEditModalIndex(id, nis, nama_intervensi, isi_intervensi, status,
                                tanggalMulai, tanggalSelesai, perubahan, existingFiles) {

        // Siapkan data existing files ke window scope
        window.existingFilesData = [];
        window.uploadedFilesEdit = [];

        if (existingFiles && existingFiles.length > 0) {
            existingFiles.forEach(function(f) {
                window.existingFilesData.push({
                    id:        f.id,
                    path:      f.path,
                    url:       '/storage/' + f.path,
                    nama_file: f.nama_file || f.path.split('/').pop()
                });
            });
        }

        // Panggil fungsi openEditModal dari edit.blade.php
        if (typeof openEditModal === 'function') {
            openEditModal({
                id:                              id,
                nis:                             nis,
                nama_intervensi:                 nama_intervensi,
                isi_intervensi:                  isi_intervensi,
                status:                          status,
                tanggal_Mulai_Perbaikan:         tanggalMulai,
                tanggal_Selesai_Perbaikan:       tanggalSelesai,
                perubahan_setelah_intervensi:    perubahan,
                return_to:                       window.location.href,
                existing_files:                  window.existingFilesData
            });
        }
    }

    function openDeleteModal(id, nama) {
        const form = document.getElementById('form-delete');
        if (form) form.action = `/intervensi/${id}/destroy`;
        const nameSpan = document.getElementById('delete-nama-intervensi');
        if (nameSpan) nameSpan.textContent = nama || '';
        openModal('modal-delete');
    }

    // ── Search AJAX ───────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const input      = document.getElementById('inputSearch');
        const tableBody  = document.getElementById('tableBody');
        const pagination = document.getElementById('pagination');
        let lastPageUrl  = window.location.href;

        function fetchData(url) {
            fetch(url)
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    tableBody.innerHTML  = doc.querySelector('#tableBody').innerHTML;
                    pagination.innerHTML = doc.querySelector('#pagination').innerHTML;
                    document.querySelectorAll('#pagination a').forEach(a => {
                        a.addEventListener('click', e => {
                            e.preventDefault();
                            lastPageUrl = a.href;
                            fetchData(a.href);
                        });
                    });
                });
        }

        if (input) {
            input.addEventListener('keyup', function () {
                clearTimeout(window._searchTimer);
                window._searchTimer = setTimeout(() => {
                    const q = this.value.trim();
                    fetchData(q ? `/intervensi?search=${encodeURIComponent(q)}` : lastPageUrl);
                }, 300);
            });
        }

        // ── Kelas dropdown search (create modal) ──────────────
        const searchInput = document.getElementById('kelasSearch');
        const list        = document.getElementById('kelasList');
        const hiddenInput = document.getElementById('kelas');

        if (searchInput && list && hiddenInput) {
            const items = list.querySelectorAll('.dropdown-item');

            searchInput.addEventListener('focus', () => {
                list.classList.remove('hidden');
                list.style.display = 'block';
            });

            searchInput.addEventListener('input', function () {
                const filter        = this.value.toLowerCase().trim();
                let hasVisibleItem  = false;
                items.forEach(item => {
                    const show = item.textContent.toLowerCase().trim().includes(filter);
                    item.style.display = show ? 'block' : 'none';
                    if (show) hasVisibleItem = true;
                });
                list.style.display = hasVisibleItem ? 'block' : 'none';
                hiddenInput.value  = '';
            });

            items.forEach(item => {
                item.addEventListener('click', function () {
                    searchInput.value  = this.textContent.trim();
                    hiddenInput.value  = this.dataset.value;
                    list.style.display = 'none';
                });
            });

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.dropdown-container')) {
                    list.style.display = 'none';
                }
            });

            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') list.style.display = 'none';
            });
        }
    });
</script>
@endpush