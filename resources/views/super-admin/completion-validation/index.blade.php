@php
    $title = 'Validasi Penyelesaian Laporan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Validasi Penyelesaian</x-slot:header>
    <x-slot:breadcrumb>Pengajuan penyelesaian dari Operator menunggu validasi Super Admin sebelum laporan dinyatakan selesai</x-slot:breadcrumb>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <p class="font-semibold mb-1">Terjadi kesalahan:</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-ink-faint">
            Total menunggu validasi penyelesaian: <strong class="text-ink">{{ $complaints->total() }}</strong> laporan
            @if ($complaints->total() > 0)
                <span>• menampilkan {{ $complaints->firstItem() }}–{{ $complaints->lastItem() }}</span>
            @endif
        </p>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold bg-accent-50 text-accent-700 border border-accent-200">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Urut terlama mengajukan
        </span>
    </div>

    {{-- Results --}}
    <h2 class="sr-only">Antrian validasi penyelesaian</h2>
    <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden card-enter">
        @if ($complaints->isEmpty())
            <div class="empty-state py-16 text-center">
                <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-semibold text-ink-soft">Tidak ada pengajuan penyelesaian menunggu validasi.</p>
                <p class="text-xs text-ink-faint mt-1">Pengajuan penyelesaian dari Operator akan muncul di sini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Antrian validasi penyelesaian: pengajuan penyelesaian dari Operator yang menunggu validasi Super Admin sebelum laporan dinyatakan selesai</caption>
                    <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Nomor Registrasi</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold">Judul / Kategori</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Dinas/Unit</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Operator</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Menunggu</th>
                            <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-sunken">
                        @foreach ($complaints as $laporan)
                            @php
                                $status = $laporan->status;
                                $waitingDays = $laporan->completion_submitted_at ? (int) $laporan->completion_submitted_at->diffInDays(now()) : null;
                            @endphp
                            <tr class="hover:bg-page transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $laporan->reference_code }}</span>
                                </td>
                                <td class="px-4 py-4 min-w-[15rem]">
                                    <p class="font-semibold text-ink line-clamp-2">{{ $laporan->title }}</p>
                                    <p class="text-xs text-ink-faint mt-1">{{ $laporan->category?->name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-ink-soft">{{ $laporan->dinasUnit?->name ?? 'Belum ditentukan' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-ink-soft">{{ $laporan->resolvedOperator()?->name ?? '—' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="block text-sm font-semibold text-ink">{{ $waitingDays !== null ? $waitingDays . ' hari' : '—' }}</span>
                                    <span class="block text-[11px] text-ink-faint mt-0.5">{{ $laporan->completion_submitted_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('super-admin.completion-validation.show', $laporan) }}" aria-label="Validasi penyelesaian laporan {{ $laporan->reference_code }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                        Validasi
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($complaints->hasPages())
                <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                    {{ $complaints->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.dashboard>
