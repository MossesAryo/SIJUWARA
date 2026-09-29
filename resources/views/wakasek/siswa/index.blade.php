@extends('layouts.wakasek.app')

    @push('css')
        <link rel="stylesheet" href="{{ asset('css/wakasek/siswa.css') }}">
    @endpush

    @section('content')
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold gradient-text">Data Siswa</h1>
                    <p class="text-gray-600 mt-1">Kelola data Siswa</p>
                </div>
                @if (auth()->user()->role == 1)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('siswa.arsip') }}"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <i class="bi bi-archive"></i>
                            Arsip
                        </a>
                        <button onclick="openCreateModal()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <i class="bi bi-plus-lg"></i>
                            Tambah Siswa
                        </button>
                    </div>
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

            @if (session('import_errors'))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                    <p class="text-sm font-semibold flex items-center gap-2">
                        <i class="bi bi-info-circle-fill text-yellow-600"></i>
                        Baris yang dilewati saat import ({{ count(session('import_errors')) }}):
                    </p>
                    <ul class="mt-2 text-sm list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                        @foreach (array_slice(session('import_errors'), 0, 50) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                    @if (count(session('import_errors')) > 50)
                        <p class="mt-1 text-xs">...dan {{ count(session('import_errors')) - 50 }} baris lainnya.</p>
                    @endif
                </div>
            @endif

            <div class="py-4">
                <div class="bg-white p-6 rounded-xl shadow-sm border px-4">
                    <div class="flex flex-col md:flex-row gap-2 items-center justify-between">
                        <div class="relative w-full md:w-64">
                            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                            <input id="inputSearch" type="text" placeholder="Cari Siswa..."
                                class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                        </div>
                        @if (auth()->user()->role == 1 || auth()->user()->role == 2 || auth()->user()->role == 4)
                            <div class="flex gap-2">
                                @php
                                    $filterCount = collect(request()->except(['page', 'search', '_token', '_method']))
                                        ->filter(function ($v) {
                                            return $v !== null && $v !== '';
                                        })
                                        ->count();
                                @endphp
                                
                                    
                                <button onclick="openfilterModal()"
                                class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-1.5">
                                <i class="bi bi-funnel"></i> Filter
                                @if ($filterCount > 0)
                                <span
                                class="ml-2 inline-flex items-center justify-center bg-blue-600 text-white text-xs font-semibold rounded-full w-6 h-6">{{ $filterCount }}</span>
                               
                            </button>
                            @endif
                        @endif



                        @if (auth()->user()->role == 1)
                            <button id="exportImportBtn"
                                class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-1.5">
                                <i class="bi bi-download"></i> Export / Import
                            </button>
                        @endif
                    </div>
                </div>
            </div>



            <div class="bg-white rounded-xl shadow-sm border overflow-visible mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Siswa</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-hash text-gray-400"></i>
                                        No
                                    </div>
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-hash text-gray-400"></i>
                                        NIS
                                    </div>
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-person text-gray-400"></i>
                                        Nama Siswa
                                    </div>
                                </th>

                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-person text-gray-400"></i>
                                        Kelas
                                    </div>
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-person text-gray-400"></i>
                                        Status
                                    </div>
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-gear text-gray-400"></i>
                                        Aksi
                                    </div>
                                </th>
                            </tr>
                        </thead>

                        <tbody id="tableBody" class="bg-white divide-y divide-gray-100">
                            @forelse ($siswa as $item)
                                <tr class="hover:bg-gray-50 group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ ($siswa->firstItem() ?? 0) + $loop->iteration - 1 }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="w-2 h-2 bg-blue-400 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                            </div>
                                            <span class="text-sm font-medium text-gray-900">{{ $item->nis  }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ strtoupper($item->nama_siswa) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">@if ($item->status != 'alumni')
                                          {{$item->kelas->nama_kelas ?? '-' }}
                                        @else
                                        {{ \App\Models\kelas::KOMPETENSI_KEAHLIAN[$item->id_jurusan] ?? ($item->id_jurusan ?? '-') }} 
                                        @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ strtoupper($item->status) }}</div>
                                    </td>


                                    {{-- <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($item->poin_total >= 100)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                                <i class="bi bi-star-fill mr-2"></i>
                                                Berprestasi
                                            </span>
                                        @elseif ($item->poin_total == 0)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                                <i class="bi bi-check-circle-fill mr-2"></i>
                                                Aman
                                            </span>
                                        @elseif ($item->poin_total > -30)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                                <i class="bi bi-exclamation-triangle-fill mr-2"></i>
                                                Bermasalah
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                                <i class="bi bi-shield-exclamation mr-2"></i>
                                                Prioritas
                                            </span>
                                        @endif
                                    </td> --}}


                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1">
                                            @if (auth()->user()->role == 1)
                                                <button
                                                    onclick="openEditModal('{{ $item->nis }}', '{{ addslashes($item->nama_siswa) }}', '{{ $item->id_kelas }}', 'index')"
                                                    class="action-btn inline-flex items-center justify-center w-9 h-9 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-full"
                                                    title="Edit Siswa">
                                                    <i class="bi bi-pencil-square text-sm"></i>
                                                </button>
                                            @endif
                                            <button onclick="window.location='{{ route('siswa.show', $item->nis) }}'"
                                                class="action-btn inline-flex items-center justify-center w-9 h-9 text-yellow-600 hover:text-yellow-800 hover:bg-orange-50 rounded-full"
                                                title="Edit Siswa">
                                                <i class="bi bi-eye text-sm"></i>
                                            </button>
                                            @if (auth()->user()->role == 1 || auth()->user()->role == 2)
                                                <button
                                                    onclick="openDeleteModal('{{ $item->nis }}', '{{ $item->nama_siswa }}')"
                                                    class="action-btn inline-flex items-center justify-center w-9 h-9 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-full"
                                                    title="Hapus Siswa">
                                                    <i class="bi bi-trash text-sm"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div
                                            class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="bi bi-people text-3xl text-gray-400"></i>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data siswa</h3>
                                        <p class="text-gray-500">Tambahkan data siswa untuk memulai.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <!-- PAGINATION -->
                    <div id="pagination" class="px-6 py-4 border-t border-gray-200 bg-white">
                        @include('layouts.wakasek.pagination', ['data' => $siswa])
                    </div>
                </div>
            </div>
        </div>
        </div>

        @if (auth()->user()->role == 1 || auth()->user()->role == 2)
            @include('wakasek.siswa.create')
            @include('wakasek.siswa.edit')
            @include('wakasek.siswa.delete')
            @include('wakasek.siswa.modalExportImport')
        @endif
        @if (auth()->user()->role != 3)
        @include('wakasek.siswa.filter')
        @endif



    @endsection


    @push('js')
        <script src="{{ asset('js/wakasek/siswa.js') }}"></script>
    @endpush