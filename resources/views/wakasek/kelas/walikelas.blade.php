<!-- Modal Pilih Walikelas -->
<div id="modal-walikelas"
    class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <form id="form-walikelas" method="POST" class="p-8 space-y-5">
            @csrf
            @method('PUT')

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold gradient-text">Atur Walikelas</h2>
                    <p class="text-gray-500 mt-1">Kelas <span id="wali-nama-kelas" class="font-semibold text-gray-700"></span></p>
                </div>
                <button type="button" onclick="closeModal('modal-walikelas')"
                    class="w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-xl flex items-center justify-center text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="relative">
                <i class="bi bi-search absolute left-3 top-3 text-gray-400"></i>
                <input type="text" id="wali-cari" autocomplete="off" placeholder="Cari nama atau NIP guru..."
                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div id="wali-daftar" class="max-h-72 overflow-y-auto space-y-1 border border-gray-200 rounded-xl p-2"></div>

            <p id="wali-peringatan"
                class="hidden text-sm bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2"></p>

            <div class="flex justify-end gap-3 pt-5 border-t">
                <button type="button" onclick="closeModal('modal-walikelas')"
                    class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium">
                    Batal
                </button>
                <button type="submit"
                    class="px-6 py-3 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>