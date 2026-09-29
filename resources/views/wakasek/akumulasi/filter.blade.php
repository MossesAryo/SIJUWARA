<!-- Modal Filter -->
<div id="modal-filter" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95"
        id="modal-content">

        <!-- Header -->
        <div
            class="flex items-center justify-between p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-funnel text-blue-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">
                    Filter Siswa
                </h2>
            </div>
            <button onclick="closeModal('modal-filter')"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-2 transition-all duration-200">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <!-- Form -->
        <form method="GET" action="{{ route('akumulasi.index') }}" class="p-6 space-y-6">
            @if (auth()->user()->role == 1 || auth()->user()->role == 2)
                <!-- Program / Kompetensi Keahlian -->
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-buildings text-gray-500"></i>
                        Program / Kompetensi Keahlian
                    </label>
                    <select id="jurusan" name="jurusan"
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-200">
                        <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                        @foreach ($jurusanList as $jurusan)
                            <option value="{{ $jurusan->id_jurusan }}"
                                {{ request('jurusan') == $jurusan->id_jurusan ? 'selected' : '' }}>
                                {{ $jurusan->label_dropdown }}
                            </option>
                        @endforeach
                    </select>
                </div>

            @endif

            @if (auth()->user()->role == 3)
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-buildings text-gray-500"></i>
                        Program / Kompetensi Keahlian
                    </label>
                    <select name="jurusan"
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-200">
                        @foreach ($jurusanList as $jur)
                            <option value="{{ $jur->id_jurusan }}"
                                @if (request('jurusan') == $jur->id_jurusan || (($jurusanKetua ?? null) == $jur->id_jurusan && !request('jurusan'))) selected @endif>
                                {{ $jur->label_dropdown }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <!-- Kelas -->
            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="bi bi-collection text-gray-500"></i>
                    Kelas
                </label>
                <select id="kelas" name="kelas"
                    class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-200">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id_kelas }}" data-jurusan="{{ $kelas->id_jurusan }}"
                            {{ request('kelas') == $kelas->id_kelas ? 'selected' : '' }}>
                            {{ $kelas->nama_kelas }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-gray-100">
                <button type="button" onclick="closeModal('modal-filter')"
                    class="px-6 py-3 text-sm font-medium rounded-xl border-2 border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 flex items-center justify-center gap-2">
                    <i class="bi bi-x-circle"></i>
                    Batal
                </button>

                <button type="button" onclick="resetForm()"
                    class="px-6 py-3 text-sm font-medium rounded-xl border-2 border-orange-200 text-orange-600 hover:bg-orange-50 hover:border-orange-300 transition-all duration-200 flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reset
                </button>

                <button type="submit"
                    class="px-6 py-3 text-sm font-medium rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="bi bi-check-circle"></i>
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        const form = document.querySelector('#modal-filter form');
        form.reset();
        window.location.href = "{{ route('akumulasi.index') }}";
    }

    // Filter kelas sesuai jurusan (hanya kalau dropdown jurusan ada, yaitu role 1 dan 2)
    const jurusanEl = document.getElementById('jurusan');

    if (jurusanEl) {
        jurusanEl.addEventListener('change', function() {
            const selectedJurusan = this.value;
            const kelasOptions = document.querySelectorAll('#kelas option');

            kelasOptions.forEach(option => {
                if (option.value === "") return; // skip default
                option.style.display = (!selectedJurusan || option.getAttribute('data-jurusan') === selectedJurusan) ?
                    'block' : 'none';
            });

            // reset pilihan kelas hanya kalau jurusan diganti oleh user
            if (this.dataset.ready === '1') {
                document.getElementById('kelas').value = "";
            }
        });

        // trigger saat load supaya daftar kelas langsung terfilter
        document.addEventListener('DOMContentLoaded', function() {
            jurusanEl.dispatchEvent(new Event('change'));
            jurusanEl.dataset.ready = '1';
        });
    }
</script>