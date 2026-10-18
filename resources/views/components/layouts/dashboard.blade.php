@php
    $user = auth()->user();
    $role = $user?->role;

    // Role home (used by the header brand link).
    $homeRoute = match ($role) {
        'super_admin' => 'super-admin.dashboard',
        'admin'       => 'admin.dashboard',
        'operator'    => 'operator.dashboard',
        default       => 'citizen.dashboard',
    };

    // Functional navbar search — wired ONLY to roles/pages that actually have a
    // searchable list endpoint (no new route, no fake UI). Citizens have no
    // searchable list, so the search box is hidden for them.
    //
    // The echoed value is coerced to a STRING: a crafted array query string
    // (e.g. `?q[]=x`) must never reach the input's `value` attribute, where it
    // would raise htmlspecialchars(): array given (HTTP 500) on every page.
    $queryValue = static fn (string $key): string => is_scalar($v = request($key)) ? (string) $v : '';

    $search = match ($role) {
        'admin'       => ['action' => route('admin.complaints'),             'name' => 'q',      'value' => $queryValue('q')],
        'super_admin' => ['action' => route('super-admin.complaints.index'), 'name' => 'q',      'value' => $queryValue('q')],
        'operator'    => ['action' => route('operator.complaint.index'),     'name' => 'search', 'value' => $queryValue('search')],
        default       => null,
    };

    $roleLabel = $role ? ucwords(str_replace('_', ' ', $role)) : '';

    // Greeting uses the real account name only (never fabricated).
    $fullName  = trim((string) $user?->name);
    $firstName = $fullName === '' ? 'Pengguna' : (explode(' ', $fullName)[0] ?? $fullName);

    // Real profile destination per role. Citizens manage their own profile;
    // staff have a read-only account page (accounts are edited by Super Admin).
    // Every role therefore links to a REAL page — no dead link, no false UI.
    $profileRoute = $role === 'masyarakat' ? 'citizen.profile.edit' : 'staff.profile';

    // Real uploaded profile photo (NULL when the account has none → initials).
    // Resolved once here and reused by both the rail and topbar account avatars
    // so the photo the user uploaded is actually reflected across the shell.
    $avatarUrl = $user?->avatarUrl();

    // PHASE N4 — in-app notifications (bell + dropdown). Owner-scoped: the
    // unread count uses the (recipient_user_id, read_at) index and the dropdown
    // is capped at the 10 newest, so there is no full scan and no per-user
    // fan-out. Admin never receives notifications, so its bell stays at zero.
    $unreadNotifications = 0;
    $recentNotifications = collect();
    if ($user !== null) {
        $unreadNotifications = $user->appNotifications()->whereNull('read_at')->count();
        $recentNotifications = $user->appNotifications()
            ->with('complaint')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }
    $notificationBadge = $unreadNotifications > 9 ? '9+' : (string) $unreadNotifications;
@endphp

