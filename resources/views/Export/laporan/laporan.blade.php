<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan {{ $type === 'pelanggaran' ? 'Pelanggaran' : 'Penghargaan' }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { text-align: center; color: #1e40af; }
        .filter-info { margin-bottom: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h1>Laporan {{ $type === 'pelanggaran' ? 'Pelanggaran' : 'Penghargaan' }}</h1>
    <div class="filter-info">
        <p><strong>Kelas:</strong> {{ $kelas }}</p>
        <p><strong>{{ $labelKeahlian ?? 'Program / Kompetensi Keahlian' }}:</strong> {{ $jurusan }}</p>
        <p><strong>Periode:</strong>@if ($startDate == null && $endDate == null)
            Semua Periode
        @elseif ($endDate == null)
            {{ $startDate }} s/d Sekarang
        @else
          {{ $startDate }} s/d {{ $endDate }}     
        @endif </p>
    </div>
    <table>
        <thead>
            <tr>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>{{ $type === 'pelanggaran' ? 'Jenis Pelanggaran' : 'Jenis Penghargaan' }}</th>
                <th>Skor</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $item)
                <tr>
                    <td>{{ $item->siswa->nis ?? '-' }}</td>
                    <td>{{ $item->siswa->nama_siswa ?? '-' }}</td>
                    <td>{{ $item->siswa->kelas->nama_kelas ?? '-' }}</td>
                    <td>{{ $item->aspek_penilaian->kategori}}</td>
                    <td>{{ $item->aspek_penilaian->indikator_poin ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($item->tanggal_pelanggaran ?? $item->created_at)->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data ditemukan</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>