function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.body.classList.add('modal-open');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        function openCreateModal() {
            
            openModal('modal-create');
        }

        function openFilterModal() { 
       openModal('modal-filter'); }

        /** Tombol edit membawa atribut data-nip dan data-jurusan. */
        function openEditModal(btn) {
            const nip = btn.dataset.nip;
            const action = `/kaprog/${encodeURIComponent(nip)}/update`;
            const select = document.getElementById('edit_nip_guru');

            // Guru yang sudah jadi ketua program tidak bisa dipilih lagi, kecuali guru yang sedang diedit.
            Array.from(select.options).forEach(opt => {
                opt.disabled = opt.dataset.kaprog === '1' && opt.value !== String(nip);
            });

            select.value = nip;
            document.getElementById('edit_jurusan').value = btn.dataset.jurusan;
            document.getElementById('form-edit').action = action;
            document.getElementById('edit_action').value = action;
            openModal('modal-edit');
        }


        /** Tombol hapus membawa atribut data-nip dan data-nama. */
        function openDeleteModal(btn) {
            document.getElementById('delete-nama-ketua').innerText = btn.dataset.nama;
            document.getElementById('form-delete').action = `/kaprog/${encodeURIComponent(btn.dataset.nip)}`;
            openModal('modal-delete');
        }


        document.addEventListener('click', function(event) {
            ['modal-create', 'modal-edit', 'modal-delete', 'modal-filter'].forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden') && event.target === modal) {
                    closeModal(modalId);
                }
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                ['modal-create', 'modal-edit', 'modal-delete', 'modal-filter'].forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModal(modalId);
                    }
                });
            }
        });


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

            const url = `/kaprog?search=${query}`;
            fetchData(url);

        }, 200);
    });
});