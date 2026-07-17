# Search + AI Discovery Playbook

Evidence-based operating specification for discoverability, crawlability, relevance, trust, user value, and conversion across service, SaaS, local, ecommerce, and publishing sites. Designed for a portfolio of approximately 15 independently useful websites.

**Scope:** strategy, content, technical SEO, structured data, local/maps/ecommerce/international SEO, AI discovery, measurement, governance, and quality gates.

**Review cycle:** quarterly and after material search-engine policy changes.

**Version:** 2.1. **Last evidence review:** 2026-07-17 (all registry URLs verified live).
**Important:** no tactic, vendor, or playbook can guarantee a ranking, rich result, citation, traffic level, or conversion outcome.

## 0. Rule language and evidence

Every normative rule uses one label:

| Label | Meaning |
|---|---|
| **REQUIRED** | Needed for technical validity, accessibility, compliance, truthful representation, or the intended indexation state. |
| **RECOMMENDED** | Strong evidence-based default; deviations require a documented reason. |
| **CONDITIONAL** | Required only when the described page, feature, market, or risk exists. |
| **OPTIONAL** | Useful in some contexts but not an SEO requirement. |
| **EXPERIMENTAL** | A measured hypothesis with a stop condition; never presented as a ranking factor. |
| **PROHIBITED** | Deceptive, spam-oriented, fabricated, inaccessible, legally risky, or materially unsupported. |

Unlabeled imperatives inside a section (table rows, checklist items, workflow steps) inherit **REQUIRED** unless the surrounding text states otherwise.

Evidence types cited where authority matters: **ENGINE REQUIREMENT** (documented engine policy/technical requirement), **ENGINE GUIDANCE** (documented engine recommendation), **WEB STANDARD**, **INDUSTRY PRACTICE**, **INTERNAL CONTROL**, and **EXPERIMENT**. Search-engine documentation and web standards outrank vendor studies. Correlation is not causation.

## 1. Non-negotiable principles

1. **REQUIRED - people and task first.** Each indexable page must serve a defined audience, intent, and outcome with original utility beyond a search-results summary. *(ENGINE GUIDANCE)*
2. **REQUIRED - truth.** Names, dates, authorship, credentials, reviews, prices, availability, locations, metrics, comparisons, and schema must be accurate and supportable. *(ENGINE REQUIREMENT for structured data; ENGINE GUIDANCE elsewhere)*
3. **PROHIBITED - manipulation.** No keyword stuffing, doorway pages, scaled low-value content, cloaking, hidden text or links, sneaky redirects, link schemes, fake reviews, expired-domain abuse, site-reputation abuse, thin affiliation, user-generated spam, scraping, machine-generated traffic, misleading functionality, back-button/navigation hijacking, policy circumvention, misleading structured data, or any attempt to manipulate rankings **or generative-AI answers** (including scaled query-variation pages targeting AI "fan-out" queries). *(ENGINE REQUIREMENT — Google spam policies, which explicitly extend to AI responses)*
4. **REQUIRED - no fabricated expertise.** Use a real author, a qualified reviewer, or transparent organizational attribution. Never invent people, biographies, quotes, customers, results, or credentials. *(ENGINE GUIDANCE + INTERNAL CONTROL)*
5. **PROHIBITED - false freshness.** Publication and modification dates must reflect real events. Never backdate content or change `dateModified` without a material review or update. *(ENGINE GUIDANCE)*
6. **RECOMMENDED - intent depth, not word quotas.** There is no universal ideal length, keyword density, heading count, image count, or link count. Cover the task completely and stop. *(ENGINE GUIDANCE — Google states it has no preferred word count)*
7. **REQUIRED - independent site value.** Every portfolio domain must have a distinct brand, audience, purpose, ownership rationale, and body of original value. Domains must not exist mainly to funnel users or authority elsewhere. *(ENGINE REQUIREMENT — doorway/policy-circumvention)*
8. **REQUIRED - accessible by default.** Public experiences target WCAG 2.2 AA (current W3C Recommendation; WCAG 3.0 remains a working draft). SEO gains never justify inaccessible controls, hidden content, or disruptive interstitials. *(WEB STANDARD + ENGINE GUIDANCE on interstitials; accessibility is a compliance control, not a documented ranking factor)*

## 2. Strategy foundation

### 2.1 Site charter

**REQUIRED:** before research or production, record: business model; audience; countries/languages; primary conversion; real-world entity; products/services; eligibility constraints; YMYL exposure; primary competitors; existing demand; and measurable business goals.

**REQUIRED:** define what this site can uniquely prove, demonstrate, calculate, compare, document, or teach. If no answer survives the question "why would an informed user pick this page over the current best result?", do not scale content.

### 2.2 Demand and intent map

**REQUIRED:** build one query-to-page map containing: query/theme, audience, intent, journey stage, target URL, page type, primary entity, supporting questions, evidence needed, conversion, owner, status, and cannibalization notes.

