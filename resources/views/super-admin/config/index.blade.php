@php
    $title = 'Konfigurasi — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Konfigurasi</x-slot:header>
    <x-slot:breadcrumb>Konfigurasi operasional yang didukung aplikasi</x-slot:breadcrumb>

    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-4">
        <p class="text-sm text-[#64748B]">
            Hanya konfigurasi yang memiliki pengaruh nyata pada aplikasi yang ditampilkan di sini.
        </p>
    </div>

    @if (empty($settings))
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-8 text-center card-enter">
            <p class="text-sm text-[#94A3B8] italic">Tidak ada konfigurasi yang dapat dikelola.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden card-enter">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#F8FAFC] text-[#475569] text-xs uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Konfigurasi</th>
                            <th class="text-center px-4 py-3">Tipe</th>
                            <th class="text-center px-4 py-3">Nilai Aktif</th>
                            <th class="text-center px-4 py-3">Sumber</th>
                            <th class="text-left px-4 py-3">Diperbarui</th>
                            <th class="text-right px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($settings as $setting)
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-[#0F172A]">{{ $setting['label'] }}</span>
                                    <span class="block font-mono text-[11px] text-[#94A3B8]">{{ $setting['key'] }}</span>
                                    <span class="block text-xs text-[#64748B] mt-0.5">{{ $setting['description'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-[#475569]">{{ $setting['type'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-[#EFF6FF] text-[#1D4ED8] border border-[#BFDBFE]">
                                        {{ $setting['effective'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($setting['is_overridden'])
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Diatur</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Default</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[#64748B] whitespace-nowrap">
                                    {{ $setting['updated_at']?->translatedFormat('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('super-admin.config.edit', $setting['key']) }}"
                                       class="text-[#2563EB] hover:underline font-semibold">Ubah</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-[#94A3B8]">
            Default ditentukan di <span class="font-mono">config/business_rules.php</span> (sumber kebenaran tunggal).
            Nilai di sini hanya menimpa default tersebut dan divalidasi sesuai aturan konsumen yang ada.
        </p>
    @endif
</x-layouts.dashboard>
