<div class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3 shadow-sm sm:px-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button type="button" onclick="toggleSidebar()"
                class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden" aria-label="Buka menu">
                <i class="bi bi-list text-2xl"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold" style="color: #1e3a5f;">SIJUWARA SEBELAS</h1>
                <p class="hidden text-xs text-gray-400 sm:block">Panel Super Admin</p>
            </div>
        </div>

        <div class="flex items-center gap-3 rounded-xl px-3 py-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white shadow-sm"
                style="background: linear-gradient(135deg, #4a7ab5, #1e3a5f);">
                {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
            </div>
            <div class="hidden leading-tight sm:block">
                <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->username }}</p>
                <p class="text-xs text-gray-400">Super Admin</p>
            </div>
        </div>
    </div>
</div>