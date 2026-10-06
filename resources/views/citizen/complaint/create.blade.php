@php
    $title = 'Buat Laporan Baru';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('citizen.dashboard') }}"
               class="nav-link {{ $route === 'citizen.dashboard' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('citizen.complaint.create') }}"
               class="nav-link {{ $route === 'citizen.complaint.create' ? 'active' : '' }}"
               aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Laporan
            </a>
            <a href="{{ route('citizen.history') }}"
               class="nav-link {{ $route === 'citizen.history' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Riwayat Laporan
            </a>
            <a href="{{ route('citizen.profile.edit') }}"
               class="nav-link {{ $route === 'citizen.profile.edit' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil Saya
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Buat Laporan Pengaduan</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Sampaikan aspirasi atau pengaduan Anda</x-slot:breadcrumb>

    <div class="max-w-4xl mx-auto space-y-6 card-enter">
        {{-- Security and Privacy Banner --}}
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-[#2563EB]/10 text-[#2563EB] flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-[#0F172A]">Kerahasiaan & Keamanan Terjamin</h2>
                <p class="text-xs text-[#475569] mt-0.5 leading-relaxed">
                    Setiap laporan yang Anda kirimkan diproses langsung oleh Pemerintah Kota Parepare dan dinas terkait. Berkas lampiran disimpan secara aman dalam sistem terisolasi.
                </p>
            </div>
        </div>

        @if ($errors->has('daily_limit'))
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ $errors->first('daily_limit') }}</span>
            </div>
        @endif

        <form
            action="{{ route('citizen.complaint.store') }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="{
                submitting: false,
                selectedCategoryId: '{{ old('category_id', '') }}',
                categories: {{ Js::from($categoriesForJs) }},
                get currentCategory() {
                    return this.categories.find(c => c.id == this.selectedCategoryId);
                }
            }"
            @submit="submitting = true"
            class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden"
        >
            @csrf

            <div class="p-6 sm:p-8 space-y-6">
                {{-- Category selection with live Dinas preview --}}
                <div>
                    <label for="category_id" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Kategori Laporan <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="category_id"
                            name="category_id"
                            x-model="selectedCategoryId"
                            required
                            class="w-full rounded-xl border {{ $errors->has('category_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                        >
                            <option value="">-- Pilih Kategori Pengaduan --</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('category_id')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror

                    {{-- Dynamic Dinas Target Card --}}
                    <div
                        x-show="currentCategory"
                        x-transition
                        class="mt-3 p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex items-start gap-3"
                    >
                        <div class="w-8 h-8 rounded-lg bg-[#DBEAFE] text-[#2563EB] flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-[#0F172A]">
                                Instansi Penanggung Jawab:
                                <span class="text-[#2563EB]"
                                      x-text="currentCategory ? (currentCategory.mapped_dinas.length ? currentCategory.mapped_dinas.join(', ') : (currentCategory.dinas_name || '—')) : ''"></span>
                            </p>
                            <p class="text-xs text-[#475569] mt-0.5" x-text="currentCategory ? currentCategory.description : ''"></p>
                        </div>
                    </div>
                </div>

                {{-- Complaint Title --}}
                <div>
                    <label for="title" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Judul Laporan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title') }}"
                        required
                        minlength="5"
                        maxlength="255"
                        placeholder="Contoh: Lampu penerangan jalan padam di Jl. Bau Massepe"
                        class="w-full rounded-xl border {{ $errors->has('title') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                    >
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-xs text-[#64748B]">Tuliskan ringkasan pokok permasalahan dengan jelas dan padat.</p>
                        @error('title')
                            <p class="text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Location text --}}
                <div>
                    <label for="location_text" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Lokasi Kejadian <span class="text-xs font-normal text-[#64748B]">(Opsional tapi sangat disarankan)</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#94A3B8]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <input
                            type="text"
                            id="location_text"
                            name="location_text"
                            value="{{ old('location_text') }}"
                            placeholder="Contoh: Depan Rujab Walikota, Jl. Jenderal Sudirman No. 1, Parepare"
                            maxlength="255"
                            class="w-full pl-10 rounded-xl border {{ $errors->has('location_text') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                        >
                    </div>
                    @error('location_text')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Complaint Description --}}
                <div>
                    <label for="description" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Isi Rincian Laporan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        required
                        minlength="10"
                        maxlength="5000"
                        placeholder="Jelaskan secara lengkap kronologi permasalahan: waktu kejadian, kendala yang dihadapi, dan dampaknya bagi warga sekitar..."
                        class="w-full rounded-xl border {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white p-4 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all leading-relaxed"
                    >{{ old('description') }}</textarea>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-xs text-[#64748B]">Minimal 10 karakter. Berikan detail selengkap mungkin agar mempermudah penanganan.</p>
                        @error('description')
                            <p class="text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Attachments Upload --}}
                <div>
                    <label class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Unggah Bukti / Foto Lampiran <span class="text-xs font-normal text-[#64748B]">(Maks. 3 berkas)</span>
                    </label>
                    <div class="border-2 border-dashed border-[#CBD5E1] hover:border-[#2563EB] rounded-2xl p-6 text-center transition-all bg-[#F8FAFC]">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-blue-50 text-[#2563EB] flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input
                            type="file"
                            id="attachments"
                            name="attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf,.mp4"
                            class="block w-full text-xs text-[#475569] file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#2563EB] file:text-white hover:file:bg-[#1D4ED8] file:cursor-pointer cursor-pointer"
                        >
                        <p class="text-xs text-[#64748B] mt-2.5">
                            Format yang didukung: <strong>JPG, JPEG, PNG, PDF, MP4</strong> (Maksimal 20 MB per berkas, maksimal 10 berkas).
                        </p>
                    </div>
                    @error('attachments')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                    @error('attachments.*')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form footer actions --}}
            <div class="px-6 sm:px-8 py-5 bg-[#F8FAFC] border-t border-[#E2E8F0] flex items-center justify-between gap-4">
                <a
                    href="{{ route('citizen.dashboard') }}"
                    class="text-sm font-semibold text-[#475569] hover:text-[#0F172A] px-4 py-2.5 rounded-xl border border-[#CBD5E1] bg-white hover:bg-[#F1F5F9] ui-animated"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold px-6 py-2.5 rounded-xl ui-animated shadow-sm hover:shadow active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <svg
                        x-show="submitting"
                        class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <svg
                        x-show="!submitting"
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <span x-text="submitting ? 'Mengirim Laporan...' : 'Kirim Laporan'">Kirim Laporan</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
