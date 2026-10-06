<div id="modal-create-penghargaan" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-[999999]">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 my-8 overflow-hidden">
        <form id="form-create-penghargaan" action="{{ route('siswa.skoringPenghargaan', ['nis' => $siswa->nis]) }}" method="POST" class="p-6 space-y-5">
            @csrf

            <div class="flex justify-between items-center border-b pb-4">
                <h2 class="text-2xl font-bold text-gray-800">Tambah Penghargaan</h2>
                <button type="button" onclick="closeModal('modal-create-penghargaan')"
                    class="text-gray-400 hover:text-gray-600 text-3xl leading-none transition">&times;</button>
            </div>

            <div class="space-y-5">
                <input type="hidden" name="nis" value="{{ $siswa->nis }}">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Siswa</label>
                    <div class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 text-gray-800 font-medium">
                        {{ $siswa->nama_siswa }}
                    </div>
                </div>

                <div>
                    <label for="aspek_penghargaan" class="block text-sm font-semibold text-gray-700 mb-2">
                        Jenis Penghargaan
                    </label>
                    <select id="aspek_penghargaan" name="id_aspekpenilaian" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-4 focus:ring-green-500 focus:border-green-500 transition"
                        onchange="updateSkor(this, 'skor_penghargaan')">
                        <option value="" disabled selected>Pilih Jenis Penghargaan</option>
                        @foreach ($skoringpenghargaan as $item)
                            <option value="{{ $item->id_aspekpenilaian }}" data-skor="{{ $item->indikator_poin }}">
                                {{ $item->indikator_poin }} - {{ $item->uraian }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Skor Penghargaan</label>
                    <input type="text" id="skor_penghargaan" name="skor" readonly placeholder="Skor otomatis terisi"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-green-50 text-green-700 font-bold text-lg text-center">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('modal-create-penghargaan')"
                    class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition font-medium">
                    Batal
                </button>
                <button type="submit" id="btn-submit-penghargaan" data-default-text="Simpan Penghargaan"
                    class="px-8 py-3 rounded-lg bg-green-600 text-white hover:bg-green-700 transition font-medium shadow-lg disabled:opacity-60 disabled:cursor-not-allowed">
                    Simpan Penghargaan
                </button>
            </div>
        </form>
    </div>
</div>