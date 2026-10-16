@if ($paginator->hasPages())
    {{-- Publikasi ulang paginator bawaan (pagination::tailwind) dengan teks
         Bahasa Indonesia dan TANPA utility `dark:` — aplikasi ini light-only,
         sehingga kelas dark-mode bawaan akan menghasilkan blok gelap di OS
         bertema gelap. Markup aksesibel: landmark <nav> bernama, rel prev/next,
         aria-current pada halaman aktif, dan ikon dekoratif aria-hidden. --}}
    <nav role="navigation" aria-label="Navigasi halaman"
         class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

        <p class="text-sm text-ink-faint">
            Menampilkan
            @if ($paginator->firstItem())
                <span class="font-semibold text-ink-body">{{ $paginator->firstItem() }}</span>
                &ndash;
                <span class="font-semibold text-ink-body">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-semibold text-ink-body">{{ $paginator->count() }}</span>
            @endif
            dari
            <span class="font-semibold text-ink-body">{{ $paginator->total() }}</span>
            hasil
        </p>

        <div class="inline-flex items-center gap-1">
            {{-- Tautan halaman sebelumnya --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Halaman sebelumnya"
                      class="inline-flex items-center justify-center w-9 h-9 text-ink-faint bg-page border border-line rounded-xl cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya"
                   class="inline-flex items-center justify-center w-9 h-9 text-ink-soft bg-white border border-line rounded-xl hover:bg-surface-sunken hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600/30 ui-animated">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true"
                          class="inline-flex items-center justify-center w-9 h-9 text-sm font-medium text-ink-faint bg-white border border-line rounded-xl cursor-default">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex items-center justify-center w-9 h-9 text-sm font-semibold text-white bg-primary-600 border border-primary-600 rounded-xl">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Buka halaman {{ $page }}"
                               class="inline-flex items-center justify-center w-9 h-9 text-sm font-medium text-ink-soft bg-white border border-line rounded-xl hover:bg-surface-sunken hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600/30 ui-animated">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Tautan halaman berikutnya --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya"
                   class="inline-flex items-center justify-center w-9 h-9 text-ink-soft bg-white border border-line rounded-xl hover:bg-surface-sunken hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600/30 ui-animated">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="Halaman berikutnya"
                      class="inline-flex items-center justify-center w-9 h-9 text-ink-faint bg-page border border-line rounded-xl cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
