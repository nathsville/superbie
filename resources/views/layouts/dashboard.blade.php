<x-layouts.app :title="$title ?? 'Dashboard'">
    <div class="min-h-screen flex" x-data="{ sidebarOpen: false }">

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-line transform transition-transform duration-300 ease-standard lg:relative lg:translate-x-0 flex flex-col"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Navigasi utama"
        >
            {{-- Logo --}}
            <div class="flex items-center gap-3 px-6 py-5 border-b border-line">
                <img src="/brand/superbie-mark.svg" alt="" class="h-9 w-auto shrink-0">
                <div>
                    <span class="font-bold text-ink text-sm block leading-tight">SuperBie</span>
                    <span class="text-[10px] text-ink-soft font-medium uppercase tracking-wide">Lapor Pak Wali</span>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 overflow-y-auto py-4 px-3" role="navigation">
                {{ $navigation }}
            </nav>

            {{-- User section --}}
            <div class="px-3 py-4 border-t border-line">
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-page">
                    <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-ink truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-ink-soft capitalize truncate">{{ str_replace('_', ' ', auth()->user()->role) }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="p-1.5 text-ink-soft hover:text-error hover:bg-red-50 rounded-lg ui-animated"
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
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            aria-hidden="true"
        ></div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- Top bar --}}
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-line px-4 sm:px-6 py-3 flex items-center gap-4">
                <button
                    @click="sidebarOpen = true"
                    class="lg:hidden p-2 rounded-lg text-ink-soft hover:bg-page ui-animated"
                    aria-label="Buka menu navigasi"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="flex-1">
                    <h1 class="text-lg font-semibold text-ink">{{ $header ?? 'Dashboard' }}</h1>
                    @isset($breadcrumb)
                        <p class="text-xs text-ink-soft mt-0.5">{{ $breadcrumb }}</p>
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
