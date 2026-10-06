@php
    $title = 'Kelola Pengguna — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $roleLabels = [
        'masyarakat'  => 'Masyarakat',
        'operator'    => 'Operator',
        'admin'       => 'Admin',
        'super_admin' => 'Super Admin',
    ];
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Kelola Pengguna</x-slot:header>
    <x-slot:breadcrumb>Kelola akun masyarakat, operator, admin, dan super admin</x-slot:breadcrumb>

    {{-- Flash --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('user'))
        <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
            {{ $errors->first('user') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-[#64748B]">Total: <strong>{{ $users->total() }}</strong> pengguna</p>
        <a href="{{ route('super-admin.users.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Pengguna
        </a>
    </div>

    {{-- Search / filter --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('super-admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3" role="search">
            <div class="sm:col-span-2">
                <label for="search" class="block text-xs font-semibold text-[#475569] mb-1">Cari</label>
                <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Nama atau email"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="role" class="block text-xs font-semibold text-[#475569] mb-1">Role</label>
                <select id="role" name="role"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold text-[#475569] mb-1">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="sm:col-span-4 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated">
                    Filter
                </button>
                <a href="{{ route('super-admin.users.index') }}" class="text-sm text-[#64748B] hover:underline">Reset</a>
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
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-center px-4 py-3">Role</th>
                        <th class="text-center px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Dibuat</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse ($users as $user)
                        <tr class="hover:bg-[#F8FAFC]">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-[#0F172A]">{{ $user->name }}</span>
                                @if ($user->id === auth()->id())
                                    <span class="ml-1 text-[10px] font-semibold text-[#2563EB]">(Anda)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#475569]">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#EFF6FF] text-[#1D4ED8] border border-[#BFDBFE]">
                                    {{ $roleLabels[$user->role] ?? $user->role }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($user->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200">Aktif</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#64748B] whitespace-nowrap">
                                {{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('super-admin.users.edit', $user) }}"
                                   class="text-[#2563EB] hover:underline font-semibold">Ubah</a>
                                <span class="text-[#CBD5E1] mx-1">•</span>
                                <form method="POST" action="{{ route('super-admin.users.toggle', $user) }}" class="inline"
                                      @if ($user->is_active) onsubmit="return confirm('Nonaktifkan pengguna ini? Pengguna tidak akan dapat login.');" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="font-semibold {{ $user->is_active ? 'text-amber-600 hover:underline' : 'text-green-600 hover:underline' }}">
                                        {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-[#94A3B8] italic">Tidak ada pengguna yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.dashboard>