- **RECOMMENDED:** group by shared intent and SERP behavior, not exact-match variations.
- **REQUIRED:** assign one preferred page per materially identical intent. "Materially different" means the audience, offer, location, language, or expected result format changes what a correct page would contain — not a synonym swap.
- **CONDITIONAL:** split pages only when that material-difference test passes.
- **PROHIBITED:** location, industry, or keyword permutations whose main content is substantially interchangeable.

### 2.3 Information architecture and topic clusters

- **REQUIRED:** every indexable page is reachable through crawlable `<a href>` links. *(ENGINE REQUIREMENT — discovery)*
- **RECOMMENDED:** use shallow, comprehensible hubs: Home -> category/solution -> detail.
- **RECOMMENDED:** plan content as clusters: one hub per topic the site can credibly own; spokes cover the distinct sub-intents from the query-to-page map; hub links to every spoke, spokes link to the hub and adjacent spokes. A cluster is complete when its map shows no unserved material intent — not when it hits a page count.
- **RECOMMENDED:** link according to user next steps and semantic relationships; do not enforce arbitrary counts.
- **REQUIRED:** prevent orphan pages and maintain breadcrumbs where hierarchy benefits users.
- **RECOMMENDED:** keep URLs stable, readable, lowercase, and concise. Choose a trailing-slash convention and enforce it consistently. *(ENGINE GUIDANCE for descriptive URLs; consistency is signal hygiene, INDUSTRY PRACTICE)*

### 2.4 Content inventory and cannibalization control

- **REQUIRED (existing sites):** maintain a content inventory (URL, page type, intent served, traffic/conversion role, indexation state, last review) before adding pages; expansion decisions come from inventory gaps, not idea lists.
- **RECOMMENDED - cannibalization detection:** quarterly, flag query groups where multiple portfolio URLs (same site or cross-domain) alternate or split impressions in Search Console for the same intent.
- **REQUIRED - resolution:** pick one winner per intent, then either differentiate the loser's intent (real difference), consolidate + 301 to the winner, or noindex/remove. Record the decision in the query-to-page map.

## 3. Universal page contract

**REQUIRED:** every public URL has an explicit `index`, `noindex`, redirect, gone, or blocked state, recorded at the template or page level. Where this playbook marks an indexation decision "conditional", the owner must resolve it to an explicit state using the stated test — hedged states may not ship.

### 3.1 Indexable pages

- **REQUIRED:** HTTP 200; useful main content visible without interaction; unique descriptive title; one clear page topic; self-referencing canonical unless intentionally consolidated; crawlable internal links; mobile usability; and no accidental `noindex` or robots block.
- **RECOMMENDED:** unique meta description written for qualified clicks. Length is preview-dependent, not a ranking quota.
- **RECOMMENDED:** descriptive H1 and logical heading hierarchy; headings are structural and aid accessibility — Google states heading order is not a ranking mechanism.
- **RECOMMENDED:** Open Graph/Twitter metadata on all public marketing/editorial pages (any page can be shared).
- **CONDITIONAL:** hreflang only for real locale alternatives (see 9.3).
- **REQUIRED:** visible content and structured data agree. Content hidden behind tabs/accordions may not be read by all crawlers (Bing documents that it does not expand them); keep primary answers in initially-rendered HTML.

### 3.2 Non-indexable and utility pages

**REQUIRED default:** login, cart, checkout, internal search results, filtered duplicates, account, admin, staging, and thin system states ship `noindex` (or access control). **CONDITIONAL exception:** index a search/filter/tag/archive URL only when a documented case shows unique demand plus curated, maintained value (see 8.2 facet test). Do not rely on robots.txt to remove indexed URLs; authenticated/private resources must use access control. *(ENGINE REQUIREMENT — robots.txt is a crawl control, not a privacy or deindexing tool)*

### 3.3 Content quality contract

**REQUIRED:** an indexable page must provide at least one defensible value contribution: first-hand experience, original data, primary documentation, expert analysis, a useful tool/calculator, verified local knowledge, unique media, a clearer synthesis, or a demonstrably better transaction experience (evidenced by task-completion or conversion data, not asserted).

**REQUIRED:** claims that could affect money, health, safety, legal rights, or major life decisions require qualified review, primary sources where available, reviewer identity, review date, and a correction path.

## 4. Page-type standards

Rows are REQUIRED contracts. Metadata, media, accessibility, and acceptance rules from sections 3, 5.3, and 14 apply to every row; the table adds type-specific requirements. Freshness triggers per risk tier are in 13.1.

