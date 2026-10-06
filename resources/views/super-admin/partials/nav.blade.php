@php
    $currentRoute = $currentRoute ?? (request()->routeIs('super-admin.*') ? request()->route()->getName() : '');

    // Only routes registered in this baseline are shown as links; the rest
    // render as disabled placeholders instead of dead links.
    $superAdminNav = [
        ['label' => 'Dashboard',          'route' => 'super-admin.dashboard'],
        ['label' => 'Semua Laporan',      'route' => 'super-admin.complaints.index'],
        ['label' => 'Kelola Pengguna',    'route' => 'super-admin.users.index'],
        ['label' => 'Kategori',           'route' => 'super-admin.categories.index'],
        ['label' => 'Dinas/Unit',         'route' => 'super-admin.dinas.index'],
        ['label' => 'Konfigurasi',        'route' => 'super-admin.config.index'],
        ['label' => 'Audit & Keamanan',   'route' => 'super-admin.audit.index'],
    ];
@endphp
<div class="space-y-1">
    @foreach ($superAdminNav as $item)
        @if (\Illuminate\Support\Facades\Route::has($item['route']))
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ str_starts_with($currentRoute, $item['route']) ? 'active' : '' }}"
               @if(str_starts_with($currentRoute, $item['route'])) aria-current="page" @endif>
                {{ $item['label'] }}
            </a>
        @else
            <span class="nav-link opacity-50 cursor-not-allowed"
                  aria-disabled="true"
                  title="Belum tersedia pada baseline ini">
                {{ $item['label'] }}
                <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">Soon</span>
            </span>
        @endif
    @endforeach
</div>
