@php
    $title = 'Pusat Bantuan';
    $currentRoute = request()->routeIs('citizen.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('citizen.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Pusat Bantuan</x-slot:header>
    <x-slot:breadcrumb>Panel Masyarakat — Tiket bantuan penggunaan aplikasi</x-slot:breadcrumb>

    {{-- The "+ Buat Tiket Bantuan" CTA lives in the content toolbar below, so the
         topbar (#page-actions) stays clean next to the profile. --}}

    <div class="w-full max-w-full space-y-5 card-enter">
        @if (session('success'))
            <div class="w-full flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Toolbar: deskripsi + aksi utama (satu baris) ─────────────────── --}}
        <div class="w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-ink-faint max-w-3xl">
                Sampaikan kendala penggunaan aplikasi (akun, login, pengiriman laporan, dsb).
                Tiket bantuan <strong class="text-ink-body">terpisah</strong> dari laporan masyarakat dan tidak mengubah status laporan Anda.
            </p>
            <a href="{{ route('citizen.help.create') }}"
               class="inline-flex items-center justify-center gap-2 shrink-0 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Buat Tiket Bantuan
            </a>
        </div>

        {{-- ── Tabel daftar tiket (full width) ──────────────────────────────── --}}
        <h2 class="sr-only">Daftar tiket bantuan saya</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($tickets->isEmpty())
                <div class="empty-state py-16 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-primary-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-ink mb-1">Belum Ada Tiket Bantuan</h3>
                    <p class="text-sm text-ink-soft mb-4">Tiket bantuan yang Anda buat akan tercatat di sini.</p>
                    <a href="{{ route('citizen.help.create') }}" class="inline-flex items-center gap-2 bg-primary-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated hover:bg-primary-700">
                        Buat Tiket Sekarang
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar tiket bantuan yang pernah Anda buat beserta kategori, status, waktu pembaruan, dan tautan detail</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 sm:px-6 py-3.5 font-semibold whitespace-nowrap">No. Tiket</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[16rem]">Judul</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap hidden sm:table-cell">Kategori</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap hidden md:table-cell">Diperbarui</th>
                                <th scope="col" class="text-right px-4 sm:px-6 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($tickets as $ticket)
                                @php $status = $ticket->status; @endphp
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-mono text-xs font-semibold text-ink-body bg-page px-2.5 py-1 rounded-lg border border-line">{{ $ticket->reference_code }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-ink max-w-md truncate">{{ $ticket->subject }}</p>
                                        <p class="text-xs text-ink-faint mt-0.5 truncate">{{ $ticket->category->label() }}</p>
                                    </td>
                                    <td class="px-4 py-4 hidden sm:table-cell text-ink-soft whitespace-nowrap">
                                        {{ $ticket->category->label() }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="status-badge {{ $status->tailwindBadge() }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ $status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 hidden md:table-cell whitespace-nowrap">
                                        <span class="block text-xs font-medium text-ink-body">{{ $ticket->updated_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                        <span class="block text-[11px] text-ink-faint">{{ $ticket->updated_at?->translatedFormat('H:i') ?? '' }} WITA</span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('citizen.help.show', $ticket) }}"
                                           aria-label="Buka detail tiket {{ $ticket->reference_code }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            Detail
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tickets->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $tickets->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