| Page type | Intent and required value | Indexation | Key content | Structured data |
|---|---|---|---|---|
| Homepage | Identify entity, audience, offer, proof, and paths | Index | Clear proposition, main categories, evidence, contact/entity details | `Organization` or applicable subtype + `WebSite` when truthful |
| Service | Evaluate/hire a service | Index | Scope, outcomes, process, proof, constraints, FAQs only if useful, CTA | `Service` (semantic value only — no Google rich result); `LocalBusiness` only for eligible real location/entity |
| SaaS feature/product landing | Evaluate capability | Index | Jobs solved, workflow, evidence, screenshots/demo, integrations, limits | `SoftwareApplication` only when page/entity meets requirements |
| Ecommerce product | Buy/compare a product | Index while purchasable or retaining user value (9.2 discontinued rule) | Unique product data, variants, price, availability, shipping/returns, media | `Product` + `Offer`; `ProductGroup`/`hasVariant` for variant families; shipping/returns properties for merchant listings |
| Category/collection | Browse a set | Index when it serves a mapped intent with curated, maintained inventory | Intro/filter context, crawlable product links, unique guidance | `BreadcrumbList`; item markup only when supported and accurate |
| Pricing | Understand cost and fit | Index | Current prices, units, inclusions, conditions, FAQs, sales path | `Offer` only when connected to a truthful supported item |
| Industry/audience | Evaluate fit for a real segment | Index only when the material-difference test (2.2) passes | Segment problems, workflow, proof, terminology, relevant offer | Match actual main entity; no invented vertical schema |
| Location/local service | Find a real staffed/service-area operation | Index only if unique and eligible | Location or service-area facts, embedded map, parking/access, unique local photos, local proof/reviews, staff, contact; SABs state the service area — never a fake street address | Applicable `LocalBusiness` subtype with `areaServed` (SABs), `geo`, `openingHoursSpecification`, `sameAs` to the GBP/Maps listing; one real entity/location per page |
| Article/guide/tutorial | Learn or complete a task | Index | Direct answer, evidence, examples, steps/media as useful, next step | `Article`/`BlogPosting`. `HowTo` has no Google rich result (deprecated 2023) — use only for non-Google semantic value |
| Pillar guide | Own a topic; route to cluster | Index | Summary, topic map, deep sections, links to every spoke, maintained updates | `Article`; `BreadcrumbList` |
| Research/data | Cite-able original findings | Index | Question, method, findings, limitations, downloadable data | `Article`; `Dataset` when a real dataset is published |
| Case study | Assess demonstrated results | Index with written customer permission on file | Verified customer, context, method, baseline, outcome, limitations | `Article`; never fabricate review/result markup |
| Comparison/alternative | Compare options | Index only while facts carry a check date within the 13.1 cadence | Selection criteria, sourced current facts, fit, limitations, date | Usually `WebPage`/`Article`; self-serving review markup is ineligible |
| About/contact | Verify and contact entity | Index | Real ownership, people, history, policies, contact/location | `Organization`, `Person`, or `ContactPage` as applicable |
| Author/expert | Verify contributor | Index when the row's full content list is met; otherwise noindex | Real bio, expertise, work, disclosures, correction/contact path | `ProfilePage` + `Person` when accurate |
| Help/FAQ | Resolve real support questions | Index pages that answer mapped demand; noindex fragments | Direct maintained answers and escalation | `FAQPage` produces no Google rich result (deprecated May 2026); OPTIONAL for other engines/AI when truthful — never a Google eligibility play |
| Legal/security | Compliance and trust | Index | Accurate reviewed policy; no SEO filler | `WebPage`; no unsupported claims/certifications |
| Search/filter/tag/archive/pagination | Navigate inventory | Noindex by default; index only via the 8.2 facet test | Unique demand and curation justify existence | Usually none beyond breadcrumbs |
| Campaign landing | Paid conversion | Noindex by default; index only when it passes the 2.2 material-difference test against organic pages | Message-match, offer, disclosure, conversion | Match visible content only |
| Login/account/checkout/admin | Complete private/system task | Noindex/access controlled | Usability and security | None unless specifically applicable |

**PROHIBITED - targeting deprecated rich results:** FAQ (May 2026), HowTo (2023), sitelinks search box (2024), Book Actions, Course Info, Claim Review, Estimated Salary, Learning Video, Special Announcement, Vehicle Listing (June 2025). Truthful markup of these types is harmless but earns nothing in Google.

## 5. Article and editorial standards

### 5.1 Formats

Select format from intent:

| Format | Minimum original value | Typical structure |
|---|---|---|
| Definition/short answer | Precise answer plus context/example | Answer -> nuance -> example -> next step |
| Informational | Complete, verified explanation | Answer -> concepts -> evidence/examples -> implications |
| Commercial investigation | Transparent selection criteria and current facts | Decision summary -> criteria -> options -> tradeoffs -> fit |
| Tutorial/how-to | Tested procedure and expected outcome | Prerequisites -> ordered steps -> validation -> troubleshooting |
| Research/data | Reproducible method and source data | Question -> method -> findings -> limitations -> data/citation |
| Pillar guide | Navigable synthesis with unique utility | Summary -> topic map -> deep sections -> tools/examples -> updates |
| News/time-sensitive | Original reporting or material analysis | What happened -> evidence -> impact -> timeline/corrections |
| Thought leadership | Named expert thesis and experience | Claim -> reasoning -> evidence -> counterargument -> implications |
| Local information | Verified first-hand local utility | Direct answer -> local evidence -> logistics -> relevant service |

### 5.2 Editorial workflow

All seven steps are REQUIRED for indexable editorial content:

