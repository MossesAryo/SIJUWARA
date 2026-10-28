@extends('layouts.superadmin.app')

@section('title', 'Isi Backup - Super Admin')

@section('content')
    @php
        $badgeStatus = [
            'aktif'    => 'bg-green-50 text-green-700',
            'nonaktif' => 'bg-gray-100 text-gray-600',
            'alumni'   => 'bg-blue-50 text-blue-700',
        ];
        $ukuranTeks = $backup['ukuran'] >= 1048576
            ? round($backup['ukuran'] / 1048576, 2) . ' MB'
            : round($backup['ukuran'] / 1024, 1) . ' KB';
        $perintah = 'php artisan backup:pulihkan ' . $backup['nama'];
        $adaFilter = $q !== '' || $berbeda;
    @endphp

    <div class="mb-4">
        <a href="{{ route('superadmin.backup.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
            <i class="bi bi-arrow-left"></i> Kembali ke Data Backup
        </a>
    </div>

    {{-- Kepala backup --}}
    <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $backup['jenis']['warna'] }}">{{ $backup['jenis']['label'] }}</span>
                    @if ($backup['valid'])
                        <span class="rounded-lg bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"><i class="bi bi-check-circle"></i> Lengkap</span>
                    @else
                        <span class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"><i class="bi bi-exclamation-triangle"></i> Rusak</span>
                    @endif
                </div>
                <h2 class="text-lg font-bold text-gray-900">{{ $backup['alasan'] }}</h2>
                <p class="break-all font-mono text-xs text-gray-400">{{ $backup['nama'] }}</p>
            </div>
            <a href="{{ route('superadmin.backup.unduh', $backup['nama']) }}"
                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                <i class="bi bi-file-earmark-excel"></i> Unduh Excel
            </a>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm lg:grid-cols-4">
            <div>
                <dt class="text-xs text-gray-400">Dibuat</dt>
                <dd class="font-medium text-gray-800">{{ $backup['dibuat']->format('d/m/Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400">Database</dt>
                <dd class="font-medium text-gray-800">{{ $backup['database'] }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400">Ukuran file</dt>
                <dd class="font-medium text-gray-800">{{ $ukuranTeks }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-400">Jumlah baris</dt>
                <dd class="font-medium text-gray-800">{{ number_format(array_sum($backup['jumlah'])) }}</dd>
            </div>
        </dl>

        <div class="mt-4 flex flex-col gap-2 rounded-xl bg-gray-50 p-3 sm:flex-row sm:items-center">
            <span class="text-xs text-gray-500">Perintah pemulihan di server:</span>
            <code id="perintahPulih" class="flex-1 break-all font-mono text-xs text-gray-800">{{ $perintah }}</code>
            <button type="button" onclick="salinPerintah(this)"
                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100">
                <i class="bi bi-clipboard"></i> Salin
            </button>
        </div>

        @if ($backup['valid'])
            <form method="POST" action="{{ route('superadmin.backup.pulihkan', $backup['nama']) }}"
                onsubmit="return confirm('Pulihkan data dari backup ini? Keadaan sekarang diamankan dulu di backup pengaman.');"
                class="mt-3 rounded-xl border border-red-200 bg-red-50 p-4">
                @csrf
                <p class="text-sm font-semibold text-red-800"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan dari backup ini</p>
                <p class="mt-1 text-xs text-red-700">
                    Mengembalikan tahun ajaran aktif, kelas &amp; status siswa, kelas wali kelas, rencana Ketua Program, dan Riwayat Kelas
                    (riwayat yang dibuat setelah backup ini dihapus). Poin, penilaian, intervensi, dan catatan tidak diubah.
                    Backup pengaman dibuat otomatis sebelum data diubah.
                </p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="text" name="konfirmasi" autocomplete="off" placeholder="Ketik PULIHKAN"
                        class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm focus:border-red-400 focus:outline-none">
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Pulihkan sekarang
                    </button>
                </div>
                @error('konfirmasi')
                    <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </form>
        @endif
    </div>

    @if (!$backup['valid'])
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i class="bi bi-exclamation-triangle mt-0.5"></i>
            <span>
                File ini tidak lengkap atau tidak bisa dibaca sebagai backup Excel (sheet Info tidak berstatus SELESAI),
                kemungkinan gagal dibuat atau sudah diubah. Isinya tidak ditampilkan dan file ini tidak bisa dipulihkan.
            </span>
        </div>
    @else
        {{-- Tab tabel --}}
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($daftarTabel as $kode => $label)
                <a href="{{ route('superadmin.backup.show', ['nama' => $backup['nama'], 'tabel' => $kode]) }}"
                    class="rounded-xl border px-3 py-1.5 text-sm
                    {{ $tabel === $kode ? 'border-blue-200 bg-blue-50 font-medium text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                    <span class="ml-1 text-xs {{ $tabel === $kode ? 'text-blue-500' : 'text-gray-400' }}">{{ number_format($backup['jumlah'][$kode] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-gray-100 p-4 lg:flex-row lg:items-center">
                <form method="GET" action="{{ route('superadmin.backup.show', $backup['nama']) }}"
                    class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="hidden" name="tabel" value="{{ $tabel }}">
                    <div class="relative flex-1">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Cari NIS / NIP / nama / kelas"
                            class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none">
                    </div>
                    <label class="flex items-center gap-2 whitespace-nowrap text-sm text-gray-600">
                        <input type="checkbox" name="berbeda" value="1" @checked($berbeda)
                            onchange="this.form.submit()" class="rounded border-gray-300">
                        Hanya yang beda dengan sekarang
                    </label>
                    <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
                    @if ($adaFilter)
                        <a href="{{ route('superadmin.backup.show', ['nama' => $backup['nama'], 'tabel' => $tabel]) }}"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                    @endif
                </form>

                <span class="whitespace-nowrap text-xs text-gray-500">
                    {{ number_format($totalTabel) }} baris,
                    <span class="{{ $totalBerbeda > 0 ? 'font-semibold text-amber-600' : '' }}">{{ number_format($totalBerbeda) }} beda dengan sekarang</span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    @if ($tabel === 'siswa')
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">NIS</th>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3">Kelas (backup)</th>
                                <th class="px-4 py-3">Wali Kelas (backup)</th>
                                <th class="px-4 py-3">Status (backup)</th>
                                <th class="px-4 py-3">Kelas (sekarang)</th>
                                <th class="px-4 py-3">Status (sekarang)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($isi as $b)
                                <tr class="{{ $b['_berbeda'] ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 text-gray-500">{{ $isi->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $b['nis'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b['_nama'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['_kelas'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['_wali'] ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badgeStatus[$b['status']] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst((string) $b['status']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 {{ $b['_kelas'] !== $b['_kelas_sekarang'] ? 'font-medium text-amber-700' : 'text-gray-700' }}">
                                        {{ $b['_kelas_sekarang'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 {{ $b['_status_sekarang'] !== $b['status'] ? 'font-medium text-amber-700' : 'text-gray-700' }}">
                                        {{ $b['_status_sekarang'] ? ucfirst($b['_status_sekarang']) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Tidak ada data yang cocok.</td></tr>
                            @endforelse
                        </tbody>
                    @elseif ($tabel === 'walikelas')
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">NIP</th>
                                <th class="px-4 py-3">Nama Wali Kelas</th>
                                <th class="px-4 py-3">Kelas (backup)</th>
                                <th class="px-4 py-3">Kelas (sekarang)</th>
                                <th class="px-4 py-3">Diubah terakhir (backup)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($isi as $b)
                                <tr class="{{ $b['_berbeda'] ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 text-gray-500">{{ $isi->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $b['nip_walikelas'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b['_nama'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['_kelas'] ?? '-' }}</td>
                                    <td class="px-4 py-3 {{ $b['_berbeda'] ? 'font-medium text-amber-700' : 'text-gray-700' }}">{{ $b['_kelas_sekarang'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $b['updated_at'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Tidak ada data yang cocok.</td></tr>
                            @endforelse
                        </tbody>
                    @elseif ($tabel === 'tahun_ajaran')
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Tahun Ajaran</th>
                                <th class="px-4 py-3">Status (backup)</th>
                                <th class="px-4 py-3">Status (sekarang)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($isi as $b)
                                <tr class="{{ $b['_berbeda'] ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 text-gray-500">{{ $isi->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $b['id'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b['_label'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $badgeStatus[$b['status']] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst((string) $b['status']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 {{ $b['_berbeda'] ? 'font-medium text-amber-700' : 'text-gray-700' }}">
                                        {{ $b['_status_sekarang'] ? ucfirst($b['_status_sekarang']) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Tidak ada data yang cocok.</td></tr>
                            @endforelse
                        </tbody>
                    @elseif ($tabel === 'rencana_pindah_kelas')
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">NIS</th>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3">Kelas Tujuan</th>
                                <th class="px-4 py-3">Dibuat Oleh</th>
                                <th class="px-4 py-3">Dibuat</th>
                                <th class="px-4 py-3">Sekarang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($isi as $b)
                                <tr class="{{ $b['_berbeda'] ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 text-gray-500">{{ $isi->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $b['nis'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b['_nama'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['_tujuan'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $b['_pembuat'] ?? '-' }}
                                        @if (!empty($b['dibuat_oleh']) && ($b['_pembuat'] ?? null) !== $b['dibuat_oleh'])
                                            <span class="block font-mono text-xs text-gray-400">{{ $b['dibuat_oleh'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $b['created_at'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        @if ($b['_masih_ada'])
                                            <span class="text-gray-600">Masih ada</span>
                                        @else
                                            <span class="font-medium text-amber-700">Sudah dihapus</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                        {{ $adaFilter ? 'Tidak ada data yang cocok.' : 'Tidak ada rencana pindah kelas saat backup dibuat.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @else
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">NIS</th>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3">Dari Kelas</th>
                                <th class="px-4 py-3">Ke Kelas</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Tahun Ajaran</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Sekarang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($isi as $b)
                                <tr class="{{ $b['_berbeda'] ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 text-gray-500">{{ $isi->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $b['nis'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b['_nama'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['nama_kelas_sebelum'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $b['nama_kelas_sesudah'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ str_replace('_', ' ', (string) ($b['jenis'] ?? '-')) }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $b['tahun_ajaran'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $b['tanggal_mulai'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        @if ($b['_masih_ada'])
                                            <span class="text-gray-600">Masih ada</span>
                                        @else
                                            <span class="font-medium text-amber-700">Sudah dihapus</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                                        {{ $adaFilter ? 'Tidak ada data yang cocok.' : (array_key_exists('student_class_histories', $backup['jumlah']) ? 'Belum ada riwayat kelas saat backup dibuat.' : 'Backup ini dibuat sebelum sheet Riwayat Kelas ada.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>

            @if ($isi->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $isi->links() }}</div>
            @endif
        </div>

        <p class="mt-3 text-xs text-gray-400">
            Baris berwarna kuning berarti nilainya sekarang berbeda dari isi backup; itu yang akan dikembalikan bila backup dipulihkan.
        </p>
    @endif
@endsection

@push('js')
    <script>
        function salinPerintah(tombol) {
            const teks = document.getElementById('perintahPulih').innerText.trim();
            const selesai = () => {
                const awal = tombol.innerHTML;
                tombol.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
                setTimeout(() => tombol.innerHTML = awal, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(teks).then(selesai);
                return;
            }

            const t = document.createElement('textarea');
            t.value = teks;
            document.body.appendChild(t);
            t.select();
            document.execCommand('copy');
            document.body.removeChild(t);
            selesai();
        }
    </script>
@endpush