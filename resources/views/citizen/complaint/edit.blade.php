@php
    $title = 'Perbaiki Laporan ' . $complaint->reference_code;
    $maxFiles = (int) config('business_rules.attachment.max_files');
    $maxMb    = (int) (config('business_rules.attachment.max_kilobytes') / 1024);
    $mimes    = config('business_rules.attachment.mimes');
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        @include('citizen.partials.nav', ['currentRoute' => $route])
    </x-slot:navigation>

    <x-slot:header>Perbaiki Laporan</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Perbaiki laporan yang dikembalikan oleh Super Admin</x-slot:breadcrumb>

    <div class="max-w-4xl mx-auto space-y-6 card-enter">
        {{-- Return reason banner (reporter-visible workflow message) --}}
        <div class="bg-orange-50 border border-orange-200 rounded-2xl p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-orange-900">Laporan Anda perlu diperbaiki</h2>
                <p class="text-xs text-orange-800 mt-1 leading-relaxed">
                    Super Admin mengembalikan laporan <span class="font-mono font-semibold">{{ $complaint->reference_code }}</span> agar Anda perbaiki. Perbaiki sesuai catatan di bawah, lalu kirim ulang untuk divalidasi kembali.
                </p>
                @if ($complaint->review_return_reason)
                    <div class="mt-3 p-3 rounded-xl bg-white border border-orange-200">
                        <p class="text-[11px] font-semibold text-orange-700 uppercase tracking-wider">Catatan dari Super Admin</p>
                        <p class="text-sm text-ink-body mt-1 whitespace-pre-line">{{ $complaint->review_return_reason }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                <p class="font-semibold mb-1">Periksa kembali laporan Anda:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('citizen.complaint.update', $complaint) }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="{
                submitting: false,
                selectedCategoryId: '{{ old('category_id', $complaint->category_id) }}',
                categories: {{ Js::from($categoriesForJs) }},
                get currentCategory() {
                    return this.categories.find(c => c.id == this.selectedCategoryId);
                },
                location: locationPicker({
                    kecamatan: {{ Js::from($kecamatanForJs) }},
                    kelurahan: {{ Js::from($kelurahanForJs) }},
                    boundaries: {{ Js::from($boundariesForJs) }},
                    selectedKecamatanId: '{{ old('kecamatan_id', $complaint->kecamatan_id) }}',
                    selectedKelurahanId: '{{ old('kelurahan_id', $complaint->kelurahan_id) }}'
                }),
                get hasKecamatan() {
                    return this.location.hasKecamatan;
                }
            }"
            @location-point.window="location.suggestFromCoordinates($event.detail.lat, $event.detail.lng)"
            @submit="submitting = true"
            class="bg-white rounded-2xl border border-line shadow-sm overflow-hidden"
        >
            @csrf
            @method('PUT')

            <div class="p-6 sm:p-8 space-y-6">
                {{-- Category --}}
                <div>
                    <label for="category_id" class="block text-sm font-semibold text-ink mb-1.5">
                        Kategori Laporan <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="category_id"
                        name="category_id"
                        x-model="selectedCategoryId"
                        required
                        class="w-full rounded-xl border {{ $errors->has('category_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all"
                    >
                        <option value="">-- Pilih Kategori Pengaduan --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id', $complaint->category_id) === (string) $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror

                    <div x-show="currentCategory" x-transition class="mt-3 p-3.5 rounded-xl bg-page border border-line flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-ink">
                                Instansi Penanggung Jawab:
                                <span class="text-primary-600"
                                      x-text="currentCategory ? (currentCategory.mapped_dinas.length ? currentCategory.mapped_dinas.join(', ') : (currentCategory.dinas_name || '—')) : ''"></span>
                            </p>
                            <p class="text-xs text-ink-soft mt-0.5" x-text="currentCategory ? currentCategory.description : ''"></p>
                        </div>
                    </div>
                </div>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-semibold text-ink mb-1.5">
                        Judul Laporan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title', $complaint->title) }}"
                        required
                        minlength="5"
                        maxlength="255"
                        class="w-full rounded-xl border {{ $errors->has('title') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white px-4 py-3 text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all"
                    >
                    @error('title')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location --}}
                <div class="location-section space-y-4">
                    <h3 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Lokasi Kejadian
                    </h3>

                    <div class="administrative-location grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="kecamatan_id" class="block text-sm font-semibold text-ink mb-1.5">
                                Kecamatan <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="kecamatan_id"
                                name="kecamatan_id"
                                x-model="location.selectedKecamatanId"
                                @change="location.onKecamatanChange()"
                                required
                                class="w-full rounded-xl border {{ $errors->has('kecamatan_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all"
                            >
                                <option value="">-- Pilih Kecamatan --</option>
                                @foreach ($kecamatan as $kec)
                                    <option value="{{ $kec->id }}" @selected((string) old('kecamatan_id', $complaint->kecamatan_id) === (string) $kec->id)>{{ $kec->name }}</option>
                                @endforeach
                            </select>
                            @error('kecamatan_id')
                                <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kelurahan_id" class="block text-sm font-semibold text-ink mb-1.5">
                                Kelurahan <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="kelurahan_id"
                                name="kelurahan_id"
                                x-model="location.selectedKelurahanId"
                                :disabled="!location.selectedKecamatanId"
                                required
                                class="w-full rounded-xl border {{ $errors->has('kelurahan_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white px-4 py-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all disabled:bg-surface-sunken disabled:text-ink-ghost disabled:cursor-not-allowed"
                            >
                                <option value=""
                                    x-text="location.selectedKecamatanId ? '-- Pilih Kelurahan --' : 'Pilih Kecamatan terlebih dahulu'"></option>
                                <template x-for="kel in location.filteredKelurahan" :key="kel.id">
                                    <option :value="kel.id" x-text="kel.name"></option>
                                </template>
                            </select>
                            @error('kelurahan_id')
                                <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Auto-detect feedback (map point / GPS → suggested pair).
                         UX ONLY: the pair stays REQUIRED and editable. --}}
                    @if ($boundariesForJs['features'] !== [])
                        <div class="space-y-1" aria-live="polite">
                            <p class="text-xs font-medium text-green-700 flex items-start gap-1.5"
                               x-show="location.detectState === 'matched'" x-cloak>
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Kecamatan &amp; Kelurahan terisi otomatis dari titik lokasi. Periksa kembali dan ubah bila perlu.</span>
                            </p>
                            <p class="text-xs font-medium text-accent-700 flex items-start gap-1.5"
                               x-show="location.detectState === 'outside'" x-cloak>
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>Titik lokasi berada di luar area yang dikenali. Silakan pilih Kecamatan &amp; Kelurahan secara manual.</span>
                            </p>
                        </div>
                        <p class="text-[11px] text-ink-faint mt-1">
                            Deteksi wilayah memakai data batas indikatif
                            (<a href="https://github.com/cahyadsn/wilayah_boundaries" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">cahyadsn/wilayah_boundaries</a>, MIT).
                        </p>
                    @endif

                    <div
                        class="location-detail"
                        x-data="locationAddress({
                            enabled: {{ $geocodeForJs['enabled'] ? 'true' : 'false' }},
                            url: {{ Js::from($geocodeForJs['url']) }},
                            email: {{ Js::from($geocodeForJs['email']) }},
                            locale: {{ Js::from($geocodeForJs['locale']) }},
                            attribution: {{ Js::from($geocodeForJs['attribution']) }},
                            maxLength: 255,
                            initialText: {{ Js::from(old('location_text', $complaint->location_text ?? '')) }}
                        })"
                        @location-point.window="handlePoint($event.detail.lat, $event.detail.lng)"
                    >
                        <label for="location_text" class="block text-sm font-semibold text-ink mb-1.5">
                            Lokasi / Alamat Detail <span class="text-xs font-normal text-ink-faint">(Opsional — jalan, nomor rumah, patokan)</span>
                        </label>
                        <input
                            type="text"
                            id="location_text"
                            name="location_text"
                            x-model="text"
                            value="{{ old('location_text', $complaint->location_text) }}"
                            maxlength="255"
                            class="w-full rounded-xl border {{ $errors->has('location_text') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white px-4 py-3 text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all"
                        >
                        @error('location_text')
                            <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                        @enderror

                        @if ($geocodeForJs['enabled'])
                            <label x-show="canLookup" class="flex items-start gap-2 mt-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    x-model="optIn"
                                    class="mt-0.5 h-4 w-4 rounded border-line-strong text-primary-600 focus:ring-2 focus:ring-primary-600"
                                >
                                <span class="text-xs text-ink-soft leading-relaxed">
                                    Isi alamat otomatis dari titik lokasi.
                                    <span class="text-ink-faint">Koordinat akan dikirim ke layanan peta
                                        (<span class="font-medium" x-text="attribution"></span>) hanya saat Anda menandai titik.</span>
                                </span>
                            </label>
                            <p
                                class="text-xs font-medium mt-1.5"
                                :class="{
                                    'text-ink-faint': lookupState === 'loading' || lookupState === 'skipped',
                                    'text-green-700': lookupState === 'done',
                                    'text-accent-700': lookupState === 'empty',
                                    'text-red-600': lookupState === 'error',
                                }"
                                aria-live="polite"
                                x-show="lookupMessage !== ''"
                                x-cloak
                                x-text="lookupMessage"
                            ></p>
                        @endif
                    </div>

                    <x-location-map-slot
                        :latitude="old('latitude', $complaint->latitude)"
                        :longitude="old('longitude', $complaint->longitude)"
                        label="Peta Lokasi Kejadian"
                    />
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-semibold text-ink mb-1.5">
                        Isi Rincian Laporan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        required
                        minlength="10"
                        maxlength="5000"
                        class="w-full rounded-xl border {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong' }} bg-white p-4 text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition-all leading-relaxed"
                    >{{ old('description', $complaint->description) }}</textarea>
                    @error('description')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Existing attachments --}}
                @if ($complaint->attachments->isNotEmpty())
                    <div>
                        <p class="block text-sm font-semibold text-ink mb-1.5">
                            Lampiran Saat Ini
                            <span class="text-xs font-normal text-ink-faint">(centang untuk menghapus)</span>
                        </p>
                        <ul class="space-y-2">
                            @foreach ($complaint->attachments as $attachment)
                                <li class="flex items-center gap-3 rounded-xl bg-page border border-line px-3 py-2">
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}"
                                        id="remove_attachment_{{ $attachment->id }}"
                                        class="rounded border-line-strong text-red-600 focus:ring-red-500">
                                    <label for="remove_attachment_{{ $attachment->id }}" class="flex-1 min-w-0 cursor-pointer">
                                        <span class="block text-xs font-semibold text-ink truncate">{{ $attachment->original_name }}</span>
                                        <span class="block text-[11px] text-ink-faint">{{ $attachment->humanSize() }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- New attachments --}}
                <div x-data="attachmentPicker({ max: {{ $maxFiles }}, maxMb: {{ $maxMb }}, mimes: {{ Js::from($mimes) }} })">
                    <label for="attachments" class="block text-sm font-semibold text-ink mb-1.5">
                        Tambah Lampiran Baru
                        <span class="text-xs font-normal text-ink-faint">(Opsional, maks. {{ $maxFiles }} berkas total)</span>
                    </label>
                    <div class="border-2 border-dashed border-line-strong hover:border-primary-600 rounded-2xl p-6 text-center transition-all bg-page">
                        <input
                            type="file"
                            id="attachments"
                            name="attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf,.mp4"
                            x-ref="input"
                            @change="onChange($event)"
                            class="block w-full text-xs text-ink-soft file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700 file:cursor-pointer cursor-pointer"
                        >
                        <p class="text-xs text-ink-faint mt-2.5">
                            Format: <strong>JPG, JPEG, PNG, PDF, MP4</strong> (Maksimal {{ $maxMb }} MB per berkas).
                        </p>
                        <ul x-show="items.length" x-cloak class="mt-4 space-y-2 text-left">
                            <template x-for="item in items" :key="item.id">
                                <li class="flex items-center gap-3 rounded-xl bg-white border border-line px-3 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-semibold text-ink truncate" x-text="item.name"></span>
                                        <span class="block text-[11px] text-ink-faint" x-text="sizeLabel(item.size)"></span>
                                    </span>
                                    <button type="button" @click="remove(item.id)"
                                        class="shrink-0 w-7 h-7 rounded-full text-ink-ghost hover:text-error hover:bg-error-soft flex items-center justify-center ui-animated"
                                        :aria-label="`Hapus ${item.name}`">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                    @error('attachments')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                    @error('attachments.*')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="px-6 sm:px-8 py-5 bg-page border-t border-line flex items-center justify-between gap-4">
                <a href="{{ route('citizen.complaint.show', $complaint) }}"
                   class="text-sm font-semibold text-ink-soft hover:text-ink px-4 py-2.5 rounded-xl border border-line-strong bg-white hover:bg-surface-sunken ui-animated">
                    Batal
                </a>
                <button type="submit" :disabled="submitting || !hasKecamatan"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-6 py-2.5 rounded-xl ui-animated shadow-sm hover:shadow active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="submitting ? 'Mengirim Ulang...' : 'Kirim Ulang Laporan'">Kirim Ulang Laporan</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
