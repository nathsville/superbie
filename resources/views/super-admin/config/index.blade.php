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

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-ink tracking-wide">Pengaturan Operasional</h2>
            <p class="text-sm text-ink-faint mt-1">
                Hanya konfigurasi yang memiliki pengaruh nyata pada aplikasi yang ditampilkan di sini.
            </p>
        </div>

        @if (empty($settings))
            <div class="w-full bg-white rounded-2xl border border-line shadow-sm py-16 text-center">
                <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p class="text-sm font-semibold text-ink-soft">Tidak ada konfigurasi yang dapat dikelola.</p>
            </div>
        @else
            {{-- ── Table ────────────────────────────────────────────────────── --}}
            <h2 class="sr-only">Daftar konfigurasi</h2>
            <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar konfigurasi beserta tipe, nilai aktif, sumber nilai, waktu pembaruan, dan aksi</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[18rem]">Konfigurasi</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Tipe</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Nilai Aktif</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Sumber</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Diperbarui</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($settings as $setting)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4">
                                        <span class="font-semibold text-ink">{{ $setting['label'] }}</span>
                                        <span class="mt-1 block">
                                            <span class="inline-flex font-mono text-[11px] text-ink-soft bg-page px-2 py-0.5 rounded-md border border-line">{{ $setting['key'] }}</span>
                                        </span>
                                        <span class="block text-xs text-ink-faint mt-1">{{ $setting['description'] }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full bg-surface-sunken text-ink-soft border border-line px-2.5 py-0.5 text-[11px] font-semibold">{{ $setting['type'] }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full bg-primary-50 text-primary-700 border border-primary-100 px-3 py-1 text-xs font-semibold">
                                            {{ $setting['effective'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        @if ($setting['is_overridden'])
                                            <span class="inline-flex items-center gap-1 rounded-full text-[11px] font-semibold bg-accent-50 text-accent-700 border border-accent-200 px-2.5 py-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Diatur
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full text-[11px] font-semibold bg-surface-sunken text-ink-soft border border-line px-2.5 py-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Default
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-ink-faint whitespace-nowrap">
                                        {{ $setting['updated_at']?->translatedFormat('d M Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.config.edit', $setting['key']) }}"
                                           aria-label="Ubah konfigurasi {{ $setting['label'] }}" title="Ubah"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Ubah
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── Footer note ──────────────────────────────────────────────── --}}
            <div class="w-full flex items-start gap-2.5 bg-page border border-line rounded-2xl px-4 py-3.5">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs text-ink-faint leading-relaxed">
                    Default ditentukan di <span class="font-mono text-ink-soft">config/business_rules.php</span> (sumber kebenaran tunggal).
                    Nilai di sini hanya menimpa default tersebut dan divalidasi sesuai aturan konsumen yang ada.
                </p>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
