<!-- Modal Create -->
    <div id="modal-create" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
            <form action="{{ route('kaprog.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-700">Tambah Ketua Program</h2>
                    <button type="button" onclick="closeModal('modal-create')"
                        class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="nip_kaprog" class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                        <input type="text" id="nip_kaprog" name="nip_kaprog" required placeholder="1907825673"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="nama_ketua_program" class="block text-sm font-medium text-gray-700 mb-1">Nama Ketua Program</label>
                        <input type="text" id="nama_ketua_program" name="nama_ketua_program" required placeholder="Robi Jainud"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="bi bi-buildings text-gray-500"></i> Program / Kompetensi Keahlian
                        </label>
                        <select id="jurusan" name="id_jurusan" class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                            <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                            @foreach ($daftar_jurusan as $jurusan)
                                <option value="{{ $jurusan->id_jurusan }}" {{ request('jurusan') == $jurusan->id_jurusan ? 'selected' : '' }}>
                                    {{ $jurusan->label_dropdown }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Kelas X memakai Program Keahlian, kelas XI/XII memakai Kompetensi Keahlian.</p>
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