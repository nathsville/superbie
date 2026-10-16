@php
    $title = 'Kelola Pengguna — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $roleLabels = [
        'masyarakat'  => 'Masyarakat',
        'operator'    => 'Operator',
        'admin'       => 'Admin',
        'super_admin' => 'Super Admin',
    ];
    // Pastel pill palette per role — keeps the "Role" column visually stable.
    $roleBadge = [
        'masyarakat'  => 'bg-surface-sunken text-ink-soft border-line',
        'operator'    => 'bg-teal-50 text-teal-700 border-teal-200',
        'admin'       => 'bg-primary-50 text-primary-700 border-primary-200',
        'super_admin' => 'bg-purple-50 text-purple-700 border-purple-200',
    ];
    $hasFilters = !empty($filters['search']) || !empty($filters['role']) || !empty($filters['status']);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Kelola Pengguna</x-slot:header>
    <x-slot:breadcrumb>Kelola akun masyarakat, operator, admin, dan super admin</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('user'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('user') }}
            </div>
        @endif

        {{-- ── Filter & toolbar ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong class="text-ink">{{ $users->total() }}</strong> pengguna
                    </p>
                </div>
                <a href="{{ route('super-admin.users.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Pengguna
                </a>
            </div>

            <form method="GET" action="{{ route('super-admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="search" aria-label="Pencarian dan filter pengguna">
                <div>
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Nama atau email"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                </div>
                <div>
                    <label for="role" class="block text-xs font-semibold text-ink-soft mb-1.5">Role</label>
                    <select id="role" name="role"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                    </select>
                </div>

                <div class="sm:col-span-3 flex items-center justify-end gap-2 pt-3 border-t border-surface-sunken">
                    @if ($hasFilters)
                        <a href="{{ route('super-admin.users.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                            Reset
                        </a>
                    @endif
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Table ────────────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar pengguna</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($users->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Tidak ada pengguna yang ditemukan.</p>
                    <p class="text-xs text-ink-faint mt-1">Ubah kata kunci atau filter untuk melihat hasil lain.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar pengguna beserta role, Dinas/Unit, status akun dan waktu pembuatan</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Nama</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[15rem]">Email</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Role</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Dinas/Unit</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Dibuat</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($users as $user)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="font-semibold text-ink">{{ $user->name }}</span>
                                        @if ($user->id === auth()->id())
                                            <span class="ml-1.5 text-[10px] font-semibold text-primary-600 bg-primary-50 border border-primary-100 px-1.5 py-0.5 rounded-full">Anda</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-ink-soft">{{ $user->email }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="status-badge border {{ $roleBadge[$user->role] ?? 'bg-surface-sunken text-ink-soft border-line' }}">
                                            {{ $roleLabels[$user->role] ?? $user->role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-ink-soft">
                                        {{ $user->dinasUnit?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($user->is_active)
                                            <span class="status-badge border bg-green-50 text-green-700 border-green-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="status-badge border bg-surface-sunken text-ink-soft border-line">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Nonaktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="block text-xs font-medium text-ink-body">{{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                        <span class="block text-[11px] text-ink-faint mt-0.5">{{ $user->created_at?->translatedFormat('H:i') ?? '' }} WITA</span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('super-admin.users.edit', $user) }}"
                                               aria-label="Ubah pengguna {{ $user->name }}" title="Ubah"
                                               class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 text-primary-600 hover:bg-primary-100 ui-animated">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('super-admin.users.toggle', $user) }}" class="inline-flex"
                                                  @if ($user->is_active) onsubmit="return confirm('Nonaktifkan pengguna ini? Pengguna tidak akan dapat login.');" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    aria-label="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} pengguna {{ $user->name }}"
                                                    title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg ui-animated {{ $user->is_active ? 'bg-accent-50 text-accent-600 hover:bg-accent-100' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
