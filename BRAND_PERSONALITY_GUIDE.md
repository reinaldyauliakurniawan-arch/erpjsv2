# Just Speak — Brand Personality & Design Guide

> Internal reference for anyone (human or AI) working on the Just Speak UI.
> Last updated: 2026-06-23

---

## 1. Brand Identity

### Who We Are

Just Speak is an English Learning Management System built for **university students and working professionals** who want to improve their speaking skills in a structured yet approachable environment. We sit in the **mid-range** segment — not a budget commodity, not an elite luxury — delivering competent tutoring and real progress at a fair price.

### Archetype: The Friendly Coach

We combine two archetypes:

- **The Everyman** — approachable, unpretentious, "one of us." No gatekeeping, no academic intimidation. Everyone deserves to speak confidently.
- **The Sage** — authoritative where it matters. Our systems, our reporting, our operations are thorough and trustworthy. When a student or parent looks at their progress, the data is reliable.

### The Analogy

> Kayak dosen muda yang ngajarnya asik dan gokil, tapi ujian-nya tetap ketat dan materinya deep.

Fun to be around. Serious about results.

### The Tension We Embrace

| Dimension | Value | Why |
|---|---|---|
| **Personality** | Fun & energik | Our users are learning to *speak* — it should feel alive, not clinical |
| **Dashboard feel** | Trust & authority | Students, parents, and staff need to feel "this place is serious" |
| **Visual reference** | Clean like Google/Notion | Information-dense ERP needs clarity above all else |
| **Language** | Bilingual (Indo + English) | This IS an English learning center — the UI itself teaches by immersion |

This tension is a **feature, not a bug**. A brand that is only fun feels like a toy. A brand that is only serious feels like a hospital. Just Speak is neither — it is a place where you work hard, but you don't dread it.

---

## 2. Design Direction: Warm Minimalism

### One-Line Definition

> Google-level structural clarity, warmed up with a human undercurrent.

### What This Means in Practice

