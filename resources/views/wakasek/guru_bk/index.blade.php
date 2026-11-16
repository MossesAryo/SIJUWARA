@extends('layouts.wakasek.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/wakasek/guru_bk.css') }}">
@endpush

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold gradient-text">Data Guru BK</h1>
                <p class="text-gray-600 mt-1">Kelola data Guru BK</p>
            </div>
            @if (auth()->user()->isSuperAdmin())
            <button onclick="document.getElementById('modal-create').classList.remove('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                <i class="bi bi-plus-lg"></i>
                Tambah Guru BK
            </button>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-gray-100 text-gray-500 text-sm">
                    <i class="bi bi-lock"></i> Hanya Super Admin yang dapat mengubah
                </span>
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


        <div class="bg-white p-6 rounded-xl shadow-sm border flex flex-col md:flex-row gap-2 items-center justify-between">
            <div class="relative w-full md:w-64">
                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                <input id="inputSearch" type="text" placeholder="Cari Guru BK..."
                    class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
            </div>
            <div class="flex gap-2">

                <button id="exportImportBtn"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-1.5">
                    <i class="bi bi-download"></i> Export / Import
                </button>
            </div>
        </div>

        <!-- Data Table -->

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Daftar Guru BK</h3>
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
                                    NIP
                                </div>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-person text-gray-400"></i>
                                    Nama
                                </div>
                            </th>
                            <th class="px-5 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-gear text-gray-400"></i>
                                    Aksi
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody id="tableBody" class="bg-white divide-y divide-gray-100">
                        @forelse ($guru_bk as $item)
                            <tr class="hover:bg-gray-50 group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ ($guru_bk->firstItem() ?? 0) + $loop->iteration - 1 }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="w-2 h-2 bg-blue-400 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">{{ $item->nip_bk }}</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $item->nama_guru_bk }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if (auth()->user()->isSuperAdmin())
                                    <div class="flex items-center gap-1">
                                        <button
                                            onclick="openEditModal('{{ $item->nip_bk }}', '{{ $item->nama_guru_bk }}')"
                                            class="action-btn inline-flex items-center justify-center w-9 h-9 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-full"
                                            title="Edit Guru BK">
                                            <i class="bi bi-pencil-square text-sm"></i>
                                        </button>
                                        <button
                                            onclick="openDeleteModal('{{ $item->nip_bk }}', '{{ $item->nama_guru_bk }}')"
                                            class="action-btn inline-flex items-center justify-center w-9 h-9 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-full"
                                            title="Hapus Guru BK">
                                            <i class="bi bi-trash text-sm"></i>
                                        </button>
                                    </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-400"><i class="bi bi-lock"></i> Super Admin</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div
                                        class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i class="bi bi-people text-3xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data Guru BK</h3>
                                    <p class="text-gray-500">Tambahkan data Guru BK untuk memulai.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- PAGINATION -->
            <div id="pagination" class="px-6 py-4 border-t border-gray-200 bg-white">
                @include('layouts.wakasek.pagination', ['data' => $guru_bk])
            </div>
        </div>
    </div>

    @include('wakasek.guru_bk.modalExportImport')
    @include('wakasek.guru_bk.create')
    @include('wakasek.guru_bk.edit')
    @include('wakasek.guru_bk.delete')
@endsection

@push('js')
    <script src="{{ asset('js/wakasek/guru_bk.js') }}"></script>
@endpush