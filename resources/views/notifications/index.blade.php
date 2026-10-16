@php
    $title = 'Notifikasi';
    $role = auth()->user()->role;
    $unreadCount = $notifications->getCollection()->whereNull('read_at')->count();
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $currentRoute = request()->route()->getName(); @endphp
        @if ($role === 'masyarakat')
            @include('citizen.partials.nav', ['currentRoute' => $currentRoute])
        @elseif ($role === 'super_admin')
            @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
        @elseif ($role === 'admin')
            <x-dashboard.nav-rail-link :href="route('admin.dashboard')" label="Monitoring">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
            <x-dashboard.nav-rail-link :href="route('admin.complaints')" label="Monitoring Laporan">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
        @else
            <x-dashboard.nav-rail-link :href="route('operator.dashboard')" label="Dashboard" :active="$currentRoute === 'operator.dashboard'">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
            <x-dashboard.nav-rail-link :href="route('operator.complaint.index')" label="Kelola Laporan">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
        @endif
    </x-slot:navigation>

    <x-slot:header>Notifikasi</x-slot:header>
    <x-slot:breadcrumb>Pemberitahuan aktivitas laporan Anda</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        <div class="bg-white rounded-2xl border border-line overflow-hidden card-enter">
            {{-- Toolbar header — sits directly above the first notification row:
                 left = list title + unread badge, right = "mark all as read". --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-line bg-surface">
                <div class="flex items-center gap-3 min-w-0">
                    <h2 class="text-base font-bold text-ink">Semua Pemberitahuan</h2>

                    @if ($unreadCount > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-600 ring-1 ring-inset ring-primary-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                            {{ $unreadCount }} belum dibaca
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <span class="hidden sm:inline text-xs text-ink-ghost">
                        Total {{ $notifications->total() }} notifikasi
                    </span>

                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}" class="shrink-0">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold px-4 py-2 rounded-xl border border-line-strong ui-animated shadow-sm"
                            >
                                <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Tandai semua dibaca
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($notifications->isEmpty())
                <x-dashboard.empty-state message="Belum ada notifikasi." />
            @else
                <div>
                    @foreach ($notifications as $item)
                        <x-notification-item :notification="$item" />
                    @endforeach
                </div>

                @if ($notifications->hasPages())
                    <div class="px-6 py-4 border-t border-line">
                        {{ $notifications->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
