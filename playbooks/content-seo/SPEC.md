# Playbook: Content + SEO

Reusable, generic content + SEO playbook for any service business or SaaS. Covers strategy (three content surfaces, keyword tiers, pillar+spoke), article spec, schema, on-page SEO, pricing strategy, author roster, internal/external linking, CTAs, images, publication cadence, trust pages, technical SEO infra, admin surfaces, i18n, case studies, competitor pages, validation checklists, content ops, GEO/AI-search readiness, measurement, and a 6-week launch sequence.

**Type:** multi-page strategy playbook (applied across an entire site, not a single component or subsystem). Stack-flexible — reference tooling is Astro/Next + Zod content collections.

**Usage:** replace placeholders in `[BRACKETS]` with target details (brand, keywords, competitors, vertical).

---

## Integration Prompt

> Paste everything below this line into the target project. Swap `[BRACKETS]` for the target's brand, keywords, and competitors.

---

You are given a task to apply a **content + SEO playbook** to a website (service business or SaaS). Build the strategy foundation, content surfaces, SEO infrastructure, and admin surfaces below. Replace every `[BRACKET]` placeholder with the target's details.

### 1. Strategy Foundation

**1.1 Three pillars** — three distinct content surfaces, different visitors/intents/funnel stages:

| Surface | Purpose | Examples |
|---------|---------|----------|
| Money pages | Convert ready-to-buy traffic | `/pricing`, `/[product]`, `/vs/<competitor>`, `/industries/<vertical>` |
| Authority pages | Build E-E-A-T + topical breadth | `/blog/*`, `/guides/*`, case studies, FAQ |
| Trust pages | Reduce bounce + satisfy crawlers | `/about`, `/contact`, `/privacy`, `/terms`, `/security`, `/team` |

**1.2 Keyword tiers** — for any niche define:
- **Tier 1 — Money (3–5):** high commercial intent, medium difficulty → money pages.
- **Tier 2 — Volume (5–10):** broad informational + commercial → pillar blog content.
- **Tier 3 — Long-tail (30–100+):** specific, low-competition → standard blog posts.
- **Tier 4 — Competitor steal (3–5):** `[competitor] alternative`, `[competitor] vs [you]`. Highest ROI.
- **Tier 5 — Blue ocean (3–5):** keywords where category leaders left a gap (feature or audience nobody serves).

**1.3 Pillar + spoke architecture** — each Tier 1 keyword gets: 1 pillar money page (1500–2500 words) + 5–10 spoke articles linking up to the pillar; spokes cross-link to each other (hub-and-spoke). Build intentionally.

### 2. Article Specification

**2.1 Required frontmatter (Zod-validatable):**
```yaml
---
title: string                    # 50-80 chars, primary keyword in first 5 words
description: string              # 130-160 chars, unique per article
publishDate: YYYY-MM-DD
updatedDate: YYYY-MM-DD          # optional, equals publishDate at launch
author: string                   # references author roster
category: enum                   # one of 5-7 defined categories
tags: string[]                   # 2-8 tags
keyword: string                  # primary target keyword
heroImage: /blog/<slug>/hero.webp
heroAlt: string                  # max 125 chars, includes keyword once
ogImage: string                  # optional, defaults to heroImage
readingTime: int                 # auto-calc or manual, target 6-9
featured: boolean                # default false, only flagship pieces true
draft: boolean                   # default false
---
```

**2.2 Body requirements (mandatory per article):**

| Block | Length | Purpose |
|-------|--------|---------|
| Hook intro | 120–180 words | Pain point in first 50 words. Promise specific outcome. Primary keyword in first 100 words. |
| TL;DR callout | 60–100 words | 3–5 bullets above the fold. AI Overview citation bait. Visual standout box. |
| Body | 800–1100 words | 5–8 H2 sections, each 100–200 words. ≥1 list/table/quote per section. |
| FAQ section | 150–250 words | 4–6 Q&A. Each H3 = question. Schema-marked. Voice-search phrasing. |
| CTA close | 80–120 words | Restate problem → product solves → CTA button block. |

