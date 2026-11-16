/**
 * Halaman Data Guru (wakasek).
 * Dipakai oleh resources/views/wakasek/guru/index.blade.php
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
    document.getElementById('nip_guru').value = '';
    document.getElementById('nama_guru').value = '';

    // Buang pesan error sisa percobaan sebelumnya
    const errorBox = document.getElementById('create-errors');
    if (errorBox) {
        errorBox.remove();
    }

    openModal('modal-create');
}

/** Tombol edit membawa atribut data-nip, data-nama, data-kaprog. */
function openEditModal(btn) {
    const nip = btn.dataset.nip;
    const sudahKaprog = btn.dataset.kaprog === '1';
    const action = `/guru/${encodeURIComponent(nip)}/update`;

    const inputNip = document.getElementById('edit_nip_guru');
    inputNip.value = nip;
    inputNip.readOnly = sudahKaprog;
    inputNip.classList.toggle('bg-gray-50', sudahKaprog);
    document.getElementById('edit_nip_info').classList.toggle('hidden', !sudahKaprog);

    document.getElementById('edit_nama_guru').value = btn.dataset.nama;
    document.getElementById('form-edit').action = action;
    document.getElementById('edit_action').value = action;

    openModal('modal-edit');
}

/** Tombol hapus membawa atribut data-nip dan data-nama. */
function openDeleteModal(btn) {
    document.getElementById('delete-nama-guru').innerText = btn.dataset.nama;
    document.getElementById('form-delete').action = `/guru/${encodeURIComponent(btn.dataset.nip)}/destroy`;
    openModal('modal-delete');
}

const MODAL_IDS = ['modal-create', 'modal-edit', 'modal-delete'];

document.addEventListener('click', function (event) {
    MODAL_IDS.forEach(id => {
        const modal = document.getElementById(id);
        if (modal && !modal.classList.contains('hidden') && event.target === modal) {
            closeModal(id);
        }
    });
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        MODAL_IDS.forEach(id => {
            const modal = document.getElementById(id);
            if (modal && !modal.classList.contains('hidden')) {
                closeModal(id);
            }
        });
    }
});

/* ───── Modal Export / Import ───── */
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('exportImportModal');
    const exportTab = document.getElementById('exportTab');
    const importTab = document.getElementById('importTab');
    const exportContent = document.getElementById('exportContent');
    const importContent = document.getElementById('importContent');
    const processBtn = document.getElementById('processBtn');
    const dropZone = document.getElementById('dropZoneGuru');
    const fileInput = document.getElementById('importFileGuru');

    const aktif = 'tab-button px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600';
    const pasif = 'tab-button px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700';

    function switchTab(tab) {
        const export_ = tab === 'export';
        exportTab.className = export_ ? aktif : pasif;
        importTab.className = export_ ? pasif : aktif;
        exportContent.classList.toggle('hidden', !export_);
        importContent.classList.toggle('hidden', export_);
        if (export_) processBtn.classList.add('hidden');
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    window.removeFile = function () {
        fileInput.value = '';
        document.getElementById('selectedFile').classList.add('hidden');
        processBtn.classList.add('hidden');
    };

    function handleFileSelect() {
        const file = fileInput.files[0];
        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB.');
            fileInput.value = '';
            return;
        }

        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        document.getElementById('selectedFile').classList.remove('hidden');
        processBtn.classList.remove('hidden');
    }

    function tutup() {
        modal.classList.add('hidden');
        switchTab('export');
        window.removeFile();
    }

    document.getElementById('exportImportBtn').addEventListener('click', () => modal.classList.remove('hidden'));
    document.getElementById('closeExportImport').addEventListener('click', tutup);
    document.getElementById('cancelBtn').addEventListener('click', tutup);
    exportTab.addEventListener('click', () => switchTab('export'));
    importTab.addEventListener('click', () => switchTab('import'));

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('border-blue-400', 'bg-blue-50');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-blue-400', 'bg-blue-50'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        }
    });
    fileInput.addEventListener('change', handleFileSelect);

    /* ───── Pencarian + paginasi tanpa reload ───── */
    const input = document.getElementById('inputSearch');
    const tableBody = document.getElementById('tableBody');
    const pagination = document.getElementById('pagination');
    let debounceTimer = null;
    let lastPageUrl = window.location.href;

    function fetchData(url) {
        fetch(url)
            .then(res => res.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                tableBody.innerHTML = doc.querySelector('#tableBody').innerHTML;
                pagination.innerHTML = doc.querySelector('#pagination').innerHTML;
                activatePaginationLinks();
            })
            .catch(err => console.error('ERR:', err));
    }

    function activatePaginationLinks() {
        document.querySelectorAll('#pagination a').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                lastPageUrl = this.href;
                fetchData(this.href);
            });
        });
    }

    activatePaginationLinks();

    input.addEventListener('keyup', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = input.value.trim();
            if (query.length === 0) {
                fetchData(lastPageUrl);
                return;
            }
            fetchData(`/guru?search=${encodeURIComponent(query)}`);
        }, 200);
    });
});