<x-layouts.app :title="$title ?? 'Dashboard'">
    <div class="min-h-screen flex" x-data="{ sidebarOpen: false }">

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-[#E2E8F0] transform transition-transform duration-300 ease-standard lg:relative lg:translate-x-0 flex flex-col"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Navigasi utama"
        >
            {{-- Logo --}}
            <div class="flex items-center gap-3 px-6 py-5 border-b border-[#E2E8F0]">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-[#0F172A] text-sm block leading-tight">SuperBie</span>
                    <span class="text-[10px] text-[#475569] font-medium uppercase tracking-wide">Lapor Pak Wali</span>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 overflow-y-auto py-4 px-3" role="navigation">
                {{ $navigation }}
            </nav>

            {{-- User section --}}
            <div class="px-3 py-4 border-t border-[#E2E8F0]">
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-[#F8FAFC]">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#0F172A] truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-[#475569] capitalize truncate">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="p-1.5 text-[#475569] hover:text-[#B91C1C] hover:bg-red-50 rounded-lg ui-animated"
                            aria-label="Logout"
                            title="Logout"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobile overlay --}}
        <div
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Top bar --}}
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-[#E2E8F0] px-4 sm:px-6 py-3 flex items-center gap-4">
                <button
                    @click="sidebarOpen = true"
                    class="lg:hidden p-2 rounded-lg text-[#475569] hover:bg-[#F8FAFC] ui-animated"
                    aria-label="Buka menu navigasi"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="flex-1">
                    <h1 class="text-lg font-semibold text-[#0F172A]">{{ $header ?? 'Dashboard' }}</h1>
                    @isset($breadcrumb)
                        <p class="text-xs text-[#475569] mt-0.5">{{ $breadcrumb }}</p>
                    @endisset
                </div>

                <div class="flex items-center gap-2">
                    {{ $headerActions ?? '' }}
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 p-4 sm:p-6 page-enter" id="main-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