1. **Brief:** audience, intent, target page, unique value, sources, entities, outline, conversion, cannibalization check against the query-to-page map, reviewer.
2. **Draft:** answer the main need early; use natural terminology and meaningful headings.
3. **Evidence review:** verify claims, dates, quotes, calculations, licenses, disclosures, and source relevance.
4. **Experience review:** add real examples, screenshots, tests, data, constraints, or expert judgment.
5. **SEO/accessibility review:** metadata, links, semantics, media alternatives, schema, canonical, indexation.
6. **Approval and publish:** record real author/reviewer and dates. Disclose AI assistance where readers would reasonably want to know how content was produced. *(ENGINE GUIDANCE)*
7. **Post-publish:** inspect rendered page, URL status, canonical, schema, sitemap inclusion, analytics, and Search Console.

**PROHIBITED:** keyword-density targets, exact-match repetition, mandatory FAQ/table/image/link quotas, padding to length, unsupported "citation bait," or AI output published without accountable human review.

### 5.3 Media

- **REQUIRED:** media must be relevant, licensed/owned, responsive, dimensioned to reduce layout shift, and accessible.
- **REQUIRED:** informative images get contextual alt text; decorative images use `alt=""`.
- **RECOMMENDED:** prefer original screenshots, diagrams, product imagery, and demonstrations; eager-load only the likely LCP image and lazy-load below-fold media; use modern formats based on measured quality, not a universal file-size cap.
- **CONDITIONAL - video:** self-describing title/description on the page, `VideoObject` markup, a crawlable thumbnail, transcript or captions (also a WCAG requirement), and a video sitemap when video is a material content type.
- **CONDITIONAL - image search value:** descriptive filenames and an image sitemap when image discovery matters (products, portfolios, local photos).
- **REQUIRED:** label synthetic media whenever a reasonable user would be misled about whether it depicts something real; never use it as fabricated evidence.

## 6. Linking, citations, and reputation

- **REQUIRED:** internal links must help users discover relevant next steps; anchor text describes the destination.
- **RECOMMENDED:** hubs link to their important detail pages; detail pages link back to useful hubs and related pages.
- **REQUIRED:** citations must directly support the adjacent claim. Prefer primary, current, independent sources.
- **PROHIBITED:** mandatory `.gov`/`.edu` quotas (no engine documents such a rule), PageRank-shaped outbound linking, paid links without required qualification, reciprocal link schemes, or fabricated mentions.
- **CONDITIONAL:** cite competitors when necessary for a fair comparison; record source URL and checked date.
- **REQUIRED:** affiliate, sponsored, and user-generated links use appropriate disclosure and link attributes (`sponsored`, `ugc`, or `nofollow` as applicable). *(ENGINE REQUIREMENT)*

**RECOMMENDED - digital PR:** earn references through newsworthy original assets, expertise, tools, research, and real partnerships (industry associations, local press, chambers for local entities). **PROHIBITED:** buying authority or manufacturing endorsements.

## 7. Structured data

1. **REQUIRED:** choose the most specific type that truthfully represents the visible main content.
2. **REQUIRED:** follow Google feature documentation (the structured-data search gallery is the authoritative list of currently supported rich results) in addition to Schema.org vocabulary when seeking Google eligibility.
3. **REQUIRED:** include all required properties; recommended properties only when factual.
4. **PROHIBITED:** empty objects, fake ratings/reviews, markup of content not visible on the page, global product/local-business/app nodes on unrelated entities, or blanketing pages with irrelevant types.
5. **RECOMMENDED:** stable `@id` values connecting real organization, website, person, and product entities; `sameAs` links from the entity's home page to its authoritative profiles (GBP/Maps, social, registries) to reinforce knowledge-graph identity.
6. **REQUIRED:** validate syntax and feature eligibility before release and monitor enhancement/manual-action reports. Note: deprecated types (e.g., FAQ) no longer appear in testing tools or Search Console reports — absence of a report is not an error.

**RECOMMENDED baseline mapping:** organization identity on the primary identity page/site graph; breadcrumbs on hierarchical pages; article markup on editorial content; product/offer on purchasable product pages; local business on real eligible location pages; profile/person on substantive real contributor pages. Bing also consumes JSON-LD for Copilot grounding and requires markup to match visible content. Structured data can enable features; it does not guarantee display, ranking, or AI citation.

## 8. Technical SEO

### 8.1 Crawl, render, index

- **REQUIRED:** important content, links, metadata, canonical, and robots directives must be present in reliable rendered output; server rendering or static generation is preferred — Google renders JavaScript, but many AI and secondary crawlers do not execute it.
- **REQUIRED:** use real anchor elements with resolvable `href` values.
- **REQUIRED:** XML sitemaps contain canonical, indexable 200 URLs only; accurate `lastmod` reflects meaningful updates (Google uses it only when consistently truthful).
- **RECOMMENDED:** submit and monitor sitemaps in Google Search Console and Bing Webmaster Tools.
- **CONDITIONAL:** use IndexNow for timely add/update/delete notifications to participating engines (Bing, Yandex, Naver, Seznam, Amazon, Yep — not Google).
- **REQUIRED:** robots.txt controls crawling, not privacy or guaranteed deindexing.
- **CONDITIONAL - crawl budget:** only a measurable concern at scale (roughly 10k+ URLs or frequent inventory churn). Then: monitor Search Console crawl stats and server logs, keep faceted/parameter spaces controlled, return correct 304/404/410 codes, and fix redirect chains. Small sites should not spend effort here.

