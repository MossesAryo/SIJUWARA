@php $bukaCreate = old('_form') === 'create'; @endphp

<!-- Modal Create Guru BK -->
<div id="modal-create" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center {{ $bukaCreate ? '' : 'hidden' }}">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form action="{{ route('gurubk.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="create">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Tambah Guru BK</h2>
                <button type="button" onclick="document.getElementById('modal-create').classList.add('hidden')"
                    class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            </div>

            @if ($bukaCreate && ($errors->any() || session('error')))
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

            <div class="space-y-3">
                <div>
                    <label for="nip_guru" class="block text-sm font-medium text-gray-700">Guru</label>
                    <select id="nip_guru" name="nip_guru" required data-guru-select
                        data-nip-target="tampil_nip" data-nama-target="tampil_nama"
                        class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 focus:ring focus:ring-blue-200 focus:outline-none">
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
                            Guru BK dipilih dari data guru. NIP dan nama terisi otomatis.
                        </p>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="tampil_nip" class="block text-sm font-medium text-gray-700">NIP</label>
                        <input type="text" id="tampil_nip" readonly tabindex="-1" placeholder="Terisi otomatis"
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 bg-gray-50 text-gray-700">
                    </div>
                    <div>
                        <label for="tampil_nama" class="block text-sm font-medium text-gray-700">Nama</label>
                        <input type="text" id="tampil_nama" readonly tabindex="-1" placeholder="Terisi otomatis"
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm px-3 py-1.5 bg-gray-50 text-gray-700">
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-create').classList.add('hidden')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>