Total: 1200–1600 words min. Pillar pieces: 1800–2500 words.

**2.3 Required elements (validation checklist):**
- ✅ 1 H1 = title (title tag = `{title} | {Brand} Blog`)
- ✅ 5–8 H2 sections (question-style preferred)
- ✅ Optional H3 subsections
- ✅ Hero image (1200×630, WebP, ≤120KB, descriptive alt, `loading="eager" fetchpriority="high"`)
- ✅ ≥2 inline images (800×450, lazy-loaded, set width/height to prevent CLS)
- ✅ TL;DR callout box, visually distinct
- ✅ ≥1 numbered or bulleted list
- ✅ ≥1 data table OR pull quote
- ✅ FAQ block with FAQPage JSON-LD
- ✅ Mid-article soft CTA (inline link to money page)
- ✅ Closing hard CTA (button block, primary + secondary)
- ✅ Author bio at bottom (avatar, name, role, credentials, social link)
- ✅ "Related posts" footer (3 cards, same category)
- ✅ Social share buttons
- ✅ Newsletter signup inline
- ✅ Trust microcopy in footer (legal entity, "Powered by", etc.)

### 3. Schema.org Structured Data

Every article ships three JSON-LD blocks + one global block on every page.

**3.1 Global (every page):**
```json
{
  "@context": "https://schema.org",
  "@graph": [
    { "@type": "Organization", "@id": "...#organization", "name": "...", "url": "...", "logo": "...", "sameAs": [], "founder": {}, "address": {} },
    { "@type": "SoftwareApplication", "@id": "...#product", "name": "...", "offers": [], "aggregateRating": {} },
    { "@type": "WebSite", "@id": "...#website", "url": "...", "publisher": {"@id": "...#organization"} }
  ]
}
```
(`SoftwareApplication` | `LocalBusiness` | `Service` for product node.)

**3.2 Per-article:** `Article` (headline, description, image, datePublished, dateModified, author, publisher, mainEntityOfPage); `BreadcrumbList` (Home > Blog > Category > Article); `FAQPage` when article has FAQ (most should).

**3.3 Validation:** Google Rich Results Test on every pillar page before launch; schema.org validator; confirm rich-result eligibility in Search Console within 1–2 weeks of publish.

### 4. On-Page SEO

**4.1 Every page must include:** unique `<title>` (50–60 chars); unique `<meta name="description">` (130–160); canonical; Open Graph (`og:title/description/image/type/url/site_name/locale`); article-specific (`article:published_time/modified_time/author/section`); Twitter Card `summary_large_image`; hreflang for every translated page + x-default; `<meta name="theme-color">`.

**4.2 URL conventions:** kebab-case lowercase slugs; trailing slash on all routes (consistent); max 5 path segments; keyword in blog slug; no date in URL (update without redirects).

### 5. Pricing + Plans Strategy

**5.1 Three-tier structure:**

| Tier | Target | Pricing Model | Anchor Strategy |
|------|--------|---------------|-----------------|
| Standard | SMB / individuals | Base + per-seat OR flat low | The "starting" anchor |
| Pro | Growing teams | Same as Standard, +50% | "Most popular" upsell |
| Enterprise | 100+ seats / multi-site | Flat bulk + per-overage seat, multi-month contract | "Anchor high" — makes Pro look reasonable |

**5.2 Pricing math rules:** 15% under nearest competitor on apples-to-apples seat math; 30-day free trial, no card for self-serve; 12-month commit for Enterprise; show 3-way competitor comparison on `/pricing`; seat-count slider with real savings %; "At X users, you save Y%" in success-green callout.

**5.3 Plan features spec (DB):**
```
{
  slug: string,                    // "standard" | "pro" | "enterprise"
  name: string,
  description: string,
  basePriceCents: number,
  seatPriceCents: number,
  includedSeats: number,           // 0 for standard/pro, 100 for enterprise
  minContractMonths: number,       // 0 month-to-month, 12 enterprise
  trialDays: number,               // 30
  features: { list, userLimit, mostPopular, yearlyPriceCents, lifetimePriceCents },
  visible: boolean,
  sortOrder: number,
}
```

