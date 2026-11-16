<!-- Modal Edit -->
<div id="modal-edit" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center hidden">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form id="form-edit" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Edit Walikelas</h2>
                <button type="button" onclick="closeModal('modal-edit')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="edit_nip_guru" class="block text-sm font-medium text-gray-700 mb-1">Guru</label>
                    <select id="edit_nip_guru" name="nip_guru" required data-guru-select
                        data-nip-target="edit_tampil_nip" data-nama-target="edit_tampil_nama"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($daftar_guru as $g)
                            <option value="{{ $g->nip_guru }}" data-nama="{{ $g->nama_guru }}"
                                data-jabatan="{{ $peta_jabatan[(string) $g->nip_guru] ?? '' }}">
                                {{ $g->nama_guru }} ({{ $g->nip_guru }}){{ isset($peta_jabatan[(string) $g->nip_guru]) ? ' - ' . $peta_jabatan[(string) $g->nip_guru] : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Mengganti guru memindahkan akun wali kelas ke guru baru dan mengembalikan password ke password awal.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_tampil_nip" class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                        <input type="text" id="edit_tampil_nip" readonly tabindex="-1"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700">
                    </div>
                    <div>
                        <label for="edit_tampil_nama" class="block text-sm font-medium text-gray-700 mb-1">Nama Walikelas</label>
                        <input type="text" id="edit_tampil_nama" readonly tabindex="-1"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700">
                    </div>
                </div>
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="edit_username" name="username" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Kalau guru diganti, username dibuat ulang dari nama guru baru.</p>
                </div>
                <div>
                    <label for="edit_id_kelas" class="block text-sm font-medium text-gray-700 mb-1">
                        Kelas <span class="text-gray-400 font-normal"></span>
                    </label>
                    {{-- Kelas yang terpilih diatur oleh openEditModal() di walikelas.js dari data-kelas --}}
                    <select class="form-select w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="edit_id_kelas" name="id_kelas">
                        <option value="">Tanpa Kelas</option>
                        @foreach ($kelas as $k)
                            <option value="{{ $k->id_kelas }}"
                                data-wali-nip="{{ optional($k->walikelas)->nip_walikelas }}"
                                data-wali-nama="{{ optional($k->walikelas)->nama_walikelas }}">
                                {{ $k->nama_kelas }}{{ $k->walikelas ? ' — wali: ' . $k->walikelas->nama_walikelas : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p id="edit_kelas_info"
                        class="hidden mt-2 text-sm bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2"></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('modal-edit')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Update</button>
            </div>
        </form>
    </div>
</div>