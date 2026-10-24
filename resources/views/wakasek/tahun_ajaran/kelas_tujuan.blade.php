@php
    $kelasKurang = \App\Support\KelasTujuan::kurang();
    $bolehBuat   = in_array((int) auth()->user()->role, [1, 5], true);
@endphp

@if ($kelasKurang->isEmpty())
    <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded text-sm flex gap-2">
        <i class="bi bi-check-circle-fill mt-0.5"></i>
        <p>Semua kelas tujuan (10 → 11 dan 11 → 12) sudah tersedia. Tahun ajaran siap diganti.</p>
    </div>
@else
    <div class="bg-amber-50 border border-amber-300 text-amber-900 p-4 rounded-xl">
        <h3 class="font-semibold text-lg mb-1">
            <i class="bi bi-exclamation-triangle-fill"></i> Buat kelas tujuan dulu
        </h3>
        <p class="text-sm mb-3">
            {{ $kelasKurang->count() }} kelas tujuan belum ada. Tahun ajaran tidak bisa diganti sebelum
            kelas-kelas ini dibuat.
        </p>

        <ul class="grid sm:grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-1 text-sm mb-3">
            @foreach ($kelasKurang->take(12) as $k)
                <li>
                    <span class="font-medium">{{ $k['nama_kelas'] }}</span>
                    <span class="text-amber-700 text-xs">(dari {{ $k['dari'] }})</span>
                </li>
            @endforeach
        </ul>

        @if ($kelasKurang->count() > 12)
            <p class="text-xs text-amber-700 mb-3">dan {{ $kelasKurang->count() - 12 }} kelas lainnya.</p>
        @endif

        @if ($bolehBuat)
            <form method="POST" action="{{ route('tahun_ajaran.kelas_tujuan') }}"
                onsubmit="return confirm('Buat {{ $kelasKurang->count() }} kelas tujuan sekarang?')">
                @csrf
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700">
                    <i class="bi bi-plus-circle mr-1"></i> Buat {{ $kelasKurang->count() }} Kelas Tujuan
                </button>
            </form>
        @else
            <p class="text-xs text-amber-800">Minta Wakasek untuk membuat kelas tujuan di halaman ini.</p>
        @endif
    </div>
@endif