**5.4 Display rules:** Most Popular badge on Pro; "Save X%" annual toggle; per-user math visible (`$32 base + $7 per user → $109 for 11 users`); competitor matrix below cards.

### 6. Author Roster (E-E-A-T)

Never ship anonymous articles. Minimum 3 distinct authors / voices:

| Slug | Role/Voice | Article share |
|------|-----------|---------------|
| founder/ops | Strategic, ROI-driven, decisive | 30–40% |
| field/practical | On-the-ground, story-driven, customer-empathy | 40–50% |
| compliance/expert | Regulatory, defensive, citation-heavy | 15–20% |

**Bio fields (DB/data file):** `slug, name (realistic, non-real-person, ungendered if possible), role, bio (1–2 sentences w/ years + specialty), avatar (/blog/authors/<slug>.webp), linkedin`.

**Author page `/blog/author/<slug>/`:** bio + role + photo, LinkedIn, all articles desc, schema `Person + sameAs`.

### 7. Internal Linking

**7.1 Per-article minimum:** 3+ internal links; descriptive anchors (never "click here"); mix 1–2 money pages, 1–2 sibling articles, 1+ pillar/hub.

**7.2 Link pool:** `/features` or `/[product]`, `/pricing`, `/vs/<competitor>`, `/industries/<vertical>`, `/blog/[case-study-flagship]`, 2+ related posts (auto-suggest via shared category).

**7.3 Hub-and-spoke:** each tag page links all articles in tag; each article links its tag page; each pillar links 5–10 spokes; each spoke links back to pillar.

### 8. External Link Strategy

**8.1 Authority signals:** every article links to 1+ `.gov` (when regulation touched), 1+ `.edu` (research claims), 1+ industry publication (peer credibility).

**8.2 Source examples:** Labor/payroll → dol.gov, irs.gov, bls.gov · Health/safety → osha.gov, cdc.gov, nih.gov · Privacy/data → ftc.gov, justice.gov, state AG · Industry stats → Pew, Statista, McKinsey, Gartner.

**8.3 Rules:** `target="_blank" rel="noopener noreferrer"` on all external; never link direct competitors; never affiliate/tracked URLs in body.

### 9. CTA Framework

**9.1 Three-touch per article:** soft mid-article inline link to feature page; hard end CTA button block (headline + sub + 2 buttons: trial / demo|pricing); optional sticky scroll CTA after 50% scroll.

**9.2 Copy rules:** verb-first (Start/See/Book/Get); ≤4 words on button; sub-headline includes 1 number (price/days/count); secondary CTA = lower commitment.

**9.3 Component:**
```jsx
<BlogCta
  headline="Ready to [outcome]?"
  sub="[product] [tier] from $X/mo. Y-day free trial. No credit card."
  primaryHref="/signup"
  primaryLabel="Start free trial"
  secondaryHref="/pricing"
  secondaryLabel="See pricing"
/>
```

### 10. Image Specification

**10.1 Format + sizing:** Hero 1200×630 WebP ≤120KB OG-compatible; Inline 800×450 WebP ≤80KB; Avatar 256×256 source served 64/96. Always set width+height (CLS). Hero `loading="eager" fetchpriority="high"`; inline `loading="lazy"`.

