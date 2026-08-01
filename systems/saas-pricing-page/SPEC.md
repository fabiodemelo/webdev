# System: SaaS Pricing Page (live plans + savings calculator)

A conversion-focused **pricing page** that reads its plans from your own admin at build time (edit a plan in the admin → the marketing site updates on next deploy, no code change), plus a monthly/yearly toggle, an **interactive crew-size slider that computes live savings against named competitors**, a capability matrix, and `Offer` JSON-LD so AI assistants and Rich Results can quote your prices correctly.

**Type:** marketing page system (build-time data fetch + 6 composable sections + structured data). One `.astro` file plus a JSON-LD component — no client framework, ~4 KB of vanilla JS.

**Reference stack:** Astro + Tailwind, fed by any JSON plans endpoint (`GET /api/v1/plans`). The data layer is stack-neutral — the fetch is 8 lines and the sections are plain HTML + a `<script>`.

> **Source build:** Klees (`www.klees.app/pricing`) — 5 tiers, base + per-seat pricing, 3 competitors, 16-row capability matrix. Reference files in [reference/](reference/), lifted verbatim from production.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap the plans endpoint, competitor set, and capability rows.

---

You are given a task to build a **SaaS pricing page** with live plan data and a savings calculator.

### 1. The six modules

Build in this order — each is independent, and the first two carry most of the value.

| # | Module | What it does |
|---|---|---|
| 1 | **Live plan fetch** | Pulls plans from your admin API at build time; static fallback so the build never breaks. |
| 2 | **Plan cards** | Responsive grid, "most popular" highlight, dark enterprise card, per-plan CTA. |
| 3 | **Billing toggle** | Monthly ⇄ yearly, swaps every card price in place. |
| 4 | **Savings calculator** | Crew-size slider → live per-competitor totals + savings banner. |
| 5 | **Capability matrix** | Feature-by-feature table vs named competitors. |
| 6 | **JSON-LD + CTA band** | `Offer` structured data, closing call to action. |

### 2. Module 1 — plans from your admin, not from code

**The point:** pricing lives in one place (your admin), and the marketing site is a read-only mirror. No more editing two systems and drifting.

```ts
const PLANS_URL = (import.meta.env.PUBLIC_API_URL ?? 'https://your.app') + '/api/v1/plans';

let plans: ApiPlan[] = FALLBACK_PLANS;   // <- always define a fallback
try {
  const res = await fetch(PLANS_URL, { headers: { Accept: 'application/json' } });
  if (res.ok) {
    const data = (await res.json()) as { items: ApiPlan[] };
    if (Array.isArray(data.items) && data.items.length > 0) plans = data.items;
  }
} catch {
  // Build-time fetch failed (network, API down) — keep fallback.
}
```

**The fallback is not optional.** A static site build that hard-fails because an API blipped is a self-inflicted outage on your highest-intent page. Keep `FALLBACK_PLANS` shaped exactly like the API response and refresh it whenever prices really change.

The plan shape that supports base + per-seat SaaS pricing:

```ts
interface ApiPlan {
  slug: string;                // 'pro' — used for /signup?plan=pro
  name: string;
  description: string;
  basePriceCents: number;      // flat monthly base
  seatPriceCents: number;      // per active user
  includedSeats: number | null;// seats bundled into the base
  trialDays: number;           // drives the CTA label
  features: {
    yearlyPriceCents?: number; // if absent, derive as base * 12 * 0.85
    userLimit?: number | null; // null = unlimited
    mostPopular?: boolean;
    list?: string[];           // bullet points on the card
  };
}
```

Server side, expose only what marketing needs (`visible: true`, ordered by `sortOrder`) — never the Stripe price IDs or internal margins.

### 3. Module 2 — plan cards

Grid that survives 3–5 tiers: `sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5`, `max-w-[1440px]`.

Three card treatments, chosen from data (not hardcoded per tier):

```ts
const cardClass = isEnterprise
  ? 'border border-ink bg-ink text-white'                        // dark, anchors the high end
  : popular
    ? 'border-2 border-primary bg-white shadow-xl shadow-primary-500/10'
    : 'border border-slate-200 bg-white';
```

CTA adapts per plan — self-serve tiers go to signup with the plan preselected; the top tier goes to sales:

```ts
const ctaHref  = isEnterprise ? 'mailto:sales@you.com?subject=Enterprise inquiry' : `/signup?plan=${p.slug}`;
const ctaLabel = isEnterprise ? 'Talk to sales' : `Start ${p.trialDays}-day trial`;
```

Put the trial length **in the button**. "Start 30-day trial" outperforms "Get started" because it answers the risk question at the moment of click.

### 4. Module 3 — billing toggle without a framework

Render both prices into `data-` attributes at build time, then swap text on click. No re-render, no hydration:

```html
<span class="plan-price"
      data-monthly-cents={p.basePriceCents}
      data-yearly-monthly-cents={monthlyFromYearly(yearlyCents)}>…</span>
<span class="monthly-sub">{cents(yearlyCents)}/yr</span>
<span class="yearly-sub hidden">billed annually</span>
```

```js
document.querySelectorAll('.plan-price').forEach((el) => {
  const c = period === 'year'
    ? Number(el.dataset.yearlyMonthlyCents)
    : Number(el.dataset.monthlyCents);
  el.textContent = fmt(c);
});
document.querySelectorAll('.monthly-sub').forEach((el) => el.classList.toggle('hidden', period === 'year'));
document.querySelectorAll('.yearly-sub').forEach((el) => el.classList.toggle('hidden', period !== 'year'));
```

