<!DOCTYPE html>
<html>
<head>
    <title>Data Ketua Program</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        h2 { text-align: center; }
    </style>
</head>
<body>
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
            @foreach($ketua_program as $item)
                <tr>
                    <td>{{ $item->nip_kaprog }}</td>
                    <td>{{ $item->nama_ketua_program }}</td>
                    <td>{{ $item->jurusan->label_dropdown }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>