@php
    $title = 'Lapor Pak Wali';

    /**
     * The four stages of a report, in the order the app really works. The order
     * words carry the sequence, so there are no digits, no icons and no cards.
     */
    $stages = [
        [
            'line' => 'Pertama, kirim laporan.',
            'detail' => 'Buat akun dengan email aktif, lalu tulis laporan: judul, uraian, kecamatan, kelurahan, dan foto bila ada.',
        ],
        [
            'line' => 'Lalu sistem menunjuk dinas.',
            'detail' => 'Kategori yang kamu pilih menentukan unit yang berwenang. Kamu tidak perlu tahu nama dinasnya.',
        ],
        [
            'line' => 'Dinas memverifikasi dan menangani.',
            'detail' => 'Petugas memeriksa laporan, menindaklanjuti di lapangan, dan menuliskan tanggapan pada laporanmu.',
        ],
        [
            'line' => 'Terakhir, kamu menilai.',
            'detail' => 'Setiap perubahan status tercatat di riwayat laporan, dan pemberitahuannya masuk ke akunmu.',
        ],
    ];

    $faq = [
        [
            'q' => 'Siapa yang membaca laporan saya?',
            'a' => 'Petugas berwenang pada unit penanggung jawab kategori laporan. Rincian laporan hanya terbuka untuk pemilik laporan dan petugas yang menanganinya; catatan internal petugas tidak ditampilkan ke pelapor.',
        ],
        [
            'q' => 'Apakah nama saya ditampilkan?',
            'a' => 'Tidak. Nama dan kontak pelapor tidak ditampilkan ke publik, dan tidak ada halaman pelacakan publik yang bisa dipakai orang lain untuk membuka laporanmu.',
        ],
        [
            'q' => 'Perlu akun untuk melapor?',
            'a' => 'Ya. Akun dengan email aktif dipakai untuk menyimpan laporan, mengirim pemberitahuan, dan menampilkan riwayat penanganannya. Pendaftarannya singkat.',
        ],
        [
            'q' => 'Apa saja yang bisa dilaporkan?',
            'a' => 'Semua urusan layanan publik kota, dari jalan rusak dan drainase sampai sampah, penerangan jalan, air bersih, dan layanan kesehatan. Untuk laporan yang belum jelas kategorinya, ada Pengaduan Umum; petugas yang menentukan unit penanganannya.',
        ],
        [
            'q' => 'Salah pilih kategori, bagaimana?',
            'a' => 'Laporan masih bisa kamu perbaiki setelah dikirim. Perbaikan tidak mengubah status laporan dan bukan penolakan; laporan tetap menunggu pemeriksaan petugas.',
        ],
        [
            'q' => 'Boleh melampirkan foto?',
            'a' => 'Boleh, opsional: maksimal 10 berkas dan 20 MB per berkas. Kecamatan dan kelurahan wajib diisi supaya laporan bisa diteruskan ke unit yang tepat.',
        ],
        [
            'q' => 'Untuk keadaan darurat?',
            'a' => 'Kanal ini bukan saluran darurat. Untuk keadaan yang mengancam nyawa atau keselamatan, hubungi 112 lebih dulu. Laporan lewat kanal ini tetap tercatat dan diteruskan ke BPBD atau Dinas Pemadam Kebakaran.',
        ],
    ];

    /**
     * The photograph row: public places of Parepare, from the Tropenmuseum
     * archive (1920s) to last year's city view. Eight pictures, three module
     * shapes, one treatment (greyscale) — the page's only true colour is the
     * brand's blue and the yellow that stands for the citizen.
     */
    $rowModules = [
        ['shape' => 'tall', 'photos' => [
            ['file' => 'parepare-view', 'alt' => 'Pemandangan Kota Parepare dari ketinggian', 'w' => 1300, 'h' => 867],
        ]],
        ['shape' => 'pair', 'photos' => [
            ['file' => 'collectie-tropenmuseum-gezicht-op-parepare-tmnr-10029351', 'alt' => 'Parepare pada 1920-an, foto arsip Tropenmuseum', 'w' => 700, 'h' => 484],
            ['file' => 'bendungan-saddang-parepare-1950s', 'alt' => 'Bendungan Saddang, Parepare, sekitar 1950-an', 'w' => 394, 'h' => 294],
        ]],
        ['shape' => 'wide', 'photos' => [
            ['file' => 'terminal-penumpang-pelabuhan-nusantara-parepare', 'alt' => 'Terminal penumpang Pelabuhan Nusantara Parepare', 'w' => 1021, 'h' => 626],
        ]],
        ['shape' => 'pair', 'photos' => [
            ['file' => 'pasar-sentral-lakessi-parepare', 'alt' => 'Pasar Sentral Lakessi, Parepare', 'w' => 768, 'h' => 482],
            ['file' => 'monumen-korban-40-ribu-jiwa-parepare-2023', 'alt' => 'Monumen Korban 40 Ribu Jiwa, Parepare', 'w' => 1300, 'h' => 975],
        ]],
        ['shape' => 'tall', 'photos' => [
            ['file' => 'kawasan-hastom-parepare', 'alt' => 'Kawasan Hastom di Jalan Sultan Hasanuddin, Parepare', 'w' => 1280, 'h' => 889],
        ]],
        ['shape' => 'wide', 'photos' => [
            ['file' => 'parepare-view-3-2023', 'alt' => 'Kota Parepare dilihat dari atas, 2023', 'w' => 1300, 'h' => 975],
        ]],
    ];

    /**
     * Every found picture is credited on the page. Source: Wikimedia Commons.
     */
    $credits = [
        ['title' => 'Parepare view-3 (2023)', 'author' => 'MesinKetik', 'licence' => 'CC BY-SA 4.0'],
        ['title' => 'Parepare View', 'author' => 'Lord Mountbatten', 'licence' => 'CC BY-SA 3.0'],
        ['title' => 'Kawasan Hastom Parepare', 'author' => 'Dinas Kepemudaan, Olahraga dan Pariwisata Kota Parepare', 'licence' => 'domain publik'],
        ['title' => 'Pasar Sentral Lakessi, Parepare', 'author' => 'Egov Team Diskominfo Parepare', 'licence' => 'domain publik'],
        ['title' => 'Terminal Penumpang Pelabuhan Nusantara Parepare', 'author' => 'Blue tooth7', 'licence' => 'CC BY-SA 3.0'],
        ['title' => 'Monumen Korban 40 Ribu Jiwa, Parepare', 'author' => 'MesinKetik', 'licence' => 'CC BY-SA 4.0'],
        ['title' => 'Gezicht op Parepare, sekitar 1920-an (Tropenmuseum)', 'author' => 'C.J. (Cees) Taillie', 'licence' => 'CC BY-SA 3.0'],
        ['title' => 'Bendungan Saddang, Parepare, sekitar 1950-an', 'author' => 'Djawatan Penerangan Provinsi Sulawesi', 'licence' => 'domain publik'],
    ];

    $photoCount = collect($rowModules)->sum(fn (array $module): int => count($module['photos']));
