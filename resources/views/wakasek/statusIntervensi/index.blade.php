@extends('layouts.wakasek.app')
@section('content')
    <div class="w-full px-4 py-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Daftar Siswa Penanganan</h2>
            </div>

            <!-- Student List -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Table Header - Desktop -->
                <div
                    class="hidden md:grid md:grid-cols-12 gap-4 bg-gray-50 px-6 py-4 font-semibold text-sm text-gray-700 border-b">
                    <div class="col-span-4">Nama Siswa</div>
                    <div class="col-span-3">Nama Penanganan</div>
                    <div class="col-span-3">Status</div>
                    <div class="col-span-2 text-center">Aksi</div>
                </div>

                <!-- Student Items -->
                <div class="divide-y divide-gray-100">
                    @php \App\Support\KelasSaatKejadian::muatDari($intervensi); @endphp
                    @forelse ($intervensi as $item)
                        <div class="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                            <div class="md:grid md:grid-cols-12 md:gap-4 md:items-center">
                                <!-- Name -->
                                <div class="col-span-4 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Nama Siswa</p>
                                    <p class="font-semibold text-gray-900">{{ $item->siswa->nama_siswa }}</p>
                                    <p class="text-gray-500 text-sm" title="Kelas siswa saat penanganan dibuat">{{ \App\Support\KelasSaatKejadian::label($item->nis, $item->created_at) }}</p>
                                </div>

                                <!-- Intervention Name -->
                                <div class="col-span-3 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Nama Penanganan</p>
                                    <p class="text-gray-700 text-sm">{{ $item->nama_intervensi }}</p>
                                </div>

                                <!-- Status -->
                                    <div class="col-span-3 mb-3 md:mb-0">
                                        <p class="text-xs text-gray-500 md:hidden mb-1">Status</p>
                                        @php
                                            $statusColor = match($item->status) {
                                                'Selesai' => 'bg-green-100 text-green-800',
                                                'Dalam Binaan' => 'bg-yellow-100 text-yellow-800',
                                                'Binaan Khusus' => 'bg-orange-100 text-orange-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                            {{ $item->status }}
                                        </span>
                                    </div>

                                <!-- Action -->
                                <div class="col-span-2 flex justify-start md:justify-center">
                                    <a href="{{ route('statusintervensi.show', $item->id_intervensi) }}"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                                        <i class="bi bi-eye"></i>
                                        Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <!-- Empty State -->
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                <i class="bi bi-inbox text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Data Penanganan</h3>
                            <p class="text-gray-500 text-sm">Tidak ada siswa yang memiliki Penanganan saat ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Pagination -->
            @if($intervensi->count() > 0)
                <div
                    class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                   <p class="text-sm text-gray-600">
                        Menampilkan
                        <span class="font-semibold">{{ $intervensi->firstItem() }}</span>
                        -
                        <span class="font-semibold">{{ $intervensi->lastItem() }}</span>
                        dari
                        <span class="font-semibold">{{ $intervensi->total() }}</span>
                        siswa
                    </p>
                   <div class="flex gap-2">

    {{-- Tombol Previous --}}
    <a href="{{ $intervensi->previousPageUrl() }}"
       class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700
       hover:bg-gray-50 {{ $intervensi->onFirstPage() ? 'opacity-50 cursor-not-allowed' : '' }}">
        <i class="bi bi-chevron-left"></i>
    </a>

    {{-- Number Pagination --}}
    @foreach ($intervensi->getUrlRange(1, $intervensi->lastPage()) as $page => $url)
        <a href="{{ $url }}"
           class="px-3 py-2 rounded-lg text-sm font-medium
           {{ $page == $intervensi->currentPage()
                ? 'bg-blue-600 text-white'
                : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            {{ $page }}
        </a>
    @endforeach

    {{-- Tombol Next --}}
    <a href="{{ $intervensi->nextPageUrl() }}"
       class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700
       hover:bg-gray-50 {{ $intervensi->currentPage() == $intervensi->lastPage() ? 'opacity-50 cursor-not-allowed' : '' }}">
        <i class="bi bi-chevron-right"></i>
    </a>

</div>

                </div>
            @endif
        </div>
    </div>
@endsection