1. **Layout and hierarchy** — Google-clean. Every page has a clear purpose, clear information hierarchy, and zero visual noise. White space is a tool, not waste.
2. **Color** — Not the cold blue-grey of a corporate SaaS. Our green (#00a982) is the anchor, and it carries warmth and growth. Secondary colors are functional, not decorative.
3. **Typography** — Clean and legible first. Personality comes from how we *use* type (size contrast, weight contrast), not from decorative fonts.
4. **Micro-copy** — This is where the "fun & energik" personality lives. Button labels, empty states, success messages, error messages — these are where we talk to the user as a human.
5. **Interactions** — Subtle, purposeful, fast. No gratuitous animations. Every transition should feel like the app respects the user's time.

### What We Are NOT

- Not playful/childish (we are not Duolingo)
- Not cold/corporate (we are not SAP)
- Not minimalist-to-the-point-of-sterile (we are not a government portal)
- Not trendy/overdesigned (we are not a startup landing page)

---

## 3. Color System

### Core Principle

All colors are defined as CSS custom properties in `resources/css/app.css` under `@theme {}`. The runtime theming system (Settings > Colors) overrides `--color-primary`, `--color-secondary`, and `--color-sidebar-bg` via the layout's `:root` block. **Never hardcode color hex values in Blade templates.** Always use the token.

### Primary Palette

| Token | Default Value | Role | Usage |
|---|---|---|---|
| `--color-primary` | `#065f46` | Dark green — authority | Active states, important badges, sidebar accent (dark mode) |
| `--color-secondary` | `#059669` | Bright green — energy | Avatar backgrounds, success indicators, links, progress bars |
| `--color-inverse-primary` | `#059669` | Mirror of secondary | Used when primary/secondary need to swap contexts |

### Guest (Auth) Palette

| Token | Default Value | Role |
|---|---|---|
| `--color-guest-gradient-from` | `#00a982` | Login/register page gradient start |
| `--color-guest-gradient-via` | `#007a60` | Gradient midpoint |
| `--color-guest-gradient-to` | `#006b5a` | Gradient end |

The guest pages use `#00a982` as the signature green — brighter and more vibrant than the in-app primary. This is intentional: the login page is the first impression, and it should feel warm and inviting. Once inside, the palette settles into the more restrained `#065f46` / `#059669` for sustained use.

### Surface & Text Palette

| Token | Value | Notes |
|---|---|---|
| `--color-surface` | `#f3f4f6` | Main background — slightly warm grey |
| `--color-surface-bright` | `#ffffff` | Cards, modals, elevated surfaces |
| `--color-surface-container-low` | `#f9fafb` | Subtle container backgrounds |
| `--color-surface-border` | `#e5e7eb` | All borders — soft, not harsh |
| `--color-on-surface` | `#111827` | Primary text — near-black |
| `--color-on-surface-variant` | `#6b7280` | Secondary text — muted |

### Status Colors

| Token | Value | When to Use |
|---|---|---|
| `--color-error` | `#dc2626` | Destructive actions, overdue items, validation errors |
| `--color-success` | `#059669` | Completed states, confirmed actions |
| `--color-warning` | `#d97706` | Expiring soon, needs attention, caution |

### Color Personality Rules

1. **Green is our identity.** It represents growth (learning), freshness (approachable), and trust (money/finance metaphor in Indonesian culture). Never replace it with blue or purple.
2. **The surface palette stays neutral.** Backgrounds, borders, and containers are grey-scale. This keeps the green impactful — if everything were tinted green, it would lose its meaning.
3. **Status colors are non-negotiable.** Red means danger, amber means caution, green means good. Never use these colors for decorative purposes.
4. **Tertiary color (`--color-tertiary: #b45309`)** is reserved for special highlights and should be used sparingly — it is an amber/warm tone that adds warmth without competing with the green.

---

## 4. Typography

### Font Stack

```css
--font-sans: "Montserrat", ui-sans-serif, system-ui, sans-serif;
```

Montserrat is our only font. It is clean, geometric, and works well at both display and body sizes. It is not the most distinctive font, but it is reliable and professional — and **personality comes from how we use it, not from the font itself.**

### Type Scale

| Token | Size | Weight | Usage |
|---|---|---|---|
| `text-display-lg` | 67.77px | Bold | Reserved for marketing/landing pages only |
| `text-display-md` | 41.89px | Bold | Reserved for marketing/landing pages only |
| `text-headline-lg` | 25.89px | Bold | Page titles inside the app (e.g., "Dashboard Utama") |
| `text-headline-lg-mobile` | 21px | Bold | Mobile version of headline-lg |
| `text-headline-md` | 16px | Semibold | Section headings, topbar title |
| `text-body-lg` | 16px | Regular | Primary body text, table cells |
| `text-body-md` | 13px | Regular | Secondary text, sidebar links, captions |
| `text-label-lg` | 11px | Semibold | Labels, badges, table headers, timestamps |

### Typography Rules

1. **Never use display sizes inside the app.** Display typography is for public-facing pages only (landing pages, marketing emails). Inside the ERP, `headline-lg` (25.89px) is the maximum.
2. **Size contrast creates hierarchy.** A `headline-lg` next to a `label-lg` (25.89px vs 11px) creates strong visual separation without needing color or decoration.
3. **Body text is 13px.** This is intentionally compact for an information-dense ERP. Do not increase it — it would reduce the amount of useful information visible per screen.
4. **Font weight creates emphasis, not color.** Use `font-bold` (700) or `font-semibold` (600) to draw attention. Avoid relying on colored text for emphasis in body copy.

---

## 5. Spacing & Layout

### Spacing Scale

| Token | Value | Feel |
|---|---|---|
| `--spacing-xs` | 4px | Tight — between related inline elements |
| `--spacing-sm` | 8px | Cozy — between icon and text in a button |
| `--spacing-md` | 16px | Standard — between form fields, card padding |
| `--spacing-lg` | 24px | Breathing room — section padding, card gaps |
| `--spacing-xl` | 40px | Section separation |
| `--spacing-xxl` | 64px | Major section breaks |

### Layout Constants

| Token | Value | Role |
|---|---|---|
| `--size-sidebar-width` | 240px | Sidebar width (desktop) |
| `--size-topbar-height` | 64px | Top bar height |
| `--width-container-max` | 1440px | Maximum content width |

### Layout Rules

1. **Sidebar is 240px.** This is fixed and intentional — wide enough for labels, narrow enough to maximize content area.
2. **Content area uses `p-lg` (24px) as standard padding.** Use `p-md` (16px) only for nested containers within the main content area.
3. **Cards use `bg-surface-bright` (#ffffff) with `border-surface-border` (#e5e7eb).** This creates a subtle lifted effect against the `#f3f4f6` background.
4. **Gap between cards is `gap-lg` (24px).** Consistent spacing between grid items.

---

## 6. Motion & Interaction

### Motion Tokens

| Token | Value | Usage |
|---|---|---|
| `--motion-fast` | 150ms | Micro-interactions (hover color change, button press) |
| `--motion-base` | 200ms | Standard transitions (dropdown open, tab switch) |
| `--motion-slow` | 300ms | Content transitions (page slide, modal appear) |
| `--motion-easing` | `cubic-bezier(0.4, 0, 0.2, 1)` | Material standard easing — feels natural, not mechanical |

### Motion Rules

1. **Respect `prefers-reduced-motion`.** The base layer in `app.css` already handles this globally. Never override it.
2. **No animation longer than 300ms inside the app.** Users are here to get things done. A 500ms+ transition feels sluggish.
3. **Hover effects use `--motion-base` (200ms).** Fast enough to feel responsive, slow enough to be visible.
4. **Transform-only animations.** When animating elements, prefer `transform` and `opacity` over `width`, `height`, `top`, or `left` to avoid expensive repaints.
5. **No continuous decorative animations.** Animated elements should serve a functional purpose (loading indicator, progress feedback). Never animate icons, badges, or decorative elements in loops.
6. **The glass button hover (`translateY(-1px)`) is the maximum "playful" interaction on auth pages.** Do not add bounce, wiggle, or pulse effects elsewhere.

---

## 7. Two Modes: Temperature System

The Just Speak UI has two "temperature modes" that share the same design system but differ in emotional tone.

### Cool Mode (Admin, CFO)

**Feel:** "I am in control. Everything is where I expect it."

- Maximum information density
- Neutral, data-driven language
- Charts, tables, numbers dominate
- Green appears only as accent (active states, success)
- No illustrations or decorative elements
- Sidebar section labels ("Laporan", "Perencanaan", "Master & Operasional") provide clear grouping

**Example micro-copy:**
- Page title: "Dashboard Utama"
- Subtitle: "Ringkasan operasional Just Speak hari ini."
- Empty state: "Belum ada data enrollment."
- Alert: "3 Enrollment Tanpa Tutor"

### Warm Mode (Student, Tutor)

**Feel:** "I am making progress. This is working."

- More white space, less density
- Encouraging, human language
- Progress indicators, streaks, achievements
- Green appears more prominently (progress bars, success states)
- Illustrations welcome in empty states
- Bilingual micro-copy with personality

**Example micro-copy:**
- Page title: "Dashboard"
- Subtitle: "Selamat datang kembali! Kamu udah belajar 3 hari berturut-turut."
- Empty state: "Belum ada tugas buat sekarang. Santai aja dulu, atau cek jadwal kamu."
- Success: "Jawaban kamu udah tersimpan! Keep going."

### Rules for Both Modes

1. **Same components, same tokens, same spacing.** The difference is in copy, content density, and how prominently green is used — not in layout or component design.
2. **Both modes share the same sidebar.** The sidebar is neutral infrastructure.
3. **The topbar is identical across modes.** Only the page title changes.

---

## 8. Language & Tone of Voice

### The Bilingual Principle

Just Speak deliberately uses **mixed Indonesian and English** in its UI. This is not laziness — it is a brand choice.

**Why:** Users are at an English learning center. Seeing English terms in context (sidebar: "Dashboard", "Enrollments", "Class Sessions") provides passive exposure. Indonesian is used for operational terms that need immediate clarity (sidebar: "Jadwal", "Absensi", "Buat Tugas").

### Language Rules

1. **Navigation labels** — English for globally understood terms (Dashboard, Students, Programs, Settings). Indonesian for culturally specific operational terms (Jadwal, Absensi, Import/Export).
2. **Body text** — Indonesian by default. This includes descriptions, form labels, table headers, and status messages. Users need to understand operational content instantly — forcing English here adds cognitive load, not value.
3. **Data labels and financial terms** — English where it is the industry standard (General Ledger, Trial Balance, Cash Flow, Balance Sheet, RAB). These terms don't have clean Indonesian equivalents, and users in these roles (CFO, admin) already know them.
4. **Micro-copy (student-facing)** — Can be casual and bilingual. This is where personality shines. "Keep going!" is fine. "Kamu udah belajar 3 hari berturut-turut" is fine. Mixing both in one sentence is fine.

### Tone Matrix

| Context | Tone | Example |
|---|---|---|
| Error message | Direct, no blame | "Gagal menyimpan data. Coba lagi dalam beberapa saat." |
| Success message | Brief encouragement | "Tersimpan!" or "Berhasil disimpan." |
| Empty state | Human, slightly warm | "Belum ada data. Mulai dengan menambah enrollment baru." |
| Warning | Clear, actionable | "3 enrollment akan expired dalam 7 hari." |
| Loading | Transparent | "Memuat data..." (never silent) |
| Confirmation | Clear consequence | "Yakin ingin menghapus enrollment ini? Data yang sudah dihapus tidak bisa dikembalikan." |
| Section label (admin) | Neutral, functional | "Laporan", "Perencanaan", "Master & Operasional" |
| Section label (student) | Encouraging | "Progress Kamu", "Tugas Baru" |

---

## 9. Sidebar & Navigation

### Design

- **Width:** 240px fixed (`--size-sidebar-width`)
- **Background:** Dark (`--color-sidebar-bg`, default `#111827`)
- **Text:** Muted (`--color-sidebar-text`, default `#9ca3af`)
- **Active text:** White (`--color-sidebar-text-active`)
- **Active indicator:** 3px left border in accent color (`--color-sidebar-accent`)
- **Section labels:** `text-label-lg`, uppercase, `tracking-widest`, 70% opacity of sidebar text
- **Icon:** Material Symbols Outlined, 18px, unfilled

### Rules

1. **One sidebar component, two renderings.** `partials/sidebar-nav.blade.php` is the single source of truth. Desktop sidebar and mobile drawer both include it.
2. **Active state uses left border, not background highlight.** This is our signature navigation pattern — a 3px accent stripe on the left edge. It is subtle but scannable.
3. **Section labels (CFO only) use `app-sidebar__section-label`.** These are typographically distinct from nav links — smaller, uppercase, tracked out, faded.
4. **The sidebar supports light/dark mode via `.app-sidebar--light`.** When the sidebar background is light (luminance >= 0.4), text colors flip automatically. This is handled server-side in the layout.
5. **Logout uses `.sidebar-link--danger`** which turns text red on hover. This is the only element that breaks the neutral color scheme — intentional for safety.

---

## 10. Guest (Auth) Pages

### Design

- **Background:** Gradient from `#00a982` to `#006b5a` at 135 degrees
- **Card:** Glassmorphism — `backdrop-filter: blur(20px)`, semi-transparent white, 24px border radius
- **Decorative:** Two pseudo-element circles (`::before`, `::after`) with 5% white fill — these add subtle depth without distracting
- **Button:** Near-white (`rgba(255,255,255,0.9)`) with dark green text, lifts 1px on hover
- **Input:** Semi-transparent white with blur, white border

### Rules

1. **The glassmorphism stays on auth pages only.** It is the "front door" of the app — it can be more expressive. Once inside, everything is flat and functional.
2. **The gradient circles (`::before`, `::after`) are the maximum decoration allowed.** Do not add more floating shapes, particles, or patterns.
3. **Button hover is `translateY(-1px)` + stronger shadow.** This is the single "playful" micro-interaction in the entire app. Protect it — don't overuse similar effects elsewhere.

---

## 11. Tables (Tabulator)

### Design

All data tables use Tabulator.js, themed via design tokens in `app.css`.

- **Container:** White background, 12px border radius, subtle box-shadow
- **Header:** Light grey background (`--color-surface-container-low`), uppercase 11px text, sorted column highlight
- **Rows:** White with grey bottom border. Hover: light grey background.
- **Footer:** Light grey background, paginated with bordered pills. Active page: primary color.
- **Pagination:** 4px 10px padding, 2px gap between pills

### Rules

1. **Never style tables with inline styles.** All theming goes through the `.tabulator` selectors in `app.css`.
2. **Active page pill uses `--color-primary`.** This is one of the few places where the dark green appears prominently in the admin interface.
3. **Header text is 11px uppercase.** This is intentionally small and dense — it maximizes data column width.

---

## 12. Accessibility

The following accessibility features are already implemented and must be preserved:

- **Focus-visible rings** on all interactive elements (2px solid secondary, 2px offset)
- **`prefers-reduced-motion`** respected globally — all animations reduced to near-zero
- **Pointer cursor** on all clickable elements; `not-allowed` on disabled
- **Semantic HTML** — `<aside>`, `<nav>`, `<main>`, `<header>` used correctly
- **`aria-label`** on icon-only buttons (mobile menu trigger, close button)
- **Color contrast** — all text/background combinations pass WCAG AA

### Rules

1. **Never remove `focus-visible` styles.** Keyboard navigation must always work.
2. **Never use color as the only indicator.** Error states must include text, not just red color. Success states must include text or icons, not just green.
3. **Touch targets** must be at least 44x44px on mobile (DaisyUI button sizes handle this by default).

---

## 13. Component Patterns

### Cards

Standard card pattern used across the app:

```html
<div class="bg-surface-bright border border-surface-border rounded-[var(--radius-lg)] p-lg shadow-sm">
    <!-- content -->
</div>
```

### Stat Cards (Dashboard)

```html
<div class="bg-surface-bright border border-surface-border rounded-[var(--radius-lg)] p-lg">
    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Label</p>
    <p class="text-headline-lg font-bold text-on-surface mt-xs">Value</p>
</div>
```

### Alert Badges

```html
<span class="badge badge-error badge-soft text-xs">Label</span>
```

### Buttons

- **Primary action:** DaisyUI `btn btn-primary` (uses `--color-primary`)
- **Secondary action:** DaisyUI `btn btn-ghost` or `btn btn-outline`
- **Danger action:** DaisyUI `btn btn-error`
- **Link action:** Plain `<a>` with `text-secondary hover:underline`

---

## 14. What Makes Just Speak Feel Like Just Speak

These are the subtle signatures that, taken together, make the app recognizable even without the logo:

1. **The 3px left-border active state** in the sidebar. Most apps use background highlights. We use a thin accent stripe.
2. **The glassmorphism auth page.** It is the "wow" first impression that transitions into clean functionality inside.
3. **The bilingual labels.** "Jadwal" next to "Class Sessions" next to "Absensi" — this mix is uniquely Just Speak.
4. **The compact 13px body text with generous spacing.** Information-dense but not cramped, because the spacing scale (4/8/16/24/40/64px) creates breathing room between elements.
5. **Green as identity, not decoration.** When you see green in this app, it means something (progress, success, active, brand). It is never used randomly.

---

## 15. Quick Reference: Do / Don't

### Do

- Use design tokens for everything (colors, spacing, motion, typography)
- Keep the two-mode temperature system (cool for admin, warm for student)
- Write human micro-copy — especially for empty states and success messages
- Maintain bilingual consistency per the rules in Section 8
- Use the existing component patterns (cards, badges, tables)
- Test at 1024px and 1440px widths
- Respect `prefers-reduced-motion` and `focus-visible`

### Don't

- Hardcode hex colors in Blade templates
- Use display typography sizes (display-lg, display-md) inside the app
- Add animations longer than 300ms
- Use glassmorphism or gradients on pages other than auth
- Use decorative illustrations on admin/CFO pages
- Use color as the sole indicator of state
- Add new fonts without a strong justification
- Change the sidebar width from 240px
- Use playful micro-interactions (bounce, wiggle, pulse) on admin pages
- Overuse the tertiary color (#b45309) — it is a spice, not an ingredient

---

## 16. Implementation Notes

### Changing Colors

1. **Permanent change:** Edit `@theme {}` in `resources/css/app.css`
2. **Runtime change (per-instance):** Use Settings > Colors page in the admin panel. This updates the database and overrides `:root` CSS variables in the layout.
3. **Adding a new token:** Define it in `@theme {}`, then create a `@utility` if it needs a class name.

### Adding New Pages

1. Extend `x-app-layout` (already provides sidebar, topbar, main content area)
2. Use `p-lg space-y-lg` as the standard page wrapper
3. Follow the component patterns in Section 13
4. Check the tone matrix in Section 8 for appropriate micro-copy

### Working with Tabulator Tables

All table styling is centralized in `app.css` under the `.tabulator` selectors. To customize:
1. Override the CSS selectors — never use inline styles on Tabulator columns
2. Use `--color-primary` for active states and `--color-surface-*` for backgrounds
3. Header columns are automatically uppercase 11px via the global Tabulator styles