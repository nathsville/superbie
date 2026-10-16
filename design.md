# UI/UX Design System
## SuperBie — Lapor Pak Wali

## 1. Design direction

Create a civic-service interface that feels clear, trustworthy, calm, modern, and approachable. The primary user task is submitting a complaint or reviewing the status of one's own complaint after login; decoration must never compete with that task. Use Indonesian copy, plain language, strong visual hierarchy, and generous whitespace.

- **Brand context:** part of SuperBie, a planned integrated portal for public services.
- **MVP scope:** Lapor Pak Wali **plus the approved WINDY public weather dashboard** (read-only public weather information on the landing page; Phase 3, O-1). Do not design active screens for **SIPHP** or **JDIH** — they remain disabled/future navigation placeholders only. WINDY must not include a GIS/wilayah boundary map, must respect `prefers-reduced-motion`, and must never present synthetic/hardcoded/default values as real measurements. **Phase 4E (D-11…D-17):** show a **3-day** forecast; display times in **`Asia/Makassar` (WITA)**; when a value is unavailable show **"Data tidak tersedia"** (never a default/synthetic value); the Windy.com embed stays as a separate interactive map and is **conditional** on the CSP/privacy decision (O-8); include required **BMKG/OpenWeatherMap attribution**.
- **Density:** low-to-medium; forms and case details must be easy to scan.
- **Responsive:** mobile-first; form controls full-width on mobile.
- **Tone:** respectful, neutral, non-partisan, public-service oriented.
- **Avoid:** fake metrics, fake testimonials, unapproved government seals/logos, excessive gradients, noisy backgrounds, and decorative movement that obstructs reading.

## 2. Visual tokens

Use CSS custom properties or Tailwind theme tokens; avoid random hard-coded values.

### Color palette

The palette is derived from the official SuperBie logo: brand blue `#013FF6`
(the "Super" wordmark and the bee) and brand yellow `#FFC14D` (the "Bie"
wordmark and the sparkle). Every intermediate step is an HSL sibling of one of
those two colours, so the whole scale stays inside the logo's hue family.

Tokens are declared in `@theme` in `resources/css/app.css` — Tailwind v4 only
turns `@theme` variables into utilities. A token declared on `:root` produces no
`bg-*`/`text-*` class at all, which is what previously forced raw hex values into
96 of 106 Blade files. **Never hardcode a hex in markup.** The only two
exceptions are SVG presentation attributes and PHP chart palettes; both are
listed at the end of this section.

#### Brand scales

| Token | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 |
|---|---|---|---|---|---|---|---|---|---|---|
| `primary-*` | `#F0F4FF` | `#DBE4FF` | `#C2D1FF` | `#99B3FF` | `#5C85FF` | `#2A5FFE` | `#013FF6` | `#0131C1` | `#01248E` | `#011C70` |
| `accent-*` | `#FFFBF5` | `#FFF1D6` | `#FFE8BD` | `#FFD88F` | `#FFCB6B` | `#FFC14D` | `#996300` | `#8A5A00` | `#704900` | `#573800` |

`primary-600` and `accent-500` are the logo colours exactly. `primary-700` is
the pressed/interactive state, `primary-100` the soft badge surface.

The accent scale is deliberately discontinuous between 500 and 600: yellow is a
fill. Nothing at 500 or lighter may carry text — `accent-600` (5.1:1 on white)
and `accent-700` (5.9:1) exist for warning text and icons, and `accent-500` and
lighter are backgrounds, borders and fills only.

#### Neutral scale

| Token | HEX | Role |
|---|---|---|
| `--color-ink-strong` | `#1E293B` | Strongest heading |
| `--color-ink-body` | `#334155` | Body copy |
| `--color-ink` | `#0F172A` | Primary text / headings |
| `--color-ink-soft` | `#475569` | Secondary text, labels |
| `--color-ink-faint` | `#64748B` | Meta text, helper |
| `--color-ink-ghost` | `#94A3B8` | Placeholder, disabled |
| `--color-line-strong` | `#CBD5E1` | Emphasised divider |
| `--color-line` | `#E2E8F0` | Default border |
| `--color-page` | `#F8FAFC` | App / page background |
| `--color-surface` | `#FFFFFF` | Cards, forms, panels |
| `--color-surface-sunken` | `#F1F5F9` | Wells, table head, chips |
| `--color-canvas` | `#F0F1EE` | Internal dashboard shell only |