<x-layouts.app :title="$title ?? 'Dashboard'">
    <x-skip-link />

    <div
        class="sb-canvas min-h-screen lg:flex lg:items-start"
        x-data="{
            // Mobile off-canvas drawer state.
            sidebarOpen: false,
            // Desktop rail: collapsed by default; expands on CLICK (not hover).
            railOpen: false,
            // Transition guard: enabled after the persisted state is applied so a
            // pinned rail does not animate on first paint.
            railReady: false,
            // Persisted pin/lock preference (NOT auth/session data). Namespaced
            // key; guarded so a blocked storage degrades to session-only.
            railPinned: false,
            init() {
                try {
                    this.railPinned = window.localStorage.getItem('sb.sidebar.pinned') === '1';
                } catch (e) { this.railPinned = false; }
                this.$nextTick(() => { this.railReady = true; });
            },
            // Effective desktop state: pinned OR manually opened.
            get railExpanded() { return this.railPinned || this.railOpen; },
            // Toggle via the menu/chevron button.
            toggleRail() {
                if (this.railPinned) return; // pinned == permanently open
                this.railOpen = !this.railOpen;
            },
            // Clicking the sidebar surface (blank/padding areas) toggles it, but
            // never on interactive elements (links/buttons/forms) so navigation
            // and the pin button keep working normally.
            onRailSurfaceClick(event) {
                if (this.railPinned) return;
                if (event.target.closest('a[href], button, input, select, textarea, label, form')) return;
                this.railOpen = !this.railOpen;
            },
            // Click outside collapses it — unless pinned.
            collapseRail() {
                if (!this.railPinned) this.railOpen = false;
            },
            togglePin() {
                this.railPinned = !this.railPinned;
                if (this.railPinned) this.railOpen = false; // pin supersedes manual open
                try {
                    window.localStorage.setItem('sb.sidebar.pinned', this.railPinned ? '1' : '0');
                } catch (e) { /* preference is best-effort; UI state already applied */ }
            },
        }"
        :class="{
            'sb-rail-pinned': railPinned,
            'sb-rail-expanded': railExpanded,
            'sb-rail-ready': railReady,
        }"
    >

        {{-- Sidebar — floating white capsule docked as a flex sibling on desktop
             (no overlay); off-canvas drawer on mobile. --}}
        {{-- id="primary-navigation": progressive navigation swaps the
             server-rendered menu so the active item always matches the
             server-derived state (Prompt 26). --}}
        <aside
            class="sb-rail fixed inset-y-0 left-0 z-50 flex w-64 max-w-[80vw] flex-col bg-white border-r border-line -translate-x-full transition-transform duration-300 ease-standard
                   sb-rail-surface lg:sticky lg:top-4 lg:z-40 lg:my-4 lg:ml-4 lg:h-[calc(100vh-2rem)] lg:max-w-none lg:shrink-0 lg:translate-x-0 lg:border-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            @click="onRailSurfaceClick($event)"
            aria-label="Navigasi utama"
        >
            {{-- Sidebar header: [Logo + App name] on the left, a single pin
                 control on the right (desktop). Collapsed → centred shield mark
                 only. Mobile drawer → brand + close button. --}}
            <div class="sb-rail-top">
                <a
                    href="{{ route($homeRoute) }}"
                    class="sb-rail-brand"
                    title="SuperBie — Lapor Pak Wali"
                >
                    <img src="/brand/superbie-mark.svg" alt="" class="h-10 w-auto shrink-0">
                    <span class="rail-label sb-rail-brand-text">
                        <span class="sb-rail-name">SuperBie</span>
                        <span class="sb-rail-tagline">Lapor Pak Wali</span>
                    </span>
                </a>

                {{-- Single desktop control: pin/lock the rail open. Shown only
                     when the rail is expanded (right side of the header). --}}
                <div class="sb-rail-controls">
                    <button
                        type="button"
                        class="sb-rail-pin p-2 rounded-full text-ink-faint hover:text-primary-700 hover:bg-surface-sunken ui-animated"
                        @click="togglePin()"
                        :aria-pressed="railPinned ? 'true' : 'false'"
                        :title="railPinned ? 'Lepas kunci sidebar' : 'Kunci sidebar agar tetap terbuka'"
                        :aria-label="railPinned ? 'Lepas kunci sidebar' : 'Kunci sidebar agar tetap terbuka'"
                    >
                        <svg class="w-5 h-5" :class="railPinned ? 'text-primary-700' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3l6 6-4 1-1 4-3 3-1-1-4 4-1-1 4-4-1-1 3-3 4-1 1-4-4-4z"/>
                        </svg>
                    </button>
                </div>

                {{-- Mobile: close the off-canvas drawer. --}}
                <button
                    type="button"
                    class="lg:hidden p-2 rounded-full text-ink-soft hover:bg-surface-sunken ui-animated shrink-0"
                    @click="sidebarOpen = false"
                    aria-label="Tutup menu navigasi"
                    title="Tutup menu"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mx-3 border-t border-line lg:mx-2" aria-hidden="true"></div>

            {{-- Navigation — starts directly after the brand header (no account
                 block above it). `flex-1` is the spacer that pushes the footer
                 controls to the very bottom of the rail. --}}
            <nav id="primary-navigation" class="sb-rail-nav flex-1 overflow-y-auto px-3 py-4 lg:px-2" role="navigation">
                <div class="sb-rail-nav-list flex flex-col items-stretch gap-1.5 lg:items-center">
                    {{ $navigation }}
                </div>
            </nav>

            {{-- Footer group — [Logo] → [Menu] → [spacer] → [Logout + toggle] →
                 [Profil]. The whole group stays OUTSIDE #primary-navigation
                 (progressive navigation never swaps it). Logout sits above the
                 account row; the expand/collapse chevron is a small round button
                 (16px icon) aligned precisely at the right of the action row. --}}
            <div class="sb-rail-footer">
                <div class="sb-rail-footer-actions">
                    <form method="POST" action="{{ route('logout') }}" class="sb-rail-logout-form">
                        @csrf
                        <button
                            type="submit"
                            class="sb-rail-logout ui-animated"
                            aria-label="Logout"
                            title="Logout"
                        >
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="rail-label">Logout</span>
                        </button>
                    </form>

                    {{-- Small round expand/collapse toggle (16px chevron, standard
                         menu-icon size). Disabled while the rail is pinned open. --}}
                    <button
                        type="button"
                        class="sb-rail-expand ui-animated"
                        @click="toggleRail()"
                        :disabled="railPinned"
                        :aria-label="railExpanded ? 'Kecilkan sidebar' : 'Perluas sidebar'"
                        :title="railExpanded ? 'Kecilkan sidebar' : 'Perluas sidebar'"
                    >
                        <svg class="sb-rail-expand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                <a
                    href="{{ route($profileRoute) }}"
                    class="sb-rail-profile ui-animated"
                    title="Buka profil akun"
                    aria-label="Buka profil akun"
                    x-data="avatarImage"
                >
                    @if ($avatarUrl)
                        <img
                            src="{{ $avatarUrl }}"
                            alt=""
                            x-show="!failed"
                            x-on:error="fail()"
                            x-init="if ($el.complete && $el.naturalWidth === 0) fail()"
                            class="sb-rail-avatar w-9 h-9 rounded-full object-cover shrink-0"
                            aria-hidden="true"
                        >
                        <span
                            x-show="failed"
                            x-cloak
                            class="sb-rail-avatar w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-bold shrink-0"
                            aria-hidden="true"
                        >
                            {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                        </span>
                    @else
                        <span class="sb-rail-avatar w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-bold shrink-0" aria-hidden="true">
                            {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                        </span>
                    @endif
                    <span class="rail-label sb-rail-id flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-ink truncate">{{ $user?->name }}</span>
                        <span class="block text-xs text-ink-soft capitalize truncate">{{ $roleLabel }}</span>
                    </span>
                </a>
            </div>
        </aside>

        {{-- Mobile overlay --}}
        <div
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            x-show="sidebarOpen"
            x-cloak
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Main content (flex sibling of the rail — no overlay on desktop) --}}
        <div class="sb-main flex min-h-screen min-w-0 flex-1 flex-col" @click="collapseRail()">
            {{-- Top bar --}}
            <header class="sticky top-0 z-30 bg-canvas/85 backdrop-blur-md px-4 sm:px-6 py-3 flex items-center gap-3 sm:gap-4">
                {{-- Mobile: open the off-canvas drawer (desktop uses the sidebar
                     header controls; no hamburger in the topbar on desktop). --}}
                <button
                    @click="sidebarOpen = true"
                    class="lg:hidden p-2 rounded-full text-ink-soft hover:bg-white ui-animated shrink-0"
                    aria-label="Buka menu navigasi"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Page title + greeting. The <h1> is the PAGE TITLE (so the
                     document outline starts at the page's subject); the greeting
                     is the secondary line. Both strings stay in the DOM so HTML
                     assertions (e.g. `assertSee('Dashboard Admin')`,
                     `assertSee('Hello, Budi!')`) hold. --}}
                <div class="flex-1 min-w-0" id="page-heading">
                    <h1 class="text-base sm:text-lg font-bold text-ink truncate">{{ $header ?? 'Dashboard' }}</h1>
                    <p class="text-xs text-ink-soft mt-0.5 truncate">
                        Hello, {{ $firstName }}!@isset($breadcrumb)<span class="text-ink-faint"> • </span>{{ $breadcrumb }}@endisset
                    </p>
                </div>

                {{-- Functional search (roles with a searchable list only) --}}
                @if ($search)
                    <form
                        method="GET"
                        action="{{ $search['action'] }}"
                        role="search"
                        aria-label="Cari laporan"
                        class="hidden md:flex items-center gap-2 bg-white rounded-full pl-1.5 pr-4 py-1.5 w-56 lg:w-72 ui-animated shadow-[0_1px_2px_rgba(16,24,40,0.04),0_10px_24px_-18px_rgba(16,24,40,0.18)] focus-within:ring-2 focus-within:ring-primary-600/30"
                    >
                        <span class="w-8 h-8 rounded-full bg-ink text-white flex items-center justify-center shrink-0" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                            </svg>
                        </span>
                        <label for="sb-global-search" class="sr-only">Cari laporan</label>
                        <input
                            id="sb-global-search"
                            type="search"
                            name="{{ $search['name'] }}"
                            value="{{ $search['value'] }}"
                            placeholder="Cari kode, judul, atau pelapor…"
                            class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-ink-faint"
                        >
                    </form>
                @endif

                {{-- PHASE N4 — Notifications: an enabled bell with an unread badge
                     and a dropdown of the latest notifications. The badge is
                     hidden at zero (no fabricated count). Opening a notification
                     goes through `notifications.open`, which marks it read and
                     redirects to the role-appropriate, access-checked target. --}}
                <div
                    class="relative"
                    x-data="{ notifOpen: false }"
                    @keydown.escape.window="notifOpen = false"
                    @click.outside="notifOpen = false"
                >
                    <button
                        type="button"
                        class="relative inline-flex w-10 h-10 items-center justify-center rounded-full bg-white text-ink-soft hover:text-primary-700 hover:bg-surface-sunken ui-animated shadow-[0_1px_2px_rgba(16,24,40,0.04)]"
                        @click="notifOpen = !notifOpen"
                        :aria-expanded="notifOpen ? 'true' : 'false'"
                        aria-haspopup="true"
                        aria-controls="sb-notif-panel"
                        aria-label="Notifikasi"
                        title="Notifikasi"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @if ($unreadNotifications > 0)
                            <span
                                class="absolute -top-0.5 -right-0.5 min-w-[1.15rem] h-[1.15rem] px-1 inline-flex items-center justify-center rounded-full bg-red-600 text-white text-[10px] font-bold leading-none"
                                aria-hidden="true"
                            >{{ $notificationBadge }}</span>
                            <span class="sr-only">{{ $unreadNotifications }} notifikasi belum dibaca</span>
                        @endif
                    </button>

                    <div
                        x-show="notifOpen"
                        x-cloak
                        id="sb-notif-panel"
                        class="absolute right-0 mt-2 w-80 max-w-[85vw] bg-white rounded-2xl border border-line shadow-lg overflow-hidden z-50"
                        role="group"
                        aria-label="Daftar notifikasi terbaru"
                    >
                        <div class="flex items-center justify-between px-4 py-3 border-b border-line">
                            <span class="text-sm font-semibold text-ink">Notifikasi</span>
                            @if ($unreadNotifications > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-primary-600 hover:text-primary-700 ui-animated">
                                        Tandai semua dibaca
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            @forelse ($recentNotifications as $item)
                                <x-notification-item :notification="$item" compact />
                            @empty
                                <div class="px-4 py-6">
                                    <p class="text-sm text-ink-soft text-center">Belum ada notifikasi.</p>
                                </div>
                            @endforelse
                        </div>

                        <a
                            href="{{ route('notifications.index') }}"
                            class="block px-4 py-3 text-center text-sm font-semibold text-primary-600 hover:bg-surface-sunken ui-animated border-t border-line"
                        >
                            Lihat semua
                        </a>
                    </div>
                </div>

                {{-- Profile — real link to the role's profile page (citizen →
                     citizen.profile.edit; staff → staff.profile). Interactive
                     with hover feedback; no dead link, no false affordance. --}}
                <a
                    href="{{ route($profileRoute) }}"
                    class="sb-topbar-profile hidden sm:flex items-center gap-2.5 shrink-0 cursor-pointer rounded-full ui-animated"
                    title="Buka profil akun"
                    aria-label="Buka profil akun"
                    x-data="avatarImage"
                >
                    @if ($avatarUrl)
                        <img
                            src="{{ $avatarUrl }}"
                            alt=""
                            x-show="!failed"
                            x-on:error="fail()"
                            x-init="if ($el.complete && $el.naturalWidth === 0) fail()"
                            class="w-9 h-9 rounded-full object-cover shrink-0"
                            aria-hidden="true"
                        >
                        <span
                            x-show="failed"
                            x-cloak
                            class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-bold shrink-0"
                            aria-hidden="true"
                        >
                            {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                        </span>
                    @else
                        <span class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-bold shrink-0" aria-hidden="true">
                            {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                        </span>
                    @endif
                    <span class="hidden xl:block leading-tight">
                        <span class="block text-sm font-semibold text-ink truncate max-w-[10rem]">{{ $user?->name }}</span>
                        <span class="block text-xs text-ink-soft capitalize truncate">{{ $roleLabel }}</span>
                    </span>
                </a>

                <div class="flex items-center gap-2" id="page-actions">
                    {{ $headerActions ?? '' }}
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 px-4 sm:px-6 pb-6 pt-1 page-enter" id="main-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
