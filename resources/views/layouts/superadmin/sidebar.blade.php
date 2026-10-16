@php
    $menus = [
        ['label' => 'Dashboard',         'icon' => 'bi-speedometer2', 'route' => 'superadmin.dashboard',          'active' => 'superadmin.dashboard'],
        ['label' => 'Manajemen Wakasek', 'icon' => 'bi-person-badge', 'route' => 'superadmin.wakasek.index',      'active' => 'superadmin.wakasek.*'],
        ['label' => 'Manajemen Role',    'icon' => 'bi-shield-lock',  'route' => 'superadmin.role.index',         'active' => 'superadmin.role.*'],
        ['label' => 'Pemantauan Role',   'icon' => 'bi-activity',     'route' => 'superadmin.pemantauan.index',   'active' => 'superadmin.pemantauan.*'],
        ['label' => 'Tong Sampah Siswa', 'icon' => 'bi-archive',      'route' => 'superadmin.siswa-arsip.index',  'active' => 'superadmin.siswa-arsip.*'],
        ['label' => 'Log Aktivitas',     'icon' => 'bi-clock-history', 'route' => 'superadmin.log.index',         'active' => 'superadmin.log.*'],
    ];
@endphp

<style>
    .active-link {
        color: #2563eb !important;
        background-color: #eff6ff !important;
    }
</style>

<div id="sidebarOverlay" class="fixed inset-0 z-20 hidden bg-black/40 lg:hidden" onclick="toggleSidebar()"></div>

<aside id="superadminSidebar"
    class="fixed left-0 top-0 z-30 flex h-screen w-64 -translate-x-full transform flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0">

    <div class="flex-shrink-0 border-b border-gray-100 p-6">
        <div class="flex items-center gap-3">
            <img src="{{ asset('img/logo.png') }}" alt="SIJUWARA" class="h-10 w-10 object-contain">
            <div>
                <h1 class="text-lg font-extrabold tracking-wide" style="color: #1e3a5f;">SIJUWARA</h1>
                <p class="text-xs font-medium" style="color: #4a7ab5;">Super Admin</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-3">
        <ul class="space-y-0.5">
            @foreach ($menus as $menu)
                <li class="px-3">
                    <a href="{{ Route::has($menu['route']) ? route($menu['route']) : '#' }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5
                        {{ request()->routeIs($menu['active']) ? 'active-link' : 'text-gray-600 hover:bg-gray-50' }}">
                        <i class="bi {{ $menu['icon'] }}"></i>
                        <span>{{ $menu['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="flex-shrink-0 border-t border-gray-100 p-3">
        <a href="{{ route('logout') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-600 hover:bg-red-50 hover:text-red-600">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
    </div>
</aside>