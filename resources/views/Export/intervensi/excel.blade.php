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
                <td>'{{ $item->nis }}</td>
                <td>{{ $item->siswa->nama_siswa ?? '-' }}</td>
                <td>{{ \App\Support\KelasSaatKejadian::nama($item->nis, $item->created_at) }}</td>
                <td>{{ $item->nama_intervensi }}</td>
                <td>{{ $item->isi_intervensi }}</td>
                <td>{{ $item->status }}</td>
                <td>{{ optional($item->tanggal_Mulai_Perbaikan)->format('Y-m-d') ?? $item->tanggal_Mulai_Perbaikan }}</td>
                <td>{{ optional($item->tanggal_Selesai_Perbaikan)->format('Y-m-d') ?? $item->tanggal_Selesai_Perbaikan }}</td>
                <td>{{ $item->perubahan_setelah_intervensi ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>