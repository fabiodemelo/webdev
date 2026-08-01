# System: Documentation Site (product docs section)

A complete, self-hosted **product documentation section** bolted onto an existing marketing site: file-based MDX pages, a grouped sticky sidebar, a section-card index, typed frontmatter, full-text search, `TechArticle` JSON-LD per page, and — the part most docs setups skip — a **Playwright capture script that logs into your own app and refreshes every screenshot automatically**, so the docs never drift from the product.

**Type:** frontend content system (content collection + dynamic route + layout + 2 MDX components + screenshot automation). Bolts onto any Astro site; the architecture ports to Next.js/Nuxt with the same 4 pieces.

**Reference stack:** Astro 5 + MDX + Tailwind (+ `@tailwindcss/typography`) + Pagefind (search) + Playwright (screenshots). No CMS, no database, no runtime — everything is static at build time.

> **Source build:** Klees (`www.klees.app/docs`) — 29 pages across 13 sections, 17 auto-captured screenshots. Reference files in [reference/](reference/), lifted verbatim from production.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap the brand color, section list, and capture routes.

---

You are given a task to add a **product documentation section** (`/docs`) to a marketing site.

### 1. What you're building

Four moving parts. Understand them before writing code — everything else is styling.

| Piece | File | Job |
|---|---|---|
| **Schema** | `src/content/config.ts` | Typed frontmatter: title, description, **section**, order. Build fails on a bad page. |
| **Route** | `src/pages/docs/[...slug].astro` | One static page per MDX file. |
| **Layout** | `src/layouts/Doc.astro` | Sidebar (grouped by section) + article + prose styling. |
| **Index** | `src/pages/docs.astro` | Landing page — one card per section. |

Plus two authoring components (`Shot`, `Callout`) and one automation script (`capture-docs.mjs`).

**The core idea:** a page's `section` frontmatter field is the *only* thing that places it in the nav. Drop in an `.mdx` file, it appears in the sidebar, the index, the sitemap, and search. No registry to update.

### 2. Install

```bash
npx astro add mdx tailwind sitemap
npm install -D @tailwindcss/typography pagefind playwright
```

`astro.config.mjs` — add `mdx()` to integrations and set `site` (required for JSON-LD/sitemap URLs). Add typography to `tailwind.config`:

```js
plugins: [require('@tailwindcss/typography')]
```

Build command runs Pagefind over the output:

```json
"build": "astro build && pagefind --site dist"
```

### 3. Schema — the section enum is load-bearing

`src/content/config.ts`:

```ts
import { defineCollection, z } from 'astro:content';

const docs = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string().min(3).max(80),
    description: z.string().min(40).max(180), // also the meta description
    section: z.enum([
      'Getting Started',
      'Core Feature A',
      'Core Feature B',
      'Administration',
      'Integrations',
      'Reference',
    ]),
    order: z.number().int().default(0), // position within the section
    updatedDate: z.coerce.date().optional(),
  }),
});

export const collections = { docs };
```

**Why an enum and not a free string:** a typo (`"Getting started"`) would silently create a second, half-empty sidebar group. The enum turns that into a build error. The cost is that adding a section means editing three places — the enum, `sectionOrder`, and `sectionIcons`. That trade is worth it; keep them adjacent in your head.

`description` is doing double duty: sidebar-adjacent subtitle **and** the `<meta name="description">`. The 40–180 char bounds keep it SEO-valid.

### 4. Route + layout

Copy [reference/slug-route.astro](reference/slug-route.astro) → `src/pages/docs/[...slug].astro` (30 lines: `getStaticPaths` over the collection, render into the layout).

Copy [reference/Doc.astro](reference/Doc.astro) → `src/layouts/Doc.astro`. What it does:

