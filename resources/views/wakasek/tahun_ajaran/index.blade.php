@extends('layouts.wakasek.app')

@push('css')
    {{-- Font isi halaman & textbox --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
@endpush

@php
    $preview      = $preview ?? ['kelas10_ke_11' => 0, 'kelas10_direncanakan' => 0, 'kelas11_ke_12' => 0, 'kelas11_direncanakan' => 0, 'lulus' => 0, 'lulus_direncanakan' => 0];
    $tahunAktif   = $tahunAktif ?? null;
    $rencanaMap   = $rencanaMap ?? collect();
    $tahunDipilih = old('tahun_ajaran_id', $tahunAktif?->id);
    $statusJurusan      = $statusJurusan ?? collect();
    $perencanaanLengkap = $perencanaanLengkap ?? true;
    $alasanPreset       = $alasanPreset ?? \App\Models\StudentClassHistory::ALASAN_PRESET;
    $jmlJurusanBelumSelesai = collect($statusJurusan)->where('selesai', false)->count();
    $belumDiproses = $belumDiproses ?? collect();
    $penandaMap    = $penandaMap ?? collect();
    $bisaTandai    = in_array((int) auth()->user()->role, [1, 5], true);
@endphp

@section('content')
<div class="space-y-6 font-jakarta">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Tahun Ajaran</h1>
        <p class="text-gray-500">Kelola proses kenaikan atau penurunan kelas siswa sesuai tahun ajaran aktif.</p>
    </div>

    {{-- Alert Messages --}}
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl flex items-start gap-3 shadow-sm">
            <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl flex items-start gap-3 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill text-red-600 mt-0.5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl shadow-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('wakasek.tahun_ajaran.kelas_tujuan')

    {{-- Info Kelas 10 & 12 --}}
    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-xl text-sm flex gap-3 shadow-sm">
        <i class="bi bi-info-circle-fill text-blue-600 mt-0.5 text-lg"></i>
        <p class="leading-relaxed">
            Penempatan siswa <strong>kelas 10 ke 11</strong> direncanakan oleh Ketua Program (siswa dicampur antar rombel). 
            Saat tahun ajaran naik 1 tahun, rencana itu diterapkan otomatis. Siswa kelas 10 yang belum direncanakan tetap di kelas 10. 
            <strong>Kelas 11 → 12</strong> tetap dipilih (dicentang) di halaman ini; jika Ketua Program sudah membuat rencana, siswa yang dicentang masuk ke kelas rencana tersebut, jika belum mereka masuk ke kelas pasangannya. 
            <strong>Kelas 12 → Alumni</strong> juga direncanakan Ketua Program (siswa kelas 12 yang ditandai lulus diluluskan otomatis, yang belum ditandai tetap di kelas 12).
        </p>
    </div>

    {{-- Status perencanaan Ketua Program per jurusan --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" id="panelStatusPerencanaan">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-2 bg-gray-50/50">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Status Perencanaan Ketua Program</h3>
                <p class="text-sm text-gray-500">
                    Syarat naik tahun ajaran: semua Ketua Program sudah selesai merencanakan penempatan siswa kelas 10 ke kelas 11.
                </p>
            </div>
            @if ($perencanaanLengkap)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 border border-green-200">
                    <i class="bi bi-check-circle-fill"></i> Semua jurusan selesai
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 text-sm font-semibold px-3 py-1 border border-red-200">
                    <i class="bi bi-lock-fill"></i> {{ $jmlJurusanBelumSelesai }} jurusan belum selesai
                </span>
            @endif
        </div>

        @unless ($perencanaanLengkap)
            <div class="px-6 py-3 bg-red-50 border-b border-red-200 text-sm text-red-800 flex gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
                <p>
                    Ganti tahun ajaran ke tahun yang lebih baru <strong>ditahan</strong> sampai semua jurusan berstatus
                    <strong>Selesai</strong>. Minta Ketua Program yang belum selesai untuk merencanakan di menu Perpindahan Kelas.
                    Tahun ajaran hanya bisa maju; mundur ke tahun lama dikunci.
                </p>
            </div>
        @endunless

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Jurusan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ketua Program</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas 10 → 11</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas 12 → Alumni</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($statusJurusan as $j)
                        @php
                            $persen = $j['total10'] > 0 ? (int) floor($j['direncanakan10'] / $j['total10'] * 100) : 100;
                            $badge = match ($j['status']) {
                                'selesai'  => ['bg-green-100 text-green-800 border-green-200', 'Selesai'],
                                'sebagian' => ['bg-amber-100 text-amber-800 border-amber-200', 'Sebagian'],
                                default    => ['bg-red-100 text-red-800 border-red-200', 'Belum'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900">{{ $j['nama'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700">
                                @if ($j['ada_kaprog'])
                                    {{ $j['kaprog'] ?? '-' }}
                                @else
                                    <span class="text-red-600 font-medium">Belum ada akun</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-700 min-w-[180px]">
                                @if ($j['total10'] === 0)
                                    <span class="text-gray-400 italic">Tidak ada siswa kelas 10</span>
                                @else
                                    <div class="font-medium">{{ $j['direncanakan10'] }} / {{ $j['total10'] }} siswa</div>
                                    <div class="mt-1 h-1.5 w-full rounded bg-gray-200 overflow-hidden">
                                        <div class="h-1.5 rounded {{ $j['selesai_kelas10'] ? 'bg-green-500' : 'bg-amber-500' }}"
                                             style="width: {{ $persen }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-700">
                                @if ($j['total12'] === 0)
                                    <span class="text-gray-400 italic">Tidak ada siswa kelas 12</span>
                                @else
                                    {{ $j['direncanakan12'] }} / {{ $j['total12'] }} ditandai lulus
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold border {{ $badge[0] }}">{{ $badge[1] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">Belum ada data jurusan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Preview Statistik --}}
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm font-medium text-amber-800">Kelas 10 → 11 (Rencana Kaprog)</p>
            <p class="text-3xl font-bold text-amber-700 mt-2">
                {{ $preview['kelas10_direncanakan'] }}<span class="text-lg text-amber-600/70"> / {{ $preview['kelas10_ke_11'] }}</span>
            </p>
            <p class="text-xs text-amber-700/80 mt-1">siswa kelas 10 sudah direncanakan</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm font-medium text-indigo-800">Siswa Kelas 11 → 12</p>
            <p class="text-3xl font-bold text-indigo-700 mt-2">{{ $preview['kelas11_ke_12'] }}</p>
            <p class="text-xs text-indigo-700/80 mt-1">{{ $preview['kelas11_direncanakan'] }} sudah direncanakan Ketua Program</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm font-medium text-green-800">Siswa Kelas 12 → Alumni</p>
            <p class="text-3xl font-bold text-green-700 mt-2">
                {{ $preview['lulus_direncanakan'] }}<span class="text-lg text-green-600/70"> / {{ $preview['lulus'] }}</span>
            </p>
            <p class="text-xs text-green-700/80 mt-1">siswa kelas 12 sudah ditandai lulus Ketua Program</p>
        </div>
    </div>

    {{-- Form Ganti Tahun Ajaran --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-lg text-gray-900 mb-1">Pilih Tahun Ajaran</h3>
        <p class="text-sm text-gray-500 mb-5">
            Sistem akan menaikkan atau menurunkan kelas siswa yang dicentang sesuai selisih tahun ajaran.
            Berlaku sama untuk kelas 10, 11, dan 12: siswa yang <strong>tidak dicentang</strong> tidak berubah kelasnya,
            dicatat sebagai <strong>tidak naik kelas (mengulang)</strong> di Riwayat Kelas dan wajib diberi alasan di jendela konfirmasi.
            Kelas 10 dan 11 yang dicentang naik ke kelas rencana Ketua Program bila ada (kalau tidak, ke kelas pasangan);
            kelas 12 yang dicentang lulus menjadi alumni.
        </p>

        <form method="POST" action="{{ route('tahun_ajaran.update') }}" id="formTahunAjaran">
            @csrf
            <input type="hidden" name="siswa_nis" id="siswaNisInput" value="{{ old('siswa_nis') }}">
            {{-- Diisi 1 oleh JavaScript hanya jika pengguna sengaja mengganti tahun tanpa mencentang siswa. --}}
            <input type="hidden" name="tanpa_siswa" id="tanpaSiswaInput" value="0">
            {{-- JSON {nis: alasan} untuk siswa yang tidak naik kelas, diisi JavaScript dari jendela konfirmasi. --}}
            <input type="hidden" name="alasan_tidak_naik" id="alasanTidakNaikInput" value="{{ old('alasan_tidak_naik') }}">
            
            <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                <div class="w-full md:w-1/2">
                    <label for="selectTahun" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <i class="bi bi-calendar3 text-gray-500"></i> Tahun Ajaran Tujuan
                    </label>
                    <select name="tahun_ajaran_id" id="selectTahun"
                            class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                        @foreach ($opsiTahun as $opsi)
                            <option value="{{ $opsi['id'] }}"
                                data-selisih="{{ $opsi['selisih'] }}"
                                {{ $opsi['selisih'] < 0 ? 'disabled' : '' }}
                                {{ (string) $opsi['id'] === (string) $tahunDipilih ? 'selected' : '' }}>
                                {{ $opsi['label'] }}@if ($opsi['aktif']) (aktif)@endif @if ($opsi['selisih'] < 0) (terkunci, tidak bisa mundur)@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" id="btnSubmitTahun"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-blue-200/70 transition duration-200 hover:bg-blue-700 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-blue-200 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none">
                    <i class="bi bi-arrow-repeat text-base"></i>
                    <span>Ganti Tahun Ajaran</span>
                </button>
            </div>

            <p id="hintSelisih" class="text-sm mt-4 font-medium"></p>
            <p id="hintTahan" class="text-sm mt-1 text-red-700 hidden font-medium">
                <i class="bi bi-lock-fill"></i>
                Tombol ditahan: {{ $jmlJurusanBelumSelesai }} jurusan belum selesai merencanakan (lihat Status Perencanaan di atas).
            </p>
            <p id="infoDipilih" class="text-sm text-gray-600 mt-1"></p>
        </form>
    </div>

    {{-- Siswa Belum Diproses: tertinggal di kelasnya karena belum direncanakan / ditandai, BUKAN keputusan mengulang --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" id="panelBelumDiproses">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Siswa Belum Diproses</h3>
                <p class="text-sm text-gray-500">
                    Siswa yang tertinggal setelah ganti tahun karena belum direncanakan / ditandai lulus oleh Ketua Program.
                    Mereka <strong>bukan</strong> siswa mengulang kecuali ditandai di sini.
                </p>
            </div>
            @if ($belumDiproses->isEmpty())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 border border-green-200">
                    <i class="bi bi-check-circle-fill"></i> Tidak ada yang tertahan
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1 border border-amber-200">
                    <i class="bi bi-hourglass-split"></i> {{ $belumDiproses->count() }} siswa belum diproses
                </span>
            @endif
        </div>

        @if ($belumDiproses->isNotEmpty())
            <form method="POST" action="{{ route('tahun_ajaran.tandai_mengulang') }}" id="formTandaiMengulang">
                @csrf
                <div class="overflow-x-auto max-h-96 overflow-y-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                            <tr>
                                @if ($bisaTandai)
                                    <th class="px-6 py-3 text-center w-12">
                                        <input type="checkbox" id="checkSemuaBelum" class="w-4 h-4 text-amber-600 rounded border-gray-300">
                                    </th>
                                @endif
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIS</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas Sekarang</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sejak</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Penyebab</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($belumDiproses as $b)
                                <tr class="hover:bg-amber-50/40">
                                    @if ($bisaTandai)
                                        <td class="px-6 py-3 text-center">
                                            <input type="checkbox" name="nis[]" value="{{ $b->nis }}" class="cek-belum w-4 h-4 text-amber-600 rounded border-gray-300">
                                        </td>
                                    @endif
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">{{ $b->nis }}</td>
                                    <td class="px-6 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">{{ strtoupper($b->siswa->nama_siswa) }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $b->siswa->kelas?->nama_kelas ?? '-' }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap">
                                        {{ $b->tanggal_mulai ? $b->tanggal_mulai->format('d M Y') : '-' }}
                                        @if ($b->tahun_ajaran)
                                            <span class="block text-xs text-gray-500">TA {{ $b->tahun_ajaran }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-700">{{ $b->keterangan ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($bisaTandai)
                    <div class="px-6 py-4 border-t border-gray-200 bg-amber-50/50 space-y-2">
                        <p class="text-sm text-gray-700">
                            Kalau sudah diputuskan siswa memang tidak naik kelas, centang lalu tandai di bawah.
                            Siswa yang kelasnya dipindah / dinaikkan akan hilang dari daftar ini dengan sendirinya.
                        </p>
                        <div class="flex flex-col md:flex-row gap-2 md:items-center">
                            <select name="alasan_pilih" id="alasanTandaiPilih" class="md:w-80 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                <option value="">-- Pilih alasan tidak naik kelas --</option>
                                @foreach ($alasanPreset as $opsiAlasan)
                                    <option value="{{ $opsiAlasan }}" {{ old('alasan_pilih') === $opsiAlasan ? 'selected' : '' }}>{{ $opsiAlasan }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="alasan_teks" id="alasanTandaiTeks" maxlength="255" value="{{ old('alasan_teks') }}" placeholder="Tulis alasan"
                                   class="hidden md:w-72 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <button type="submit" id="btnTandaiMengulang"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                                <i class="bi bi-arrow-repeat"></i> Tandai Tidak Naik Kelas
                            </button>
                        </div>
                        <p id="errTandai" class="hidden text-xs font-medium text-red-700"></p>
                    </div>
                @endif
            </form>
        @endif
    </div>

    {{-- Data Siswa --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Data Siswa</h3>
                    <p class="text-sm text-gray-500">{{ $siswaList->count() }} siswa aktif. Centang siswa yang akan diproses.</p>
                </div>
                <div class="relative w-full md:w-72">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input id="inputSearchTahun" type="text" placeholder="Cari NIS atau Nama Siswa..."
                        class="w-full rounded-xl border-2 border-gray-200 bg-white pl-10 pr-4 py-3 text-sm font-medium text-gray-800 transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50">
            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                @if (auth()->user()->role == 1 || auth()->user()->role == 2)
                    <div class="w-full md:w-72">
                        <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2 mb-1.5">
                            <i class="bi bi-buildings text-gray-500"></i> Program Keahlian
                        </label>
                        <select id="filterJurusanTahun"
                                class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                            <option value="">-- Semua Program Keahlian --</option>
                            @foreach ($jurusanList as $jurusan)
                                <option value="{{ $jurusan->id_jurusan }}">
                                    {{ $jurusan->label_dropdown }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="w-full md:w-72">
                    <label class="block text-sm font-semibold text-gray-700 flex items-center gap-2 mb-1.5">
                        <i class="bi bi-collection text-gray-500"></i> Kelas
                    </label>
                    <select id="filterKelasTahun"
                            class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                        <option value="">-- Semua Kelas --</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}" data-jurusan="{{ $kelas->id_jurusan }}">
                                {{ $kelas->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-16">No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIS</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kelas & Rencana</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-32">
                            <div class="flex items-center justify-center gap-2">
                                <input type="checkbox" id="checkSemuaSiswa" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                <label for="checkSemuaSiswa" class="cursor-pointer">Pilih Semua</label>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody id="tahunTableBody" class="bg-white divide-y divide-gray-100">
                    @forelse ($siswaList as $item)
                        @php
                            $tingkatSiswa = \App\Models\kelas::tingkatDariId($item->id_kelas);
                            $rencanaSiswa = in_array($tingkatSiswa, ['10', '11', '12'], true) ? ($rencanaMap[(string) $item->nis] ?? null) : null;

                            // Rencana Ketua Program yang SAH (dipakai JS untuk memperkirakan siapa yang tidak naik kelas).
                            // Kelas 10 -> kelas 11 yang ada; kelas 12 -> ALUMNI. Kelas 11 ditentukan centang, bukan rencana.
                            $rencanaSah = false;
                            if ($rencanaSiswa) {
                                $tujuanRencana = (string) $rencanaSiswa->id_kelas_tujuan;
                                $rencanaSah = ($tingkatSiswa === '10' && $rencanaSiswa->kelasTujuan && \App\Models\kelas::tingkatDariId($tujuanRencana) === '11')
                                    || ($tingkatSiswa === '12' && $tujuanRencana === 'ALUMNI');
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 group transition-colors"
                            data-nis="{{ $item->nis }}"
                            data-nama="{{ strtolower($item->nama_siswa) }}"
                            data-kelas="{{ $item->id_kelas ?? '' }}"
                            data-jurusan="{{ $item->id_jurusan ?? '' }}"
                            data-tingkat="{{ $tingkatSiswa ?? '' }}"
                            data-rencana-sah="{{ $rencanaSah ? '1' : '0' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900 nomor-urut">{{ $loop->iteration }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">{{ $item->nis }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ strtoupper($item->nama_siswa) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $item->kelas?->nama_kelas ?? '-' }}</div>
                                @php $penanda = $penandaMap[(string) $item->nis] ?? null; @endphp
                                @if ($penanda && $penanda->jenis === 'belum_diproses')
                                    <span class="text-xs text-amber-800 bg-amber-100 border border-amber-200 rounded px-2 py-0.5 mt-1 inline-flex items-center gap-1" title="{{ $penanda->keterangan }}">
                                        <i class="bi bi-hourglass-split"></i> Belum diproses{{ $penanda->tahun_ajaran ? ' · TA ' . $penanda->tahun_ajaran : '' }}
                                    </span>
                                @elseif ($penanda && $penanda->jenis === 'tinggal_kelas')
                                    <span class="text-xs text-red-800 bg-red-100 border border-red-200 rounded px-2 py-0.5 mt-1 inline-flex items-center gap-1" title="{{ $penanda->alasan }}">
                                        <i class="bi bi-arrow-repeat"></i> Mengulang{{ $penanda->tahun_ajaran ? ' · TA ' . $penanda->tahun_ajaran : '' }}
                                    </span>
                                @endif
                                @if ($tingkatSiswa === '10')
                                    @if ($rencanaSiswa)
                                        <div class="text-xs text-emerald-700 mt-1 font-medium">
                                            <i class="bi bi-arrow-right-circle"></i> Rencana: {{ $rencanaSiswa->kelasTujuan?->nama_kelas ?? $rencanaSiswa->id_kelas_tujuan }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1 italic">Belum direncanakan, naik ke kelas pasangan bila dicentang</div>
                                    @endif
                                    <span class="badge-kaprog hidden text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 mt-1.5 inline-block">
                                        Mengikuti rencana Ketua Program
                                    </span>
                                    <span class="badge-tidak-naik hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-0.5 mt-1.5 inline-block">
                                        <i class="bi bi-arrow-repeat"></i> Tidak naik kelas (mengulang)
                                    </span>
                                @elseif ($tingkatSiswa === '12')
                                    @if (($rencanaSiswa?->id_kelas_tujuan) === 'ALUMNI')
                                        <div class="text-xs text-emerald-700 mt-1 font-medium">
                                            <i class="bi bi-arrow-right-circle"></i> Rencana: Alumni (lulus)
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1 italic">Belum ditandai lulus Ketua Program (tetap lulus bila dicentang)</div>
                                    @endif
                                    <span class="badge-kaprog hidden text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 mt-1.5 inline-block">
                                        Mengikuti rencana Ketua Program
                                    </span>
                                    <span class="badge-tidak-naik hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-0.5 mt-1.5 inline-block">
                                        <i class="bi bi-arrow-repeat"></i> Tidak naik kelas (mengulang)
                                    </span>
                                @elseif ($tingkatSiswa === '11')
                                    <span class="badge-tidak-naik hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-0.5 mb-1 inline-block">
                                        <i class="bi bi-arrow-repeat"></i> Tidak naik kelas (mengulang)
                                    </span>
                                    @if ($rencanaSiswa)
                                        <div class="text-xs text-emerald-700 mt-1 font-medium">
                                            <i class="bi bi-arrow-right-circle"></i> Rencana: {{ $rencanaSiswa->kelasTujuan?->nama_kelas ?? $rencanaSiswa->id_kelas_tujuan }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-1 italic">Belum direncanakan, naik ke kelas pasangan</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <input type="checkbox" class="siswa-check w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" data-nis="{{ $item->nis }}">
                            </td>
                        </tr>
                    @empty
                        <tr id="barisKosong">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="bi bi-people text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada siswa aktif</h3>
                                <p class="text-gray-500">Tambahkan data siswa untuk memulai proses kenaikan kelas.</p>
                            </td>
                        </tr>
                    @endforelse

                    <tr id="barisTidakDitemukan" style="display:none">
                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                            <i class="bi bi-search text-2xl mb-2 block"></i>
                            Tidak ada siswa yang cocok dengan filter pencarian.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Konfirmasi --}}
<div id="modalKonfirmasi"
     class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-gray-100 flex items-center gap-3 shrink-0">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                <i class="bi bi-shield-check text-xl"></i>
            </div>
            <h3 class="font-semibold text-lg text-gray-900">Konfirmasi Ganti Tahun Ajaran</h3>
        </div>
        
        <div class="p-5 text-sm space-y-4 overflow-y-auto custom-scrollbar">
            <div>
                <p class="font-semibold text-gray-700 mb-1">Perubahan tingkat kelas:</p>
                <p class="text-blue-700 font-medium" id="modalArah"></p>
                <ul id="modalPerubahan" class="mt-2 list-disc list-inside text-gray-600 space-y-1 text-sm"></ul>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-700 mb-1">Siswa yang diproses:</p>
                <ul id="modalSiswa" class="mt-2 list-disc list-inside text-gray-600 space-y-1 text-sm"></ul>
            </div>

            <div id="modalKaprogWrapper" class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-700 mb-1">Rencana Ketua Program:</p>
                <ul id="modalKaprog" class="mt-2 list-disc list-inside text-gray-600 space-y-1 text-sm"></ul>
            </div>

            {{-- Siswa yang tetap di kelasnya dipisah dua: TIDAK NAIK (alasan wajib) vs BELUM DIPROSES (tanpa alasan). --}}
            <div id="modalTidakNaikWrapper" class="hidden border border-red-200 bg-red-50 rounded-xl p-4 mt-2">
                <p class="font-semibold text-red-800 flex items-center gap-2">
                    <i class="bi bi-arrow-repeat"></i>
                    Tidak naik kelas / mengulang (<span id="jumlahTidakNaik">0</span>)
                </p>
                <p class="text-xs text-red-700 mt-1">
                    Siswa kelas 10 / 11 / 12 yang tidak dicentang. Mereka tetap di kelasnya dan dicatat sebagai mengulang. Alasan wajib diisi untuk setiap siswa.
                </p>

                <div class="mt-3 flex flex-col sm:flex-row gap-2">
                    <select id="alasanMassalPilih" class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-red-100 focus:border-red-300">
                        <option value="">-- Alasan untuk yang masih kosong --</option>
                        @foreach ($alasanPreset as $opsiAlasan)
                            <option value="{{ $opsiAlasan }}">{{ $opsiAlasan }}</option>
                        @endforeach
                    </select>
                    <input type="text" id="alasanMassalTeks" maxlength="255" placeholder="Tulis alasan" class="hidden flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-100 focus:border-red-300">
                    <button type="button" id="btnAlasanMassal" class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100 transition">
                        Terapkan ke yang kosong
                    </button>
                </div>

                <div id="daftarTidakNaik" class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-red-200 bg-white divide-y divide-gray-100 custom-scrollbar"></div>
            </div>

            <div id="modalBelumWrapper" class="hidden border border-amber-200 bg-amber-50 rounded-xl p-4">
                <p class="font-semibold text-amber-800 flex items-center gap-2">
                    <i class="bi bi-hourglass-split"></i>
                    Belum diproses (<span id="jumlahBelum">0</span>)
                </p>
                <p class="text-xs text-amber-800 mt-1">
                    Siswa yang kelasnya tidak dikenali sistem. Mereka tetap di kelasnya dan
                    ditandai <strong>Belum diproses</strong> (bukan mengulang). Pilih alasan hanya bila memang diputuskan tidak naik kelas.
                </p>

                <div id="daftarBelum" class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-amber-200 bg-white divide-y divide-gray-100 custom-scrollbar"></div>
            </div>

            <p id="errTidakNaik" class="hidden text-xs font-medium text-red-700"></p>

            <div class="bg-green-50 border border-green-200 rounded-lg p-3 flex gap-2">
                <i class="bi bi-file-earmark-excel text-green-600 mt-0.5"></i>
                <p class="text-green-800 text-xs leading-relaxed">
                    <strong>Backup otomatis (file Excel)</strong> dibuat sebelum data diubah. Jika backup gagal, tahun ajaran tidak akan diganti.
                </p>
            </div>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 flex gap-2">
                <i class="bi bi-exclamation-triangle text-red-600 mt-0.5"></i>
                <p class="text-red-800 text-xs leading-relaxed">
                    Proses ini <strong>tidak bisa dibatalkan</strong> dari aplikasi. Pemulihan data lewat backup hanya dapat dilakukan oleh administrator melalui terminal.
                </p>
            </div>
        </div>
        
        <div class="p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex justify-end gap-3 shrink-0">
            <button type="button" id="btnBatal"
                    class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-medium text-sm hover:bg-gray-100 transition">
                Batal
            </button>
            <button type="button" id="btnConfirm"
                    class="px-4 py-2.5 rounded-xl bg-blue-600 text-white font-medium text-sm hover:bg-blue-700 shadow-md shadow-blue-200 transition flex items-center gap-2">
                <span>Ya, Ganti Tahun Ajaran</span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    const TINGKAT = ['10', '11', '12'];

    // Data dari Controller
    const JUMLAH_KELAS10       = {{ (int) $preview['kelas10_ke_11'] }};
    const JUMLAH_DIRENCANAKAN  = {{ (int) $preview['kelas10_direncanakan'] }};
    const JUMLAH_KELAS12       = {{ (int) $preview['lulus'] }};
    const JUMLAH_LULUS_RENCANA = {{ (int) $preview['lulus_direncanakan'] }};
    const activeId             = {{ $tahunAktif?->id ?? 'null' }};
    
    // Syarat naik tahun: semua Ketua Program sudah selesai merencanakan (dicek ulang di server).
    const PERENCANAAN_LENGKAP = {{ $perencanaanLengkap ? 'true' : 'false' }};

    // Elemen DOM
    const form                 = document.getElementById('formTahunAjaran');
    const select               = document.getElementById('selectTahun');
    const hint                 = document.getElementById('hintSelisih');
    const hintTahan            = document.getElementById('hintTahan');
    const infoDipilih          = document.getElementById('infoDipilih');
    const nisInput             = document.getElementById('siswaNisInput');
    const tanpaSiswaInput      = document.getElementById('tanpaSiswaInput');
    const alasanInput          = document.getElementById('alasanTidakNaikInput');
    const btnSubmit            = document.getElementById('btnSubmitTahun');

    const modal                = document.getElementById('modalKonfirmasi');
    const modalArah            = document.getElementById('modalArah');
    const modalPerubahan       = document.getElementById('modalPerubahan');
    const modalSiswa           = document.getElementById('modalSiswa');
    const modalKaprogWrapper   = document.getElementById('modalKaprogWrapper');
    const modalKaprog          = document.getElementById('modalKaprog');
    const modalTidakNaikWrapper = document.getElementById('modalTidakNaikWrapper');
    const daftarTidakNaik       = document.getElementById('daftarTidakNaik');
    const modalBelumWrapper     = document.getElementById('modalBelumWrapper');
    const daftarBelum           = document.getElementById('daftarBelum');
    const jumlahBelum           = document.getElementById('jumlahBelum');
    const jumlahTidakNaik       = document.getElementById('jumlahTidakNaik');
    const errTidakNaik          = document.getElementById('errTidakNaik');
    const alasanMassalPilih     = document.getElementById('alasanMassalPilih');
    const alasanMassalTeks      = document.getElementById('alasanMassalTeks');
    const btnAlasanMassal       = document.getElementById('btnAlasanMassal');
    const btnConfirm           = document.getElementById('btnConfirm');
    const btnBatal             = document.getElementById('btnBatal');

    const tableBody            = document.getElementById('tahunTableBody');
    const inputSearch          = document.getElementById('inputSearchTahun');
    const filterJurusan        = document.getElementById('filterJurusanTahun');
    const filterKelas          = document.getElementById('filterKelasTahun');
    const checkSemua           = document.getElementById('checkSemuaSiswa');
    const barisTidakAda        = document.getElementById('barisTidakDitemukan');

    const ALASAN_LAINNYA  = @json(\App\Models\StudentClassHistory::ALASAN_LAINNYA ?? 'Lainnya');
    const ALASAN_PRESET   = @json($alasanPreset);

    function updateTombol() {
        const tahan  = !!activeId && selisihTerpilih() > 0 && !PERENCANAAN_LENGKAP;
        const mundur = !!activeId && selisihTerpilih() < 0;

        btnSubmit.disabled = tahan || mundur;
        btnSubmit.title    = mundur
            ? 'Tahun ajaran terkunci: tidak bisa mundur'
            : (tahan ? 'Semua Ketua Program harus selesai merencanakan dulu' : '');
        if (hintTahan) hintTahan.classList.toggle('hidden', !tahan);
    }

    /* ---------- Ringkasan Perubahan ---------- */
    function hitungPerubahan(selisih) {
        return TINGKAT.map((t, idx) => {
            const target = idx + selisih;

            if (selisih > 0) {
                const tujuan  = target >= TINGKAT.length ? 'Alumni' : TINGKAT[target];
                const rencana = selisih === 1 && (t === '10' || t === '11') ? ' (kelas rencana Kaprog bila ada)' : '';

                return `${t} → ${tujuan}${rencana}, hanya yang dicentang`;
            }

            const tujuan = Math.max(0, target);
            return tujuan === idx ? `${t} tetap` : `${t} → ${TINGKAT[tujuan]}`;
        });
    }

    function isiDaftar(el, items) {
        el.innerHTML = '';
        items.forEach(function (teks) {
            const li = document.createElement('li');
            li.textContent = teks;
            el.appendChild(li);
        });
    }

    function selisihTerpilih() {
        const opt = select.options[select.selectedIndex];
        return parseInt(opt?.dataset.selisih ?? '0', 10) || 0;
    }

    function updateHint() {
        const selisih = selisihTerpilih();

        if (!activeId || selisih === 0) {
            hint.textContent = '';
            hint.className = 'text-sm mt-4';
            return;
        }

        if (selisih > 0) {
            hint.textContent = `⬆ Naik ${selisih} tingkat: ${hitungPerubahan(selisih).join(', ')}`;
            hint.className = 'text-sm text-green-700 mt-4 font-medium';
        } else {
            hint.textContent = '🔒 Tahun ajaran terkunci: tidak bisa mundur. Kalau ganti tahun salah, minta administrator memulihkan backup.';
            hint.className = 'text-sm text-red-700 mt-4 font-medium';
        }
    }

    /* ---------- Kelas 10, 11, 12 sama: dicentang = naik, tidak dicentang = tidak naik ---------- */
    // Nama fungsi dipertahankan (dipanggil dari beberapa tempat). Kelas 10 / 12 TIDAK lagi dikunci.
    function kunciKelas10() {
        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            const cb = row.querySelector('input.siswa-check');
            if (cb) cb.disabled = false;
            row.classList.remove('opacity-60');
        });

        syncSiswaNisInput();
        syncMasterCheck();
    }

    /* ---------- Pemilihan Siswa ---------- */
    function getSelectedSiswaNis() {
        return Array.from(tableBody.querySelectorAll('input.siswa-check:checked:not(:disabled)'))
            .map(cb => cb.dataset.nis)
            .filter(Boolean);
    }

    // Tanda langsung di baris saat naik tahun: "Tidak naik kelas" bila tidak dicentang (kelas 10 / 11 / 12),
    // dan "Mengikuti rencana Ketua Program" bila dicentang dan rencananya sah (kelas 10 / 12).
    function updateBadgeTidakNaik() {
        const naik = !!activeId && selisihTerpilih() > 0;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            const cb = row.querySelector('input.siswa-check');

            const badge = row.querySelector('.badge-tidak-naik');
            if (badge) badge.classList.toggle('hidden', !(naik && cb && !cb.checked));

            const badgeKaprog = row.querySelector('.badge-kaprog');
            if (badgeKaprog) {
                badgeKaprog.classList.toggle('hidden', !(naik && cb && cb.checked && row.dataset.rencanaSah === '1'));
            }
        });
    }

    function syncSiswaNisInput() {
        updateBadgeTidakNaik();
        const daftar = getSelectedSiswaNis();
        nisInput.value = daftar.join(',');
        infoDipilih.textContent = daftar.length > 0
            ? `<span class="font-semibold text-blue-700">${daftar.length}</span> siswa dipilih.`
            : '<span class="text-gray-400">Belum ada siswa yang dipilih.</span>';
        return daftar;
    }

    function barisTampak() {
        return Array.from(tableBody.querySelectorAll('tr[data-nis]')).filter(row => row.style.display !== 'none');
    }

    function kotakAktifTampak() {
        return barisTampak()
            .map(row => row.querySelector('input.siswa-check'))
            .filter(cb => cb && !cb.disabled);
    }

    function syncMasterCheck() {
        const kotak = kotakAktifTampak();
        const jumlahCentang = kotak.filter(cb => cb.checked).length;
        
        checkSemua.checked = kotak.length > 0 && jumlahCentang === kotak.length;
        checkSemua.indeterminate = jumlahCentang > 0 && jumlahCentang < kotak.length;
    }

    function toggleSemuaSiswa(checked) {
        kotakAktifTampak().forEach(cb => {
            cb.checked = checked;
        });
        syncSiswaNisInput();
        syncMasterCheck();
    }

    /* ---------- Filter ---------- */
    function applyFilter() {
        const query = (inputSearch?.value || '').trim().toLowerCase();
        const jurusan = filterJurusan?.value || '';
        const kelas = filterKelas?.value || '';
        let no = 0;

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            let tampil = true;

            if (jurusan && String(row.dataset.jurusan || '') !== String(jurusan)) tampil = false;
            if (kelas && String(row.dataset.kelas || '') !== String(kelas)) tampil = false;

            if (query) {
                const nis = String(row.dataset.nis || '').toLowerCase();
                const nama = String(row.dataset.nama || '').toLowerCase();
                if (!nis.includes(query) && !nama.includes(query)) tampil = false;
            }

            if (tampil) {
                no++;
                const nomor = row.querySelector('.nomor-urut');
                if (nomor) nomor.textContent = no;
            }

            row.style.display = tampil ? '' : 'none';
        });

        const adaBaris = tableBody.querySelector('tr[data-nis]') !== null;
        if (barisTidakAda) {
            barisTidakAda.style.display = (adaBaris && no === 0) ? '' : 'none';
        }

        syncMasterCheck();
    }

    function filterKelasByJurusan() {
        if (!filterJurusan || !filterKelas) return;

        const terpilih = String(filterJurusan.value).trim();

        Array.from(filterKelas.options).forEach(opt => {
            if (!opt.value) return;

            const optJurusan = String(opt.getAttribute('data-jurusan') || '').trim();
            const cocok = !terpilih || optJurusan === terpilih;

            opt.hidden = !cocok;
            opt.disabled = !cocok;
        });

        const current = filterKelas.options[filterKelas.selectedIndex];
        if (current && current.value && current.hidden) {
            filterKelas.value = '';
        }
    }

    /* ---------- Modal & Submit ---------- */
    function bukaModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function tutupModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function poinKaprog(selisih) {
        if (selisih <= 0) return [];
        if (selisih > 1) {
            return ['Naik >1 tahun: rencana Kaprog tidak dipakai. Siswa yang dicentang naik sejauh selisih tahun lewat kelas pasangan, yang tidak dicentang tetap.'];
        }

        return [
            `Kelas 10 dicentang → kelas 11 sesuai rencana Kaprog (${JUMLAH_DIRENCANAKAN} dari ${JUMLAH_KELAS10} sudah direncanakan)`,
            'Kelas 11 dicentang → kelas 12 sesuai rencana Kaprog bila ada, kalau tidak ke kelas pasangan',
            'Kelas 12 dicentang → Alumni',
            'Yang tidak dicentang tetap di kelasnya dan wajib diberi alasan; rencana Kaprog-nya dibuang',
        ];
    }

    /* ---------- Siswa tidak naik kelas + alasan ---------- */

    // [nis => {pilih, teks}] disimpan supaya isian tidak hilang saat modal dibuka ulang.
    const alasanMap = {};

    // Pulihkan alasan setelah redirect karena error (old input).
    try {
        const lama = JSON.parse(alasanInput.value || '{}');
        Object.keys(lama || {}).forEach(nis => {
            const teks = String(lama[nis] || '');
            alasanMap[nis] = ALASAN_PRESET.includes(teks) && teks !== ALASAN_LAINNYA
                ? {pilih: teks, teks: ''}
                : {pilih: ALASAN_LAINNYA, teks: teks};
        });
    } catch (e) { /* abaikan */ }

    function alasanAkhir(nis) {
        const a = alasanMap[nis];
        if (!a || !a.pilih) return '';
        return (a.pilih === ALASAN_LAINNYA ? a.teks : a.pilih).trim();
    }

    // Tidak ada alasan otomatis: kelas 10 / 12 yang kosong alasannya dicatat "Belum diproses", bukan mengulang.
    function alasanBawaan() {
        return '';
    }

    // Perkiraan siswa yang tetap di kelasnya saat naik tahun. Server menghitung ulang dan menolak
    // bila ada yang tertinggal tanpa alasan.
    function daftarSiswaTidakNaik(selisih, dicentang) {
        if (selisih <= 0) return [];

        const terpilih = new Set(dicentang);
        const hasil = [];

        tableBody.querySelectorAll('tr[data-nis]').forEach(row => {
            if (!row.dataset.kelas) return; // tanpa kelas: tidak ikut proses

            const t   = row.dataset.tingkat || '';
            const bisaDicentang = t === '10' || t === '11' || t === '12';
            const tinggal = bisaDicentang ? !terpilih.has(row.dataset.nis) : true;

            if (!tinggal) return;

            const sel = row.querySelectorAll('td');
            hasil.push({
                nis: row.dataset.nis,
                nama: (sel[2]?.textContent || '').trim(),
                kelas: (sel[3]?.querySelector('div')?.textContent || '').trim(),
                tingkat: t,
                // Kelas 10 / 11 / 12 yang tidak dicentang = keputusan tidak naik (alasan wajib).
                // Kelas lain yang tidak dikenali = belum diproses (alasan hanya bila sengaja diputuskan).
                wajib: bisaDicentang,
            });
        });

        return hasil;
    }

    function buatOpsiAlasan(select, wajib) {
        const kosong = document.createElement('option');
        kosong.value = '';
        kosong.textContent = wajib ? '-- Pilih alasan --' : 'Belum diproses (tanpa alasan)';
        select.appendChild(kosong);

        ALASAN_PRESET.forEach(a => {
            const o = document.createElement('option');
            o.value = a;
            o.textContent = a;
            select.appendChild(o);
        });
    }

    function isiBaris(wadah, daftar) {
        wadah.innerHTML = '';

        daftar.forEach(function (s) {
            if (!alasanMap[s.nis]) {
                alasanMap[s.nis] = {pilih: alasanBawaan(), teks: ''};
            }

            const baris = document.createElement('div');
            baris.className = 'p-2 grid grid-cols-1 sm:grid-cols-5 gap-2 items-center';
            baris.dataset.nis = s.nis;

            const info = document.createElement('div');
            info.className = 'sm:col-span-2';
            const nama = document.createElement('p');
            nama.className = 'text-xs font-semibold text-gray-900';
            nama.textContent = s.nama;
            const kls = document.createElement('p');
            kls.className = 'text-xs text-gray-500';
            kls.textContent = `${s.nis} · ${s.kelas || '-'}`;
            info.append(nama, kls);

            const select = document.createElement('select');
            select.className = 'sm:col-span-3 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-100 focus:border-red-300';
            buatOpsiAlasan(select, s.wajib);
            select.value = alasanMap[s.nis].pilih || '';

            const teks = document.createElement('input');
            teks.type = 'text';
            teks.maxLength = 255;
            teks.placeholder = 'Tulis alasan';
            teks.className = 'sm:col-span-3 sm:col-start-3 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-100 focus:border-red-300';
            teks.value = alasanMap[s.nis].teks || '';
            teks.classList.toggle('hidden', select.value !== ALASAN_LAINNYA);

            select.addEventListener('change', function () {
                alasanMap[s.nis].pilih = select.value;
                teks.classList.toggle('hidden', select.value !== ALASAN_LAINNYA);
                select.classList.remove('border-red-500');
            });
            teks.addEventListener('input', function () {
                alasanMap[s.nis].teks = teks.value;
                teks.classList.remove('border-red-500');
            });

            baris.append(info, select, teks);
            wadah.appendChild(baris);
        });
    }

    function gambarDaftarTidakNaik(daftar) {
        errTidakNaik.classList.add('hidden');

        const wajib = daftar.filter(s => s.wajib);
        const belum = daftar.filter(s => !s.wajib);

        isiBaris(daftarTidakNaik, wajib);
        isiBaris(daftarBelum, belum);

        jumlahTidakNaik.textContent = wajib.length;
        jumlahBelum.textContent     = belum.length;

        modalTidakNaikWrapper.classList.toggle('hidden', wajib.length === 0);
        modalBelumWrapper.classList.toggle('hidden', belum.length === 0);
    }

    alasanMassalPilih.addEventListener('change', function () {
        alasanMassalTeks.classList.toggle('hidden', alasanMassalPilih.value !== ALASAN_LAINNYA);
    });

    btnAlasanMassal.addEventListener('click', function () {
        const pilih = alasanMassalPilih.value;
        const teks  = alasanMassalTeks.value.trim();

        if (!pilih || (pilih === ALASAN_LAINNYA && !teks)) {
            errTidakNaik.textContent = 'Pilih atau tulis alasan dulu sebelum diterapkan.';
            errTidakNaik.classList.remove('hidden');
            return;
        }

        errTidakNaik.classList.add('hidden');

        // Hanya untuk yang masih kosong; alasan yang sudah dipilih tidak ditimpa.
        daftarTidakNaikSaatIni.filter(s => s.wajib).forEach(function (s) {
            if (!alasanAkhir(s.nis)) {
                alasanMap[s.nis] = {pilih: pilih, teks: pilih === ALASAN_LAINNYA ? teks : ''};
            }
        });

        gambarDaftarTidakNaik(daftarTidakNaikSaatIni);
    });

    // Mengembalikan {nis: alasan} untuk siswa yang mengulang, atau null bila ada isian yang belum lengkap.
    // Kelas 10 / 11 / 12 yang tidak naik wajib beralasan. Kelas lain (tidak dikenali) boleh kosong (= belum diproses),
    // tetapi bila memilih "Lainnya" teksnya harus diisi. Yang kosong tidak ikut dikirim.
    function kumpulkanAlasan() {
        let lengkap = true;
        const hasil = {};

        daftarTidakNaikSaatIni.forEach(function (s) {
            const alasan = alasanAkhir(s.nis);
            const baris  = modal.querySelector(`[data-nis="${s.nis}"]`);
            const lainnyaKosong = alasanMap[s.nis]?.pilih === ALASAN_LAINNYA && !alasan;

            if ((s.wajib && !alasan) || lainnyaKosong) {
                lengkap = false;
                baris?.querySelectorAll('select, input').forEach(el => el.classList.add('border-red-500'));
                return;
            }

            if (alasan) hasil[s.nis] = alasan;
        });

        return lengkap ? hasil : null;
    }

    let daftarTidakNaikSaatIni = [];

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const selisih = selisihTerpilih();
        const daftar  = syncSiswaNisInput();

        // Belum ada tahun aktif: cukup aktifkan tanpa kenaikan kelas.
        if (!activeId) {
            form.submit();
            return;
        }

        if (selisih < 0) {
            alert('Tahun ajaran terkunci: tidak bisa mundur.');
            return;
        }

        if (selisih === 0) {
            alert('Tahun ajaran ini sudah aktif.');
            return;
        }

        if (selisih > 0 && !PERENCANAAN_LENGKAP) {
            alert('Ganti tahun ajaran ditahan: semua Ketua Program harus selesai merencanakan dulu.');
            return;
        }

        const arah = selisih > 0
            ? `Naik ${selisih} tingkat`
            : `Turun ${Math.abs(selisih)} tingkat`;

        modalArah.textContent = arah;
        isiDaftar(modalPerubahan, hitungPerubahan(selisih));

        // Rincian siswa yang dicentang per tingkat, supaya jelas kelas 11 dan 12 ikut terkirim.
        const perTingkat = {'10': 0, '11': 0, '12': 0};
        daftar.forEach(nis => {
            const baris = tableBody.querySelector(`tr[data-nis="${nis}"]`);
            const t = baris ? baris.dataset.tingkat : '';
            if (perTingkat[t] !== undefined) perTingkat[t]++;
        });

        tanpaSiswaInput.value = daftar.length === 0 ? '1' : '0';

        if (daftar.length > 0) {
            isiDaftar(modalSiswa, [
                `<strong>${daftar.length}</strong> siswa dicentang untuk diproses`,
                `Kelas 10: ${perTingkat['10']} siswa`,
                `Kelas 11: ${perTingkat['11']} siswa`,
                `Kelas 12: ${perTingkat['12']} siswa`,
            ]);
        } else {
            isiDaftar(modalSiswa, selisih > 0
                ? ['<strong class="text-red-600">Peringatan:</strong> tidak ada siswa dicentang, semua siswa akan tetap di kelasnya (tidak naik)', 'Setiap siswa wajib diberi alasan di bawah']
                : ['<strong class="text-red-600">Peringatan:</strong> tidak ada siswa kelas 11 / 12 dicentang', 'Hanya tahun ajaran yang diganti']
            );
        }

        const kaprog = poinKaprog(selisih);
        isiDaftar(modalKaprog, kaprog);
        modalKaprogWrapper.classList.toggle('hidden', kaprog.length === 0);

        daftarTidakNaikSaatIni = daftarSiswaTidakNaik(selisih, daftar);
        gambarDaftarTidakNaik(daftarTidakNaikSaatIni);

        bukaModal();
    });

    btnConfirm.addEventListener('click', function () {
        // Hitung ulang daftar tepat sebelum dikirim, supaya selalu sesuai centang terakhir.
        const daftarAkhir = syncSiswaNisInput();
        tanpaSiswaInput.value = daftarAkhir.length === 0 ? '1' : '0';

        // Siswa tidak naik kelas wajib punya alasan sebelum dikirim.
        const alasanLengkap = kumpulkanAlasan();
        if (alasanLengkap === null) {
            errTidakNaik.textContent = 'Alasan wajib diisi untuk semua siswa yang tidak naik kelas (dan teks alasan "Lainnya" tidak boleh kosong).';
            errTidakNaik.classList.remove('hidden');
            return;
        }
        alasanInput.value = JSON.stringify(alasanLengkap);

        btnConfirm.disabled = true;
        btnSubmit.disabled  = true;
        btnConfirm.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Memproses...';
        form.submit();
    });

    btnBatal.addEventListener('click', tutupModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) tutupModal();
    });

    /* ---------- Event Listeners ---------- */
    select.addEventListener('change', function () {
        updateHint();
        updateTombol();
        kunciKelas10();
    });
    
    inputSearch?.addEventListener('input', applyFilter);
    filterKelas?.addEventListener('change', applyFilter);
    
    filterJurusan?.addEventListener('change', function () {
        filterKelasByJurusan();
        applyFilter();
    });
    
    checkSemua?.addEventListener('change', () => toggleSemuaSiswa(checkSemua.checked));

    tableBody.addEventListener('change', function (event) {
        if (event.target?.classList?.contains('siswa-check')) {
            syncSiswaNisInput();
            syncMasterCheck();
        }
    });

    /* ---------- Init ---------- */
    // Pulihkan centang setelah redirect karena error (old input).
    const sebelumnya = (nisInput.value || '').split(',').map(v => v.trim()).filter(Boolean);
    if (sebelumnya.length > 0) {
        tableBody.querySelectorAll('input.siswa-check').forEach(cb => {
            cb.checked = sebelumnya.includes(cb.dataset.nis);
        });
    }

    updateHint();
    updateTombol();
    filterKelasByJurusan();
    applyFilter();
    kunciKelas10();
    syncSiswaNisInput();
})();
</script>
@endpush

@push('js')
<script>
(function () {
    const form   = document.getElementById('formTandaiMengulang');
    if (!form) return;

    const pilih  = document.getElementById('alasanTandaiPilih');
    const teks   = document.getElementById('alasanTandaiTeks');
    const err    = document.getElementById('errTandai');
    const semua  = document.getElementById('checkSemuaBelum');
    const kotak  = () => Array.from(form.querySelectorAll('input.cek-belum'));

    function tampilTeks() {
        if (!pilih || !teks) return;
        teks.classList.toggle('hidden', pilih.value !== @json(\App\Models\StudentClassHistory::ALASAN_LAINNYA));
    }

    pilih?.addEventListener('change', tampilTeks);
    tampilTeks();

    semua?.addEventListener('change', function () {
        kotak().forEach(cb => cb.checked = semua.checked);
    });

    form.addEventListener('submit', function (e) {
        err.classList.add('hidden');

        const dicentang = kotak().filter(cb => cb.checked).length;
        let pesan = '';

        if (dicentang === 0) {
            pesan = 'Centang minimal satu siswa.';
        } else if (!pilih.value) {
            pesan = 'Pilih alasan tidak naik kelas.';
        } else if (pilih.value === @json(\App\Models\StudentClassHistory::ALASAN_LAINNYA) && !teks.value.trim()) {
            pesan = 'Tulis alasan terlebih dahulu.';
        } else if (!confirm(`Tandai ${dicentang} siswa sebagai tidak naik kelas (mengulang)?`)) {
            e.preventDefault();
            return;
        }

        if (pesan) {
            e.preventDefault();
            err.textContent = pesan;
            err.classList.remove('hidden');
        }
    });
})();
</script>
@endpush