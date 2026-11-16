@php
    $bukaCreate = old('_form') === 'create';
    $guruTersedia = $daftar_guru->reject(fn ($g) => in_array((string) $g->nip_guru, $nip_kaprog_terpakai, true));
@endphp

<!-- Modal Create -->
<div id="modal-create" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center {{ $bukaCreate ? '' : 'hidden' }}">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form action="{{ route('kaprog.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="create">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Tambah Ketua Program</h2>
                <button type="button" onclick="closeModal('modal-create')"
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

            <div class="space-y-4">
                <div>
                    <label for="nip_guru" class="block text-sm font-medium text-gray-700 mb-1">Guru</label>
                    <select id="nip_guru" name="nip_guru" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($guruTersedia as $g)
                            <option value="{{ $g->nip_guru }}"
                                {{ $bukaCreate && (string) old('nip_guru') === (string) $g->nip_guru ? 'selected' : '' }}>
                                {{ $g->nama_guru }} ({{ $g->nip_guru }})
                            </option>
                        @endforeach
                    </select>
                    @if ($guruTersedia->isEmpty())
                        <p class="text-xs text-amber-600 mt-1">
                            Belum ada guru yang bisa dipilih. Tambah atau import dulu di menu
                            <a href="{{ route('guru.index') }}" class="underline font-medium">Guru</a>.
                        </p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">
                            Ketua program dipilih dari data guru. NIP dan nama mengikuti data guru.
                        </p>
                    @endif
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="bi bi-buildings text-gray-500"></i> Program / Kompetensi Keahlian
                    </label>
                    <select id="jurusan" name="id_jurusan" required
                        class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:ring-4 focus:ring-blue-100 focus:border-blue-500">
                        <option value="">-- Pilih Program / Kompetensi Keahlian --</option>
                        @foreach ($daftar_jurusan as $jurusan)
                            <option value="{{ $jurusan->id_jurusan }}"
                                {{ (string) old('id_jurusan', request('jurusan')) === (string) $jurusan->id_jurusan ? 'selected' : '' }}>
                                {{ $jurusan->label_dropdown }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500">Kelas 10 memakai Program Keahlian, kelas 11/12 memakai Kompetensi Keahlian.</p>
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