### 8.2 Canonicals, duplicates, pagination, facets

- **REQUIRED:** redirects, canonicals, internal links, sitemap URLs, and hreflang references agree on preferred URLs.
- **REQUIRED:** each paginated URL that exposes unique items remains crawlable; do not canonicalize all pages to page 1.
- **CONDITIONAL - facet test:** index a facet only when all four hold: (1) search demand exists in the query-to-page map, (2) inventory is stable enough to stay useful, (3) the page adds curation or guidance beyond the filter itself, (4) indexable combinations are enumerated and capped. Otherwise noindex or block.
- **RECOMMENDED:** verify chosen block/noindex handling on representative URLs before rollout; never create infinite crawl spaces.
- **REQUIRED:** merge overlapping pages when they serve the same intent; redirect only to a genuinely equivalent destination (irrelevant redirects are treated as soft 404s). Use 404/410 when no replacement exists.

### 8.3 Performance and experience

**RECOMMENDED:** measure field data at the 75th percentile by mobile/desktop and meet Core Web Vitals "good" thresholds: LCP <= 2.5s, INP <= 200ms, CLS <= 0.1. Use lab tests for diagnosis, not as proof of field performance. **REQUIRED:** set project budgets for JavaScript, images, fonts, third parties, and server response based on templates and real-user data; the per-page gate in section 14 tests against these budgets. Pending metrics (soft-navigation measurement for SPAs; the in-development "Engagement Reliability" metric) are monitor-only — do not target. *(ENGINE GUIDANCE)*

### 8.4 Security and environment controls

**REQUIRED:** HTTPS, supported security headers, access-controlled staging, no public secrets, safe redirects, and monitored uptime. Security headers are security controls, not ranking levers. Analytics/script injection requires sanitization, least privilege, audit logs, consent controls, and CSP compatibility. *(INTERNAL CONTROL; HTTPS is a documented lightweight signal)*

## 9. Specialized programs

### 9.1 Local and Maps

- **CONDITIONAL - GBP (required when eligible):** create a Google Business Profile only for a real staffed location or service-area operation; represent the real-world name (matching signage — no keyword additions), the fewest categories that complete "this business IS a", accurate address or hidden-address service area (roughly a 2-hour driving radius), true hours, and a locally answered phone. *(ENGINE REQUIREMENT — GBP representation guidelines)*
- **PROHIBITED:** virtual-office or unstaffed locations, keyword-stuffed business names, duplicate profiles, location pages without distinct real-world value, and any review manipulation: fake, incentivized, gated, kiosk-collected, employee-quota, coached-wording, or AI-generated reviews. Google's rating-manipulation enforcement can block new reviews, unpublish existing ones, and display a public consumer warning on the profile. *(ENGINE REQUIREMENT)*
- **RECOMMENDED - ranking model:** Google documents exactly three local factors — relevance, distance, prominence (prominence includes web presence, citations, review count and score) — and states ranking cannot be requested or paid for. Optimize the inputs; never promise pack positions.
- **REQUIRED - review operations:** ask all customers uniformly (no gating), respond to reviews, and use a documented flag-and-appeal workflow for policy-violating reviews.
- **RECOMMENDED - NAP program:** one canonical name/address/phone record per entity; sync quarterly across site, GBP, Apple Business Connect, Bing Places, and key directories.
- **RECOMMENDED - profile completeness:** attributes (parking, accessibility, amenities, dietary and similar), full services/products menus with descriptions, fresh photos, holiday hours ahead of events, and Q&A seeded with real questions. Complete structured attributes now also feed conversational Maps answers (Gemini-powered "Ask Maps"), so profiles must be answerable, not just keyword-matched.
- **CONDITIONAL:** GBP messaging/booking only with a staffed response SLA; multi-location brands use one page per real location meeting the section 4 location row, plus a store locator.
- **CONDITIONAL - suspension runbook:** keep an evidence pack per location (signage photos, licenses, utility bills) for reinstatement appeals.
- **EXPERIMENTAL:** geo-grid rank tracking and local-justification optimization — useful observation tools; not documented ranking guidance.

### 9.2 Ecommerce

- **REQUIRED:** crawlable category-to-product paths, accurate price/availability/variant data, unique product utility, and clear shipping/returns.
- **RECOMMENDED:** use both valid on-page product structured data and Merchant Center feeds when eligible; reconcile mismatches.
- **CONDITIONAL - Merchant Center eligibility gates:** verified and claimed website; Googlebot not blocked (products are recrawled about daily); return/refund policy disclosed even when returns are not offered; visible contact information; conventional payment methods; SSL checkout; products purchasable on your own store; account sign-in at least every 14 months. *(ENGINE REQUIREMENT)*
- **CONDITIONAL:** preserve discontinued pages when they retain user value and offer alternatives; otherwise redirect only to an equivalent item/category or return 404/410.