- **Groups every doc by section**, sorts by `order` then title, renders the sidebar in a hardcoded `sectionOrder` array (so nav order is editorial, not alphabetical).
- **Sticky sidebar** — `lg:sticky lg:top-8 lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto`. The sidebar scrolls independently; long docs don't strand the nav.
- **Active page** gets a left border + primary color, and `aria-current="page"`.
- **Breadcrumb** (Docs › Section), title, description, "Updated <date>".
- **Prose styling** — one long `prose-*` class chain on the content wrapper. This is where the docs get their look; tune it once here and every page follows. Notable: `prose-h2` gets a bottom border (visual section breaks), `prose-img` gets a rounded border (screenshots look framed), `prose-code` gets a tinted background.

Grid: `lg:grid-cols-[240px_1fr]` — fixed sidebar, fluid content.

### 5. Index page

Copy [reference/docs-index.astro](reference/docs-index.astro) → `src/pages/docs.astro`. One card per section with an inline stroke-SVG icon and its page list.

Icons are **raw SVG path strings in a `sectionIcons` map** — not an icon library, not emoji. Rationale: zero dependency, no font loading, and they inherit `currentColor` so they theme for free. Each new section needs a path added to the map.

### 6. The two authoring components

**`Shot`** ([reference/Shot.astro](reference/Shot.astro)) — a screenshot with a caption. Its one clever behavior: **`src` is optional**. With no `src` it renders a labeled dashed placeholder box sized to the right aspect (16:9 desktop, 9:19 mobile).

```astro
<Shot device="desktop" alt="Timesheet grid" caption="The weekly grid." />
<Shot src="/docs-img/timesheets/grid.png" device="mobile" alt="..." caption="..." />
```

This is what lets you **write all the pages first and capture screenshots later** without leaving broken images or blank gaps. Every missing shot is visible and self-labeling. Ship the prose, fill the images in a second pass.

**`Callout`** ([reference/Callout.astro](reference/Callout.astro)) — four variants: `note`, `tip`, `warning`, `admin`. Each has its own color, icon path, and default label.

```astro
<Callout type="admin">Only owners and company admins can do this.</Callout>
<Callout type="warning" title="Careful">This cannot be undone.</Callout>
```

The `admin` variant matters more than it looks: product docs constantly need "not everyone can do this," and a consistent badge beats a paragraph of prose every time.

Import both at the top of any `.mdx`:

```mdx
import Shot from '../../components/docs/Shot.astro';
import Callout from '../../components/docs/Callout.astro';
```

### 7. Screenshot automation — the differentiator

Copy [reference/capture-docs.mjs](reference/capture-docs.mjs) → `scripts/capture-docs.mjs`. It logs into your live app with Playwright, walks a list of routes, and writes PNGs to `public/docs-img/<slug>/<name>.png`.

```bash
APP_EMAIL=you@co.com APP_PASSWORD=... node scripts/capture-docs.mjs
```

Edit one array to define coverage:

```js
const SHOTS = [
  { slug: 'overview',   name: 'dashboard',  path: '/admin/dashboard' },
  { slug: 'timesheets', name: 'timesheets', path: '/admin/timesheets' },
];
```

Filenames are deterministic, so `Shot src` paths never change — **re-run after any UI change and every screenshot in the docs refreshes.** That's the whole point: docs screenshots rot faster than docs prose, and this makes refreshing them a one-command chore instead of an afternoon.

**Two traps, both hit in the source build:**

1. **Verify login by session token, not URL.** `waitForURL('**/admin/**')` also matches `/admin/login` — the first run silently captured 17 screenshots *of the login page*. Wait for the real artifact instead:
   ```js
   await page.waitForFunction(() => !!localStorage.getItem('app:access'));
   ```
   And re-check per shot: `if (page.url().includes('/login')) throw new Error('session lost')`.
2. **Screenshot whoever you log in as.** Logging in as a superadmin captures the *platform* sidebar, not the customer view. Use a normal account with realistic demo data — or seed a demo tenant with fictional names (privacy + a fuller-looking product in one move).

Add `--full-page` per shot if you want whole scrollable pages; default is viewport (1440×900 desktop, 390×844 mobile).

### 8. Search + SEO (near-free)

