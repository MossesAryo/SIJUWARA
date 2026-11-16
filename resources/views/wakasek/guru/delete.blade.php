<!-- Modal Delete -->
<div id="modal-delete" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center hidden" style="z-index:1000">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <form id="form-delete" method="POST" class="p-6 space-y-4">
            @csrf
            @method('DELETE')
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Hapus Guru</h2>
                <button type="button" onclick="closeModal('modal-delete')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>
            <p class="text-gray-600">Apakah kamu yakin ingin menghapus guru <span id="delete-nama-guru" class="font-semibold"></span>?</p>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('modal-delete')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Hapus</button>
            </div>
        </form>
    </div>
</div>