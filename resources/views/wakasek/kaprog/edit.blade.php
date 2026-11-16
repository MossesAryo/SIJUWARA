@php $bukaEdit = old('_form') === 'edit'; @endphp

<!-- Modal Edit -->
<div id="modal-edit" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center {{ $bukaEdit ? '' : 'hidden' }}">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form id="form-edit" method="POST" action="{{ $bukaEdit ? old('_action') : '' }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" id="edit_action" name="_action" value="{{ $bukaEdit ? old('_action') : '' }}">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Edit Ketua Program</h2>
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
                    <label for="edit_nip_guru" class="block text-sm font-medium text-gray-700 mb-1">Guru</label>
                    <select id="edit_nip_guru" name="nip_guru" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($daftar_guru as $g)
                            <option value="{{ $g->nip_guru }}"
                                data-kaprog="{{ in_array((string) $g->nip_guru, $nip_kaprog_terpakai, true) ? 1 : 0 }}"
                                {{ $bukaEdit && (string) old('nip_guru') === (string) $g->nip_guru ? 'selected' : '' }}>
                                {{ $g->nama_guru }} ({{ $g->nip_guru }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Mengganti guru memindahkan akun ketua program ke guru baru dan mengembalikan password ke password awal.
                    </p>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-buildings text-gray-500"></i> Program / Kompetensi Keahlian
                    </label>
                    <select id="edit_jurusan" name="id_jurusan" required
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                        @foreach ($daftar_jurusan as $jurusan)
                            <option value="{{ $jurusan->id_jurusan }}"
                                {{ $bukaEdit && (string) old('id_jurusan') === (string) $jurusan->id_jurusan ? 'selected' : '' }}>
                                {{ $jurusan->label_dropdown }}
                            </option>
                        @endforeach
                    </select>
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