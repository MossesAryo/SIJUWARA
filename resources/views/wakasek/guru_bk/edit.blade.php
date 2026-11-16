    <!-- Modal Edit Guru BK -->
    <div id="modal-edit" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
            <form id="form-edit" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-700">Edit Guru BK</h2>
                    <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700 text-xl ">&times;</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="edit_nip_guru" class="block text-sm font-medium text-gray-700">Guru</label>
                        <select id="edit_nip_guru" name="nip_guru" required data-guru-select
                            data-nip-target="edit_tampil_nip" data-nama-target="edit_tampil_nama"
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 focus:ring focus:ring-blue-200 focus:outline-none">
                            <option value="">-- Pilih Guru --</option>
                            @foreach ($daftar_guru as $g)
                                <option value="{{ $g->nip_guru }}" data-nama="{{ $g->nama_guru }}"
                                    data-jabatan="{{ $peta_jabatan[(string) $g->nip_guru] ?? '' }}">
                                    {{ $g->nama_guru }} ({{ $g->nip_guru }}){{ isset($peta_jabatan[(string) $g->nip_guru]) ? ' - ' . $peta_jabatan[(string) $g->nip_guru] : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Mengganti guru memindahkan akun guru BK ke guru baru dan mengembalikan password ke password awal.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="edit_tampil_nip" class="block text-sm font-medium text-gray-700">NIP</label>
                            <input type="text" id="edit_tampil_nip" readonly tabindex="-1"
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 bg-gray-50 text-gray-700">
                        </div>
                        <div>
                            <label for="edit_tampil_nama" class="block text-sm font-medium text-gray-700">Nama</label>
                            <input type="text" id="edit_tampil_nama" readonly tabindex="-1"
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 bg-gray-50 text-gray-700">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>