### 9.3 International

- **REQUIRED:** each locale page is useful and accurately localized, not unreviewed bulk translation.
- **REQUIRED:** locale-specific URLs, self-canonicals, and reciprocal hreflang: annotations that do not point at each other are ignored; every set includes a self-reference and `x-default` where appropriate; use ISO 639-1 language plus optional ISO 3166-1 Alpha-2 region codes and fully-qualified URLs; pick one delivery method (HTML head, HTTP header, or sitemap) per page set. *(ENGINE REQUIREMENT)*
- **RECOMMENDED:** localize currency, units, policies, examples, search terminology, and conversion paths.

### 9.4 YMYL

**REQUIRED:** health, legal, financial, safety, and civic content gets heightened sourcing, qualified review, conflicts/disclosures, update schedules, and escalation/correction procedures. **PROHIBITED:** AI inventing or independently approving professional advice.

### 9.5 News and Discover

**CONDITIONAL (publishing sites):** Google News surfaces content algorithmically — no submission required; Publisher Center manages publication branding. Discover has no opt-in and no eligibility guarantee; documented inputs are helpful-content quality, clear dates/authorship, and large preview images (`max-image-preview:large`). Discover traffic is volatile by design — never build a revenue plan on it. *(ENGINE GUIDANCE)*

## 10. AI discovery and content automation

AI answer systems largely depend on accessible, indexable, trustworthy web content and retrieval systems. The foundation remains excellent SEO plus unambiguous, supportable information; Google states its generative features are rooted in core Search ranking systems.

- **RECOMMENDED:** make key facts explicit; keep entity names and attributes consistent everywhere (pages, schema, profiles); publish original evidence; use descriptive headings, tables, steps, images, and video when they genuinely improve comprehension.
- **REQUIRED:** expose meaningful content in crawlable, server-rendered HTML and keep it current.
- **OPTIONAL:** `llms.txt`. Google states such files are not needed and neither help nor harm its generative features; no major assistant documents consuming it. Treat as an experimental publisher convenience, never "AI citation control."
- **REQUIRED - crawler policy:** decide robots.txt treatment per bot class and vendor, because the classes serve different purposes: search indexing (`OAI-SearchBot`, `Claude-SearchBot`, `PerplexityBot`, `Bingbot`), user-triggered fetching (`ChatGPT-User`, `Claude-User`, `Perplexity-User`), and model training (`GPTBot`, `ClaudeBot`, `Google-Extended`, `CCBot`). Blocking a search-class bot removes the site from that assistant's answers (OpenAI documents this; robots changes take ~24h to propagate). User-triggered fetchers may not honor robots.txt (OpenAI and Perplexity document this) — use access control, not robots, for genuinely private content. Record the decision per user agent as licensing policy. *(ENGINE REQUIREMENT — official crawler docs)*
- **PROHIBITED:** rewriting solely for bots, tiny "AI chunks" (Google explicitly rejects the need), special "AI schema" (none exists), fake consensus/mentions, mass query permutations, or claims that any tactic guarantees AI citation.
- **REQUIRED:** AI-assisted content has a named accountable owner, fact/source verification, originality checks, disclosure when appropriate, and the same quality bar as human-originated work.

**RECOMMENDED - measurement:** Google Search Console generative-AI performance reporting where available; Bing Webmaster Tools AI Performance (Public Preview since Feb 2026: total citations, average cited pages, grounding queries, page-level citation activity across Copilot and Bing AI surfaces); identifiable referral logs; server-log bot segmentation; citation samples with a recorded prompt set, date, and model version; assisted conversions; and brand demand. Manual prompt checks are volatile observations, not rank tracking.

## 11. Portfolio governance for approximately 15 sites

**REQUIRED:** maintain a central registry: domain, brand/entity, owner, purpose, audience, markets, content boundaries, analytics/Search Console/Bing properties, schema identity, business profiles, canonical host, risk tier, and review date.

### 11.1 Reuse policy

- **RECOMMENDED:** reuse shared code, design tokens, analytics conventions, and validation logic freely.
- **CONDITIONAL:** reuse facts and source material only when relevant and re-expressed within genuinely site-specific value.
- **PROHIBITED:** cloning pages with swapped brand/location/keyword tokens; cross-domain canonicalization used to disguise duplication; or a network whose domains mainly cross-link to manipulate authority.
- **CONDITIONAL:** syndicated identical content requires a documented business reason, attribution, and a canonical/indexation plan; canonical signals are not guarantees.

### 11.2 Cross-domain links

**REQUIRED:** link only when the destination is useful and the relationship is transparent. Sitewide portfolio links belong in a restrained corporate/brand context, not keyword-rich SEO footers. Paid or controlled promotional relationships receive appropriate disclosure/qualification.

### 11.3 Publishing authority

**REQUIRED:** every page has an owner, reviewer where risk requires it, evidence record, indexation decision, and review trigger. Templates may enforce mechanics but cannot approve truth, usefulness, expertise, or legal claims.