@endphp

<x-layouts.app
    :title="$title"
    description="Kanal resmi pengaduan warga Kota Parepare. Kirim laporan tentang layanan publik, lalu pantau penanganannya sampai selesai."
>
    {{-- The `.js` gate and the `data-reveal` entrance machinery live in the
         layout (components/layouts/app.blade.php) so every public page shares
         one copy; this page only marks its sections below. --}}

    <style>
        /* ── Landing composition (design-taste-frontend · direction:
              "The printed annual" · seed 251049812) ───────────────────────────
           Page-specific only: the masthead mosaic, the staircase, the
           photograph row, the masked headline and the sliced footer name.
           Everything else is Tailwind utilities over the design.md tokens. */

        .lp-band {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            grid-template-rows: repeat(2, minmax(0, 1fr));
            height: 52svh;
            min-height: 300px;
        }

        .lp-band > * { position: relative; overflow: hidden; }

        /* Gutterless mosaic: cells butt together, no gaps anywhere. */
        .lp-b1 { grid-column: 1 / span 2; grid-row: 1; }
        .lp-b2 { grid-column: 3; grid-row: 1 / span 2; }
        .lp-b3 { grid-column: 4; grid-row: 1; }
        .lp-b4 { grid-column: 5; grid-row: 1; }
        .lp-b5 { grid-column: 6; grid-row: 1; }
        .lp-b6 { grid-column: 1; grid-row: 2; }
        .lp-b7 { grid-column: 2; grid-row: 2; }
        .lp-b8 { grid-column: 4 / span 2; grid-row: 2; }
        .lp-b9 { grid-column: 6; grid-row: 2; }

        /* One treatment for every photograph on the page: greyscale, slightly
           more contrast, cropped hard. The only colour is the brand's. */
        .lp-photo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(1) contrast(1.05);
        }

        /* The moving picture: one mosaic cell crossfades through three views of
           the city. CSS only; reduce-motion leaves the first frame standing. */
        @media (prefers-reduced-motion: no-preference) {
            .lp-cross > img {
                position: absolute;
                inset: 0;
                opacity: 0;
                animation: lp-cross 21s linear infinite;
            }
            .lp-cross > img:nth-child(1) { animation-delay: 0s; }
            .lp-cross > img:nth-child(2) { animation-delay: 7s; }
            .lp-cross > img:nth-child(3) { animation-delay: 14s; }
        }

        @keyframes lp-cross {
            0%, 30%    { opacity: 1; }
            33.3%, 96% { opacity: 0; }
            100%       { opacity: 1; }
        }

        /* Masked headline: each line rises from behind its own baseline. */
        .lp-line {
            display: block;
            overflow: hidden;
            padding-bottom: 0.06em;
            margin-bottom: -0.06em;
        }
        .lp-line > span { display: block; }

        @media (prefers-reduced-motion: no-preference) {
            .js .lp-line > span {
                transform: translateY(104%);
                animation: lp-line-up 900ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
                animation-delay: calc(var(--i, 0) * 110ms + 140ms);
            }
        }

        @keyframes lp-line-up { to { transform: none; } }

        /* Section entrances are marked per section below (`data-reveal`); the
           mechanism itself — the `.js` gate, the hidden state and the observer
           that writes `data-anim` — is shared from the layout and app.css. */

        /* Footer doors, set on the heading step. */
        .lp-door { font-size: clamp(26px, 2.8vw, 40px); }

        /* Staircase: four clauses, each starting two columns further right. */
        .lp-stair {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            row-gap: 56px;
        }
        .lp-stage { grid-column: var(--lp-from, 1) / -1; }

        @media (max-width: 1023px) {
            .lp-stair { row-gap: 40px; }
            .lp-stage {
                grid-column: 1 / -1;
                padding-left: calc(var(--lp-i, 0) * 6vw);
            }
        }

        /* The photograph row: drag sideways, snap, no scrollbar. */
        .lp-row {
            display: flex;
            gap: 12px;
            height: 68svh;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x proximity;
            scroll-padding-left: 6vw;
            padding: 0 6vw;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .lp-row::-webkit-scrollbar { display: none; }
        .lp-row > * { flex: 0 0 auto; scroll-snap-align: start; }

        .lp-mod { position: relative; height: 100%; overflow: hidden; }
        .lp-mod img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(1) contrast(1.05);
        }
        .lp-tall { width: calc(68svh * 3 / 4); }
        .lp-wide { width: calc(68svh * 1.6); }
        .lp-pair {
            display: grid;
            grid-template-rows: 1fr 1fr;
            gap: 12px;
            width: calc((68svh - 12px) / 2 * 4 / 3);
            background: none;
        }
        .lp-pair > * { height: auto; min-height: 0; }

        @media (hover: hover) and (pointer: fine) {
            .lp-mod img { transition: transform 600ms cubic-bezier(0.22, 1, 0.36, 1); }
            .lp-mod:hover img { transform: scale(1.03); }
        }

        /* Tablet: four columns, so a single cell is never a narrow strip. */
        @media (max-width: 1023px) {
            .lp-band {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                height: 46svh;
            }
            .lp-b1 { grid-column: 1 / span 2; grid-row: 1; }
            .lp-b2 { grid-column: 3; grid-row: 1 / span 2; }
            .lp-b3 { grid-column: 4; grid-row: 1; }
            .lp-b4 { grid-column: 1; grid-row: 2; }
            .lp-b5 { grid-column: 2; grid-row: 2; }
            .lp-b6 { grid-column: 4; grid-row: 2; }
            .lp-b7, .lp-b8, .lp-b9 { display: none; }

            .lp-row { height: 62svh; gap: 8px; padding: 0 20px; scroll-padding-left: 20px; }
            .lp-tall { width: 72vw; }
            .lp-pair { width: 64vw; }
            .lp-wide { width: 88vw; height: 70%; align-self: center; }
        }

        /* Phone: three columns, fewer photographs, more flat colour. */
        @media (max-width: 767px) {
            .lp-band {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                height: 38svh;
                min-height: 220px;
            }
            .lp-b1 { grid-column: 1 / span 2; grid-row: 1; }
            .lp-b2 { grid-column: 3; grid-row: 1 / span 2; }
            .lp-b3 { grid-column: 1; grid-row: 2; }
            .lp-b5 { grid-column: 2; grid-row: 2; }
            .lp-b4, .lp-b6 { display: none; }
        }

        /* The category list: plain names in columns, no boxes and no bullets.
           The columns are balanced by the browser, so the longest name
           ("Administrasi Kependudukan dan Pencatatan Sipil") can never push the
           list past the viewport edge; the bottoms stay ragged at line
           granularity because the names differ in length.
           The chosen name is marked the way a citizen marks a form — with a
           yellow highlighter, never a border. */
        .lp-cats {
            columns: 4;
            column-gap: 32px;
            font-size: 22px;
            line-height: 1.4;
        }
        .lp-cat-row { margin-bottom: 0.45em; }
        .lp-cat {
            display: inline;
            padding: 0 0.12em;
            margin: 0 -0.12em;
            border: 0;
            background: none;
            font: inherit;
            color: inherit;
            text-align: left;
            cursor: pointer;
            text-decoration: underline;
            text-decoration-color: transparent;
            text-decoration-thickness: 0.16em;
            text-underline-offset: 0.08em;
            -webkit-box-decoration-break: clone;
            box-decoration-break: clone;
            transition:
                background-color 180ms ease,
                text-decoration-color 180ms ease;
        }
        @media (hover: hover) and (pointer: fine) {
            .lp-cat:hover { text-decoration-color: var(--color-accent-500); }
        }
        .lp-cat[aria-pressed='true'] {
            background: var(--color-accent-500);
            text-decoration-color: transparent;
        }

        @media (max-width: 1023px) {
            .lp-cats {
                columns: 2;
                column-gap: 20px;
                font-size: 17px;
                line-height: 1.5;
            }
        }

        /* Footer: the name spans the full width and its bottom edge is sliced
           by the field, so the page closes on the two words it is named after.
           The 40px floor (measured: "Lapor Pak Wali" is 353px wide at 52px)
           keeps the name inside a 320px viewport instead of scrolling it. */
        .lp-name {
            font-size: clamp(40px, 13vw, 208px);
            line-height: 0.8;
            letter-spacing: -0.055em;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>

    <x-skip-link />

    <div class="bg-surface text-ink">

        {{-- ─── Header ─────────────────────────────────────────────────────────
             Flush on the band's upper edge: the wordmark at far left, a flat
             accent tab of plain links, one action at the right margin. No blur,
             no border, no shadow.

             The tab ends on the 42% axis, where the title below begins. Three
             labels cannot hold that 240px track at 1024px (measured in the
             browser: the logged-in row is ~325px with its gaps), so lg–xl widens
             the band to the 50% axis and 2xl returns it to the 42% axis. --}}
        <header class="px-5 sm:px-8">
            <div class="grid grid-cols-2 items-stretch lg:grid-cols-12">
                <a
                    href="{{ route('home') }}"
                    class="flex items-center py-3 lg:col-span-2 lg:col-start-1 lg:bg-surface"
                    aria-label="SuperBie — halaman utama"
                >
                    <img src="/brand/superbie-logo.svg" alt="SuperBie — Lapor Pak Wali" class="h-10 w-auto">
                </a>

                <div class="flex items-center justify-end lg:col-span-7 lg:col-start-6">
                    @auth
                        <a
                            href="{{ route('citizen.complaint.create') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @else
                        <a
                            href="{{ route('register') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @endauth
                </div>

                {{-- On a phone the tab drops to its own full-width row under the
                     wordmark, so the three doors stay on screen instead of
                     hiding behind a menu word. --}}
                <nav
                    class="-mx-5 col-span-2 flex items-center gap-4 bg-accent-500 px-5 py-3 sm:-mx-8 sm:px-8 lg:mx-0 lg:col-span-4 lg:col-start-3 lg:gap-4 lg:px-5 lg:py-0 xl:gap-6 2xl:col-span-3"
                    aria-label="Navigasi publik"
                >
                    {{-- The tab holds three links only (one row at `lg`), so
                         "Harga pasar" takes the middle seat; the FAQ anchor
                         stays reachable on the page and from the footer. --}}
                    <a href="{{ route('weather.index') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Cuaca</a>
                    <a href="{{ route('siphp.index') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Harga pasar</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Masuk</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main-content" tabindex="-1">

            {{-- ─── Masthead + title ───────────────────────────────────────────
                 A gutterless mosaic of five real photographs and four flat
                 colour tiles, then the title starting exactly on the 42% axis. --}}
            <section aria-labelledby="hero-title">
                <div class="lp-band">
                    <div class="lp-b1 lp-cross">
                        <img src="/img/kota/parepare-view-3-2023.jpg" width="1300" height="975" alt="Kota Parepare dilihat dari atas, 2023" class="lp-photo">
                        <img src="/img/kota/parepare-view.jpg" width="1300" height="867" alt="Pemandangan Kota Parepare" class="lp-photo" loading="lazy">
                        <img src="/img/kota/monumen-korban-40-ribu-jiwa-parepare-2023.jpg" width="1300" height="975" alt="Monumen Korban 40 Ribu Jiwa, Parepare" class="lp-photo" loading="lazy">
                    </div>
                    <div class="lp-b2 bg-primary-600"></div>
                    <div class="lp-b3">
                        <img src="/img/kota/collectie-tropenmuseum-gezicht-op-parepare-tmnr-10029351.jpg" width="700" height="484" alt="Parepare pada 1920-an, foto arsip Tropenmuseum" class="lp-photo">
                    </div>
                    <div class="lp-b4">
                        <img src="/img/kota/kawasan-hastom-parepare.jpg" width="1280" height="889" alt="Kawasan Hastom di Jalan Sultan Hasanuddin, Parepare" class="lp-photo">
                    </div>
                    <div class="lp-b5 bg-ink"></div>
                    <div class="lp-b6">
                        <img src="/img/kota/pasar-sentral-lakessi-parepare.jpg" width="768" height="482" alt="Pasar Sentral Lakessi, Parepare" class="lp-photo">
                    </div>
                    <div class="lp-b7 bg-accent-500"></div>
                    <div class="lp-b8">
                        <img src="/img/kota/terminal-penumpang-pelabuhan-nusantara-parepare.jpg" width="1021" height="626" alt="Terminal penumpang Pelabuhan Nusantara Parepare" class="lp-photo">
                    </div>
                    <div class="lp-b9 bg-accent-500"></div>
                </div>

                <div class="grid grid-cols-1 gap-8 px-5 pb-20 pt-10 sm:px-8 sm:pb-28 sm:pt-14 lg:grid-cols-12">
                    <p class="max-w-[42ch] text-pretty text-[17px] leading-relaxed text-ink-body lg:col-span-4 lg:pt-2">
                        Kanal resmi pengaduan warga Kota Parepare. Kirim laporan tentang
                        layanan publik, lalu pantau penanganannya sampai selesai.
                    </p>

                    <h1
                        id="hero-title"
                        class="text-balance font-bold tracking-[-0.055em] text-ink lg:col-span-8 lg:col-start-6"
                        style="font-size: clamp(46px, 8vw, 116px); line-height: 0.92;"
                    >
                        <span class="lp-line"><span style="--i: 0;">Laporanmu</span></span>
                        <span class="lp-line"><span style="--i: 1;">dibaca kota.</span></span>
                    </h1>
                </div>
            </section>

            {{-- ─── The four stages ────────────────────────────────────────────
                 Written, not counted: each line is a clause that steps two
                 columns further right, and the citizen's own part is the one
                 line that takes the accent colour. --}}
            <section class="px-5 py-20 sm:px-8 sm:py-28 lg:py-40" aria-label="Cara kerja pengaduan">
                <ol class="lp-stair" data-reveal="rows">
                    @foreach ($stages as $stage)
                        <li class="lp-stage" style="--lp-from: {{ 1 + $loop->index * 2 }}; --lp-i: {{ $loop->index }}">
                            <p @class([
                                'font-semibold tracking-[-0.03em] leading-[1.05]',
                                'text-primary-700' => $loop->last,
                                'text-ink' => ! $loop->last,
                            ]) style="font-size: clamp(26px, 2.8vw, 40px)">
                                {{ $stage['line'] }}
                            </p>
                            <p class="mt-3 max-w-[34ch] text-pretty text-[17px] leading-relaxed text-ink-soft">
                                {{ $stage['detail'] }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- ─── The city's public places ───────────────────────────────────
                 One draggable row of real photographs, the archive beside
                 today's city, all in the same greyscale treatment. --}}
            <section class="pb-20 sm:pb-28 lg:pb-40" aria-labelledby="ruang-publik">
                <div class="flex items-baseline justify-between px-5 sm:px-8">
                    <h2 id="ruang-publik" class="font-semibold tracking-[-0.03em] text-ink" style="font-size: clamp(26px, 2.8vw, 40px)">
                        Ruang publik Parepare
                    </h2>
                    <p class="text-[17px] text-ink-soft">{{ $photoCount }} foto</p>
                </div>

                <div class="lp-row mt-8" data-reveal="rows">
                    @foreach ($rowModules as $module)
                        @if ($module['shape'] === 'pair')
                            <div class="lp-pair">
                                @foreach ($module['photos'] as $photo)
                                    <div class="lp-mod">
                                        <img
                                            src="/img/kota/{{ $photo['file'] }}.jpg"
                                            width="{{ $photo['w'] }}"
                                            height="{{ $photo['h'] }}"
                                            alt="{{ $photo['alt'] }}"
                                            loading="lazy"
                                        >
                                    </div>
                                @endforeach
                            </div>
                        @else
                            @php $photo = $module['photos'][0]; @endphp
                            <div @class(['lp-mod', 'lp-tall' => $module['shape'] === 'tall', 'lp-wide' => $module['shape'] === 'wide'])>
                                <img
                                    src="/img/kota/{{ $photo['file'] }}.jpg"
                                    width="{{ $photo['w'] }}"
                                    height="{{ $photo['h'] }}"
                                    alt="{{ $photo['alt'] }}"
                                    loading="lazy"
                                >
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- ─── Categories (the live thing) ────────────────────────────────
                 The one thing a visitor can do here, built from the mapping the
                 app already runs on: pick a report category, see the unit that
                 receives it. Server-rendered with the first category already
                 selected, so the section is complete without scripting. --}}
            <section class="px-5 py-20 sm:px-8 sm:py-28 lg:py-40" aria-labelledby="kategori">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-8">
                    <div class="lg:col-span-4 lg:pt-1">
                        <h2 id="kategori" class="font-semibold tracking-[-0.04em] text-ink" style="font-size: clamp(26px, 2.8vw, 40px); line-height: 1.05">
                            Kategori laporan
                        </h2>
                        <p class="mt-5 max-w-[38ch] text-pretty text-[17px] leading-relaxed text-ink-soft">
                            {{ count($categories) }} kategori, {{ $unitCount }} unit pengampu, di
                            {{ $kecamatanCount }} kecamatan dan {{ $kelurahanCount }} kelurahan.
                            Kategori menentukan unit yang menangani, bukan kamu.
                        </p>

                        <p class="mt-10 text-[17px] text-ink-soft">Ditangani oleh</p>
                        <p id="kategori-unit" class="mt-1 max-w-[26ch] text-pretty text-[22px] font-semibold leading-snug text-ink" aria-live="polite">
                            {{ $categories[0]['units'][0] ?? 'Petugas verifikasi' }}
                        </p>
                        <p id="kategori-sisa" class="mt-3 max-w-[40ch] text-pretty text-[17px] leading-relaxed text-ink-soft">
                            @if (count($categories[0]['units'] ?? []) > 1)
                                Ditangani bersama {{ implode(', ', array_slice($categories[0]['units'], 1)) }}.
                            @endif
                        </p>
                    </div>

                    <div class="lg:col-span-8">
                        <div class="lp-cats" id="kategori-daftar" data-reveal="rise">
                            @foreach ($categories as $category)
                                <div class="lp-cat-row">
                                    <button
                                        type="button"
                                        class="lp-cat"
                                        data-kategori="{{ $loop->index }}"
                                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                    >{{ $category['name'] }}</button>
                                </div>
                            @endforeach
                        </div>
                        <noscript>
                            <p class="mt-6 text-[17px] text-ink-soft">
                                Memilih kategori untuk melihat unit penanggung jawab memerlukan JavaScript.
                                Unit yang menangani tetap ditentukan oleh kategori laporanmu.
                            </p>
                        </noscript>
                    </div>
                </div>
            </section>

            {{-- ─── Questions, answers beside them ─────────────────────────────
                 Nothing collapses; the questions are large enough to scan as an
                 index, and every answer is open on the page. --}}
            <section id="tanya" class="px-5 py-20 sm:px-8 sm:py-28 lg:py-40" aria-labelledby="tanya-judul">
                <div class="grid grid-cols-1 gap-y-14 lg:grid-cols-12 lg:gap-x-8 lg:gap-y-[88px]">
                    <div class="lg:col-span-6">
                        <h2 id="tanya-judul" class="font-semibold tracking-[-0.03em] text-ink-soft" style="font-size: clamp(26px, 2.8vw, 40px); line-height: 1.1">
                            Tanya jawab
                        </h2>
                    </div>
                    <div class="hidden lg:col-span-5 lg:col-start-8 lg:block"></div>

                    @foreach ($faq as $item)
                        <p class="font-medium tracking-[-0.02em] text-ink lg:col-span-6" style="font-size: clamp(26px, 2.8vw, 40px); line-height: 1.1">
                            {{ $item['q'] }}
                        </p>
                        <p class="text-pretty text-[17px] leading-relaxed text-ink-body lg:col-span-5 lg:col-start-8" style="max-width: 42ch">
                            {{ $item['a'] }}
                        </p>
                    @endforeach
                </div>
            </section>

            {{-- ─── Closing ────────────────────────────────────────────────────
                 Three photographs, then a flat field: the last thing said is the
                 citizen's own part, and the phrase that carries the action is
                 the only underlined thing on the page. --}}
            <section aria-label="Kirim laporan">
                <div class="grid grid-cols-3 gap-0">
                    <div class="h-[34svh] min-h-[180px] overflow-hidden" data-reveal="shutter">
                        <img src="/img/kota/pasar-sentral-lakessi-parepare.jpg" width="768" height="482" alt="Pasar Sentral Lakessi, Parepare" class="lp-photo" loading="lazy">
                    </div>
                    <div class="h-[34svh] min-h-[180px] overflow-hidden" data-reveal="shutter">
                        <img src="/img/kota/kawasan-hastom-parepare.jpg" width="1280" height="889" alt="Kawasan Hastom, Parepare" class="lp-photo" loading="lazy">
                    </div>
                    <div class="h-[34svh] min-h-[180px] overflow-hidden" data-reveal="shutter">
                        <img src="/img/kota/terminal-penumpang-pelabuhan-nusantara-parepare.jpg" width="1021" height="626" alt="Pelabuhan Nusantara Parepare" class="lp-photo" loading="lazy">
                    </div>
                </div>

                <div class="bg-ink px-5 py-16 sm:px-8 sm:py-20 lg:py-24">
                    <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-8">
                        <p
                            class="text-pretty font-semibold leading-[1.25] tracking-[-0.02em] text-white lg:col-span-7"
                            style="font-size: clamp(24px, 2.6vw, 36px); max-width: 30ch"
                        >
                            Berkas kota terbuka: jalan, drainase, sampah, lampu jalan.
                            Kalau ada yang salah, <a
                                href="{{ auth()->check() ? route('citizen.complaint.create') : route('register') }}"
                                class="text-white underline decoration-accent-500 decoration-[4px] underline-offset-[6px] transition-colors hover:text-accent-400"
                            >kirim laporan</a>.
                        </p>

                        <div class="lg:col-span-4 lg:col-start-9">
                            <p class="max-w-[34ch] text-pretty text-[17px] leading-relaxed text-white/80">
                                Mengirim laporan perlu akun supaya statusnya bisa kamu pantau.
                            </p>
                            <p class="mt-4 max-w-[34ch] text-pretty text-[17px] leading-relaxed text-white/80">
                                Nama dan kontakmu tidak ditampilkan ke publik.
                            </p>

                            <div class="mt-8 flex w-[150px] flex-col items-center bg-surface px-4 py-5">
                                <img src="/brand/superbie-mark.svg" alt="" class="h-16 w-auto" aria-hidden="true">
                                <p class="mt-4 text-center text-[13px] font-medium leading-snug text-ink">
                                    Tim Lapor Pak Wali
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        {{-- ─── Footer ─────────────────────────────────────────────────────────
             The name opens the close instead of ending it, and the field slices
             its bottom edge. Under it: the doors, the small links, and
             the picture credits this page is required to carry. --}}
        <footer class="bg-primary-900 px-5 pt-20 text-white sm:px-8 lg:pt-28">
            <div>
                <p class="lp-name mb-[-0.14em] block text-white">Lapor Pak Wali</p>
            </div>

            <div class="grid grid-cols-1 gap-12 pt-16 lg:grid-cols-12 lg:gap-8">
                <nav class="lg:col-span-5" aria-label="Tautan utama">
                    <a href="{{ route('weather.index') }}" class="block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Info cuaca</a>
                    <a href="{{ route('siphp.index') }}" class="mt-2 block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Harga pasar</a>
                    <a href="{{ route('jdih.index') }}" class="mt-2 block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Dokumen hukum</a>
                    <a href="#tanya" class="mt-2 block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Tanya jawab</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="mt-2 block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="mt-2 block lp-door font-semibold tracking-[-0.02em] text-accent-400 transition-colors hover:text-accent-300">Masuk</a>
                    @endauth
                </nav>

                <div class="grid grid-cols-2 gap-8 text-[17px] sm:grid-cols-3 lg:col-span-6 lg:col-start-7">
                    <div>
                        <p class="font-semibold text-white">Akun</p>
                        <ul class="mt-3 space-y-2 text-white/75">
                            @auth
                                <li><a href="{{ route('citizen.dashboard') }}" class="transition-colors hover:text-white">Ringkasan laporan</a></li>
                                <li><a href="{{ route('citizen.history') }}" class="transition-colors hover:text-white">Riwayat laporan</a></li>
                                <li><a href="{{ route('citizen.profile.edit') }}" class="transition-colors hover:text-white">Profil</a></li>
                            @else
                                <li><a href="{{ route('register') }}" class="transition-colors hover:text-white">Daftar akun</a></li>
                                <li><a href="{{ route('login') }}" class="transition-colors hover:text-white">Masuk</a></li>
                                <li><a href="{{ route('password.request') }}" class="transition-colors hover:text-white">Lupa kata sandi</a></li>
                            @endauth
                        </ul>
                    </div>

                    <div>
                        <p class="font-semibold text-white">Layanan</p>
                        <ul class="mt-3 space-y-2 text-white/75">
                            <li><a href="{{ route('citizen.complaint.create') }}" class="transition-colors hover:text-white">Kirim laporan</a></li>
                            <li><a href="{{ route('citizen.help.index') }}" class="transition-colors hover:text-white">Pusat bantuan</a></li>
                            <li><a href="{{ route('weather.index') }}" class="transition-colors hover:text-white">Cuaca kota</a></li>
                            <li><a href="{{ route('siphp.index') }}" class="transition-colors hover:text-white">Harga pasar kota</a></li>
                            <li><a href="{{ route('jdih.index') }}" class="transition-colors hover:text-white">Dokumen hukum (JDIH)</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="font-semibold text-white">Sumber</p>
                        <ul class="mt-3 space-y-2 text-white/75">
                            <li>Data cuaca: BMKG</li>
                            <li>Harga pasar: input manual petugas</li>
                            <li>Kategori &amp; unit: basis data kota</li>
                            <li>Peta wilayah: OpenStreetMap</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-20">
                <p class="text-[13px] font-semibold text-white">Foto</p>
                <ul class="mt-3 grid grid-cols-1 gap-x-8 gap-y-2 text-[13px] leading-snug text-white/60 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($credits as $credit)
                        <li>
                            &ldquo;{{ $credit['title'] }}&rdquo; &mdash; {{ $credit['author'] }}
                            ({{ $credit['licence'] }}), via Wikimedia Commons
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-12 flex flex-col gap-3 pb-10 text-[13px] text-white/70 sm:flex-row sm:items-center sm:justify-between">
                <p>Dikelola Pemerintah Kota Parepare, Sulawesi Selatan.</p>
                <a href="#main-content" class="transition-colors hover:text-white">Kembali ke atas</a>
            </div>
        </footer>
    </div>

    @push('scripts')
        <script>
            (function () {
                // The mapping this section is about: the app's own data
                // (category_dinas_unit), server-rendered above as the plain list
                // and encoded here as one array of unit names per category.
                var peta = @json(collect($categories)->pluck('units'));

                // Delegated on `document` and re-read at click time: the app's
                // progressive navigation swaps `#main-content` from a cache, so
                // any node reference held here would go stale.
                document.addEventListener('click', function (event) {
                    var tombol = event.target.closest('[data-kategori]');
                    if (!tombol) { return; }

                    var daftar = document.getElementById('kategori-daftar');
                    var keluaran = document.getElementById('kategori-unit');
                    var sisa = document.getElementById('kategori-sisa');
                    if (!daftar || !keluaran || !daftar.contains(tombol)) { return; }

                    var lama = daftar.querySelector('[aria-pressed="true"]');
                    if (lama) { lama.setAttribute('aria-pressed', 'false'); }
                    tombol.setAttribute('aria-pressed', 'true');

                    var unit = peta[tombol.getAttribute('data-kategori')] || [];
                    keluaran.textContent = unit.length ? unit[0] : 'Petugas verifikasi';
                    if (sisa) {
                        sisa.textContent = unit.length > 1
                            ? 'Ditangani bersama ' + unit.slice(1).join(', ') + '.'
                            : '';
                    }
                });

                // The section entrances (`data-reveal` markers -> `data-anim`
                // hidden state -> `is-in`) are set up once in the layout, shared
                // by every public page.

                // The masthead crossfade holds still while the tab is hidden.
                document.addEventListener('visibilitychange', function () {
                    var gambar = document.querySelectorAll('.lp-cross img');
                    for (var i = 0; i < gambar.length; i++) {
                        gambar[i].style.animationPlayState = document.hidden ? 'paused' : 'running';
                    }
                });
            })();
        </script>
    @endpush
</x-layouts.app>
