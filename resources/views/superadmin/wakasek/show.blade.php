@extends('layouts.superadmin.app')

@section('title', 'Detail Wakasek - Super Admin')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('superadmin.wakasek.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
                <i class="bi bi-arrow-left"></i> Kembali ke daftar
            </a>
            <h2 class="mt-2 text-lg font-bold text-gray-900">Detail Wakasek</h2>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('superadmin.wakasek.edit', $wakasek->nip_wakasek) }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50">
                <i class="bi bi-pencil-square"></i> Edit
            </a>
            <a href="{{ route('superadmin.wakasek.hapus', $wakasek->nip_wakasek) }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                <i class="bi bi-trash"></i> Hapus
            </a>
        </div>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            @foreach ([
                'NIP' => $wakasek->nip_wakasek,
                'Nama Wakasek' => $wakasek->nama_wakasek,
                'Username' => $wakasek->username,
                'Email' => $wakasek->user->email ?? '-',
                'Role' => 'Wakasek',
                'Akun Dibuat' => optional($wakasek->created_at)->format('d M Y H:i') ?? '-',
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endsection