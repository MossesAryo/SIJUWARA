@extends('layouts.wakasek.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/wakasek/siswa.css') }}">
@endpush

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold gradient-text">Arsip Siswa</h1>
                <p class="text-gray-600 mt-1">Daftar siswa yang telah dihapus. Anda dapat memulihkan atau menghapusnya secara permanen.</p>
            </div>
            <a href="{{ route('siswa.index') }}"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
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

        <!-- Search -->
        <div class="py-4">
            <div class="bg-white p-6 rounded-xl shadow-sm border px-4">
                <form method="GET" action="{{ route('siswa.arsip') }}" class="flex flex-col md:flex-row gap-2 items-center">
                    <div class="relative w-full md:w-64">
                        <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                        <input name="search" value="{{ request('search') }}" type="text" placeholder="Cari NIS/Nama..."
                            class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                    </div>
                    <button type="submit"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-1.5">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-xl shadow-sm border overflow-visible mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Daftar Siswa Terhapus</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIS</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Dihapus Pada</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse ($siswa as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    {{ ($siswa->firstItem() ?? 0) + $loop->iteration - 1 }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->nis }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ strtoupper($item->nama_siswa) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $item->kelas->nama_kelas ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $item->deleted_at ? $item->deleted_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1">
                                        <!-- Restore -->
                                        <button type="button"
                                            onclick="openRestoreModal('{{ $item->nis }}', '{{ addslashes($item->nama_siswa) }}')"
                                            class="action-btn inline-flex items-center justify-center w-9 h-9 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-full"
                                            title="Pulihkan Siswa">
                                            <i class="bi bi-arrow-counterclockwise text-sm"></i>
                                        </button>

                                        @if ((int) auth()->user()->role === 5)
                                            <!-- Force Delete -->
                                            <button type="button"
                                                onclick="openForceDeleteModal('{{ $item->nis }}', '{{ addslashes($item->nama_siswa) }}')"
                                                class="action-btn inline-flex items-center justify-center w-9 h-9 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-full"
                                                title="Hapus Permanen">
                                                <i class="bi bi-trash-fill text-sm"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i class="bi bi-archive text-3xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Arsip kosong</h3>
                                    <p class="text-gray-500">Tidak ada siswa yang terhapus.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200 bg-white">
                    @include('layouts.wakasek.pagination', ['data' => $siswa])
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Restore -->
    <div id="modal-restore"
        class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-[9999]">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <form id="form-restore" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PATCH')
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-700">Pulihkan Siswa</h2>
                    <button type="button" onclick="closeModalArsip('modal-restore')"
                        class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 flex items-center justify-center bg-green-100 rounded-full">
                        <i class="bi bi-arrow-counterclockwise text-green-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-base">
                            Pulihkan siswa
                            <span id="restore-nama-siswa" class="font-semibold text-green-600"></span>?
                        </p>
                        <p class="text-sm text-gray-500 mt-1">Data siswa akan dikembalikan ke Daftar Siswa.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeModalArsip('modal-restore')"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
                        Pulihkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ((int) auth()->user()->role === 5)
        <!-- Modal Force Delete -->
        <div id="modal-force-delete"
            class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-[9999]">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
                <form id="form-force-delete" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('DELETE')
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-700">Hapus Permanen</h2>
                        <button type="button" onclick="closeModalArsip('modal-force-delete')"
                            class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 flex items-center justify-center bg-red-100 rounded-full">
                            <i class="bi bi-exclamation-triangle-fill text-red-600 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-gray-600 text-base">
                                Hapus permanen siswa
                                <span id="force-delete-nama-siswa" class="font-semibold text-red-600"></span>?
                            </p>
                            <p class="text-sm text-gray-500 mt-1">Tindakan ini <strong>tidak dapat dibatalkan</strong> dan data akan hilang selamanya.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" onclick="closeModalArsip('modal-force-delete')"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Hapus Permanen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('js')
    <script>
        function openRestoreModal(nis, nama) {
            document.getElementById('restore-nama-siswa').innerText = nama;
            document.getElementById('form-restore').action = `/siswa/${nis}/restore`;
            document.getElementById('modal-restore').classList.remove('hidden');
        }

        @if ((int) auth()->user()->role === 5)
            function openForceDeleteModal(nis, nama) {
                document.getElementById('force-delete-nama-siswa').innerText = nama;
                document.getElementById('form-force-delete').action = `/siswa/${nis}/force-delete`;
                document.getElementById('modal-force-delete').classList.remove('hidden');
            }
        @endif

        function closeModalArsip(id) {
            document.getElementById(id).classList.add('hidden');
        }

        document.querySelectorAll('#modal-restore, #modal-force-delete').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.add('hidden');
            });
        });
    </script>
@endpush