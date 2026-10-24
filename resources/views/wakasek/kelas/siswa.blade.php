<!-- Modal Daftar Siswa Kelas -->
<div id="modal-siswa" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 flex flex-col max-h-[85vh]">

        <div class="flex justify-between items-start p-6 border-b border-gray-200">
            <div>
                <h2 id="siswa-judul" class="text-xl font-bold text-gray-700">Daftar Siswa</h2>
                <p id="siswa-sub" class="text-sm text-gray-500 mt-1"></p>
            </div>
            <button type="button" onclick="closeModal('modal-siswa')"
                class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>

        <div class="px-6 pt-4">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                <input type="text" id="siswa-cari" placeholder="Cari nama atau NIS..."
                    class="pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg w-full text-sm">
            </div>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
            <p id="siswa-status" class="hidden text-center text-sm text-gray-500 py-8"></p>

            <table id="siswa-tabel" class="hidden w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left w-14">No</th>
                        <th class="px-3 py-2 text-left w-40">NIS</th>
                        <th class="px-3 py-2 text-left">Nama Siswa</th>
                    </tr>
                </thead>
                <tbody id="siswa-isi"></tbody>
            </table>
        </div>

        <div class="flex justify-end p-4 border-t border-gray-200">
            <button type="button" onclick="closeModal('modal-siswa')"
                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">Tutup</button>
        </div>
    </div>
</div>

<script>
    (function() {
        let semuaSiswa = [];
        let permintaanTerakhir = 0;

        const $ = (id) => document.getElementById(id);

        function tampilStatus(teks, error) {
            const s = $('siswa-status');
            s.textContent = teks;
            s.classList.remove('hidden', 'text-red-600', 'text-gray-500');
            s.classList.add(error ? 'text-red-600' : 'text-gray-500');
            $('siswa-tabel').classList.add('hidden');
        }

        function sembunyiStatus() {
            $('siswa-status').classList.add('hidden');
            $('siswa-tabel').classList.remove('hidden');
        }

        function gambar(daftar) {
            const isi = $('siswa-isi');
            isi.innerHTML = '';

            if (daftar.length === 0) {
                tampilStatus(semuaSiswa.length === 0 ?
                    'Belum ada siswa aktif di kelas ini.' :
                    'Tidak ada siswa yang cocok dengan pencarian.');
                return;
            }

            sembunyiStatus();

            daftar.forEach((s, i) => {
                const tr = document.createElement('tr');
                tr.className = 'border-b border-gray-100 hover:bg-gray-50';

                [i + 1, s.nis, s.nama].forEach((teks, idx) => {
                    const td = document.createElement('td');
                    td.className = 'px-3 py-2' + (idx === 1 ? ' font-mono text-gray-600' : '');
                    td.textContent = teks;
                    tr.appendChild(td);
                });

                isi.appendChild(tr);
            });
        }

        // Dipanggil dari tombol "Jumlah Siswa" di tabel kelas.
        window.openSiswaModal = async function(id, nama) {
            const nomor = ++permintaanTerakhir;

            $('siswa-judul').textContent = 'Siswa ' + nama;
            $('siswa-sub').textContent = '';
            $('siswa-cari').value = '';
            $('siswa-isi').innerHTML = '';
            semuaSiswa = [];

            tampilStatus('Memuat data siswa...');
            document.getElementById('modal-siswa').classList.remove('hidden');

            try {
                const res = await fetch('/kelas/' + encodeURIComponent(id) + '/siswa', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) {
                    throw new Error(res.status === 403 ?
                        'Anda tidak punya akses ke kelas ini.' :
                        'Data siswa gagal dimuat (kode ' + res.status + ').');
                }

                const data = await res.json();

                // Abaikan hasil lama jika modal sudah dibuka untuk kelas lain.
                if (nomor !== permintaanTerakhir) return;

                semuaSiswa = data.siswa || [];
                $('siswa-sub').textContent = data.jumlah + ' siswa aktif · ' + data.jurusan;
                gambar(semuaSiswa);
            } catch (err) {
                if (nomor !== permintaanTerakhir) return;
                tampilStatus(err.message || 'Data siswa gagal dimuat.', true);
            }
        };

        // Cari nama / NIS di dalam popup (tanpa request baru).
        $('siswa-cari').addEventListener('input', function() {
            const kata = this.value.trim().toLowerCase();

            gambar(kata === '' ? semuaSiswa : semuaSiswa.filter(s =>
                s.nama.toLowerCase().includes(kata) || s.nis.includes(kata)
            ));
        });
    })();
</script>