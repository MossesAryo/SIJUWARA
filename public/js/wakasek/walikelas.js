/**
 * Halaman Data Walikelas (wakasek).
 * Dipakai oleh resources/views/wakasek/walikelas/index.blade.php
 */

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.classList.add('modal-open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.classList.remove('modal-open');
}

function openCreateModal() {
    document.getElementById('nip_walikelas').value = '';
    document.getElementById('nama_walikelas').value = '';
    document.getElementById('id_kelas').value = '';

    // Buang pesan error sisa percobaan sebelumnya
    const errorBox = document.getElementById('create-errors');
    if (errorBox) {
        errorBox.remove();
    }

    openModal('modal-create');
}

function openFilterModal() {
    openModal('modal-filter');
}

/**
 * Bisa dipanggil dengan elemen tombol (memakai atribut data-*)
 * atau dengan 4 argumen: (nip, username, nama, id_kelas).
 */
function openEditModal(a, b, c, d) {
    let nip, username, nama, kelas;

    if (a && a.dataset) {
        nip = a.dataset.nip;
        username = a.dataset.username;
        nama = a.dataset.nama;
        kelas = a.dataset.kelas;
    } else {
        nip = a;
        username = b;
        nama = c;
        kelas = d;
    }

    document.getElementById('edit_nip_walikelas').value = nip;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_nama_walikelas').value = nama;
    document.getElementById('edit_id_kelas').value = kelas;
    document.getElementById('form-edit').action =
        `/walikelas/${encodeURIComponent(nip)}/${encodeURIComponent(username)}/update`;

    openModal('modal-edit');
}

/**
 * Bisa dipanggil dengan elemen tombol (data-nip, data-nama)
 * atau dengan 2 argumen: (nip, nama).
 */
function openDeleteModal(a, b) {
    let nip, nama;

    if (a && a.dataset) {
        nip = a.dataset.nip;
        nama = a.dataset.nama;
    } else {
        nip = a;
        nama = b;
    }

    document.getElementById('delete-nama-walikelas').innerText = nama;
    document.getElementById('form-delete').action = `/walikelas/${encodeURIComponent(nip)}`;
    openModal('modal-delete');
}

// Klik di luar modal menutup modal
document.addEventListener('click', function (event) {
    ['modal-create', 'modal-edit', 'modal-delete', 'modal-filter'].forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && !modal.classList.contains('hidden') && event.target === modal) {
            closeModal(modalId);
        }
    });
});

// Tombol Escape menutup modal
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        ['modal-create', 'modal-edit', 'modal-delete', 'modal-filter'].forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                closeModal(modalId);
            }
        });
    }
});

// Pencarian live + pagination tanpa reload
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('inputSearch');
    const tableBody = document.getElementById('tableBody');
    const pagination = document.getElementById('pagination');
    const filterSearch = document.getElementById('filterSearch');

    if (!input || !tableBody || !pagination) {
        return;
    }

    let debounceTimer = null;
    let controller = null;

    // Supaya tombol "Terapkan" di filter tidak menghapus kata kunci pencarian
    function sinkronFilter() {
        if (filterSearch) {
            filterSearch.value = input.value.trim();
        }
    }

    // URL halaman sekarang (filter kelas tetap terbawa), page dikembalikan ke 1
    function urlPencarian(keyword) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');

        if (keyword) {
            url.searchParams.set('search', keyword);
        } else {
            url.searchParams.delete('search');
        }

        return url;
    }

    function muatData(url) {
        // Batalkan request lama supaya hasil lama tidak menimpa hasil baru
        if (controller) {
            controller.abort();
        }
        const current = new AbortController();
        controller = current;

        tableBody.style.opacity = '0.5';

        fetch(url.toString(), {
            signal: current.signal,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newBody = doc.querySelector('#tableBody');
                const newPagination = doc.querySelector('#pagination');

                // Bukan halaman daftar (misalnya sesi habis dan dialihkan ke login)
                if (!newBody || !newPagination) {
                    window.location.href = url.toString();
                    return;
                }

                tableBody.innerHTML = newBody.innerHTML;
                pagination.innerHTML = newPagination.innerHTML;
                window.history.replaceState(null, '', url.toString());
                sinkronFilter();
            })
            .catch(err => {
                if (err.name === 'AbortError') {
                    return;
                }
                console.error('Gagal memuat data walikelas:', err);
                window.location.href = url.toString();
            })
            .finally(() => {
                if (controller === current) {
                    tableBody.style.opacity = '';
                }
            });
    }

    // Klik nomor halaman (delegasi, tetap jalan setelah isi pagination diganti)
    pagination.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || !link.href) {
            return;
        }

        event.preventDefault();
        muatData(new URL(link.href));
    });

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        sinkronFilter();

        debounceTimer = setTimeout(() => {
            muatData(urlPencarian(input.value.trim()));
        }, 300);
    });

    sinkronFilter();
});