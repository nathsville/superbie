@php $title = 'Lapor Pak Wali — SuperBie'; @endphp

<x-layouts.app :title="$title">
    {{-- Header --}}
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E2E8F0] px-4 sm:px-8 py-3.5">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-[#0F172A] text-base leading-tight block">SuperBie</span>
                    <span class="text-[10px] text-[#475569] font-semibold uppercase tracking-wider">Command Center Kota Parepare</span>
                </div>
            </div>

            <nav class="flex items-center gap-3" aria-label="Navigasi publik">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 bg-[#2563EB] text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-[#1D4ED8] ui-animated">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-[#475569] hover:text-[#0F172A] px-3 py-2 rounded-lg hover:bg-slate-100 ui-animated">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 bg-[#2563EB] text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-[#1D4ED8] ui-animated shadow-sm">
                        Daftar Akun
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        {{-- Hero --}}
        <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center page-enter">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#DBEAFE] text-[#1D4ED8] mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-[#2563EB]"></span>
                Layanan Pengaduan Resmi Kota Parepare
            </span>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-[#0F172A] tracking-tight mb-6">
                Lapor Pak Wali
            </h1>

            <p class="text-lg sm:text-xl text-[#475569] max-w-2xl mx-auto mb-10 leading-relaxed">
                Sampaikan aspirasi, keluhan, dan laporan Anda secara langsung kepada Pemerintah Kota Parepare. Pantau perkembangan penanganan secara transparan.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                @auth
                    <a href="{{ route('citizen.complaint.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-base px-8 py-3.5 rounded-xl shadow-md hover:shadow-lg ui-animated active:scale-[0.98]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Buat Pengaduan Sekarang
                    </a>
                @else
                    <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-base px-8 py-3.5 rounded-xl shadow-md hover:shadow-lg ui-animated active:scale-[0.98]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Mulai Melapor (Daftar Akun)
                    </a>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-[#0F172A] font-semibold text-base px-6 py-3.5 rounded-xl border border-[#E2E8F0] ui-animated">
                        Sudah Punya Akun? Masuk
                    </a>
                @endauth
            </div>

            {{-- Privacy reminder --}}
            <p class="text-xs text-[#475569] mt-4 flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4 text-[#0F766E]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Masyarakat wajib login untuk mengirim laporan. Data pribadi Anda terlindungi.
            </p>
        </section>

        {{-- How it works --}}
        <section class="bg-white border-y border-[#E2E8F0] py-16 px-4 sm:px-6">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-2xl font-bold text-center text-[#0F172A] mb-12">Alur Pengaduan</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center card-enter">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-2xl bg-[#DBEAFE] flex items-center justify-center text-[#2563EB] font-bold text-lg">1</div>
                        <h3 class="text-base font-bold text-[#0F172A] mb-2">Daftar & Masuk</h3>
                        <p class="text-sm text-[#475569]">Buat akun masyarakat dengan email aktif, lalu masuk ke panel pelapor Anda.</p>
                    </div>
                    <div class="text-center card-enter">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-2xl bg-[#CCFBF1] flex items-center justify-center text-[#0F766E] font-bold text-lg">2</div>
                        <h3 class="text-base font-bold text-[#0F172A] mb-2">Pilih Kategori & Kirim</h3>
                        <p class="text-sm text-[#475569]">Pilih kategori keluhan. Sistem akan otomatis menampilkan dinas/unit terkait tujuan laporan.</p>
                    </div>
                    <div class="text-center card-enter">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-lg">3</div>
                        <h3 class="text-base font-bold text-[#0F172A] mb-2">Pantau Perkembangan</h3>
                        <p class="text-sm text-[#475569]">Dapatkan nomor referensi laporan dan pantau status serta respons dari petugas berwenang.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="bg-[#F8FAFC] border-t border-[#E2E8F0] py-8 px-4 text-center text-xs text-[#475569]">
        <p class="font-medium text-[#0F172A]">SuperBie — Command Center Lapor Pak Wali</p>
        <p class="mt-1">Pemerintah Kota Parepare, Sulawesi Selatan</p>
    </footer>
</x-layouts.app>
