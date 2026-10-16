@php
    $title = 'Buat Tiket Bantuan';
    $currentRoute = request()->routeIs('citizen.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('citizen.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Buat Tiket Bantuan</x-slot:header>
    <x-slot:breadcrumb>Panel Masyarakat — Sampaikan kendala penggunaan aplikasi</x-slot:breadcrumb>

    {{-- The "Kembali" action moved to a bottom ghost link, so the topbar
         (#page-actions) stays clean next to the profile. --}}

    <div class="w-full max-w-full space-y-5 card-enter">
        @if ($errors->any())
            <div class="w-full bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <p class="font-semibold mb-1">Periksa kembali isian Anda:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('citizen.help.store') }}"
              class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @csrf

            <div class="p-6 sm:p-8 space-y-6">
                {{-- ── Baris 1: Kategori (kiri) | Judul (kanan) ─────────────────── --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                    {{-- Kategori Bantuan --}}
                    <div>
                        <label for="category" class="block text-sm font-semibold text-ink mb-1.5">
                            Kategori Bantuan <span class="text-red-500">*</span>
                        </label>
                        <select id="category" name="category" required
                            class="w-full px-4 py-3 text-sm border {{ $errors->has('category') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white rounded-xl text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 transition-all">
                            <option value="">— Pilih kategori —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Judul --}}
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-ink mb-1.5">
                            Judul <span class="text-red-500">*</span>
                        </label>
                        <input id="subject" name="subject" type="text" required maxlength="180" value="{{ old('subject') }}"
                            placeholder="Ringkasan singkat kendala Anda"
                            class="w-full px-4 py-3 text-sm border {{ $errors->has('subject') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white rounded-xl text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 transition-all">
                        @error('subject')
                            <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- ── Baris 2 (full width): Deskripsi Masalah ──────────────────── --}}
                <div>
                    <label for="description" class="block text-sm font-semibold text-ink mb-1.5">
                        Deskripsi Masalah <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description" rows="6" required maxlength="5000"
                        placeholder="Jelaskan kendala yang Anda alami secara detail: langkah yang dilakukan, pesan galat yang muncul, dan kapan hal itu terjadi..."
                        class="w-full px-4 py-3 text-sm border {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white rounded-xl text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 transition-all resize-none leading-relaxed">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ── Baris 3 (full width): Laporan Terkait ────────────────────── --}}
                <div>
                    <label for="related_complaint_id" class="block text-sm font-semibold text-ink mb-1.5">
                        Laporan Terkait <span class="text-xs font-normal text-ink-faint">(opsional)</span>
                    </label>
                    <select id="related_complaint_id" name="related_complaint_id"
                        class="w-full px-4 py-3 text-sm border {{ $errors->has('related_complaint_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white rounded-xl text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 transition-all">
                        <option value="">— Tidak ada laporan terkait —</option>
                        @foreach ($complaints as $complaint)
                            <option value="{{ $complaint->id }}" @selected((string) old('related_complaint_id') === (string) $complaint->id)>
                                {{ $complaint->reference_code }} — {{ \Illuminate\Support\Str::limit($complaint->title, 50) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-ink-faint mt-1.5">Hanya laporan milik Anda yang dapat dipilih. Membuat tiket tidak mengubah status laporan.</p>
                    @error('related_complaint_id')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- ── Action toolbar (kanan bawah) ─────────────────────────────────── --}}
            <div class="px-6 sm:px-8 py-5 bg-page border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('citizen.help.index') }}"
                   class="text-sm font-semibold text-ink-soft hover:text-ink px-5 py-2.5 rounded-xl border border-line-strong bg-white hover:bg-surface-sunken ui-animated">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-6 py-2.5 rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Kirim Tiket
                </button>
            </div>
        </form>

        {{-- ── Bottom navigation (di luar kartu) ────────────────────────────── --}}
        <div class="w-full flex items-center">
            <a href="{{ route('citizen.help.index') }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold px-4 py-2.5 rounded-xl border border-line-strong ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Kembali ke Pusat Bantuan
            </a>
        </div>
    </div>
</x-layouts.dashboard>
