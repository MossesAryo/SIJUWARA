@extends('layouts.wakasek.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/gurubk/siswa.css') }}">
    <style>
        .file-card-hover { transition: transform .2s ease, box-shadow .2s ease; }
        .file-card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .status-badge-selesai     { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
        .status-badge-dalam       { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .status-badge-binaan      { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
        .timeline-line            { background: linear-gradient(to bottom, #3b82f6, #e5e7eb); }
        @keyframes fadeSlideUp    { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeSlideUp .35s ease forwards; }
        .fade-up-1 { animation-delay:.05s; opacity:0; }
        .fade-up-2 { animation-delay:.12s; opacity:0; }
        .fade-up-3 { animation-delay:.19s; opacity:0; }
        .fade-up-4 { animation-delay:.26s; opacity:0; }
    </style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-5 pb-10">

    {{-- ── Hero Header ──────────────────────────────────────────── --}}
    <div class="fade-up fade-up-1 bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 text-white rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-blue-100 text-xs font-medium uppercase tracking-widest mb-0.5">Detail Penanganan</p>
                    <h1 class="text-xl font-bold leading-tight">{{ $intervensi->nama_intervensi }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        @php
                            $statusClass = match($intervensi->status) {
                                'Selesai'      => 'bg-green-400/30 text-green-100 border-green-300/40',
                                'Dalam Binaan' => 'bg-blue-400/30  text-blue-100  border-blue-300/40',
                                default        => 'bg-amber-400/30 text-amber-100 border-amber-300/40',
                            };
                        @endphp
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full border {{ $statusClass }}">
                            {{ $intervensi->status }}
                        </span>
                        <span class="text-blue-200 text-xs">ID #{{ $intervensi->id_intervensi }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('intervensi.index') }}"
                    class="flex items-center gap-1.5 px-3.5 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white text-sm font-medium rounded-xl transition-colors border border-white/20">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Kembali
                </a>
                <button
                    onclick="openEditModalFromShow('{{ $intervensi->id_intervensi }}', '{{ $intervensi->nis }}', '{{ addslashes($intervensi->nama_intervensi) }}', '{{ addslashes($intervensi->isi_intervensi) }}', '{{ $intervensi->status }}', '{{ $intervensi->tanggal_Mulai_Perbaikan }}', '{{ $intervensi->tanggal_Selesai_Perbaikan }}', '{{ addslashes($intervensi->perubahan_setelah_intervensi ?? '') }}', {{ $intervensi->bukti->map(fn($b) => ['id' => $b->id_bukti_pembinaan, 'path' => $b->file, 'nama_file' => $b->nama_file])->toJson() }})"
                    class="flex items-center gap-1.5 px-3.5 py-2 bg-white text-blue-600 text-sm font-semibold rounded-xl shadow hover:shadow-md hover:bg-gray-50 transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                    Edit
                </button>
            </div>
        </div>
    </div>

    {{-- ── Two Column Layout ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- LEFT: Main Content --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Siswa Info Card --}}
            <div class="fade-up fade-up-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    <h2 class="text-sm font-semibold text-gray-700">Informasi Siswa</h2>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Nama Siswa</p>
                        <p class="text-sm font-semibold text-gray-800">{{ $intervensi->siswa->nama_siswa }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">NIS: {{ $intervensi->nis }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Kelas saat penanganan</p>
                        <p class="text-sm font-semibold text-gray-800">{{ \App\Support\KelasSaatKejadian::label($intervensi->nis, $intervensi->created_at) }}</p>
                        @if (($intervensi->siswa->id_kelas ?? null) && $intervensi->siswa->id_kelas !== \App\Support\KelasSaatKejadian::pada($intervensi->nis, $intervensi->created_at)['id_kelas'])
                            <p class="text-xs text-gray-400 mt-0.5">Kelas sekarang: {{ $intervensi->siswa->kelas->nama_kelas ?? '-' }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-0.5">{{ $intervensi->siswa->jurusan->nama_jurusan ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Detail Penanganan --}}
            <div class="fade-up fade-up-3 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                    <h2 class="text-sm font-semibold text-gray-700">Isi Penanganan</h2>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div class="bg-gray-50 rounded-xl px-4 py-3">
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $intervensi->isi_intervensi }}</p>
                    </div>

                    @if($intervensi->perubahan_setelah_intervensi)
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">Perubahan Setelah Penanganan</p>
                        </div>
                        <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $intervensi->perubahan_setelah_intervensi }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── File Gallery ──────────────────────────────────── --}}
            @if($intervensi->bukti && $intervensi->bukti->count())
            <div class="fade-up fade-up-4 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                        <h2 class="text-sm font-semibold text-gray-700">Bukti Pendukung</h2>
                    </div>
                    <span class="text-xs font-medium text-violet-600 bg-violet-50 rounded-full px-2.5 py-0.5 border border-violet-100">
                        {{ $intervensi->bukti->count() }} file
                    </span>
                </div>
                <div class="px-5 py-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($intervensi->bukti as $bukti)
                            @php $isImg = preg_match('/\.(jpe?g|png)$/i', $bukti->file); @endphp
                            @if($isImg)
                                {{-- Image: opens in modal --}}
                                <div onclick="openImagePreview('{{ asset('storage/' . $bukti->file) }}')"
                                    class="file-card-hover relative group rounded-xl overflow-hidden border border-gray-100 aspect-square block bg-gray-50 cursor-zoom-in">
                                    <img src="{{ asset('storage/' . $bukti->file) }}" alt="Bukti"
                                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                        <div class="opacity-0 group-hover:opacity-100 bg-white/90 backdrop-blur-sm rounded-full p-1.5 transition-opacity">
                                            <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                        </div>
                                    </div>
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2 pb-2 pt-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-white text-[9px] truncate">{{ basename($bukti->file) }}</p>
                                        <p class="text-white/60 text-[9px]">Klik untuk lihat</p>
                                    </div>
                                </div>
                            @else
                                {{-- PDF: opens in new tab --}}
                                <a href="{{ asset('storage/' . $bukti->file) }}" target="_blank"
                                    class="file-card-hover relative group rounded-xl overflow-hidden border border-red-100 bg-red-50/50 aspect-square flex flex-col items-center justify-center gap-2 p-3 cursor-pointer hover:bg-red-50 transition-colors">
                                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition-colors">
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                        </svg>
                                    </div>
                                    <p class="text-[10px] text-gray-600 text-center break-all leading-tight line-clamp-2">{{ $bukti->nama_file ?? basename($bukti->file) }}</p>
                                    <span class="text-[9px] font-bold text-red-400 bg-red-100 rounded-full px-2 py-0.5">PDF</span>
                                    <span class="text-[9px] text-gray-400">Buka di tab baru ↗</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @else
            <div class="fade-up fade-up-4 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Pendukung</h2>
                </div>
                <div class="px-5 py-8 text-center">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32"/></svg>
                    </div>
                    <p class="text-sm text-gray-400">Belum ada bukti pendukung</p>
                </div>
            </div>
            @endif

        </div>{{-- end LEFT --}}

        {{-- RIGHT: Sidebar --}}
        <div class="space-y-4">

            {{-- Status Card --}}
            <div class="fade-up fade-up-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</h2>
                </div>
                <div class="px-5 py-4 flex items-center gap-3">
                    @php
                        $dot = match($intervensi->status) {
                            'Selesai'      => 'bg-green-500',
                            'Dalam Binaan' => 'bg-blue-500',
                            default        => 'bg-amber-500',
                        };
                        $badge = match($intervensi->status) {
                            'Selesai'      => 'status-badge-selesai',
                            'Dalam Binaan' => 'status-badge-dalam',
                            default        => 'status-badge-binaan',
                        };
                    @endphp
                    <div class="w-2.5 h-2.5 rounded-full {{ $dot }} flex-shrink-0 shadow-sm ring-4 ring-offset-0 {{ str_replace('bg-', 'ring-', $dot) }}/20"></div>
                    <span class="text-sm font-semibold px-3 py-1.5 rounded-xl {{ $badge }}">{{ $intervensi->status }}</span>
                </div>
            </div>

            {{-- Timeline Card --}}
            <div class="fade-up fade-up-3 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Timeline</h2>
                </div>
                <div class="px-5 py-4 space-y-3">
                    {{-- Mulai --}}
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                            </div>
                            <div class="w-px h-8 bg-gray-100 mt-1"></div>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium">Tanggal Mulai</p>
                            <p class="text-sm font-semibold text-gray-800">
                                {{ $intervensi->tanggal_Mulai_Perbaikan ? \Carbon\Carbon::parse($intervensi->tanggal_Mulai_Perbaikan)->isoFormat('D MMM Y') : '-' }}
                            </p>
                        </div>
                    </div>
                    {{-- Selesai --}}
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full {{ $intervensi->status === 'Selesai' ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center flex-shrink-0 mt-0.5">
                            @if($intervensi->status === 'Selesai')
                                <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            @else
                                <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium">Tanggal Selesai</p>
                            <p class="text-sm font-semibold {{ $intervensi->status === 'Selesai' ? 'text-green-700' : 'text-gray-800' }}">
                                {{ $intervensi->tanggal_Selesai_Perbaikan ? \Carbon\Carbon::parse($intervensi->tanggal_Selesai_Perbaikan)->isoFormat('D MMM Y') : '-' }}
                            </p>
                        </div>
                    </div>
                    {{-- Duration --}}
                    @if($intervensi->tanggal_Mulai_Perbaikan && $intervensi->tanggal_Selesai_Perbaikan)
                    @php
                        $days = \Carbon\Carbon::parse($intervensi->tanggal_Mulai_Perbaikan)
                                    ->diffInDays(\Carbon\Carbon::parse($intervensi->tanggal_Selesai_Perbaikan));
                    @endphp
                    <div class="bg-gray-50 rounded-xl px-3 py-2.5 flex items-center gap-2 mt-1">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-gray-500">Durasi: <span class="font-semibold text-gray-700">{{ $days }} hari</span></p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Meta Info --}}
            <div class="fade-up fade-up-4 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-50">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Info</h2>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <p class="text-xs text-gray-400">Dibuat</p>
                        <p class="text-xs font-medium text-gray-700">{{ $intervensi->created_at->isoFormat('D MMM Y, HH:mm') }}</p>
                    </div>
                    @if($intervensi->updated_at && $intervensi->updated_at != $intervensi->created_at)
                    <div>
                        <p class="text-xs text-gray-400">Diperbarui</p>
                        <p class="text-xs font-medium text-gray-700">{{ $intervensi->updated_at->isoFormat('D MMM Y, HH:mm') }}</p>
                    </div>
                    @endif
                    @if($intervensi->bukti)
                    <div>
                        <p class="text-xs text-gray-400">Bukti</p>
                        <p class="text-xs font-medium text-gray-700">{{ $intervensi->bukti->count() }} file terlampir</p>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- end RIGHT --}}
    </div>

</div>

@include('wakasek.intervensi.edit')

{{-- Image Preview Modal --}}
<div id="image-preview-modal"
    class="fixed inset-0 bg-black bg-opacity-90 z-[60] hidden items-center justify-center"
    onclick="if(event.target === this) closeImagePreview()">
    <button onclick="closeImagePreview()"
        class="absolute top-4 right-4 text-white text-sm hover:text-gray-300">
        <span class="text-2xl">&times;</span>
    </button>
    <img id="preview-image" src="" alt=""
        class="max-w-full max-h-[90vh] object-contain">
</div>

<script>
    function openImagePreview(src) {
        document.getElementById('preview-image').src = src;
        document.getElementById('image-preview-modal').classList.remove('hidden');
        document.getElementById('image-preview-modal').classList.add('flex');
    }
    
    function closeImagePreview() {
        document.getElementById('image-preview-modal').classList.add('hidden');
        document.getElementById('image-preview-modal').classList.remove('flex');
    }
    
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    document.addEventListener('click', function (e) {
        ['modal-edit'].forEach(id => {
            const m = document.getElementById(id);
            if (m && !m.classList.contains('hidden') && e.target === m) closeModal(id);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ['modal-edit'].forEach(id => {
            const m = document.getElementById(id);
            if (m && !m.classList.contains('hidden')) closeModal(id);
        });
    });
    
    // Open edit modal from show page
    function openEditModalFromShow(id, nis, nama_intervensi, isi_intervensi, status, tanggalMulai, tanggalSelesai, perubahan, existingFiles) {
        const form = document.getElementById('form-edit');
        if (form) form.action = `/intervensi/${id}/update`;

        const returnInput = document.getElementById('return_to_edit');
        if (returnInput) returnInput.value = window.location.href;

        const nisSelect = document.getElementById('nis_edit'); if (nisSelect) nisSelect.value = nis;
        const nisHidden = document.getElementById('nis_hidden_edit'); if (nisHidden) nisHidden.value = nis;
        const namaEl = document.getElementById('nama_intervensi_edit'); if (namaEl) namaEl.value = nama_intervensi || '';
        const isiEl = document.getElementById('isi_intervensi_edit'); if (isiEl) isiEl.value = isi_intervensi || '';
        const statusEl = document.getElementById('status_edit'); if (statusEl) statusEl.value = status || '';
        const tMulai = document.getElementById('tanggal_Mulai_Perbaikan_edit'); if (tMulai) tMulai.value = tanggalMulai || '';
        const tSelesai = document.getElementById('tanggal_Selesai_Perbaikan_edit'); if (tSelesai) tSelesai.value = tanggalSelesai || '';
        const perubahanEl = document.getElementById('perubahan_setelah_intervensi_edit'); if (perubahanEl) perubahanEl.value = perubahan || '';
        
        // Load existing files
        window.existingFilesData = window.existingFilesData || [];
        window.uploadedFilesEdit = [];
        window.filesToDeleteEdit = new Set();
        if (existingFiles && existingFiles.length > 0) {
            window.existingFilesData.length = 0;
            existingFiles.forEach(f => {
                window.existingFilesData.push({
                    id: f.id,
                    path: f.path,
                    url: '/storage/' + f.path,
                    nama_file: f.nama_file || f.path.split('/').pop()
                });
            });
        }
        if (typeof renderExistingFiles === 'function') {
            renderExistingFiles();
        }
        
        openModal('modal-edit');
    }
</script>
@endsection