**Search** — Pagefind indexes the built HTML at build time; no config, no service. A search page is ~20 lines:

```astro
<link rel="stylesheet" href="/pagefind/pagefind-ui.css" />
<div id="search"></div>
<script>
  import { PagefindUI } from '/pagefind/pagefind-ui.js';
  new PagefindUI({ element: '#search' });
</script>
```

**JSON-LD** — copy [reference/TechArticleJsonLd.astro](reference/TechArticleJsonLd.astro) and render it in the layout's head slot. Emits `TechArticle` per page with `articleSection`, `dateModified` (from `updatedDate`), and publisher/website `@id` references. This is what earns docs pages rich results.

**Sitemap** — `@astrojs/sitemap` picks the pages up automatically; wire `updatedDate` into the serializer for per-page `<lastmod>`.

### 9. Authoring conventions

Structure that held up across 29 pages — copy [reference/example-page.mdx](reference/example-page.mdx) as the starting template:

```mdx
---
title: "Reviewing & approving timesheets"
description: "The weekly grid — review every entry, fix, approve for payroll."
section: "Timesheets & Approvals"
order: 0
updatedDate: 2026-07-27
---
import Shot from '../../components/docs/Shot.astro';
import Callout from '../../components/docs/Callout.astro';

One-paragraph orientation: what this screen is and why it exists.

<Shot src="/docs-img/timesheets/grid.png" device="desktop" alt="..." caption="..." />

## Doing the main thing
1. Numbered steps, imperative voice.
2. **Bold** the literal UI labels the user must find.

<Callout type="admin">Who is allowed to do this.</Callout>
```

Rules worth enforcing:

- **Every page opens with orientation, not a step.** Readers arrive from search mid-task.
- **Bold every literal UI string** (`**Approve all**`) — makes scanning for the button trivial.
- **Numbered lists only for real sequences.** Bullets for options.
- **Tables for permission matrices and field references.** Prose for behavior.
- **Cross-link generously** — `[Roles & access](/docs/roles-and-access/)`. Sections are silos otherwise.
- **`updatedDate` on every edit.** It renders on the page and drives sitemap `lastmod`.

### 10. Order of work (what actually goes fastest)

1. Schema + route + layout + index — the shell, ~1 hour. Verify with two throwaway pages.
2. **Write every page with placeholder `Shot`s.** Don't stop for images. The placeholders make gaps obvious.
3. Capture screenshots in one pass, wire the `src` paths, re-run the build.
4. Search + JSON-LD last — they're additive and need no page changes.

Inverting 2 and 3 is the classic mistake: you stall the whole docs effort on screenshot logistics.

### 11. Verify before calling it done

```bash
npm run build   # schema errors fail the build — this is your linter
```

- [ ] Every section in the enum appears in `sectionOrder` **and** `sectionIcons` (a missing icon renders an empty card).
- [ ] Sidebar highlights the current page and scrolls independently on a long doc.
- [ ] Every `Shot src` resolves — placeholders are fine, 404s are not (`curl -o /dev/null -w '%{http_code}' <url>`).
- [ ] `/pagefind/` exists in `dist` and search returns docs hits.
- [ ] JSON-LD validates (Google Rich Results Test) on one page.
- [ ] Mobile: sidebar collapses, no horizontal scroll, tables scroll inside their own container.

### 12. Porting to Next.js / Nuxt

The architecture is framework-agnostic — same four pieces:

| Astro | Next.js (App Router) | Nuxt |
|---|---|---|
| Content collection + Zod | `contentlayer` / MDX + Zod | `@nuxt/content` + schema |
| `[...slug].astro` | `app/docs/[...slug]/page.tsx` | `pages/docs/[...slug].vue` |
| `Doc.astro` | `app/docs/layout.tsx` | `layouts/docs.vue` |
| Pagefind | Pagefind (same — indexes built HTML) | Pagefind |

`Shot`/`Callout` become `.tsx`/`.vue` with identical props. `capture-docs.mjs` is pure Playwright — it ports unchanged.
