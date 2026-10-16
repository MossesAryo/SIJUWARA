<div id="modal-create-pelanggaran" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-[999999]">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 my-8 overflow-hidden">
        <form action="{{ route('siswa.skoringPelanggaran', ['nis' => $siswa->nis]) }}" method="POST" class="p-6 space-y-5"
            onsubmit="if (this.dataset.terkirim) { return false; } this.dataset.terkirim = '1'; var b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Menyimpan...';">
            @csrf

            <div class="flex justify-between items-center border-b pb-4">
                <h2 class="text-2xl font-bold text-gray-800">Tambah Pelanggaran</h2>
                <button type="button" onclick="closeModal('modal-create-pelanggaran')"
                    class="text-gray-400 hover:text-gray-600 text-3xl leading-none transition">&times;</button>
            </div>

            <div class="space-y-5">
                <input type="hidden" name="nis" value="{{ $siswa->nis }}">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Siswa</label>
                    <div class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-white-50 text-black-800 font-medium">
                        {{ $siswa->nama_siswa }}
                    </div>
                </div>

                <div>
                    <label for="aspek_pelanggaran" class="block text-sm font-semibold text-gray-700 mb-2">
                        Jenis Pelanggaran
                    </label>
                    <select id="aspek_pelanggaran" name="id_aspekpenilaian" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-4 focus:ring-red-500 focus:border-red-500 transition"
                        onchange="updateSkor(this, 'skor_pelanggaran')">
                        <option value="" disabled selected>Pilih Jenis Pelanggaran</option>
                        @foreach ($skoringpelanggaran as $item)
                            <option value="{{ $item->id_aspekpenilaian }}" data-skor="{{ $item->indikator_poin }}">
                                {{ $item->indikator_poin }} - {{ $item->uraian }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Skor Pengurangan</label>
                    <input type="text" id="skor_pelanggaran" name="skor" readonly placeholder="Skor otomatis terisi"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-red-50 text-red-700 font-bold text-lg text-center">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('modal-create-pelanggaran')"
                    class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition font-medium">
                    Batal
                </button>
                <button type="submit"
                    class="px-8 py-3 rounded-lg bg-red-600 text-white hover:bg-red-700 transition font-medium shadow-lg disabled:opacity-60 disabled:cursor-not-allowed">
                    Simpan Pelanggaran
                </button>
            </div>
        </form>
    </div>
</div>