`--color-bg`, `--color-text`, `--color-text-muted` and `--color-border` remain as
canonical aliases of `page`, `ink`, `ink-soft` and `line`; prefer the readable
name in new markup.

#### Status colours — deliberately NOT brand colours

| Token | HEX | Meaning |
|---|---|---|
| `--color-success` | `#15803D` | Selesai / normal |
| `--color-warning` | `#B45309` | Perlu perhatian |
| `--color-error` | `#B91C1C` | Ditolak / gagal |
| `--color-error-soft` | `#FEF2F2` | Error surface |
| `--color-info` | `#0369A1` | Informational |

A citizen has to read complaint status at a glance, so green means selesai and
red means ditolak. Do not re-tint these to the brand. The seven complaint
statuses keep seven distinguishable hues — `primary` (Diajukan), `accent`
(Ditinjau), `violet` (Diproses), `orange` (Menunggu informasi), `green`
(Selesai), `red` (Ditolak), neutral (Ditutup) — see
`App\Enums\ComplaintStatus::tailwindBadge()`.

#### Utility names — the old default names are gone

| Old | New |
|---|---|
| `blue-*`, `-[#2563EB]` | `primary-*` |
| `amber-*`, `yellow-*`, `teal-*`, `-[#F59E0B]`, `-[#0F766E]` | `accent-*` |
| `indigo-*` | `violet-*` |
| `slate-*`, `gray-*`, `-[#64748B]` | `ink-*` / `line-*` / `surface-*` / `page` |

#### Literal hex — the only permitted cases

1. SVG presentation attributes, which cannot take `var()`:
   `stroke="#013FF6"`, `fill="#FFFFFF"`.
2. Chart palettes in PHP arrays under `resources/views/components/dashboard/`,
   passed to inline styles. Those series colours stay visually distinct by
   design (green `#22C55E`, red `#EF4444`, orange `#F97316`, violet `#7C3AED`);
   only the brand-family entries moved onto brand values.
3. Blade `<style>` blocks can and should use `var(--color-*)`;
   `admin/complaints.blade.php` is already converted this way.

Check actual contrast in rendered components; do not assume every pairing is accessible. Color must not be the only way to communicate status.

### Typography
- Primary: `Poppins`, fallback `ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif`.
- Body base: 16px / 1.5.
- Small/help: 14px / 1.45.
- H1: clamp(2rem, 4vw, 3.25rem), weight 700, line-height 1.1.
- H2: clamp(1.5rem, 3vw, 2.25rem), weight 700, line-height 1.2.
- H3: 1.25rem, weight 650, line-height 1.3.
- Form labels: 14px, weight 600.
- Maximum long-form text width: 70–75 characters where practical.

### Spacing scale
`4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96px`.

### Radius, shadow, container
- Small control radius: 8px.
- Card radius: 16px.
- Large hero panel: 24px.
- Shadow: subtle, used to distinguish surfaces rather than decorate.
- Content max-width: 1200px; form max-width: 760px; reading content max-width: 720px.
- Page gutters: 16px mobile, 24px tablet, 32px desktop.
- Buttons/input min height: 44px; prefer 48px for primary controls.

### Breakpoints
- Mobile: `< 640px`.
- Tablet: `640px–1023px`.
- Desktop: `1024px–1279px`.
- Wide desktop: `>= 1280px`.

## 3. Layouts and pages

### Public and masyarakat/pelapor
1. **Landing page:** accessible header, service hero, clear “Kirim Pengaduan” primary CTA, short steps, privacy reminder, footer with only approved contact/official information. (No “Lacak Pengaduan” — public tracking does not exist.)
2. **Registration page:** clear account fields, password guidance, validation feedback, and accessible success/error states.
3. **Login page:** login form, link to registration and reset password, generic authentication errors.
4. **Citizen dashboard:** ringkasan laporan milik pengguna, akses riwayat, laporan terbaru, and primary “Buat Pengaduan” CTA.
5. **Submission form:** authenticated-only; category selector shows the related dinas/unit; **Kecamatan + Kelurahan are required** (coordinates optional via map/GPS); clear labels; attachment guidance; privacy notice before submit; error summary and field-level errors. Daily-limit feedback must be clear without exposing internal rules beyond the configured limit message.
6. **Receipt/detail page:** reference code, next steps, and related dinas/unit. Authenticated owner-only; there is no one-time tracking secret flow and no public tracking. Do not put internal data in the URL.
7. **Complaint detail (owner):** status timeline, public responses, attachments; internal notes never shown.
8. **Profile page:** view/edit allowed account information.
9. **Not found/error pages:** plain language and safe way back.

