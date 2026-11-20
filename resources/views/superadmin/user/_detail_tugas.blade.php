{{--
    Isian khusus satu tugas (kelas perwalian / jurusan / kelas binaan). Dipakai di halaman Tambah dan Edit Akun.
    Variabel: $kode (int), $label, $input, $aktif (bool), $nilai (array, opsional),
              $daftarKelas, $kelasTerisi, $daftarJurusan, $jurusanTerisi
    Nama field: detail[<kode>][id_kelas|id_jurusan|kelas[]]. Wakasek tidak punya isian khusus.
--}}
@php
    $kd = (int) $kode;
    $nilai = $nilai ?? [];
@endphp

@if (in_array($kd, [2, 3, 4], true))
    <div data-detail="{{ $kd }}" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 {{ $aktif ? '' : 'hidden' }}">
        <p class="flex items-center gap-2 text-sm font-semibold text-gray-800">
            <i class="bi bi-sliders"></i> Isian {{ $label }}
        </p>

        @if ($kd === 3)
            <div>
                <label for="detail_3_id_kelas" class="mb-1 block text-sm font-medium text-gray-700">Kelas Perwalian <span class="text-red-500">*</span></label>
                <select id="detail_3_id_kelas" name="detail[3][id_kelas]" required
                    class="{{ $input }} {{ $errors->has('detail.3.id_kelas') ? 'border-red-400' : 'border-gray-300' }}">
                    <option value="">-- Pilih kelas --</option>
                    @foreach ($daftarKelas as $k)
                        @php $terisi = in_array($k->id_kelas, $kelasTerisi, true); @endphp
                        <option value="{{ $k->id_kelas }}"
                            @selected((string) old('detail.3.id_kelas', $nilai['id_kelas'] ?? '') === (string) $k->id_kelas)
                            @disabled($terisi)>
                            {{ $k->nama_kelas }}{{ $terisi ? ' (sudah ada wali kelas)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Satu kelas hanya dapat memiliki satu wali kelas.</p>
                @error('detail.3.id_kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($kd === 4)
            <div>
                <label for="detail_4_id_jurusan" class="mb-1 block text-sm font-medium text-gray-700">Jurusan <span class="text-red-500">*</span></label>
                <select id="detail_4_id_jurusan" name="detail[4][id_jurusan]" required
                    class="{{ $input }} {{ $errors->has('detail.4.id_jurusan') ? 'border-red-400' : 'border-gray-300' }}">
                    <option value="">-- Pilih jurusan --</option>
                    @foreach ($daftarJurusan as $j)
                        @php $terisi = in_array($j->id_jurusan, $jurusanTerisi, true); @endphp
                        <option value="{{ $j->id_jurusan }}"
                            @selected((string) old('detail.4.id_jurusan', $nilai['id_jurusan'] ?? '') === (string) $j->id_jurusan)
                            @disabled($terisi)>
                            {{ $j->nama_jurusan }}{{ $terisi ? ' (sudah ada ketua program)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Satu jurusan hanya dapat memiliki satu ketua program.</p>
                @error('detail.4.id_jurusan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($kd === 2)
            @php
                $pilihBk = old('detail.2.kelas', $nilai['kelas'] ?? []);
                $pilihBk = is_array($pilihBk) ? $pilihBk : [];
            @endphp
            <div>
                <p class="mb-1 text-sm font-medium text-gray-700">Kelas Binaan <span class="text-xs font-normal text-gray-400">(boleh dikosongkan)</span></p>
                <div class="grid max-h-64 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-200 p-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($daftarKelas as $k)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="detail[2][kelas][]" value="{{ $k->id_kelas }}"
                                @checked(in_array($k->id_kelas, $pilihBk, true))
                                class="rounded border-gray-300">
                            <span>{{ $k->nama_kelas }}</span>
                        </label>
                    @endforeach
                </div>
                @error('detail.2.kelas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('detail.2.kelas.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>
@endif
