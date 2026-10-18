{{--
    Skip link (WCAG 2.4.1 Bypass Blocks).

    The first focusable element on every page that renders `#main-content`:
    invisible until focused, then it lands the keyboard user directly on the
    page's main region, past the header and the public tab. Used by the public
    sheets and the dashboard shell; the target element keeps
    `id="main-content"` + `tabindex="-1"` so focus really moves there.
--}}
<a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-primary-700 focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white"
>
    Lewati ke konten utama
</a>
