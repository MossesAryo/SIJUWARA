<div class="bg-white border-b border-gray-200 px-6 py-3 sticky top-0 z-10 shadow-sm">
    <div class="flex items-center justify-between">
        <!-- Left: Page Title -->
        <div>
            <h1 class="text-xl font-bold" style="color: #1e3a5f;">SIJUWARA SEBELAS</h1>
            <p class="text-xs text-gray-400">Selamat datang di Sistem Jurnal Siswa Aktif</p>
        </div>

        <!-- Right: User Profile -->
        <div class="flex items-center gap-3">

            @if (auth()->user()->punyaBanyakTugas())
                <!-- Pindah tugas aktif tanpa login ulang -->
                <a href="{{ route('pilih_tugas') }}" title="Ganti tugas aktif"
                    class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 transition-all hover:bg-gray-50 hover:border-[#56A6E8] hover:text-[#3d8fd4]">
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="hidden sm:inline">Ganti Tugas</span>
                </a>
            @endif

            <!-- Divider -->
            <div class="w-px h-6 bg-gray-200"></div>

            <!-- Profile -->
            <a href="{{ route('profile') }}"
                class="flex items-center gap-3 hover:bg-gray-50 px-3 py-2 rounded-xl transition-all border border-transparent hover:border-gray-200">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-sm"
                    style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
                    @if (auth()->user()->role == 2)
                        {{ strtoupper(substr(Auth::user()->gurubk->nama_guru_bk, 0, 1)) }}
                    @elseif (auth()->user()->role == 1)
                        {{ strtoupper(substr(Auth::user()->wakasek->nama_wakasek, 0, 1)) }}
                    @elseif (auth()->user()->role == 4)
                        {{ strtoupper(substr(Auth::user()->ketua_program->nama_ketua_program, 0, 1)) }}
                    @elseif (auth()->user()->role == 3)
                        {{ strtoupper(substr(Auth::user()->walikelas->nama_walikelas, 0, 1)) }}
                    @endif
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-gray-800">
                        @if (auth()->user()->role == 2)
                            @auth {{ Auth::user()->gurubk->nama_guru_bk }} @endauth
                        @elseif (auth()->user()->role == 1)
                            @auth {{ Auth::user()->wakasek->nama_wakasek }} @endauth
                        @elseif (auth()->user()->role == 4)
                            @auth {{ Auth::user()->ketua_program->nama_ketua_program }} @endauth
                        @elseif (auth()->user()->role == 3)
                            @auth {{ Auth::user()->walikelas->nama_walikelas }} @endauth
                        @endif
                    </p>
                    <p class="text-xs text-gray-400">
                        @if (auth()->user()->role == 1) Wakasek
                        @elseif (auth()->user()->role == 2) Guru BK
                        @elseif (auth()->user()->role == 3) Wali Kelas
                        @elseif (auth()->user()->role == 4) Ketua Program
                        @endif
                    </p>
                </div>
                
            </a>
        </div>
    </div>
</div>