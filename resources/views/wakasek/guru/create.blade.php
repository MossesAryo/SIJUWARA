@php $bukaCreate = old('_form') === 'create'; @endphp

<!-- Modal Create -->
<div id="modal-create" class="fixed inset-0 bg-black bg-opacity-40 modal-overlay flex items-center justify-center {{ $bukaCreate ? '' : 'hidden' }}" style="z-index:1000">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4">
        <form action="{{ route('guru.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_form" value="create">

            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-700">Tambah Guru</h2>
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
                    <label for="nip_guru" class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                    <input type="text" id="nip_guru" name="nip_guru" required inputmode="numeric" maxlength="30"
                        value="{{ $bukaCreate ? old('nip_guru') : '' }}" placeholder="Contoh : 1902854768"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="nama_guru" class="block text-sm font-medium text-gray-700 mb-1">Nama Guru</label>
                    <input type="text" id="nama_guru" name="nama_guru" required maxlength="255"
                        value="{{ $bukaCreate ? old('nama_guru') : '' }}" placeholder="Contoh : Ahmad Fauzi"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeModal('modal-create')"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>