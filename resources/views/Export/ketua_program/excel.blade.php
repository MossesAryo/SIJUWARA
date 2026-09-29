<h2>Daftar Ketua Program</h2>
<table>
    <thead>
        <tr>
            <th>NIP</th>
            <th>Username</th>
            <th>Nama Ketua Program</th>
            <th>Program / Kompetensi Keahlian</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($ketua_program as $item)
            <tr>
                <td>
                    '{{ $item->nip_kaprog }}
                </td>
                <td>{{ $item->nama_ketua_program }}</td>
                <td>{{ $item->jurusan->label_dropdown }}</td>
            </tr>
        @endforeach
    </tbody>
</table>