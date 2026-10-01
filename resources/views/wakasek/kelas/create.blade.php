<!-- Modal Create Kelas -->
<div id="modal-create" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form action="{{ route('kelas.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Tambah Kelas</h2>
                <button type="button" onclick="closeModal('modal-create')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="create_tingkat" class="block text-sm font-medium text-gray-700 mb-1">Tingkat</label>
                        {{-- Tanpa name: hanya bantuan untuk isi otomatis --}}
                        <select id="create_tingkat"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="" selected>Pilih</option>
                            <option value="X">X</option>
                            <option value="XI">XI</option>
                            <option value="XII">XII</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="id_jurusan" class="block text-sm font-medium text-gray-700 mb-1">
                            Jurusan (Program / Kompetensi Keahlian)
                        </label>
                        <select id="id_jurusan" name="id_jurusan" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="" disabled selected>Pilih Jurusan</option>
                            @foreach ($jurusanList as $item)
                                <option value="{{ $item->id_jurusan }}">
                                    {{ $item->nama_jurusan }} ({{ \App\Models\kelas::ringkasanKode($item->id_jurusan) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p id="create_hint" class="text-xs text-blue-600 -mt-2"></p>

                <div>
                    <label for="id_kelas" class="block text-sm font-medium text-gray-700 mb-1">ID Kelas</label>
                    <input type="text" id="id_kelas" name="id_kelas" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Contoh: X-RPL-2, XI-RPL-1, XII-TKJ-3">
                    <p class="text-xs text-gray-500 mt-1">
                        Kode di ID sama untuk semua tingkat (mis. X-RPL-1, XI-RPL-1) agar proses naik kelas berjalan.
                    </p>
                </div>

                <div>
                    <label for="nama_kelas" class="block text-sm font-medium text-gray-700 mb-1">Nama Kelas</label>
                    <input type="text" id="nama_kelas" name="nama_kelas" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Contoh: X PPLG 2, XI RPL 1, XII TKJ 3">
                    <p class="text-xs text-gray-500 mt-1">
                        Kelas X memakai Program Keahlian (AKL, MPLB, PM, PPLG, DKV, TJKT).
                        Kelas XI/XII memakai Kompetensi Keahlian (AK, MP, MLOG, RPL, TKJ, BR, DKV).
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('modal-create')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>