**10.2 Alt text:** descriptive (what's in it, not "image of X"); keyword once naturally; ≤125 chars; never empty (use `alt=""` only for decorative).

**10.3 Naming:** `<slug>-hero.webp`, `<slug>-inline-1.webp`, etc. All under `/public/blog/<slug>/`.

**10.4 Sourcing:** AI-gen (banana/DALL-E/Midjourney → consistent brand); product screenshots (highest trust); stock (fastest/cheapest); customer-supplied (highest authenticity, needs permission).

### 11. Backdating + Publication Cadence

**11.1 Backdating (launch with depth):** write 50 articles, backdate `publishDate` over preceding 50 days (1/day) so the archive looks lived-in; `updatedDate = publishDate` initially; re-publish in real-time after launch.

**11.2 Real-time cadence:** min 2/week (freshness); sweet spot 3–5/week (growth); pillar refresh every 90 days with new sections + bumped `updatedDate`.

**11.3 Editorial calendar fields:** slug, title, publishDate, author, category, target keyword, owner, status (draft → review → scheduled → live).

### 12. Trust Pages (Required)

**12.1 Mandatory (kill 404s, build E-E-A-T):**

| Page | Purpose | Notes |
|------|---------|-------|
| `/about` | Company history, founders, mission | Author bios link here |
| `/contact` | Address, phone, email, hours, support | NAP consistency → local SEO |
| `/privacy` | Data collection + use + retention | GDPR + CCPA + biometric if applicable |
| `/terms` | Subscription, acceptable use, liability | Your state jurisdiction |
| `/security` | Encryption, access control, SOC2 status | Enterprise trust |
| `/legal/dmca` | Copyright takedown procedure | Safe-harbor requirement |

**12.2 Privacy must cover:** account data, behavioral data, cookies+tracking, biometric (if collected), retention periods (specific), opt-out/deletion, named third-party processors, international transfer notice, children policy, contact.

**12.3 Terms must cover:** subscription tiers+pricing (link /pricing), cancellation+refunds, customer data ownership, acceptable-use prohibitions, third-party integration disclaimer, IP ownership, warranty disclaimer + liability cap (12 months fees standard), indemnification, governing law+venue, change-notification period.

### 13. Technical SEO Infrastructure

**13.1 robots.txt** — allow all major AI crawlers explicitly:
```
User-agent: *
Allow: /
Disallow: /admin/

User-agent: GPTBot
Allow: /
User-agent: ChatGPT-User
Allow: /
User-agent: PerplexityBot
Allow: /
User-agent: ClaudeBot
Allow: /
User-agent: Google-Extended
Allow: /
User-agent: CCBot
Allow: /

Sitemap: https://www.[domain]/sitemap-index.xml
```

**13.2 sitemap.xml** — auto-generated (`@astrojs/sitemap` / `next-sitemap`); all public URLs; exclude admin/signup/auth; reference `sitemap-index.xml` in robots.

**13.3 llms.txt** (AI citation control) — `/llms.txt` at root, plain markdown:
```markdown
# [Brand]
> [1-2 sentence positioning with pricing, location, founding year]
## Pricing
- [Tier 1]: $X
- [Tier 2]: $Y
- [Tier 3]: $Z
## Unique features (vs competitors)
- [Differentiator 1]
- [Differentiator 2]
## Key pages
- [/pricing](https://...) — description
- [/features](https://...) — description
## Citation guidance for AI assistants
- Brand name: [exact capitalization]
- Founder: [name]
- HQ: [city, state]
- Primary competitors: [list]
## Contact
- sales@[domain]
- [address]
```

**13.4 Security headers (origin/CDN):**

| Header | Value |
|--------|-------|
| Strict-Transport-Security | `max-age=63072000; includeSubDomains; preload` |
| Content-Security-Policy | Restrictive, explicit sources |
| X-Content-Type-Options | `nosniff` |
| X-Frame-Options | `DENY` |
| Referrer-Policy | `strict-origin-when-cross-origin` |
| Permissions-Policy | Minimal whitelist |

**13.5 Core Web Vitals targets:** LCP < 2.0s · INP < 200ms (replaced FID Mar 2024) · CLS < 0.05 · TTFB < 600ms. Monitor via CrUX field data, not just lab Lighthouse.

### 14. Admin Settings Surface

Build these so non-devs manage growth surfaces.

**14.1 Required sections:**

| Section | Path | Controls |
|---------|------|----------|
| Plans | `/admin/platform/plans` | Tier CRUD, pricing, features, Stripe sync |
| Coupons | `/admin/platform/coupons` | Promo codes, discounts, free months |
| Site Settings | `/admin/platform/site-settings` | Analytics, OAuth, Translation, Email |
| Analytics tab | within Site Settings | Head + Body code injection (GA, GTM, Meta Pixel) |
| Email Templates | `/admin/platform/email-templates` | Transactional, welcome, password reset |
| Reserved Words | `/admin/platform/reserved-words` | Username/slug blocklist |

**14.2 Site code injection (critical for marketing analytics):**
```
model PlatformConfig {
  id          String   @id @default("singleton")
  analytics   Json?    // { headScript, bodyScript, lastUpdatedBy, lastUpdatedAt }
  updatedAt   DateTime @updatedAt
}
// public read endpoint
GET /api/v1/public/site-code
→ { headScript: string, bodyScript: string, updatedAt: Date }
Cache-Control: public, max-age=60
// marketing site: build-time fetch + inline
<SiteCode target="head" />   // inside <head>, after canonical/og
<SiteCode target="body" />   // immediately after <body> open
```
**Important:** static marketing site requires rebuild on each save. Wire admin Save → deploy webhook.

**14.3 Admin auth gating:** all admin platform routes require role `PLATFORM_OWNER | PLATFORM_ADMIN | PLATFORM_BILLING` (most-restrictive per action); audit-log every CREATE/UPDATE/DELETE; show "last updated by [user] on [date]" per panel.

### 15. Multi-Language Strategy

**15.1 When:** Day 1 if audience demands (Spanish-primary contractors, EU, LATAM SaaS); else ship English first, translate bestsellers later.

**15.2 Implementation:** URL structure `/es/`, `/pt-br/` (not query params); reciprocal hreflang + x-default; same canonical structure across locales; translate page-by-page (not bulk-machine); keep brand+product names untranslated.

### 16. Case Study Format (E-E-A-T + sales)

**16.1 Structure:** TL;DR box (80–120) · The Company (200) · The Pain (300) · Why [Brand] (250) · The Migration (400, week-by-week) · The Results (350 + table, before/after, %∆) · What Changed for [users] (200, quote) · What Changed for [office] (200) · Unexpected Wins (150) · Lessons (200, 5 takeaways) · FAQ (200) · CTA (100). Total 1800–2500 words.

**16.2 Real-customer risk controls (before publish):** ✅ written permission (logo+name+metrics+quotes) · ✅ verified metrics (no fabrication) · ✅ approved quotes w/ attribution · ✅ logo license · ✅ legal review for NDA. Until confirmed: park in `_drafts/` with `[VERIFY: …]` markers per unconfirmed number/quote.

### 17. Competitor Comparison Pages

`/vs/<competitor>` converts 3–5× better than blog posts.

**17.1 Structure:** Hero ("X vs Y" + 1-line value prop) · TL;DR (4 bullets: who each best for + winner) · Pricing comparison (side-by-side + per-seat math) · Feature matrix (20+ rows, check/dash/asterisk + footnotes) · Differentiators (3–5 you have, they don't) · Customer voice (2–3 switcher quotes) · Migration plan · FAQ (5 Q&A) · CTA.

**17.2 Tone:** neutral factual, never smear; cite competitor pricing from their public page (timestamp source); footnote claims (esp. feature absences); update quarterly.

### 18. Build + Validation Checklist

**18.1 Per-article (before draft → live):** ≥1200 words · 1 H1, 5–8 H2, optional H3 · unique meta 130–160 · hero WebP ≤120KB + alt + width/height · ≥2 inline images · FAQ + FAQPage schema · Article + BreadcrumbList JSON-LD · 3+ internal links · 1+ external authoritative citation · closing CTA button block · author bio · related posts · OG + Twitter tags · trailing-slash URL (no 301) · renders dark+light · mobile clean · no broken anchors · Lighthouse mobile ≥90 perf, 100 SEO.

**18.2 Pre-deploy gates:** typecheck passes (`astro check` / `tsc --noEmit`) · build zero errors · sitemap regenerates with new URLs · all JSON-LD validates · critical page CWV green · privacy+terms 200 (no 404s).

### 19. Content Operations

**19.1 Editorial review:** Brief (keyword+outline+persona) → Draft (80% length) → Edit (fact-check+voice+link audit) → SEO pass (meta+schema+internal links) → Image pass → Schedule → Post-publish (track CWV + GSC 30 days).

**19.2 Update cadence:** pillars every 90 days; comparisons quarterly; standard annually unless stale; FAQ when new support-ticket questions appear.

**19.3 Decommission rules:** 404 with confidence, 301 with intent; never delete a ranking article — update it; 0 impressions after 6 months → audit content quality, not URL; stale-but-ranking → update + bump `updatedDate`, never delete.

### 20. AI Search Readiness (GEO)

**20.1 Citation magnets:** TL;DR box above fold (citation-pull format); clear definitional sentences ("X is [definition]." — citeable in isolation); FAQ direct Q→A; numbered lists for procedures; stats with sources; visible author attribution + role.

**20.2 Test citability (per pillar):** copy a TL;DR bullet → search ChatGPT (does it surface you?); ask Perplexity your target keyword (does it cite you?); check Google AI Overview. Iterate until you appear.

### 21. Measurement Framework

**21.1 Leading (wk 1–4):** GSC impressions on targets; discovered URLs post-sitemap; rich-result eligibility; internal blog→money CTR; AI Overview/Perplexity citations.

**21.2 Lagging (mo 2–6):** organic clicks/article; avg position; brand search volume; signup conversion from blog; branded vs non-branded impression ratio.

**21.3 Quality (continuous):** time-on-page > 2 min (1500-word); bounce < 60%; scroll depth > 70%; newsletter signup CR > 0.5%.

### 22. Quick-Start Sequence (fresh site)

| Sprint | Items | Effort |
|--------|-------|--------|
| Week 1 | Site shell, privacy, terms, llms.txt, robots, sitemap, global JSON-LD, 3 trust pages | 16 hr |
| Week 2 | Pricing page, 3 plan tiers, 1 competitor comparison (vs flagship), blog scaffold, 3 author profiles | 20 hr |
| Week 3 | 5 pillar articles (1/Tier-1 keyword), category landing pages | 30 hr |
| Week 4 | 20 spoke articles, backdate to weeks 1–4 dates | 60 hr |
| Week 5 | 20 more spokes + 1 flagship case study | 60 hr |
| Week 6 | Admin settings (analytics injection, plans, coupons), image gen pass, final audit + deploy | 30 hr |

Total: ~215 hours to launch with depth.

### 23. Tooling Recommendations

Static framework: Astro/Next/Nuxt/Eleventy · Content collection: Astro Content Collections (Zod) · MDX: `@astrojs/mdx`, `next-mdx-remote` · Schema validation: Zod/Yup · Sitemap: `@astrojs/sitemap`, `next-sitemap` · SEO testing: Google Rich Results Test, schema.org validator, GTmetrix · Keyword research: DataForSEO/Ahrefs/SEMrush/Keyword Planner · AI image gen: Banana MCP/DALL-E 3/Midjourney · Performance: Lighthouse CI/WebPageTest/CrUX · Analytics: GA4 + Search Console + Plausible · Newsletter: Resend/Buttondown/Mailchimp.

### 24. Anti-Patterns (Don't)

❌ Identical meta descriptions · ❌ Generic off-brand stock images · ❌ Authorless articles · ❌ Keyword stuffing in titles · ❌ Bottom-only hidden CTAs · ❌ Orphan pages (no internal links) · ❌ Affiliate links in editorial · ❌ Auto-translated content unmarked · ❌ "Updated" displayed but content stale · ❌ Indexed "coming soon" pages · ❌ Footer links to 404s · ❌ Single-tier pricing (no anchor) · ❌ No comparison content · ❌ Real customer mentions without consent · ❌ Fabricated case study metrics.

---

## Playbook Metadata

| Field | Value |
|-------|-------|
| Category | Content strategy + SEO + GEO (full-site) |
| Scope | Multi-page playbook applied across entire site |
| Stack | Stack-flexible; reference = Astro/Next + Zod content collections |
| Surfaces | Money / authority / trust pages, pricing, blog, comparison, case studies, admin |
| Touches other specs | Pricing/plans + coupons → [paid-subscription-system](../../systems/paid-subscription-system/SPEC.md); email templates → [email-template-system](../../systems/email-template-system/SPEC.md) |
| Customize | Swap `[BRACKETS]`: brand, keywords, competitors, vertical |
