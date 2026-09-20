<aside
    class="fixed inset-y-0 left-0 z-30 w-64 border-r border-black/5 transform md:translate-x-0 transition-transform"
    style="background-color: #16233a;"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    @click="if (window.innerWidth < 768 && $event.target.closest('a[href]')) sidebarOpen = false">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-[#22334f]">
        @if($AppCfg['logo_url'])
            <img src="{{ $AppCfg['logo_url'] }}" alt="" class="w-10 h-10 object-contain rounded-lg bg-white/90 p-0.5 shadow-soft">
        @else
            <div class="w-10 h-10 rounded-xl bg-white/20 grid place-items-center text-white font-bold shadow-soft ring-2 ring-white/40">
                {{ mb_substr($AppCfg['app_name'], 0, 1) }}
            </div>
        @endif
        <div class="min-w-0">
            <div class="text-sm font-bold text-white leading-tight truncate">{{ $AppCfg['app_name'] }}</div>
            <div class="text-[11px] text-white leading-tight truncate">{{ $AppCfg['app_tagline'] }}</div>
        </div>
    </div>

    @php
        $grpDataCenter  = request()->routeIs('tahun-ajaran.*', 'jurusan.*', 'mapel.*', 'tingkat-kelas.*', 'rombel.*');
        $grpKepegawaian = request()->routeIs('guru.*', 'guru-mapel.*');
        $grpPeriodikal  = request()->routeIs('periodikal.*');
        $grpAdmin       = request()->routeIs('log-login.*', 'pengaturan.*', 'pengguna.*');
    @endphp
    <nav class="px-3 py-4 overflow-y-auto h-[calc(100vh-4rem)]">
        <div class="sidebar-section">Beranda</div>
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-icon name="home"/> Dashboard
        </a>

        <div class="sidebar-section">Menu Utama</div>

        {{-- Data Center --}}
        <div x-data="{ open: @js($grpDataCenter) }">
            <button type="button" @click="open = !open" class="sidebar-link sidebar-toggle w-full {{ $grpDataCenter ? 'text-white' : '' }}">
                <x-icon name="database"/> Data Center
                <x-icon name="chevron-down" class="w-4 h-4 ml-auto transition-transform" x-bind:class="open ? '' : '-rotate-90'"/>
            </button>
            <div x-show="open" x-cloak x-transition.opacity class="sidebar-sub">
                <a href="{{ route('tahun-ajaran.index') }}" class="sidebar-link {{ request()->routeIs('tahun-ajaran.*') ? 'active' : '' }}">
                    <x-icon name="calendar" class="w-4 h-4"/> Tahun Ajaran
                </a>
                <a href="{{ route('jurusan.index') }}" class="sidebar-link {{ request()->routeIs('jurusan.*') ? 'active' : '' }}">
                    <x-icon name="layers" class="w-4 h-4"/> Jurusan
                </a>
                <a href="{{ route('mapel.index') }}" class="sidebar-link {{ request()->routeIs('mapel.*') ? 'active' : '' }}">
                    <x-icon name="book" class="w-4 h-4"/> Mata Pelajaran
                </a>
                <a href="{{ route('tingkat-kelas.index') }}" class="sidebar-link {{ request()->routeIs('tingkat-kelas.*') ? 'active' : '' }}">
                    <x-icon name="layers" class="w-4 h-4"/> Tingkat Kelas
                </a>
                <a href="{{ route('rombel.index') }}" class="sidebar-link {{ request()->routeIs('rombel.*') ? 'active' : '' }}">
                    <x-icon name="grid" class="w-4 h-4"/> Rombongan Belajar
                </a>
            </div>
        </div>

        {{-- Kepegawaian --}}
        <div x-data="{ open: @js($grpKepegawaian) }">
            <button type="button" @click="open = !open" class="sidebar-link sidebar-toggle w-full {{ $grpKepegawaian ? 'text-white' : '' }}">
                <x-icon name="briefcase"/> Kepegawaian
                <x-icon name="chevron-down" class="w-4 h-4 ml-auto transition-transform" x-bind:class="open ? '' : '-rotate-90'"/>
            </button>
            <div x-show="open" x-cloak x-transition.opacity class="sidebar-sub">
                <a href="{{ route('guru.index') }}" class="sidebar-link {{ request()->routeIs('guru.*') ? 'active' : '' }}">
                    <x-icon name="user-tie" class="w-4 h-4"/> Data Guru
                </a>
                <a href="{{ route('guru-mapel.index') }}" class="sidebar-link {{ request()->routeIs('guru-mapel.*') ? 'active' : '' }}">
                    <x-icon name="bookmark" class="w-4 h-4"/> Guru Mapel
                </a>
            </div>
        </div>

        {{-- Data Siswa (berdiri sendiri) --}}
        <a href="{{ route('siswa.index') }}" class="sidebar-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
            <x-icon name="users"/> Data Siswa
        </a>

        {{-- Administrasi Periodikal --}}
        <div x-data="{ open: @js($grpPeriodikal) }">
            <button type="button" @click="open = !open" class="sidebar-link sidebar-toggle w-full {{ $grpPeriodikal ? 'text-white' : '' }}">
                <x-icon name="refresh"/> Periodikal
                <x-icon name="chevron-down" class="w-4 h-4 ml-auto transition-transform" x-bind:class="open ? '' : '-rotate-90'"/>
            </button>
            <div x-show="open" x-cloak x-transition.opacity class="sidebar-sub">
                <a href="{{ route('periodikal.semua.form') }}" class="sidebar-link {{ request()->routeIs('periodikal.semua.*') ? 'active' : '' }}">
                    <x-icon name="arrow-right" class="w-4 h-4"/> Proses Semua Siswa
                </a>
                <a href="{{ route('periodikal.per-rombel.form') }}" class="sidebar-link {{ request()->routeIs('periodikal.per-rombel.*') ? 'active' : '' }}">
                    <x-icon name="user" class="w-4 h-4"/> Proses Per Rombel
                </a>
                <a href="{{ route('periodikal.koreksi.index') }}" class="sidebar-link {{ request()->routeIs('periodikal.koreksi.*') ? 'active' : '' }}">
                    <x-icon name="edit" class="w-4 h-4"/> Koreksi Hasil
                </a>
            </div>
        </div>

        <div class="sidebar-section">Sistem</div>

        {{-- Administrasi --}}
        <div x-data="{ open: @js($grpAdmin) }">
            <button type="button" @click="open = !open" class="sidebar-link sidebar-toggle w-full {{ $grpAdmin ? 'text-white' : '' }}">
                <x-icon name="settings"/> Administrasi
                <x-icon name="chevron-down" class="w-4 h-4 ml-auto transition-transform" x-bind:class="open ? '' : '-rotate-90'"/>
            </button>
            <div x-show="open" x-cloak x-transition.opacity class="sidebar-sub">
                @if(auth()->user()?->canAccess('pengguna/index'))
                    <a href="{{ route('pengguna.index') }}" class="sidebar-link {{ request()->routeIs('pengguna.*') ? 'active' : '' }}">
                        <x-icon name="user" class="w-4 h-4"/> Akun Pengguna
                    </a>
                @endif
                <a href="{{ route('log-login.index') }}" class="sidebar-link {{ request()->routeIs('log-login.*') ? 'active' : '' }}">
                    <x-icon name="key" class="w-4 h-4"/> Log Login
                </a>
                <a href="{{ route('pengaturan.index') }}" class="sidebar-link {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}">
                    <x-icon name="settings" class="w-4 h-4"/> Pengaturan Aplikasi
                </a>
            </div>
        </div>
    </nav>
</aside>

<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 z-20 bg-ink-900/50 md:hidden"></div>
