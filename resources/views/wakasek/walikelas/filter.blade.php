
<div id="modal-filter" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-[9999]">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 relative">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-funnel text-blue-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Filter Walikelas</h2>
            </div>
            <button type="button" onclick="closeModal('modal-filter')"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-2 transition">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <!-- Form -->
        <form method="GET" class="p-6 space-y-6">
            {{-- Dijaga sinkron dengan kotak pencarian oleh walikelas.js --}}
            <input type="hidden" id="filterSearch" name="search" value="{{ request('search') }}">

            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="bi bi-collection text-gray-500"></i> Kelas
                </label>
                <select id="kelas" name="kelas" class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach ($kelas as $item)
                        <option value="{{ $item->id_kelas }}"
                            {{ request('kelas') == $item->id_kelas ? 'selected' : '' }}>
                            {{ $item->nama_kelas }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('modal-filter')"
                    class="px-6 py-3 rounded-xl border-2 border-gray-200 text-gray-600 hover:bg-gray-50">
                    <i class="bi bi-x-circle"></i> Batal
                </button>
                <button type="button" onclick="window.location.href='{{ route('walikelas.index') }}'"
                    class="px-6 py-3 rounded-xl border-2 border-orange-200 text-orange-600 hover:bg-orange-50">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <button type="submit"
                    class="px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 shadow-lg">
                    <i class="bi bi-check-circle"></i> Terapkan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Pastikan DOM sudah siap
    document.addEventListener('DOMContentLoaded', function () {
        const openBtn = document.getElementById('openFilterModal');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                openFilterModal();
            });
        }
    });
</script>