## 12. Measurement and experiments

### 12.1 Outcome tree

**REQUIRED:** track by site, template, page type, intent, country, device, and brand/non-brand where possible:

- **Discovery:** crawl status, indexed canonical pages, sitemap health, rendering, server errors.
- **Visibility:** impressions, clicks, CTR interpreted with position/feature context, query/page coverage, local/merchant visibility, AI citations (GSC generative-AI report, Bing AI Performance) where measurable.
- **Engagement:** task completion, qualified scroll/interactions, internal navigation; do not use universal bounce/time thresholds.
- **Business:** leads, revenue, assisted conversions (state the attribution model used — last-non-direct vs data-driven changes the numbers), customer quality, retention, and cost per incremental outcome.
- **Quality/risk:** stale claims, accessibility defects, schema errors, cannibalization, policy/manual actions, and content corrections.

### 12.2 Experiment protocol

**REQUIRED:** write hypothesis, affected URLs, primary/guardrail metrics, baseline, duration/sample needs, confounders, stop condition, and rollback. Change one meaningful variable per cohort where feasible. Record inconclusive and negative results. Never describe a test outcome as a universal ranking factor.

## 13. Operations and incident response

### 13.1 Review triggers

**REQUIRED:** review content when facts, product, law, inventory, competitors, search intent, traffic, conversions, or engine guidance materially changes. **RECOMMENDED default cadences (starting points, not ranking rules):** high-risk/YMYL/pricing/policy pages quarterly; commercial and local pages semi-annually; evergreen editorial annually; time-sensitive content on its stated expiry.

### 13.2 Consolidation and removal

**REQUIRED:** keep, improve, merge, redirect, noindex, or remove based on user value and URL equivalence. Ranking history is an input, not a command to preserve obsolete content. Maintain a redirect map and avoid chains/loops.

### 13.3 Visibility incident

1. Confirm analytics and reporting integrity.
2. Check the Google Search Status Dashboard for in-progress core/spam updates before diagnosing — update rollouts are the most common confounder.
3. Segment loss by engine, site, directory, template, query, device, country, and date.
4. Check manual/security actions, index coverage, robots, canonicals, rendering, server logs, migrations, releases, and (local) profile suspensions.
5. Compare affected/unaffected cohorts and search-result changes.
6. Fix the evidenced cause, validate on representative URLs, then monitor. Avoid broad speculative rewrites during diagnosis.

## 14. Quality gates

### Per-page pre-publication

- [ ] Audience, intent, page type, unique value, owner, indexation state, and review trigger recorded
- [ ] Cannibalization checked against the query-to-page map
- [ ] Claims, quotes, dates, prices, calculations, comparisons, and permissions verified
- [ ] Real author/reviewer attribution and disclosures where relevant
- [ ] Descriptive title/H1; useful snippet copy; canonical/robots/hreflang correct; Open Graph present
- [ ] Crawlable internal links; no orphan or broken destination
- [ ] Relevant accessible media with licenses, dimensions, and captions/transcripts for video
- [ ] Structured data is visible, truthful, applicable, currently supported, and validated
- [ ] Mobile, keyboard, focus, forms, contrast, and responsive layout checked
- [ ] Rendered HTML, HTTP status, performance budget, analytics, and conversion tested

### Sitewide pre-launch

- [ ] Entity/site charter, query-to-page map, architecture, and ownership approved
- [ ] Production host, HTTPS, redirects, canonicals, robots (including AI-crawler policy), sitemaps, 404s, and staging controls tested
- [ ] Search Console/Bing verification, analytics consent, goals, and alerts configured
- [ ] Template metadata/schema/accessibility/performance tested on representative URLs
- [ ] No doorway, duplicate-network, site-reputation-abuse, fabricated-identity, false-date, scaled-content, or AI-answer-manipulation patterns
- [ ] Legal, privacy, security, accessibility, local, merchant, and YMYL reviews completed where applicable

### New-domain onboarding (portfolio)

- [ ] Registry entry complete; distinct entity, audience, and unique-value rationale documented
- [ ] No intent overlap with existing portfolio domains (query-to-page maps compared)
- [ ] Cross-domain link plan and schema identity reviewed for collisions
- [ ] Sitewide pre-launch checklist passed

### Monthly health

- [ ] Crawl/index/sitemap/server errors and manual/security actions reviewed
- [ ] Visibility and conversions segmented; anomalies investigated
- [ ] Broken links, schema errors, Merchant Center disapprovals, GBP changes/reviews, and top-page freshness checked
- [ ] AI-visibility reports (GSC generative-AI, Bing AI Performance) reviewed where available
- [ ] New cannibalization, duplication, orphaning, or portfolio overlap reviewed

### Quarterly governance

- [ ] Source registry guidance and this playbook reviewed; rich-result support list re-verified
- [ ] Portfolio registry, ownership, cross-domain links, and content boundaries audited
- [ ] Cross-domain duplicate-content scan and per-domain independent-value test passed
- [ ] NAP sync across GBP, Apple Business Connect, Bing Places, and key directories
- [ ] High-risk pages, experiments, redirects, access, and data retention reviewed
- [ ] Consolidate low-value overlap; prioritize original assets and demonstrated demand

