 <!-- Modal Edit Kelas -->
 <div id="modal-edit" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
     <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-lg">
         <form id="form-edit" method="POST" class="p-8 space-y-6">
             @csrf
             @method('PUT')
             <div class="flex items-center justify-between mb-6">
                 <div>
                     <h2 class="text-2xl font-bold gradient-text">Edit Kelas</h2>
                     <p class="text-gray-500 mt-1">Perbarui informasi kelas yang dipilih</p>
                 </div>
                 <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                     class="w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-xl flex items-center justify-center text-gray-500 hover:text-gray-700 transition-colors">
                     <i class="bi bi-x-lg"></i>
                 </button>
             </div>

             <div class="space-y-5">
                 <div>
                     <label for="edit_id_kelas" class="block text-sm font-semibold text-gray-700 mb-2">
                         <i class="bi bi-hash mr-1"></i>ID Kelas
                     </label>
                     <input type="text" id="edit_id_kelas" name="id_kelas"
                         class="form-input w-full px-4 py-3 rounded-xl border-2 border-gray-300 bg-gray-50 uppercase"
                         required readonly>
                     <p class="text-xs text-gray-500 mt-1">ID kelas tidak dapat diubah</p>
                 </div>

                 <div>
                     <label for="edit_nama_kelas" class="block text-sm font-semibold text-gray-700 mb-2">
                         <i class="bi bi-tag mr-1"></i>Nama Kelas
                     </label>
                     <input type="text" id="edit_nama_kelas" name="nama_kelas"
                         class="form-input w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:ring-0 focus:outline-none uppercase"
                         required>
                     <p class="text-xs text-gray-500 mt-1">
                         Kelas X: Program Keahlian (AKL, MPLB, PM, PPLG, DKV, TJKT).
                         Kelas XI/XII: Kompetensi Keahlian (AK, MP, MLOG, RPL, TKJ, BR, DKV).
                     </p>
                 </div>

                 <div>
                     <label for="edit_jurusan" class="block text-sm font-medium text-gray-700 mb-1">
                         Jurusan (Program / Kompetensi Keahlian)
                     </label>
                     <select id="edit_jurusan" name="id_jurusan" required
                         class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                         <option value="" disabled selected>Pilih Jurusan</option>
                         @foreach ($jurusanList as $item)
                             <option value="{{ $item->id_jurusan }}">
                                 {{ $item->nama_jurusan }} ({{ \App\Models\kelas::ringkasanKode($item->id_jurusan) }})
                             </option>
                         @endforeach
                     </select>
                     <p id="edit_hint" class="text-xs text-blue-600 mt-1"></p>
                 </div>

             </div>

             <div class="flex justify-end gap-3 pt-5 border-t">
                 <button type="button" onclick="closeModal('modal-edit')"
                     class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium">
                     Batal
                 </button>
                 <button type="submit"
                     class="px-6 py-3 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition">
                     Simpan Perubahan
                 </button>
             </div>
         </form>
     </div>
 </div>