/**
 * Isi NIP dan nama otomatis dari guru yang dipilih di dropdown.
 * Dropdown ditandai data-guru-select, tujuan isiannya di data-nip-target / data-nama-target.
 */
function sinkronGuru(select) {
    const nipEl = document.getElementById(select.dataset.nipTarget);
    const namaEl = document.getElementById(select.dataset.namaTarget);
    const opsi = select.options[select.selectedIndex];

    if (nipEl) {
        nipEl.value = select.value || '';
    }
    if (namaEl) {
        namaEl.value = (opsi && opsi.dataset.nama) || '';
    }
}

document.addEventListener('change', function (event) {
    if (event.target.matches && event.target.matches('[data-guru-select]')) {
        sinkronGuru(event.target);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Form tambah bisa terbuka lagi setelah validasi gagal: isi ulang NIP & nama dari pilihan lama.
    document.querySelectorAll('[data-guru-select]').forEach(sinkronGuru);
});

function openEditModal(nip, nama) {
    // Guru yang sudah punya jabatan tidak bisa dipilih lagi, kecuali guru BK yang sedang diedit.
    const guru = document.getElementById('edit_nip_guru');
    Array.from(guru.options).forEach(opt => {
        opt.disabled = (opt.dataset.jabatan || '') !== '' && opt.value !== String(nip);
    });
    guru.value = String(nip);
    sinkronGuru(guru);

    document.getElementById('form-edit').action = `/gurubk/${nip}/update`;
    document.getElementById('modal-edit').classList.remove('hidden');
}

function openDeleteModal(nip, nama) {
    document.getElementById('delete-nama-guru').innerText = nama;
    document.getElementById('form-delete').action = `/gurubk/${nip}/destroy`;
    document.getElementById('modal-delete').classList.remove('hidden');
}


document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("inputSearch");
    const tableBody = document.getElementById("tableBody");
    const pagination = document.getElementById("pagination");

    let debounceTimer = null;

    let lastPageUrl = window.location.href;

    function fetchData(url) {
        fetch(url)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, "text/html");

                tableBody.innerHTML = doc.querySelector("#tableBody").innerHTML;
                pagination.innerHTML = doc.querySelector("#pagination").innerHTML;

                activatePaginationLinks();
            })
            .catch(err => console.error("ERR:", err));
    }

    function activatePaginationLinks() {
        const links = document.querySelectorAll("#pagination a");

        links.forEach(link => {
            link.addEventListener("click", function (e) {
                e.preventDefault();

                lastPageUrl = this.href;

                fetchData(this.href);
            });
        });
    }

    activatePaginationLinks();

    input.addEventListener("keyup", function () {
        clearTimeout(debounceTimer);

        debounceTimer = setTimeout(() => {
            const query = input.value.trim();

            if (query.length === 0) {
                fetchData(lastPageUrl);
                return;
            }

            const url = `/gurubk?search=${query}`;
            fetchData(url);

        }, 200);
    });
});