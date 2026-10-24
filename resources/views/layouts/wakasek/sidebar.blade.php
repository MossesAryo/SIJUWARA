<!-- Sidebar -->
<style>
    .dropdown-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }

    .dropdown-content.show {
        max-height: 500px;
        transition: max-height 0.3s ease-in;
    }

    .dropdown-arrow {
        transition: transform 0.3s ease;
    }

    .dropdown-arrow.rotate {
        transform: rotate(180deg);
    }

    .active-link {
        color: #2563eb !important;
        background-color: #eff6ff !important;
    }

    .menu-link.active-link:hover {
        background-color: #dbeafe;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .section-label {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 12px 16px 4px;
    }

    .section-divider {
        height: 1px;
        background-color: #f1f5f9;
        margin: 6px 12px;
    }
</style>

<div class="w-64 h-screen bg-white border-r border-gray-200 fixed left-0 top-0 z-20 flex flex-col">
    <!-- Fixed Header -->
    <div class="p-6 flex-shrink-0 border-b border-gray-100">
        <div class="flex items-center gap-3">
            <img src="{{ asset('img/logo.png') }}" alt="SIJUWARA" class="w-10 h-10 object-contain">
            <div>
                <h1 class="text-lg font-extrabold tracking-wide" style="color: #1e3a5f;">SIJUWARA</h1>
                <p class="text-xs font-medium" style="color: #4a7ab5;">(Sistem Jurnal Siswa Aktif)</p>
            </div>
        </div>
    </div>

    <!-- Scrollable Navigation -->
    <div class="flex-1 overflow-y-auto sidebar-nav">
        <nav class="py-3 pb-20">
            <ul class="space-y-0.5">

                <!-- Dashboard (tanpa section) -->
                <li class="px-3">
                    <a href="{{ route('wakasek.dashboard') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('wakasek.dashboard') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- ───── MASTER DATA ───── -->
                <li><div class="section-divider mt-2"></div></li>
                <li><div class="section-label">Master Data</div></li>

                <li class="px-3">
                    <a href="{{ route('siswa.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('siswa.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-person-badge"></i>
                        <span>Siswa</span>
                    </a>
                </li>
                 @if (auth()->user()->hasRole([1,2, 4]))
                <li class="px-3">
                    <a href="{{ route('kelas') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('kelas*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>Kelas</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->hasRole(4))
                <li class="px-3">
                    <a href="{{ route('perpindahan_kelas.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('perpindahan_kelas.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Perpindahan Kelas</span>
                    </a>
                </li>
                @endif

                

                <!-- ───── MANAJEMEN USER ───── -->
                @if (auth()->user()->hasRole(1))
                <li><div class="section-divider mt-2"></div></li>
                <li><div class="section-label">Manajemen User</div></li>

                <li class="px-3">
                    <button onclick="toggleDropdown('userDropdown','userArrow')"
                        class="flex items-center justify-between w-full px-3 py-2.5 text-gray-600 hover:bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i class="bi bi-people"></i>
                            <span>User</span>
                        </div>
                        <i class="bi bi-chevron-down dropdown-arrow text-sm" id="userArrow"></i>
                    </button>
                    <div id="userDropdown"
                        class="dropdown-content ml-4 mt-1
                        {{ request()->routeIs('walikelas.*') || request()->routeIs('gurubk.*') || request()->routeIs('kaprog.*') ? 'show' : '' }}">
                        <ul class="space-y-0.5 border-l border-gray-100 ml-2 pl-2">
                            <li>
                                <a href="{{ route('walikelas.index') }}"
                                    class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg menu-link
                                    {{ request()->routeIs('walikelas.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="bi bi-person-vcard"></i>
                                    <span>Wali Kelas</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('gurubk.index') }}"
                                    class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg menu-link
                                    {{ request()->routeIs('gurubk.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="bi bi-person-hearts"></i>
                                    <span>Guru BK</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('kaprog.index') }}"
                                    class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg menu-link
                                    {{ request()->routeIs('kaprog.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <i class="bi bi-mortarboard"></i>
                                    <span>Kaprog</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif

                <!-- ───── ASPEK PENILAIAN ───── -->
                <li><div class="section-divider mt-2"></div></li>
                <li><div class="section-label">Aspek Penilaian</div></li>

                <li class="px-3">
                    <a href="{{ route('aspek_penghargaan.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('aspek_penghargaan.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-star"></i>
                        <span>Aspek Penghargaan</span>
                    </a>
                </li>
                <li class="px-3">
                    <a href="{{ route('aspek_pelanggaran.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('aspek_pelanggaran.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-x-octagon"></i>
                        <span>Aspek Pelanggaran</span>
                    </a>
                </li>

                @if (auth()->user()->hasRole([1, 2, 3]))
                <!-- ───── INPUT DATA ───── -->
                <li><div class="section-divider mt-2"></div></li>
                <li><div class="section-label">Input Data</div></li>
               

                {{-- <li class="px-3">
                    <a href="{{ route('penghargaan.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('penghargaan.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-trophy"></i>
                        <span>Penghargaan</span>
                    </a>
                </li>
                <li class="px-3">
                    <a href="{{ route('peringatan.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('peringatan.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>Pelanggaran</span>
                    </a>
                </li> --}}
                
                
                
                <li class="px-3">
                    <a href="{{ route('skoring_penghargaan.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('skoring_penghargaan.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-clipboard-check"></i>
                        <span>Skoring Penghargaan</span>
                    </a>
                </li>
                <li class="px-3">
                    <a href="{{ route('skoring_pelanggaran.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('skoring_pelanggaran.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-clipboard-x"></i>
                        <span>Skoring Pelanggaran</span>
                    </a>
                </li>
                <li class="px-3">
                    <a href="{{ route('intervensi.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('intervensi.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-life-preserver"></i>
                        <span>Penanganan</span>
                    </a>
                </li>
                @endif

                <!-- ───── REKAP & TINDAK LANJUT ───── -->
                <li><div class="section-divider mt-2"></div></li>
                <li><div class="section-label">Rekap & Tindak Lanjut</div></li>

                <li class="px-3">
                    <a href="{{ route('akumulasi.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('akumulasi.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-graph-up"></i>
                        <span>Akumulasi</span>
                    </a>
                </li>

              

                @if (auth()->user()->hasRole([1]))
                <li class="px-3">
                    <a href="{{ route('laporan.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('laporan.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li class="px-3">
                    <a href="{{ route('tahun_ajaran.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg menu-link
                        {{ request()->routeIs('tahun_ajaran.*') ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi bi-calendar3"></i>
                        <span>Tahun Ajaran</span>
                    </a>
                </li>
                
                @endif

            </ul>
        </nav>
    </div>
</div>

<script>
    function toggleDropdown(dropdownId, arrowId) {
        const dropdown = document.getElementById(dropdownId);
        const arrow = document.getElementById(arrowId);
        dropdown.classList.toggle('show');
        arrow.classList.toggle('rotate');
    }

    document.addEventListener("click", function(event) {
        const dropdowns = [{ btn: "userArrow", content: "userDropdown" }];
        dropdowns.forEach(d => {
            const dropdown = document.getElementById(d.content);
            const arrow = document.getElementById(d.btn);
            if (!dropdown || !arrow) return;
            if (!event.target.closest(`#${d.content}`) &&
                !event.target.closest(`#${d.btn}`) &&
                !event.target.closest("button")) {
                dropdown.classList.remove("show");
                arrow.classList.remove("rotate");
            }
        });
    });

    document.addEventListener("DOMContentLoaded", () => {
        const sidebarScrollArea = document.querySelector('.sidebar-nav');
        if (sidebarScrollArea) {
            sidebarScrollArea.addEventListener('wheel', (e) => {
                const { scrollTop, scrollHeight, clientHeight } = sidebarScrollArea;
                const isAtBottom = scrollTop + clientHeight >= scrollHeight;
                const isAtTop = scrollTop === 0;
                if ((e.deltaY > 0 && !isAtBottom) || (e.deltaY < 0 && !isAtTop)) {
                    e.stopPropagation();
                }
            });
        }

        const userDropdown = document.getElementById('userDropdown');
        const userArrow = document.getElementById('userArrow');
        if (userDropdown?.classList.contains('show')) {
            userArrow?.classList.add('rotate');
        }
    });
</script>