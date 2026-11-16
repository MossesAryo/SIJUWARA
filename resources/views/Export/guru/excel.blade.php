<h2>Daftar Guru</h2>
<table>
    <thead>
        <tr>
            <th>NIP</th>
            <th>Nama Guru</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($guru as $item)
            <tr>
                <td>{{ $item->nip_guru }}</td>
                <td>{{ $item->nama_guru }}</td>
            </tr>
        @endforeach
    </tbody>
</table>