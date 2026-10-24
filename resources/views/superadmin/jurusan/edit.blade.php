@extends('layouts.superadmin.app')

@section('title', 'Edit Jurusan - Super Admin')

@section('content')
    <div class="mb-5">
        <a href="{{ route('superadmin.jurusan.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
            <i class="bi bi-arrow-left"></i> Kembali ke daftar
        </a>
        <h2 class="mt-2 text-lg font-bold text-gray-900">Edit Jurusan</h2>
        <p class="text-sm text-gray-400">Kode, nama, program keahlian, dan konsentrasi keahlian dapat diubah.</p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
        @include('superadmin.jurusan._form', [
            'action' => route('superadmin.jurusan.update', $jurusan->id_jurusan),
            'method' => 'PUT',
        ])
    </div>
@endsection