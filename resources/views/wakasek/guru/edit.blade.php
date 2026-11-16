@php $bukaEdit = old('_form') === 'edit'; @endphp

<!-- Modal Edit -->
<div id="modal-edit" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center {{ $bukaEdit ? '' : 'hidden' }}" style="z-index:1000">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form id="form-edit" method="POST" action="{{ $bukaEdit ? old('_action') : '' }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" id="edit_action" name="_action" value="{{ $bukaEdit ? old('_action') : '' }}">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Edit Guru &amp; Jabatan</h2>
                <button type="button" onclick="closeModal('modal-edit')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>

            @if ($bukaEdit && ($errors->any() || session('error')))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                        @if (session('error'))
                            <li>{{ session('error') }}</li>
                        @endif
                    </ul>
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label for="edit_nip_guru" class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                    <input type="text" id="edit_nip_guru" name="nip_guru" required inputmode="numeric" maxlength="30"
                        value="{{ $bukaEdit ? old('nip_guru') : '' }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p id="edit_nip_info" class="text-xs text-gray-500 mt-1 hidden">
                        NIP tidak bisa diubah karena guru ini menjadi ketua program.
                    </p>
                </div>
                <div>
                    <label for="edit_nama_guru" class="block text-sm font-medium text-gray-700 mb-1">Nama Guru</label>
                    <input type="text" id="edit_nama_guru" name="nama_guru" required maxlength="255"
                        value="{{ $bukaEdit ? old('nama_guru') : '' }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="border-t pt-4 space-y-3">
                    <div>
                        <label for="edit_jabatan" class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                        <select id="edit_jabatan" name="jabatan" onchange="toggleJabatanEdit()"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tanpa jabatan</option>
                            @foreach (\App\Models\guru::DAFTAR_JABATAN as $kode => $label)
                                <option value="{{ $kode }}" {{ $bukaEdit && old('jabatan') === $kode ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Menambah jabatan membuat akun login (NIP, password awal: password123).
                            Menghapus jabatan ikut menghapus akun login; data guru tetap ada.
                        </p>
                        <p id="edit_info_wakasek" class="text-xs text-amber-600 mt-1 hidden">
                            Akun Wakasek punya akses penuh ke data jurnal siswa.
                        </p>
                    </div>

                    <div id="edit_blok_kelas" class="hidden">
                        <label for="edit_id_kelas" class="block text-sm font-medium text-gray-700 mb-1">Kelas (opsional)</label>
                        <select id="edit_id_kelas" name="id_kelas"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Belum ada kelas</option>
                            @foreach ($daftar_kelas as $k)
                                <option value="{{ $k->id_kelas }}"
                                    {{ $bukaEdit && (string) old('id_kelas') === (string) $k->id_kelas ? 'selected' : '' }}>
                                    {{ $k->nama_kelas }}{{ $k->walikelas ? ' (wali: ' . $k->walikelas->nama_walikelas . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Satu kelas hanya punya satu wali. Kalau kelas sudah punya wali, wali lamanya dilepas.</p>
                    </div>

                    <div id="edit_blok_jurusan" class="hidden">
                        <label for="edit_id_jurusan" class="block text-sm font-medium text-gray-700 mb-1">Program / Kompetensi Keahlian</label>
                        <select id="edit_id_jurusan" name="id_jurusan"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                            @foreach ($daftar_jurusan as $j)
                                <option value="{{ $j->id_jurusan }}"
                                    {{ $bukaEdit && (string) old('id_jurusan') === (string) $j->id_jurusan ? 'selected' : '' }}>
                                    {{ $j->label_dropdown }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('modal-edit')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Update</button>
            </div>
        </form>
    </div>
</div>