# UI/UX Design System
## SuperBie — Lapor Pak Wali

## 1. Design direction

Create a civic-service interface that feels clear, trustworthy, calm, modern, and approachable. The primary user task is submitting or tracking a complaint; decoration must never compete with that task. Use Indonesian copy, plain language, strong visual hierarchy, and generous whitespace.

- **Brand context:** part of SuperBie, a planned integrated portal for public services.
- **MVP scope:** Lapor Pak Wali only. Do not design active screens for SIPHP, weather dashboard, or JDIH; they may appear only as disabled/future navigation placeholders if explicitly approved.
- **Density:** low-to-medium; forms and case details must be easy to scan.
- **Responsive:** mobile-first; form controls full-width on mobile.
- **Tone:** respectful, neutral, non-partisan, public-service oriented.
- **Avoid:** fake metrics, fake testimonials, unapproved government seals/logos, excessive gradients, noisy backgrounds, and decorative movement that obstructs reading.

## 2. Visual tokens

Use CSS custom properties or Tailwind theme tokens; avoid random hard-coded values.

### Color palette

| Token | HEX | Usage |
|---|---|---|
| `--color-primary-700` | `#1D4ED8` | Main action / links |
| `--color-primary-600` | `#2563EB` | Interactive primary |
| `--color-primary-100` | `#DBEAFE` | Soft primary surface |
| `--color-secondary-700` | `#0F766E` | Supporting accents |
| `--color-secondary-100` | `#CCFBF1` | Soft supporting surface |
| `--color-accent-500` | `#F59E0B` | Attention accent, sparingly |
| `--color-bg` | `#F8FAFC` | App background |
| `--color-surface` | `#FFFFFF` | Cards/forms |
| `--color-text` | `#0F172A` | Primary text |
| `--color-text-muted` | `#475569` | Secondary text |
| `--color-border` | `#E2E8F0` | Borders |
| `--color-success` | `#15803D` | Success state |
| `--color-warning` | `#B45309` | Warning state |
| `--color-error` | `#B91C1C` | Error state |
| `--color-info` | `#0369A1` | Informational state |

Check actual contrast in rendered components; do not assume every pairing is accessible. Color must not be the only way to communicate status.

### Typography
- Primary: `Inter`, fallback `ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif`.
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
1. **Landing page:** accessible header, service hero, clear “Kirim Pengaduan” primary CTA, “Lacak Pengaduan” secondary CTA, short steps, privacy reminder, footer with only approved contact/official information.
2. **Registration page:** clear account fields, password guidance, validation feedback, and accessible success/error states.
3. **Login page:** login form, link to registration and reset password, generic authentication errors.
4. **Citizen dashboard:** ringkasan laporan milik pengguna, akses riwayat, laporan terbaru, and primary “Buat Pengaduan” CTA.
5. **Submission form:** authenticated-only; category selector shows the related dinas/unit; clear labels; attachment guidance; privacy notice before submit; error summary and field-level errors. Daily-limit feedback must be clear without exposing internal rules beyond the configured limit message.
6. **Receipt page:** reference code, one-time tracking secret if used, copy action, next steps, warning to save the code securely, and related dinas/unit. Do not put tracking secret in URL.
7. **Tracking page:** reference/verification form, loading feedback, generic invalid result, public status timeline, no private notes or PII.
8. **Profile page:** view/edit allowed account information.
9. **Not found/error pages:** plain language and safe way back.

### Internal panel
- Sidebar/navigation collapses on mobile.
- Dashboard uses real database values only.
- **Operator dashboard:** operational complaint management (review, category, assignment, status, notes, responses).
- **Admin dashboard:** monitoring only; no management actions.
- **Super Admin dashboard:** full management navigation.
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
- Prefer specific labels: “Kirim Pengaduan”, “Lacak Pengaduan”, “Simpan Perubahan”.
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