### Internal panel
- Sidebar/navigation collapses on mobile.
- Dashboard uses real database values only.
- **Operator dashboard:** operational complaint management for the Operator's own Dinas/Unit (review, status, notes, responses, location, completion submission). Category / Dinas-Unit / assignment are read-only (decided by Super Admin during initial validation). While the complaint awaits Super Admin completion validation (`pending_completion_validation`), the "Perbarui Status" options are hidden and the panel shows an informative message instead; they reappear after the Super Admin returns the submission (`completion_returned_to_operator`). This is a UX convenience — the server enforces it (PHASE N6A / F-1).
- **Admin dashboard:** monitoring only; no management actions.
- **Super Admin dashboard:** full management navigation, including initial validation and completion validation queues. The completion-validation queue shows a complaint only when `status = in_progress` AND `review_state = pending_completion_validation` (PHASE N6C / N6B-1); a frozen G7 direct resolve leaves `review_state` untouched, so such a row drops out of the queue because its status is no longer `in_progress`. Approve/Return actions are only offered for an actionable row; a stale non-`in_progress` row's completion-validation detail page is rendered read-only (no Approve/Return form, a "Tidak Lagi Dapat Divalidasi" notice) while staying reachable so the M5 deep-link never 404s (PHASE N6E / N6D-1).
- Complaint list has filter/search, clear status badges, pagination, and responsive table-to-card transformation on narrow screens.
- Complaint detail separates reporter data, complaint text, attachments, status timeline, internal notes, and public responses visually and semantically.
- Category controls show the related dinas/unit.
- Confirm before destructive or high-impact actions.
- Admin/Super Admin settings use the same tokens/components as the public experience.

## 4. Component specifications

### Buttons
Variants: primary, secondary, outline, ghost, danger, link. States: default, hover, focus-visible, active, loading, disabled. Keep labels action-oriented. Loading must not change the button's semantic meaning.

### Inputs and textareas
Label always visible; placeholder is not a label. Display help/error text close to the control. Associate error with `aria-describedby`; set `aria-invalid=true` on invalid input. Preserve entered non-sensitive values after validation failure.

### Selects, checkbox, radio, toggle
Use native controls where practical for accessibility and reliability. Animate focus/selection subtly. Avoid custom controls unless the behavior is fully keyboard-accessible.

### Cards and panels
Use consistent padding, border, radius, and surface colors. Hover movement only for genuinely interactive cards. Non-interactive content cards should not pretend to be clickable.

### Status badges
Always include text label and optionally icon; do not rely on color alone. Use consistent mappings from status to semantic color.

### Alerts and toast
Use semantic status, readable copy, close action if appropriate, and sufficient duration. Critical errors must remain visible until understood or explicitly dismissed.

### Modal/dialog/dropdown
Manage focus, Escape behavior, click-outside behavior where appropriate, and focus return. Do not animate a dialog in a way that delays user input.

### Notification bell + dropdown (PHASE N4)
The dashboard shell header shows an **enabled** notification bell (a real button, `aria-label="Notifikasi"`, `aria-haspopup="true"`, `aria-expanded` bound to Alpine state, keyboard/Escape + click-outside to close). It carries an **unread badge** that is **hidden at 0** and shows **9+** for large counts (never a fabricated count). The dropdown lists the ≤10 latest notifications and a “Lihat semua” link to `/notifikasi`. A notification row (`x-notification-item`) is a clickable link (through `notifications.open`, which marks it read and redirects to a safe, access-checked target) plus a separate delete form (a form cannot nest inside an anchor). Unread rows are visually distinct; the page (`notifications.index`) is paginated 20/page, newest first, with a “Tandai semua dibaca” action when unread items exist. All text is escaped (`{{ }}`). On narrow screens the bell is hidden (`hidden sm:block`) so it never breaks the mobile layout.

### Table, pagination, filters
Use semantic table markup on desktop; on mobile transform to labelled cards when that improves readability. Preserve query/filter state in links/forms when practical.