Show yearly as a **monthly-equivalent** figure (`yearly / 12`), not the annual lump sum — buyers compare monthly numbers, and the discount only reads as a discount in the same unit. Put "Save 15%" on the toggle itself.

**Disclose mixed billing.** If yearly prepays the base but seats still bill monthly on usage, say so in a note under the toggle. Discovering that at the invoice is a support ticket and a trust hit:

> *Yearly plans pay the base subscription up front. Active-user (seat) charges are still billed monthly on actual usage.*

### 5. Module 4 — the savings calculator (highest-value module)

A range input for crew size that recomputes a comparison table and a savings banner on every tick. This is the section that does the persuading, because the visitor plugs in *their* number.

```ts
// Only seats beyond the included allotment bill at the seat rate.
const total = (p, seats) => p.base + p.seat * Math.max(0, seats - (p.included || 0));
```

**The honesty rule that makes it credible:** compare against the **cheapest plan the crew actually fits into**, not your flagship. Compute it, and label it.

```ts
const eligible = KLEES.all.filter((p) => p.userLimit == null || p.userLimit >= seats);
const best = eligible.reduce((a, b) => (total(a, seats) <= total(b, seats) ? a : b));
```

Then render your row with the plan name shown (`Klees (Basic plan)`) so a 12-person crew sees the tier they'd genuinely buy. A calculator that always quotes your top tier gets caught and destroys the page's credibility.

Competitors are a static array — refresh quarterly and **date it**:

```ts
const COMPETITORS = [
  { name: 'ClockShark',      std: { base: 40, seat: 8,     included: 0 }, pro: { base: 60, seat: 10,    included: 0 } },
  { name: 'busybusy',        std: { base: 0,  seat: 11.99, included: 0 }, pro: { base: 0,  seat: 19.99, included: 0 } },
  { name: 'QuickBooks Time', std: { base: 20, seat: 10,    included: 0 }, pro: { base: 40, seat: 10,    included: 0 } },
];
```

> Prices in USD per month, billed monthly. Competitor prices from public pricing pages, Q2 2026 snapshot.

Show each competitor's **delta vs you** (`+38%`) next to their price, and a banner with the average saving. Legally and reputationally: only use **publicly published** prices, cite the snapshot date, and re-check quarterly.

### 6. Module 5 — capability matrix

A table of capabilities × competitors. Cells accept three states, so you can be precise instead of binary:

```ts
function cell(v: string) {
  if (v === 'yes') return { txt: '✓', cls: 'text-success-600 font-bold text-lg' };
  if (v === 'no')  return { txt: '—', cls: 'text-slate-300' };
  return { txt: v, cls: 'text-slate-400 text-xs' };  // 'limited', '14 days, card', 'Enterprise only'
}
```

That third state is what separates a useful matrix from marketing noise — `limited`, `historical`, `trails only`, `Enterprise only` are more persuasive than a dishonest ✗, and they survive a competitor reading the page.

Lead with capabilities **only you** have; close with ones everyone has. Footnote the source: *"from each vendor's public documentation, Q2 2026."*

### 7. Module 6 — JSON-LD

Copy [reference/PricingJsonLd.astro](reference/PricingJsonLd.astro). Emits `WebPage` → `ItemList` → `Offer` per plan with `UnitPriceSpecification` describing base + seat pricing in words:

```ts
unitText: 'per month base + $6.25 per user per month'
```

This is how assistants quote your price correctly instead of hallucinating it. Keep it in sync when prices change — it's the one part that doesn't auto-update from the API.

### 8. Install

```bash
# Astro
npx astro add tailwind
# no other deps — the page is one .astro file + one JSON-LD component
```

Copy [reference/pricing.astro](reference/pricing.astro) → `src/pages/pricing.astro` and edit, in order: `PLANS_URL`, `FALLBACK_PLANS`, `COMPETITORS`, `capabilities`, then the JSON-LD.

### 9. Verify before shipping

- [ ] **Kill the API and rebuild** — page still builds and shows fallback prices. (Test this deliberately; it's the failure mode that takes the page down.)
- [ ] Toggle swaps every card, including the sub-line and the yearly note.
- [ ] Slider at 1, at your smallest tier's limit, at the limit + 1, and at max — the named plan changes at the right thresholds and no total goes negative.
- [ ] `includedSeats` tiers don't bill the included seats (Enterprise at exactly N seats = base price).
- [ ] Competitor snapshot date is present and current.
- [ ] JSON-LD prices match the rendered prices (Rich Results Test).
- [ ] Mobile: 5 cards stack, the table scrolls inside its own `overflow-x-auto` container, the slider is thumb-friendly.

### 10. Porting

The data layer is a `fetch` and the interactivity is vanilla JS on `data-` attributes — both port unchanged.

| Astro | Next.js | Nuxt |
|---|---|---|
| top-of-file `await fetch` | `async` Server Component / `generateStaticParams` | `useAsyncData` |
| `<script>` in page | `'use client'` island for slider only | `<script setup>` |
| `PricingJsonLd.astro` | `<Script type="application/ld+json">` | `useHead({ script })` |

Keep the calculator as an island — everything else should stay static HTML.