### Migration

- [ ] Pre-migration baseline captured: rankings, traffic, conversions, indexed-page counts (rollback thresholds need a baseline)
- [ ] Complete old-to-new URL inventory and one-to-one redirect map
- [ ] Preserve useful content, metadata, canonicals, hreflang, schema, and internal links
- [ ] Test in controlled staging; block staging from public access/indexation
- [ ] Launch redirects atomically; update sitemaps and important external profiles (GBP, Merchant Center)
- [ ] Monitor logs, status codes, indexing, rankings, conversions against baseline; execute rollback at threshold

## 15. Priorities for adopting this version

**P0:** remove backdating, invented authors, fake metrics/reviews, review manipulation, universal schema, forced authority-link quotas, deprecated rich-result targets (FAQ/HowTo), and ranking guarantees.

**P1:** implement explicit indexation, query-to-page mapping, page-type contracts, AI-crawler policy, portfolio governance, accessibility, and evidence review.

**P2:** implement local/Maps, ecommerce/Merchant Center, international, YMYL, and News/Discover modules; automated mechanical checks; dashboards; incident response.

**P3:** run controlled content/UX experiments, geo-grid tracking, and optional AI-publisher formats only after foundations are healthy.

## 16. Integration prompt

Apply this playbook to `[SITE]`. First produce: (1) the site charter (2.1 fields), (2) a risk profile (YMYL exposure, local/merchant eligibility, legal constraints, portfolio-overlap risk), (3) the query-to-page map (2.2 fields), (4) a page-type inventory (every URL/template mapped to a section 4 row with an explicit indexation state), (5) a technical baseline (crawl/render/index status, CWV field data, schema validity, accessibility pass, robots/AI-crawler policy), and (6) prioritized gaps labeled P0-P3. Do not generate pages until those artifacts identify a defensible audience need and unique value. Label every proposed rule with this playbook's classification and, where authority matters, its evidence type. Implement P0/P1 first; validate representative templates before scaling. Never fabricate identities, evidence, dates, reviews, locations, or outcomes. Report what changed, what remains uncertain, validation results, and the measurement plan.

## 17. Source registry

Primary sources governing this edition (all verified live 2026-07-17):

- [Google Search Essentials](https://developers.google.com/search/docs/essentials)
- [Google SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Helpful, reliable, people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Google spam policies](https://developers.google.com/search/docs/essentials/spam-policies)
- [Google AI-generated content guidance](https://developers.google.com/search/docs/fundamentals/using-gen-ai-content)
- [Google guidance for generative AI search](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)
- [Google structured data policies](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
- [Google structured data search gallery (supported features)](https://developers.google.com/search/docs/appearance/structured-data/search-gallery)
- [Google JavaScript SEO basics](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)
- [Google ecommerce SEO guidance](https://developers.google.com/search/docs/specialty/ecommerce)
- [Google Product structured data](https://developers.google.com/search/docs/appearance/structured-data/product)
- [Google localized versions / hreflang](https://developers.google.com/search/docs/specialty/international/localized-versions)
- [Google Business Profile representation guidelines](https://support.google.com/business/answer/3038177)
- [Google local ranking factors](https://support.google.com/business/answer/7091)
- [GBP policy-violation restrictions](https://support.google.com/business/answer/14114287)
- [Merchant Center policies](https://support.google.com/merchants/answer/6363310)
- [Merchant Center free-listings requirements](https://support.google.com/merchants/answer/7538732)
- [Google Search Status Dashboard](https://status.search.google.com/)
- [Bing Webmaster Guidelines](https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a)
- [Bing sitemaps and AI-powered search](https://blogs.bing.com/webmaster/July-2025/Keeping-Content-Discoverable-with-Sitemaps-in-AI-Powered-Search)
- [Bing AI Performance](https://blogs.bing.com/webmaster/February-2026/Introducing-AI-Performance-in-Bing-Webmaster-Tools-Public-Preview)
- [IndexNow](https://www.indexnow.org/)
- [OpenAI crawlers](https://developers.openai.com/api/docs/bots)
- [Perplexity crawlers](https://docs.perplexity.ai/guides/bots)
- [Anthropic crawlers](https://support.claude.com/en/articles/8896518)
- [Schema.org](https://schema.org/) ([release notes](https://schema.org/docs/releases.html) — v30.0 current)
- [Core Web Vitals](https://web.dev/articles/vitals)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)

**REQUIRED:** for each quarterly review, record source changes, affected rules, reviewer, date, and decision in repository history.

---

## Playbook metadata

| Field | Value |
|---|---|
| Category | Search, content, AI discovery, and conversion governance |
| Scope | Portfolio and full-site playbook |
| Supported models | Service, SaaS, local, ecommerce, publishing |
| Stack | Stack-flexible; automate mechanical checks only |
| Related specs | [Paid subscriptions](../../systems/paid-subscription-system/SPEC.md), [email templates](../../systems/email-template-system/SPEC.md) |