### Loading/empty/error states
Every async component must define all states. Skeletons should resemble the eventual content; do not use fake percentages or indefinite motion that looks like progress.

## 5. Animation system — mandatory component coverage

The project owner requests animation for every frontend component. Implement this as a consistent **animation contract** rather than continuous motion on every element. Every visible component must have at least one intentional animated state transition or entrance/exit behavior; components that are not interactive can use a short opacity/position entrance or a restrained reveal. Motion must remain subtle, purposeful, and fast.

### Tokens
```css
:root {
  --motion-fast: 120ms;
  --motion-normal: 200ms;
  --motion-slow: 320ms;
  --ease-standard: cubic-bezier(0.2, 0, 0, 1);
  --ease-enter: cubic-bezier(0, 0, 0.2, 1);
  --ease-exit: cubic-bezier(0.4, 0, 1, 1);
}
```

### Required patterns
- **Page enter:** opacity 0 → 1 and translateY 8px → 0, 200–320ms; do not hide core content if JS fails.
- **Header/navigation:** opacity/translate entrance; active link underline or color transition.
- **Buttons/links:** color, shadow, border, and transform transitions; active press scale must be subtle (e.g. 0.98).
- **Cards:** border/shadow transition; optional translateY(-2px) only for clickable cards.
- **Inputs:** border-color/box-shadow transition on focus; validation feedback fade/slide.
- **Badges/alerts:** fade/translate when state appears; no flashing.
- **Dropdown/modal:** opacity + small translate/scale; manage focus independently of animation.
- **Tables/list items:** stagger only for short lists; do not stagger large paginated tables item-by-item.
- **Loading:** restrained spinner/skeleton; respect reduced-motion.
- **Toasts:** enter/exit animation and accessible live region.
- **Empty states:** short entrance once; no looping decorative movement.
- **File preview:** opacity/scale when added or removed.
- **Form submit:** immediate pending feedback; never delay server request to finish animation.
- **Status timeline:** animate new entry appearance, but do not replay long sequences on every page visit.

### Global implementation example
```css
.ui-animated {
  transition:
    color var(--motion-normal) var(--ease-standard),
    background-color var(--motion-normal) var(--ease-standard),
    border-color var(--motion-normal) var(--ease-standard),
    box-shadow var(--motion-normal) var(--ease-standard),
    opacity var(--motion-normal) var(--ease-standard),
    transform var(--motion-normal) var(--ease-standard);
}
.page-enter {
  animation: page-enter var(--motion-slow) var(--ease-enter) both;
}
@keyframes page-enter {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
  }
}
```

Do not globally force every element to animate on every rerender. Use reusable classes/components and explicit state changes; avoid layout properties such as `height`/`top` where transform/opacity works. Animation must not obscure focus, produce horizontal overflow, or create cumulative layout shift. Respect `prefers-reduced-motion` even though the design requirement requests full animation.

## 6. Accessibility requirements

- Semantic landmarks, heading order, and button/link semantics.
- Keyboard-operable navigation, forms, dialogs, filters, and pagination.
- Visible `:focus-visible` indicator with adequate contrast.
- Every input has an explicit label; errors are programmatically associated.
- `aria-live` for submission result/toasts where appropriate.
- Status includes text, not color alone.
- Minimum interactive target 44×44px where practical.
- Reduced-motion support is mandatory.
- Do not use animation as the only signal of state or validation.

## 7. Content design

- Use concise Bahasa Indonesia.
- Prefer specific labels: “Kirim Pengaduan”, “Lihat Riwayat Laporan”, “Simpan Perubahan”.
- Error copy should say what went wrong and how to fix it, without exposing internal exceptions.
- Avoid claims such as “pasti ditindaklanjuti dalam 24 jam” unless an official SLA is provided.
- Do not invent official seals, agency names, addresses, phone numbers, stats, or service guarantees.

## 8. QA checklist for UI

- [ ] Every component has defined default, hover/focus, active, loading, error/success, and disabled states where applicable.
- [ ] Every visible component is covered by the shared animation tokens/patterns.
- [ ] Reduced-motion mode removes non-essential movement.
- [ ] No animation delays form submission or hides server errors.
- [ ] Keyboard navigation and focus management work.
- [ ] Mobile layouts do not overflow horizontally.
- [ ] Color is not the only indicator of status.
- [ ] Forms retain accessible labels and server-side error mapping.
