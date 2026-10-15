@extends('layouts.superadmin.app')

@section('title', 'Dashboard - Super Admin')

@section('content')
    @php
        $warna = [
            'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'bar' => 'bg-blue-500'],
            'green'  => ['bg' => 'bg-green-50',  'text' => 'text-green-600',  'bar' => 'bg-green-500'],
            'red'    => ['bg' => 'bg-red-50',    'text' => 'text-red-500',    'bar' => 'bg-red-500'],
            'slate'  => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600',  'bar' => 'bg-slate-500'],
            'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'bar' => 'bg-purple-500'],
            'amber'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'bar' => 'bg-amber-500'],
            'teal'   => ['bg' => 'bg-teal-50',   'text' => 'text-teal-600',   'bar' => 'bg-teal-500'],
        ];
    @endphp

    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">Ringkasan Data</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($ringkasan as $item)
                @php $c = $warna[$item['color']]; @endphp
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $c['bg'] }}">
                            <i class="bi {{ $item['icon'] }} text-2xl {{ $c['text'] }}"></i>
                        </div>
                        <span class="rounded-lg px-3 py-1 text-xs font-medium {{ $c['bg'] }} {{ $c['text'] }}">{{ $item['label'] }}</span>
                    </div>
                    <p class="mb-1 text-3xl font-bold text-gray-900">{{ number_format($item['value']) }}</p>
                    <p class="text-sm text-gray-400">{{ $item['note'] }}</p>
                    @if (!empty($item['sub']))
                        <p class="mt-1 text-xs font-medium {{ $c['text'] }}">{{ $item['sub'] }}</p>
                    @endif
                    <div class="absolute bottom-0 left-0 right-0 h-1 {{ $c['bar'] }}"></div>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">Akun Pengguna</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            @foreach ($akun as $item)
                @php $c = $warna[$item['color']]; @endphp
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl {{ $c['bg'] }}">
                        <i class="bi {{ $item['icon'] }} text-xl {{ $c['text'] }}"></i>
                    </div>
                    <p class="mb-0.5 text-2xl font-bold text-gray-900">{{ number_format($item['value']) }}</p>
                    <p class="text-sm font-medium text-gray-700">{{ $item['label'] }}</p>
                    <p class="text-xs text-gray-400">{{ $item['note'] }}</p>
                    <div class="absolute bottom-0 left-0 right-0 h-1 {{ $c['bar'] }}"></div>
                </div>
            @endforeach
        </div>
    </div>
@endsection