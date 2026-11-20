@php
    // Dipakai bersama partial modal guru (filter, edit) supaya labelnya "Tugas Tambahan".
    $labelJabatan = 'Tugas Tambahan';
    $guruUrl = route('superadmin.user.index');
    $guruUrlReset = route('superadmin.user.index', ['tab' => 'guru']);
    $tabGuru = true;
    $bisaEditTugas = true;

    $filterCount = 0;
    if (request()->filled('jabatan')) {
        $filterCount++;
    }
    if (in_array(request('sort'), ['nip', 'jabatan'], true)
        || (request('sort') === 'nama' && request('dir') === 'desc')) {
        $filterCount++;
    }
@endphp

<div class="mb-4 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="bi bi-info-circle mt-0.5"></i>
    <span>
        Data guru dan staf TU tidak punya akun login sampai diberi tugas tambahan. Memilih tugas tambahan
        (Wakasek, Guru BK, Wali Kelas, atau Ketua Program) membuat akun login dengan NIP dan password awal
        <strong>password123</strong>. Menghapus tugas tambahan ikut menghapus akunnya, data guru tetap ada.
    </span>
</div>

@if (session('import_errors'))
    <div class="mb-4 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-800">
        <p class="flex items-center gap-2 text-sm font-semibold">
            <i class="bi bi-info-circle-fill text-yellow-600"></i>
            Baris yang dilewati saat import ({{ count(session('import_errors')) }}):
        </p>
        <ul class="mt-2 max-h-40 list-inside list-disc space-y-0.5 overflow-y-auto text-sm">
            @foreach (array_slice(session('import_errors'), 0, 50) as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        @if (count(session('import_errors')) > 50)
            <p class="mt-1 text-xs">...dan {{ count(session('import_errors')) - 50 }} baris lainnya.</p>
        @endif
    </div>
@endif

<div class="rounded-2xl border border-gray-200 bg-white">
    {{-- Pencarian, filter, export/import (dikelola oleh public/js/wakasek/guru.js) --}}
    <div class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:w-72">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input id="inputSearch" type="text" autocomplete="off" value="{{ request('search') }}"
                placeholder="Cari nama atau NIP..."
                class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
        </div>
        <div class="flex gap-2">
            <button id="openFilterModal" type="button" onclick="openFilterModal()"
                class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                <i class="bi bi-funnel"></i> Filter
                @if ($filterCount > 0)
                    <span class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{{ $filterCount }}</span>
                @endif
            </button>
            <button id="exportImportBtn" type="button"
                class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                <i class="bi bi-download"></i> Export / Import
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">No</th>
                    <th class="px-4 py-3">NIP</th>
                    <th class="px-4 py-3">Nama Guru</th>
                    <th class="px-4 py-3">Tugas Tambahan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody" class="divide-y divide-gray-100">
                @forelse ($guru as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ ($guru->firstItem() ?? 0) + $loop->index }}</td>
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $item->nip_guru }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item->nama_guru }}</td>
                        <td class="px-4 py-3">
                            @if ($item->jabatan)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    <i class="bi bi-diagram-3"></i> {{ $item->jabatan }}
                                </span>
                                @if ($item->jabatan_detail)
                                    <span class="ml-1 text-xs text-gray-500">{{ $item->jabatan_detail }}</span>
                                @endif
                            @else
                                <span class="text-sm text-gray-400">Belum dipetakan</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <button type="button"
                                data-nip="{{ $item->nip_guru }}"
                                data-nama="{{ $item->nama_guru }}"
                                data-jabatan="{{ $item->jabatan }}"
                                data-jabatan-kode="{{ $item->jabatan_kode }}"
                                data-id-kelas="{{ optional($item->walikelas)->id_kelas }}"
                                data-id-jurusan="{{ optional($item->kaprog)->id_jurusan }}"
                                onclick="openEditModal(this)"
                                class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit Guru & Tugas Tambahan">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button"
                                data-nip="{{ $item->nip_guru }}"
                                data-nama="{{ $item->nama_guru }}"
                                onclick="openDeleteModal(this)"
                                class="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Hapus Guru">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                            @if (request()->filled('search') || request()->filled('jabatan'))
                                Data guru tidak ditemukan. Coba kata kunci atau filter yang lain.
                            @else
                                Belum ada data guru. Tambahkan atau import data guru untuk memulai.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="pagination" class="border-t border-gray-100">
        @include('layouts.wakasek.pagination', ['data' => $guru])
    </div>
</div>

@include('wakasek.guru.filter')
@include('wakasek.guru.modalExportImport')
@include('wakasek.guru.create')
@include('wakasek.guru.edit')
@include('wakasek.guru.delete')

@push('js')
    <script src="{{ asset('js/wakasek/guru.js') }}"></script>
@endpush