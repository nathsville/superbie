@php
    $title = 'Dinas/Unit — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Master Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>Kelola Dinas/Unit dan Mapping Kategori</x-slot:breadcrumb>

    {{-- Flash --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-[#64748B]">Total: <strong>{{ $dinasUnits->total() }}</strong> Dinas/Unit</p>
        <a href="{{ route('super-admin.dinas.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Dinas/Unit
        </a>
    </div>

    {{-- Search / filter --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('super-admin.dinas.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="search">
            <div class="sm:col-span-2">
                <label for="search" class="block text-xs font-semibold text-[#475569] mb-1">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}"
                    placeholder="Nama atau kode Dinas/Unit"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold text-[#475569] mb-1">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated">
                    Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden card-enter">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] text-[#475569] text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3">Nama</th>
                        <th class="text-left px-4 py-3">Kode</th>
                        <th class="text-center px-4 py-3">Status</th>
                        <th class="text-center px-4 py-3">Kategori</th>
                        <th class="text-center px-4 py-3">Laporan</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse ($dinasUnits as $unit)
                        <tr class="hover:bg-[#F8FAFC]">
                            <td class="px-4 py-3 font-semibold text-[#0F172A]">{{ $unit->name }}</td>
                            <td class="px-4 py-3 text-[#64748B]">{{ $unit->code ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($unit->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200">Aktif</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-[#64748B]">{{ $unit->categories_count }}</td>
                            <td class="px-4 py-3 text-center text-[#64748B]">{{ $unit->complaints_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('super-admin.dinas.edit', $unit) }}"
                                   class="text-[#2563EB] hover:underline font-semibold">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-[#94A3B8] italic">Belum ada Dinas/Unit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $dinasUnits->links() }}</div>
</x-layouts.dashboard>
