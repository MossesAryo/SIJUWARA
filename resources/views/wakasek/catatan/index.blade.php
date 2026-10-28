@extends('layouts.wakasek.app')
@php
    // Kelas siswa PADA SAAT catatan dibuat (dari Riwayat Kelas), bukan kelas sekarang.
    \App\Support\KelasSaatKejadian::muatDari($catatans);
    $kelasCatatan = collect($catatans->items())->mapWithKeys(fn ($c) => [
        $c->id => \App\Support\KelasSaatKejadian::label($c->nis, $c->created_at),
    ]);
@endphp
@section('content')
    <div class="w-full px-4 py-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Daftar Catatan</h2>
                <p class="text-gray-500 text-sm mt-1">Kelola catatan perkembangan siswa</p>
            </div>

            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 flex items-start gap-3">
                    <i class="bi bi-check-circle-fill text-green-600 text-xl"></i>
                    <div class="flex-1">
                        <p class="text-green-800 font-medium">Berhasil!</p>
                        <p class="text-green-700 text-sm">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            <!-- Catatan List -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Table Header - Desktop -->
                <div
                    class="hidden md:grid md:grid-cols-12 gap-4 bg-gray-50 px-6 py-4 font-semibold text-sm text-gray-700 border-b">
                    <div class="col-span-2">Nama Siswa</div>
                    <div class="col-span-2">Judul Catatan</div>
                    <div class="col-span-3">Isi Catatan</div>
                    <div class="col-span-2">Pengirim</div>
                    <div class="col-span-1">Dibuat</div>
                    <div class="col-span-2 text-center">Aksi</div>
                </div>

                <!-- Catatan Items -->
                <div class="divide-y divide-gray-100">
                    @forelse ($catatans as $catatan)
                        <div class="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                            <div class="md:grid md:grid-cols-12 md:gap-4 md:items-center">
                                <!-- Nama Siswa -->
                                <div class="col-span-2 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Nama Siswa</p>
                                    <p class="font-semibold text-gray-900">{{ $catatan->siswa->nama_siswa ?? 'N/A' }}</p>
                                    <p class="text-gray-500 text-xs">NIS: {{ $catatan->siswa->nis ?? '-' }}</p>
                                    <p class="text-xs text-indigo-700 mt-0.5" title="Kelas siswa saat catatan ini dibuat">
                                        <i class="bi bi-collection"></i> {{ $kelasCatatan[$catatan->id] ?? '-' }}
                                    </p>
                                </div>

                                <!-- Title -->
                                <div class="col-span-2 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Judul Catatan</p>
                                    <p class="font-semibold text-gray-900">{{ $catatan->judul_catatan }}</p>
                                </div>

                                <!-- Content -->
                                <div class="col-span-3 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Isi Catatan</p>
                                    <p class="text-gray-700 text-sm line-clamp-2">{{ $catatan->isi_catatan }}</p>
                                </div>

                                <!-- Pengirim -->
                                <div class="col-span-2 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Pengirim</p>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="bi bi-person-fill text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-900 text-sm font-medium">
                                                @if ($catatan->nip_wakasek)
                                                    {{ $catatan->wakasek->nama_wakasek }}
                                                @elseif($catatan->nip_walikelas)
                                                    {{ $catatan->walikelas->nama_walikelas }}
                                                @else
                                                    N/A
                                                @endif
                                            </p>
                                            <p class="text-gray-500 text-xs">
                                                @if ($catatan->nip_wakasek)
                                                    Wakasek
                                                @elseif($catatan->nip_walikelas)
                                                    Wali Kelas
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Created Date -->
                                <div class="col-span-1 mb-3 md:mb-0">
                                    <p class="text-xs text-gray-500 md:hidden mb-1">Dibuat</p>
                                    <p class="text-gray-600 text-sm">
                                        <i class="bi bi-calendar3 mr-1"></i>
                                        {{ $catatan->created_at->format('d M Y') }}
                                    </p>
                                    <p class="text-gray-500 text-xs">{{ $catatan->created_at->format('H:i') }}</p>
                                </div>

                                <!-- Actions -->
                                <div class="col-span-2 flex justify-start md:justify-center gap-2">
                                    <button onclick="showDetailModal({{ $catatan->id }})"
                                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1">
                                        <i class="bi bi-eye"></i>
                                        Detail
                                    </button>
                                    @if (auth()->user()->role == 1)
                                        <button onclick="showEditModal({{ $catatan->id }})"
                                            class="px-3 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1">
                                            <i class="bi bi-pencil-square"></i>
                                            Edit
                                        </button>
                                    @else
                                        <a href="{{ route('intervensi.index') }}"
                                            class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1">
                                            <i class="bi bi-pencil-square"></i>
                                            Intervensi
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <!-- Empty State -->
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                <i class="bi bi-journal-text text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Catatan</h3>
                            <p class="text-gray-500 text-sm">Tidak ada catatan yang tersedia saat ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Pagination -->
            @if ($catatans->count() > 0)
                <div class="mt-6">
                    {{ $catatans->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">Detail Catatan</h3>
                    <p class="text-blue-100 text-sm">Informasi lengkap catatan</p>
                </div>
                <button onclick="closeDetailModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 overflow-y-auto max-h-[calc(90vh-140px)]">
                <!-- Student Info -->
                <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                    <label class="block text-sm font-semibold text-purple-700 mb-2">
                        <i class="bi bi-person-badge mr-1"></i>
                        Nama Siswa
                    </label>
                    <p id="detail_siswa_nama" class="text-gray-900 text-lg font-semibold"></p>
                    <p id="detail_siswa_nis" class="text-gray-600 text-sm"></p>
                </div>

                <!-- Sender Info -->
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                    <label class="block text-sm font-semibold text-indigo-700 mb-2">
                        <i class="bi bi-person-circle mr-1"></i>
                        Pengirim Catatan
                    </label>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-indigo-200 rounded-full flex items-center justify-center">
                            <i class="bi bi-person-fill text-indigo-700"></i>
                        </div>
                        <div>
                            <p id="detail_pengirim_nama" class="text-gray-900 font-semibold"></p>
                            <p id="detail_pengirim_role" class="text-gray-600 text-sm"></p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <label class="block text-sm font-semibold text-blue-700 mb-2">Judul Catatan</label>
                    <p id="detail_judul" class="text-gray-900 text-lg font-semibold"></p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Isi Catatan</label>
                    <div id="detail_isi" class="text-gray-800 text-base leading-relaxed whitespace-pre-line"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-green-700 mb-2">
                            <i class="bi bi-calendar-check mr-1"></i>
                            Dibuat Pada
                        </label>
                        <p id="detail_created" class="text-gray-900 font-medium"></p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-yellow-700 mb-2">
                            <i class="bi bi-clock-history mr-1"></i>
                            Terakhir Diperbarui
                        </label>
                        <p id="detail_updated" class="text-gray-900 font-medium"></p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeDetailModal()"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">Edit Catatan</h3>
                    <p class="text-yellow-100 text-sm">Perbarui informasi catatan</p>
                </div>
                <button onclick="closeEditModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form id="editForm" method="POST" class="overflow-y-auto max-h-[calc(90vh-140px)]">
                @csrf
                @method('PUT')
                <div class="p-6 space-y-4">
                    <div>
                        <label for="edit_judul_catatan" class="block text-sm font-semibold text-gray-700 mb-2">
                            Judul Catatan
                            <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="judul_catatan" id="edit_judul_catatan"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                            placeholder="Masukkan judul catatan" required>
                    </div>

                    <div>
                        <label for="edit_isi_catatan" class="block text-sm font-semibold text-gray-700 mb-2">
                            Isi Catatan
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea name="isi_catatan" id="edit_isi_catatan" rows="8"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                            placeholder="Tuliskan isi catatan secara detail" required></textarea>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="bi bi-info-circle-fill text-blue-600 text-xl"></i>
                            <p class="text-blue-800 text-sm">
                                <span class="font-semibold">Catatan:</span> Pastikan semua informasi yang diisi sudah benar
                                sebelum menyimpan.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1"></i>
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg transition-colors">
                        <i class="bi bi-check-lg mr-1"></i>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Catatan data
        const catatanData = @json($catatans->items());
        const kelasSaatKejadian = @json($kelasCatatan);

        // Show Detail Modal
        function showDetailModal(id) {
            const catatan = catatanData.find(c => c.id === id);
            if (!catatan) return;

            // Siswa Info
            document.getElementById('detail_siswa_nama').textContent = catatan.siswa.nama_siswa || 'N/A';
            document.getElementById('detail_siswa_nis').textContent = 'NIS: ' + (catatan.siswa.nis || '-')
                + ' · Kelas saat itu: ' + (kelasSaatKejadian[catatan.id] || '-');

            // Pengirim Info
            let pengirimNama = 'N/A';
            let pengirimRole = '-';

            if (catatan.nip_wakasek && catatan.nip_wakasek) {
                pengirimNama = catatan.wakasek.nama_wakasek || 'Wakasek';
                pengirimRole = 'Wakasek Kesiswaan';
            } else if (catatan.nip_walikelas && catatan.nip_walikelas) {
                pengirimNama = catatan.walikelas.nama_walikelas || 'Wali Kelas';
                pengirimRole = 'Wali Kelas';
            }

            document.getElementById('detail_pengirim_nama').textContent = pengirimNama;
            document.getElementById('detail_pengirim_role').textContent = pengirimRole;

            document.getElementById('detail_judul').textContent = catatan.judul_catatan;
            document.getElementById('detail_isi').textContent = catatan.isi_catatan;

            const createdDate = new Date(catatan.created_at);
            const updatedDate = new Date(catatan.updated_at);

            document.getElementById('detail_created').innerHTML = `
                ${createdDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}<br>
                <span class="text-gray-600 text-sm">${createdDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB</span>
            `;

            document.getElementById('detail_updated').innerHTML = `
                ${updatedDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}<br>
                <span class="text-gray-600 text-sm">${updatedDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB</span>
            `;

            document.getElementById('detailModal').classList.remove('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        // Show Edit Modal
        function showEditModal(id) {
            const catatan = catatanData.find(c => c.id === id);
            if (!catatan) return;

            document.getElementById('edit_judul_catatan').value = catatan.judul_catatan;
            document.getElementById('edit_isi_catatan').value = catatan.isi_catatan;
            document.getElementById('editForm').action = `/catatan/${id}`;

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modals on outside click
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetailModal();
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetailModal();
                closeEditModal();
            }
        });
    </script>
@endsection