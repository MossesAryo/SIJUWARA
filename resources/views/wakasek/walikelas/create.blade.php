@php $bukaCreate = old('_form') === 'create'; @endphp

<!-- Modal Create -->
<div id="modal-create" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center {{ $bukaCreate ? '' : 'hidden' }} z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4 z-60">
        <form action="{{ route('walikelas.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="create">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Tambah Walikelas</h2>
                <button type="button" onclick="closeModal('modal-create')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>

            @if ($bukaCreate && ($errors->any() || session('error')))
                <div id="create-errors" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
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
                    <label for="nip_guru" class="block text-sm font-medium text-gray-700 mb-1">Guru</label>
                    <select id="nip_guru" name="nip_guru" required data-guru-select
                        data-nip-target="tampil_nip" data-nama-target="tampil_nama"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($daftar_guru as $g)
                            @php $jabatan = $peta_jabatan[(string) $g->nip_guru] ?? null; @endphp
                            <option value="{{ $g->nip_guru }}" data-nama="{{ $g->nama_guru }}"
                                {{ $jabatan ? 'disabled' : '' }}
                                {{ $bukaCreate && (string) old('nip_guru') === (string) $g->nip_guru ? 'selected' : '' }}>
                                {{ $g->nama_guru }} ({{ $g->nip_guru }}){{ $jabatan ? ' - sudah menjadi ' . $jabatan : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($daftar_guru->isEmpty())
                        <p class="text-xs text-amber-600 mt-1">
                            Belum ada data guru. Tambah atau import dulu di menu
                            <a href="{{ route('guru.index') }}" class="underline font-medium">Guru</a>.
                        </p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">
                            Wali kelas dipilih dari data guru. NIP dan nama terisi otomatis.
                        </p>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="tampil_nip" class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                        <input type="text" id="tampil_nip" readonly tabindex="-1" placeholder="Terisi otomatis"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700">
                    </div>
                    <div>
                        <label for="tampil_nama" class="block text-sm font-medium text-gray-700 mb-1">Nama Walikelas</label>
                        <input type="text" id="tampil_nama" readonly tabindex="-1" placeholder="Terisi otomatis"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700">
                    </div>
                </div>
                <div>
                    <label for="id_kelas" class="block text-sm font-medium text-gray-700 mb-1">
                        Kelas <span class="text-gray-400 font-normal"></span>
                    </label>
                    <select class="form-select w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="id_kelas" name="id_kelas">
                        <option value="" {{ $bukaCreate && old('id_kelas') ? '' : 'selected' }}>Tanpa Kelas</option>
                        @foreach ($kelas as $item)
                            <option value="{{ $item->id_kelas }}"
                                {{ $bukaCreate && old('id_kelas') === $item->id_kelas ? 'selected' : '' }}>
                                {{ $item->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
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

<style>
.modal-overlay {
    z-index: 1000 !important;
}

.modal-overlay > div {
    z-index: 1001 !important;
}

#modal-create {
    z-index: 1000 !important;
}

#modal-create > div {
    z-index: 1001 !important;
}
</style>