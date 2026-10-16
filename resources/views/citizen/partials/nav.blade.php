@php
    $currentRoute = $currentRoute ?? (request()->routeIs('citizen.*') ? request()->route()->getName() : '');

    // Citizen navigation (shared across all citizen pages). Icons are inline
    // 24x24 stroke SVGs. Labels stay in the DOM for accessibility/tests.
    //
    // There is deliberately NO "Profil Saya" item here: profile access is
    // reached through the account element in the topbar / sidebar footer, which
    // already links to the real profile page for every role.
    $citizenNav = [
        ['label' => 'Dashboard',       'route' => 'citizen.dashboard',        'match' => fn ($r) => $r === 'citizen.dashboard',                 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['label' => 'Buat Laporan',    'route' => 'citizen.complaint.create', 'match' => fn ($r) => $r === 'citizen.complaint.create',          'icon' => 'M12 4v16m8-8H4'],
        ['label' => 'Riwayat Laporan', 'route' => 'citizen.history',          'match' => fn ($r) => $r === 'citizen.history',                   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['label' => 'Pusat Bantuan',   'route' => 'citizen.help.index',       'match' => fn ($r) => str_starts_with($r, 'citizen.help.'),       'icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'],
    ];
@endphp

@foreach ($citizenNav as $item)
    <x-dashboard.nav-rail-link
        :href="route($item['route'])"
        :label="$item['label']"
        :active="($item['match'])($currentRoute)"
    >
        <x-slot:icon>
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
            </svg>
        </x-slot:icon>
    </x-dashboard.nav-rail-link>
@endforeach
