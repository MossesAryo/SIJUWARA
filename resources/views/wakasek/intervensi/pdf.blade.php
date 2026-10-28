<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Intervensi</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size:12px }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:6px 8px; border:1px solid #ddd; }
        th { background:#f4f6f8; }
    </style>
</head>
<body>
    <h3 style="text-align:center">Data Intervensi</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Nama Intervensi</th>
                <th>Isi Intervensi</th>
                <th>Status</th>
                <th>Tanggal Mulai</th>
                <th>Tanggal Selesai</th>
                <th>Perubahan Setelah Intervensi</th>
            </tr>
        </thead>
        <tbody>
            @php \App\Support\KelasSaatKejadian::muatDari($intervensi); @endphp
            @foreach($intervensi as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->nis }}</td>
                    <td>{{ $item->siswa->nama_siswa ?? '-' }}</td>
                    <td>{{ \App\Support\KelasSaatKejadian::nama($item->nis, $item->created_at) }}</td>
                    <td>{{ $item->nama_intervensi }}</td>
                    <td>{{ $item->isi_intervensi }}</td>
                    <td>{{ $item->status }}</td>
                    <td>{{ $item->tanggal_Mulai_Perbaikan }}</td>
                    <td>{{ $item->tanggal_Selesai_Perbaikan }}</td>
                    <td>{{ $item->perubahan_setelah_intervensi ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>