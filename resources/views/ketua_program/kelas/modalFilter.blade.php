<!-- Modal Filter -->
<div id="modal-filter" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 transform transition-all">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-funnel text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Filter Kelas</h3>
                    <p class="text-sm text-gray-500">Pilih kriteria untuk memfilter data</p>
                </div>
            </div>
            <button onclick="closeModal('modal-filter')" 
                class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="form-filter" method="GET" action="{{ route('ketua_program.kelas') }}">
            <div class="px-6 py-6 space-y-6">
                <!-- Divider -->
                <div class="border-t border-gray-200"></div>

                <!-- Filter Tingkat Kelas -->
                <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="bi bi-layers mr-2 text-green-600"></i>
                        Tingkat Kelas
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="tingkat[]" value="10" 
                                class="sr-only peer"
                                {{ in_array('10', request('tingkat', [])) ? 'checked' : '' }}>
                            <div class="text-center peer-checked:text-blue-600 peer-checked:font-semibold">
                                <div class="text-lg font-bold">10</div>
                                <div class="text-xs text-gray-500">Kelas</div>
                            </div>
                        </label>
                        <label class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="tingkat[]" value="11" 
                                class="sr-only peer"
                                {{ in_array('11', request('tingkat', [])) ? 'checked' : '' }}>
                            <div class="text-center peer-checked:text-blue-600 peer-checked:font-semibold">
                                <div class="text-lg font-bold">11</div>
                                <div class="text-xs text-gray-500">Kelas</div>
                            </div>
                        </label>
                        <label class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="tingkat[]" value="12" 
                                class="sr-only peer"
                                {{ in_array('12', request('tingkat', [])) ? 'checked' : '' }}>
                            <div class="text-center peer-checked:text-blue-600 peer-checked:font-semibold">
                                <div class="text-lg font-bold">12</div>
                                <div class="text-xs text-gray-500">Kelas</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Filter Status (Opsional) -->
                <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="bi bi-toggles mr-2 text-purple-600"></i>
                        Urutkan Berdasarkan
                    </label>
                    <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Pengurutan --</option>
                        <option value="nama_kelas_asc" {{ request('sort') == 'nama_kelas_asc' ? 'selected' : '' }}>Nama Kelas (A-Z)</option>
                        <option value="nama_kelas_desc" {{ request('sort') == 'nama_kelas_desc' ? 'selected' : '' }}>Nama Kelas (Z-A)</option>
                        <option value="tingkat_asc" {{ request('sort') == 'tingkat_asc' ? 'selected' : '' }}>Tingkat (10, 11, 12)</option>
                        <option value="tingkat_desc" {{ request('sort') == 'tingkat_desc' ? 'selected' : '' }}>Tingkat (12, 11, 10)</option>
                    </select>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="resetFilter()" 
                        class="text-sm text-gray-600 hover:text-gray-800 flex items-center gap-1">
                        <i class="bi bi-arrow-clockwise"></i>
                        Reset Filter
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeModal('modal-filter')" 
                        class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i class="bi bi-funnel"></i>
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Script khusus untuk modal filter
function resetFilter() {
    // Reset semua checkbox jurusan
    
    // Reset semua checkbox tingkat
    document.querySelectorAll('input[name="tingkat[]"]').forEach(cb => cb.checked = false);
    
    // Reset select sort
    document.querySelector('select[name="sort"]').value = '';
    
    // Redirect ke halaman tanpa parameter filter
    window.location.href = "{{ route('ketua_program.kelas') }}";
}

// Menampilkan indikator filter aktif
function updateFilterIndicator() {
    const tingkat = document.querySelectorAll('input[name="tingkat[]"]:checked').length;
    const sort = document.querySelector('select[name="sort"]').value;
    
    const filterBtn = document.querySelector('button[onclick="openFilterModal()"]');
    const hasActiveFilter = tingkat > 0 || sort;
    
    if (hasActiveFilter) {
        filterBtn.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
        filterBtn.innerHTML = `
            <i class="bi bi-funnel-fill"></i> 
            Filter 
            <span class="bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full ml-1">
                ${tingkat + (sort ? 1 : 0)}
            </span>
        `;
    } else {
        filterBtn.classList.remove('bg-blue-50', 'text-blue-700', 'border-blue-200');
        filterBtn.innerHTML = '<i class="bi bi-funnel"></i> Filter';
    }
}

// Jalankan saat halaman dimuat
document.addEventListener('DOMContentLoaded', updateFilterIndicator);

// Update indikator saat checkbox berubah
document.querySelectorAll('input[name="tingkat[]"]').forEach(cb => {
    cb.addEventListener('change', updateFilterIndicator);
});

document.querySelector('select[name="sort"]').addEventListener('change', updateFilterIndicator);
</script>