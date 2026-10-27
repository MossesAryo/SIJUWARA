<!-- Modal Filter -->
<div id="modal-filter" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    {{-- Tinggi dibatasi 90% layar; isi di tengah bisa di-scroll, header & footer tetap terlihat --}}
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] flex flex-col transform transition-all">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-funnel text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Filter Kelas</h3>
                    <p class="text-sm text-gray-500">Pilih kriteria untuk memfilter data</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-filter')"
                class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Tutup">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="form-filter" method="GET" action="{{ route('kelas') }}" class="flex flex-col min-h-0 flex-1">
            @php
                $jurusanDipilih = array_map('strval', (array) request('jurusan', []));
                $tingkatDipilih = array_map('strval', (array) request('tingkat', []));
                // Ketua Program otomatis hanya melihat kelas jurusannya, jadi filter jurusan disembunyikan.
                $tampilFilterJurusan = !auth()->user() || auth()->user()->role != '4';
            @endphp

            <div class="px-6 py-5 space-y-6 overflow-y-auto flex-1">

                @if ($tampilFilterJurusan)
                    <!-- Filter Program / Kompetensi Keahlian -->
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="bi bi-mortarboard mr-2 text-blue-600"></i>
                            Program / Kompetensi Keahlian
                        </label>
                        <p class="text-xs text-gray-500">
                            Kelas 10 = Program Keahlian, kelas 11/12 = Kompetensi Keahlian
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($jurusanList as $jur)
                                <label
                                    class="flex items-start p-2 rounded-lg border border-transparent hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="jurusan[]" value="{{ $jur->id_jurusan }}"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        {{ in_array((string) $jur->id_jurusan, $jurusanDipilih, true) ? 'checked' : '' }}>
                                    <span class="ml-2">
                                        <span
                                            class="block text-sm text-gray-700 font-medium">{{ $jur->nama_jurusan }}</span>
                                        <span
                                            class="block text-xs text-gray-500">{{ \App\Models\kelas::ringkasanKode($jur->id_jurusan) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="border-t border-gray-200"></div>
                @endif

                <!-- Filter Tingkat Kelas -->
                <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="bi bi-layers mr-2 text-green-600"></i>
                        Tingkat Kelas
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['10', '11', '12'] as $tk)
                            <label class="cursor-pointer block">
                                <input type="checkbox" name="tingkat[]" value="{{ $tk }}" class="sr-only peer"
                                    {{ in_array($tk, $tingkatDipilih, true) ? 'checked' : '' }}>
                                <div
                                    class="text-center p-3 border border-gray-200 rounded-lg transition-colors hover:bg-gray-50
                                           peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                           peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500">
                                    <div class="text-lg font-bold">{{ $tk }}</div>
                                    <div class="text-xs text-gray-500">Kelas</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Urutkan -->
                <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="bi bi-toggles mr-2 text-purple-600"></i>
                        Urutkan Berdasarkan
                    </label>
                    <select name="sort"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Pengurutan --</option>
                        <option value="nama_kelas_asc" {{ request('sort') == 'nama_kelas_asc' ? 'selected' : '' }}>Nama
                            Kelas (A-Z)</option>
                        <option value="nama_kelas_desc" {{ request('sort') == 'nama_kelas_desc' ? 'selected' : '' }}>
                            Nama Kelas (Z-A)</option>
                        <option value="jurusan_asc" {{ request('sort') == 'jurusan_asc' ? 'selected' : '' }}>Jurusan
                            (A-Z)</option>
                        <option value="jurusan_desc" {{ request('sort') == 'jurusan_desc' ? 'selected' : '' }}>Jurusan
                            (Z-A)</option>
                        <option value="tingkat_asc" {{ request('sort') == 'tingkat_asc' ? 'selected' : '' }}>Tingkat
                            (10, 11, 12)</option>
                        <option value="tingkat_desc" {{ request('sort') == 'tingkat_desc' ? 'selected' : '' }}>Tingkat
                            (12, 11, 10)</option>
                    </select>
                </div>

            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl shrink-0">
                <button type="button" onclick="resetFilter()"
                    class="text-sm text-gray-600 hover:text-gray-800 flex items-center gap-1">
                    <i class="bi bi-arrow-clockwise"></i>
                    Reset
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeModal('modal-filter')"
                        class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i class="bi bi-funnel"></i>
                        Terapkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Hapus semua filter & urutan (kolom cari ikut dikosongkan karena halaman dimuat ulang).
    function resetFilter() {
        window.location.href = "{{ route('kelas') }}";
    }

    // Jangan kirim parameter kosong (mis. sort=) supaya URL tetap bersih.
    document.getElementById('form-filter').addEventListener('submit', function() {
        const sort = this.querySelector('select[name="sort"]');
        if (sort && !sort.value) sort.disabled = true;

        // Pertahankan kata kunci pencarian yang sedang diketik.
        const kata = (document.getElementById('inputSearch')?.value || '').trim();
        if (kata) {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'search';
            h.value = kata;
            this.appendChild(h);
        }
    });
</script>