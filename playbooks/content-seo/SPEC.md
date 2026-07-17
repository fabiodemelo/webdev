# Search + AI Discovery Playbook

Evidence-based operating specification for discoverability, crawlability, relevance, trust, user value, and conversion across service, SaaS, local, ecommerce, and publishing sites. Designed for a portfolio of approximately 15 independently useful websites.

**Scope:** strategy, content, technical SEO, structured data, local/ecommerce/international SEO, AI discovery, measurement, governance, and quality gates.

**Review cycle:** quarterly and after material search-engine policy changes.

**Last evidence review:** 2026-07-17.
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

Evidence labels used in decisions: **ENGINE REQUIREMENT**, **ENGINE GUIDANCE**, **WEB STANDARD**, **INDUSTRY PRACTICE**, **INTERNAL CONTROL**, and **EXPERIMENT**. Search-engine documentation and web standards outrank vendor studies. Correlation is not causation.

## 1. Non-negotiable principles

1. **REQUIRED - people and task first.** Each indexable page must serve a defined audience, intent, and outcome with original utility beyond a search-results summary.
2. **REQUIRED - truth.** Names, dates, authorship, credentials, reviews, prices, availability, locations, metrics, comparisons, and schema must be accurate and supportable.
3. **PROHIBITED - manipulation.** No keyword stuffing, doorway pages, scaled low-value content, cloaking, link schemes, fake reviews, expired-domain abuse, site-reputation abuse, or misleading structured data.
4. **REQUIRED - no fabricated expertise.** Use a real author, a qualified reviewer, or transparent organizational attribution. Never invent people, biographies, quotes, customers, results, or credentials.
5. **PROHIBITED - false freshness.** Publication and modification dates must reflect real events. Never backdate content or change `dateModified` without a material review or update.
6. **RECOMMENDED - intent depth, not word quotas.** There is no universal ideal length, keyword density, heading count, image count, or link count. Cover the task completely and stop.
7. **REQUIRED - independent site value.** Every portfolio domain must have a distinct brand, audience, purpose, ownership rationale, and body of original value. Domains must not exist mainly to funnel users or authority elsewhere.
8. **REQUIRED - accessible by default.** Public experiences target WCAG 2.2 AA. SEO gains never justify inaccessible controls, hidden content, or disruptive interstitials.

## 2. Strategy foundation

### 2.1 Site charter

Before research or production, record: business model; audience; countries/languages; primary conversion; real-world entity; products/services; eligibility constraints; YMYL exposure; primary competitors; existing demand; and measurable business goals.

**REQUIRED:** define what this site can uniquely prove, demonstrate, calculate, compare, document, or teach. If there is no defensible answer, do not scale content.

### 2.2 Demand and intent map

Build one query-to-page map containing: query/theme, audience, intent, journey stage, target URL, page type, primary entity, supporting questions, evidence needed, conversion, owner, status, and cannibalization notes.

- **RECOMMENDED:** group by shared intent and SERP behavior, not exact-match variations.
- **REQUIRED:** assign one preferred page per materially identical intent.
- **CONDITIONAL:** split pages only when users, offer, location, language, or result format materially differs.
- **PROHIBITED:** location, industry, or keyword permutations whose main content is substantially interchangeable.

### 2.3 Information architecture

- **REQUIRED:** every indexable page is reachable through crawlable `<a href>` links.
- **RECOMMENDED:** use shallow, comprehensible hubs: Home -> category/solution -> detail.
- **RECOMMENDED:** link according to user next steps and semantic relationships; do not enforce arbitrary counts.
- **REQUIRED:** prevent orphan pages and maintain breadcrumbs where hierarchy benefits users.
- **RECOMMENDED:** keep URLs stable, readable, lowercase, and concise. Choose a trailing-slash convention and enforce it consistently.

## 3. Universal page contract

Every public URL has an explicit `index`, `noindex`, redirect, gone, or blocked state.

### 3.1 Indexable pages

- **REQUIRED:** HTTP 200; useful main content visible without interaction; unique descriptive title; one clear page topic; self-referencing canonical unless intentionally consolidated; crawlable internal links; mobile usability; and no accidental `noindex` or robots block.
- **RECOMMENDED:** unique meta description written for qualified clicks. Length is preview-dependent, not a ranking quota.
- **RECOMMENDED:** descriptive H1 and logical heading hierarchy; headings are structural, not keyword containers.
- **CONDITIONAL:** Open Graph/Twitter metadata when pages are shared socially.
- **CONDITIONAL:** hreflang only for real locale alternatives; reciprocal links, valid language/region codes, and `x-default` where appropriate.
- **REQUIRED:** visible content and structured data agree.

### 3.2 Non-indexable and utility pages

Login, cart, checkout, internal search, filtered duplicates, account, admin, staging, and thin system states are normally **CONDITIONAL `noindex`**. Do not rely on robots.txt to remove indexed URLs. Authenticated/private resources must use access control.

### 3.3 Content quality contract

An indexable page must provide at least one defensible value contribution: first-hand experience, original data, primary documentation, expert analysis, a useful tool/calculator, verified local knowledge, unique media, a clearer synthesis, or a demonstrably better transaction experience.

Claims that could affect money, health, safety, legal rights, or major life decisions require qualified review, primary sources where available, reviewer identity, review date, and a correction path.

## 4. Page-type standards

| Page type | Intent and required value | Indexation | Key content | Structured data |
|---|---|---|---|---|
| Homepage | Identify entity, audience, offer, proof, and paths | Index | Clear proposition, main categories, evidence, contact/entity details | `Organization` or applicable subtype + `WebSite` when truthful |
| Service | Evaluate/hire a service | Index | Scope, outcomes, process, proof, constraints, FAQs only if useful, CTA | `Service`; `LocalBusiness` only for eligible real location/entity |
| SaaS feature/product landing | Evaluate capability | Index | Jobs solved, workflow, evidence, screenshots/demo, integrations, limits | `SoftwareApplication` only when page/entity meets requirements |
| Ecommerce product | Buy/compare a product | Index if available/useful | Unique product data, variants, price, availability, shipping/returns, media | `Product`/`ProductGroup` + `Offer` as applicable |
| Category/collection | Browse a set | Index when curated and useful | Intro/filter context, crawlable product links, unique guidance | `BreadcrumbList`; item markup only when supported and accurate |
| Pricing | Understand cost and fit | Index | Current prices, units, inclusions, conditions, FAQs, sales path | `Offer` only when connected to a truthful supported item |
| Industry/audience | Evaluate fit for a real segment | Index only if distinct | Segment problems, workflow, proof, terminology, relevant offer | Match actual main entity; no invented vertical schema |
| Location/local service | Find a real staffed/service-area operation | Index only if unique and eligible | Location/service-area facts, local proof, staff, directions/contact | Applicable `LocalBusiness`; one real entity/location per page |
| Article/guide/tutorial | Learn or complete a task | Index | Direct answer, evidence, examples, steps/media as useful, next step | `Article`/`BlogPosting`; `HowTo` only when supported/eligible |
| Case study | Assess demonstrated results | Index with permission | Verified customer, context, method, baseline, outcome, limitations | `Article`; never fabricate review/result markup |
| Comparison/alternative | Compare options | Index if fair and maintained | Selection criteria, sourced current facts, fit, limitations, date | Usually `WebPage`/`Article`; avoid self-serving review markup |
| About/contact | Verify and contact entity | Index | Real ownership, people, history, policies, contact/location | `Organization`, `Person`, or `ContactPage` as applicable |
| Author/expert | Verify contributor | Index when substantive | Real bio, expertise, work, disclosures, correction/contact path | `ProfilePage` + `Person` when accurate |
| Help/FAQ | Resolve real support questions | Index selectively | Direct maintained answers and escalation | `FAQPage` only when eligible and visible; never assumed rich-result access |
| Legal/security | Compliance and trust | Usually index | Accurate reviewed policy; do not use SEO filler | `WebPage`; no unsupported claims/certifications |
| Search/filter/tag/archive | Navigate inventory | Conditional | Unique demand/curation determines value | Usually none beyond breadcrumbs |
| Campaign landing | Paid conversion | Usually noindex if duplicative | Message-match, offer, disclosure, conversion | Match visible content only |
| Login/account/checkout/admin | Complete private/system task | Noindex/access controlled | Usability and security | None unless specifically applicable |

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

1. **Brief:** audience, intent, target page, unique value, sources, entities, outline, conversion, conflicts, reviewer.
2. **Draft:** answer the main need early; use natural terminology and meaningful headings.
3. **Evidence review:** verify claims, dates, quotes, calculations, licenses, disclosures, and source relevance.
4. **Experience review:** add real examples, screenshots, tests, data, constraints, or expert judgment.
5. **SEO/accessibility review:** metadata, links, semantics, media alternatives, schema, canonical, indexation.
6. **Approval and publish:** record real author/reviewer and dates.
7. **Post-publish:** inspect rendered page, URL status, canonical, schema, sitemap inclusion, analytics, and Search Console.

**PROHIBITED:** keyword-density targets, exact-match repetition, mandatory FAQ/table/image/link quotas, padding to length, unsupported “citation bait,” or AI output published without accountable human review.

### 5.3 Media

- **REQUIRED:** media must be relevant, licensed/owned, responsive, dimensioned to reduce layout shift, and accessible.
- **REQUIRED:** informative images get contextual alt text; decorative images use `alt=""`.
- **RECOMMENDED:** prefer original screenshots, diagrams, product imagery, and demonstrations.
- **CONDITIONAL:** eager-load only the likely LCP image; lazy-load below-fold media. Use modern formats based on measured quality, not a universal file-size cap.
- **REQUIRED:** disclose or label synthetic media when context, law, or user trust warrants it; never use it as fabricated evidence.

## 6. Linking, citations, and reputation

- **REQUIRED:** internal links must help users discover relevant next steps; anchor text describes the destination.
- **RECOMMENDED:** hubs link to their important detail pages; detail pages link back to useful hubs and related pages.
- **REQUIRED:** citations must directly support the adjacent claim. Prefer primary, current, independent sources.
- **PROHIBITED:** mandatory `.gov`/`.edu` quotas, PageRank-shaped outbound linking, paid links without required qualification, reciprocal link schemes, or fabricated mentions.
- **CONDITIONAL:** cite competitors when necessary for a fair comparison; record source URL and checked date.
- **REQUIRED:** affiliate, sponsored, and user-generated links use appropriate disclosure and link attributes (`sponsored`, `ugc`, or `nofollow` as applicable).

Digital PR focuses on newsworthy original assets, expertise, tools, research, and partnerships. Buying authority or manufacturing endorsements is prohibited.

## 7. Structured data

1. **REQUIRED:** choose the most specific type that truthfully represents the visible main content.
2. **REQUIRED:** follow Google feature documentation in addition to Schema.org vocabulary when seeking Google rich-result eligibility.
3. **REQUIRED:** include all required properties; recommended properties only when factual.
4. **PROHIBITED:** empty objects, fake ratings/reviews, invisible content, global product/local-business/app nodes on unrelated entities, or marking every page as FAQ.
5. **RECOMMENDED:** stable `@id` values may connect real organization, website, person, and product entities.
6. **REQUIRED:** validate syntax and feature eligibility before release and monitor enhancement/manual-action reports.

Baseline mapping: organization identity on the primary identity page/site graph; breadcrumbs on hierarchical pages; article markup on editorial content; product/offer on purchasable product pages; local business on real eligible location pages; profile/person on substantive real contributor pages. Structured data can enable features; it does not guarantee display or ranking.

## 8. Technical SEO

### 8.1 Crawl, render, index

- **REQUIRED:** important content, links, metadata, canonical, and robots directives must be present in reliable rendered output; server rendering or static generation is preferred when it reduces failure risk.
- **REQUIRED:** use real anchor elements with resolvable `href` values.
- **REQUIRED:** XML sitemaps contain canonical, indexable 200 URLs only; accurate `lastmod` reflects meaningful updates.
- **RECOMMENDED:** submit and monitor sitemaps in Google Search Console and Bing Webmaster Tools.
- **CONDITIONAL:** use IndexNow for timely add/update/delete notifications to participating engines.
- **REQUIRED:** robots.txt controls crawling, not privacy or guaranteed deindexing.

### 8.2 Canonicals, duplicates, pagination, facets

- **REQUIRED:** redirects, canonicals, internal links, sitemap URLs, and hreflang references agree on preferred URLs.
- **REQUIRED:** each paginated URL that exposes unique items remains crawlable; do not canonicalize all pages to page 1.
- **CONDITIONAL:** index a facet only when it has demonstrated demand, stable inventory, unique value, and controlled combinations.
- **RECOMMENDED:** block or noindex low-value sort/filter permutations using a tested strategy; never create infinite crawl spaces.
- **REQUIRED:** merge overlapping pages when they serve the same intent; redirect only to a genuinely equivalent destination. Use 404/410 when no replacement exists.

### 8.3 Performance and experience

Measure field data at the 75th percentile by mobile/desktop. **RECOMMENDED:** meet Core Web Vitals “good” thresholds: LCP <= 2.5s, INP <= 200ms, CLS <= 0.1. Use lab tests for diagnosis, not as proof of field performance. Set project budgets for JavaScript, images, fonts, third parties, and server response based on templates and real-user data.

### 8.4 Security and environment controls

Use HTTPS, supported security headers, access-controlled staging, no public secrets, safe redirects, and monitored uptime. Security headers are security controls, not direct ranking promises. Analytics/script injection requires sanitization, least privilege, audit logs, consent controls, and CSP compatibility.

## 9. Specialized programs

### 9.1 Local

- **CONDITIONAL REQUIRED:** only eligible businesses create Google Business Profiles; represent the real-world name, category, address/service area, hours, and phone accurately.
- **PROHIBITED:** virtual-office locations, keyword-stuffed business names, duplicate profiles, fake reviews, or location pages without distinct real-world value.
- **RECOMMENDED:** keep business facts consistent across owned properties and authoritative listings; maintain photos, services, holiday hours, review responses, and local conversion tracking.

### 9.2 Ecommerce

- **REQUIRED:** crawlable category-to-product paths, accurate price/availability/variant data, unique product utility, and clear shipping/returns.
- **RECOMMENDED:** use both valid on-page product structured data and Merchant Center feeds when eligible; reconcile mismatches.
- **CONDITIONAL:** preserve discontinued pages when they retain user value and offer alternatives; otherwise redirect only to an equivalent item/category or return 404/410.

### 9.3 International

- **REQUIRED:** each locale page is useful and accurately localized, not unreviewed bulk translation.
- **REQUIRED:** locale-specific URLs, self-canonicals, reciprocal hreflang, and consistent language navigation.
- **RECOMMENDED:** localize currency, units, policies, examples, search terminology, and conversion paths.

### 9.4 YMYL

Health, legal, financial, safety, and civic content requires heightened sourcing, qualified review, conflicts/disclosures, update schedules, and escalation/correction procedures. AI must not invent or independently approve professional advice.

## 10. AI discovery and content automation

AI answer systems largely depend on accessible, indexable, trustworthy web content and retrieval systems. The foundation remains excellent SEO plus unambiguous, supportable information.

- **RECOMMENDED:** make key facts explicit; keep entity names and attributes consistent; publish original evidence; use descriptive headings, tables, steps, images, and video when they genuinely improve comprehension.
- **REQUIRED:** expose meaningful content in crawlable HTML and keep it current.
- **OPTIONAL:** `llms.txt`. Google states it is not needed for its generative search features; treat it as an experimental publisher convenience, not “AI citation control.”
- **CONDITIONAL:** crawler permissions reflect an explicit legal/content-licensing policy. Search indexing, user-triggered retrieval, and model training are different purposes; document the decision per user agent.
- **PROHIBITED:** rewriting solely for bots, tiny “AI chunks,” fake consensus/mentions, mass query permutations, or claims that schema guarantees AI citation.
- **REQUIRED:** AI-assisted content has a named accountable owner, fact/source verification, originality checks, disclosure when appropriate, and the same quality bar as human-originated work.

Track AI visibility using available first-party reports (for example Bing Webmaster Tools AI Performance when available), referral logs where identifiable, server logs, citations sampled with a recorded methodology, assisted conversions, and brand demand. Manual prompt checks are volatile observations, not rank tracking.

## 11. Portfolio governance for approximately 15 sites

Maintain a central registry: domain, brand/entity, owner, purpose, audience, markets, content boundaries, analytics/Search Console/Bing properties, schema identity, business profiles, canonical host, risk tier, and review date.

### 11.1 Reuse policy

- Shared code, design tokens, analytics conventions, and validation logic may be reused.
- Facts and source material may be reused only when relevant and re-expressed within genuinely site-specific value.
- **PROHIBITED:** cloning pages with swapped brand/location/keyword tokens; cross-domain canonicalization used to disguise duplication; or a network whose domains mainly cross-link to manipulate authority.
- Syndicated identical content requires a documented business reason, attribution, and a canonical/indexation plan; canonical signals are not guarantees.

### 11.2 Cross-domain links

Link only when the destination is useful and the relationship is transparent. Sitewide portfolio links belong in a restrained corporate/brand context, not keyword-rich SEO footers. Paid or controlled promotional relationships receive appropriate disclosure/qualification.

### 11.3 Publishing authority

Every page has an owner, reviewer where risk requires it, evidence record, indexation decision, and review trigger. Templates may enforce mechanics but cannot approve truth, usefulness, expertise, or legal claims.

## 12. Measurement and experiments

### 12.1 Outcome tree

Track by site, template, page type, intent, country, device, and brand/non-brand where possible:

- **Discovery:** crawl status, indexed canonical pages, sitemap health, rendering, server errors.
- **Visibility:** impressions, clicks, CTR interpreted with position/feature context, query/page coverage, local/merchant visibility, AI citations where measurable.
- **Engagement:** task completion, qualified scroll/interactions, internal navigation; do not use universal bounce/time thresholds.
- **Business:** leads, revenue, assisted conversions, customer quality, retention, and cost per incremental outcome.
- **Quality/risk:** stale claims, accessibility defects, schema errors, cannibalization, policy/manual actions, and content corrections.

### 12.2 Experiment protocol

Write hypothesis, affected URLs, primary/guardrail metrics, baseline, duration/sample needs, confounders, stop condition, and rollback. Change one meaningful variable per cohort where feasible. Record inconclusive and negative results. Never describe a test outcome as a universal ranking factor.

## 13. Operations and incident response

### 13.1 Review triggers

Review content when facts, product, law, inventory, competitors, search intent, traffic, conversions, or engine guidance materially changes. Calendar intervals are risk-based: high-risk/time-sensitive pages more often; evergreen pages when evidence indicates need.

### 13.2 Consolidation and removal

Keep, improve, merge, redirect, noindex, or remove based on user value and URL equivalence. Ranking history is an input, not a command to preserve obsolete content. Maintain a redirect map and avoid chains/loops.

### 13.3 Visibility incident

1. Confirm analytics and reporting integrity.
2. Segment loss by engine, site, directory, template, query, device, country, and date.
3. Check status pages, manual/security actions, index coverage, robots, canonicals, rendering, server logs, migrations, and releases.
4. Compare affected/unaffected cohorts and search-result changes.
5. Fix the evidenced cause, validate on representative URLs, then monitor. Avoid broad speculative rewrites during diagnosis.

## 14. Quality gates

### Per-page pre-publication

- [ ] Audience, intent, page type, unique value, owner, and indexation state recorded
- [ ] Claims, quotes, dates, prices, calculations, comparisons, and permissions verified
- [ ] Real author/reviewer attribution and disclosures where relevant
- [ ] Descriptive title/H1; useful snippet copy; canonical/robots/hreflang correct
- [ ] Crawlable internal links; no orphan or broken destination
- [ ] Relevant accessible media with licenses and dimensions
- [ ] Structured data is visible, truthful, applicable, and validated
- [ ] Mobile, keyboard, focus, forms, contrast, and responsive layout checked
- [ ] Rendered HTML, HTTP status, performance budget, analytics, and conversion tested

### Sitewide pre-launch

- [ ] Entity/site charter, query-to-page map, architecture, and ownership approved
- [ ] Production host, HTTPS, redirects, canonicals, robots, sitemaps, 404s, and staging controls tested
- [ ] Search Console/Bing verification, analytics consent, goals, and alerts configured
- [ ] Template metadata/schema/accessibility/performance tested on representative URLs
- [ ] No doorway, duplicate-network, fabricated identity, false-date, or scaled-content patterns
- [ ] Legal, privacy, security, accessibility, local, merchant, and YMYL reviews completed where applicable

### Monthly health

- [ ] Crawl/index/sitemap/server errors and manual/security actions reviewed
- [ ] Visibility and conversions segmented; anomalies investigated
- [ ] Broken links, schema errors, merchant/local data, and top-page freshness checked
- [ ] New cannibalization, duplication, orphaning, or portfolio overlap reviewed

### Quarterly governance

- [ ] Source guidance and this playbook reviewed
- [ ] Portfolio registry, ownership, cross-domain links, and content boundaries audited
- [ ] High-risk pages, experiments, redirects, access, and data retention reviewed
- [ ] Consolidate low-value overlap; prioritize original assets and demonstrated demand

### Migration

- [ ] Complete old-to-new URL inventory and one-to-one redirect map
- [ ] Preserve useful content, metadata, canonicals, hreflang, schema, and internal links
- [ ] Test in controlled staging; block staging from public access/indexation
- [ ] Launch redirects atomically; update sitemaps and important external profiles
- [ ] Monitor logs, status codes, indexing, rankings, conversions, and rollback thresholds

## 15. Priorities for adopting this version

**P0:** remove backdating, invented authors, fake metrics/reviews, universal schema, forced authority-link quotas, and ranking guarantees.

**P1:** implement explicit indexation, query-to-page mapping, page-type contracts, portfolio governance, accessibility, and evidence review.

**P2:** implement local/ecommerce/international/YMYL modules, automated mechanical checks, dashboards, and incident response.
**P3:** run controlled content/UX experiments and optional AI-publisher formats only after foundations are healthy.

## 16. Integration prompt

Apply this playbook to `[SITE]`. First produce the site charter, risk profile, query-to-page map, page-type inventory, technical baseline, and prioritized gaps. Do not generate pages until those artifacts identify a defensible audience need and unique value. Label every proposed rule with this playbook's classification and evidence type. Implement P0/P1 issues first; validate representative templates before scaling. Never fabricate identities, evidence, dates, reviews, locations, or outcomes. Report what was changed, what remains uncertain, validation results, and the measurement plan.

## 17. Source registry

Primary sources governing this edition:

- [Google Search Essentials](https://developers.google.com/search/docs/essentials)
- [Google SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Helpful, reliable, people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Google spam policies](https://developers.google.com/search/docs/essentials/spam-policies)
- [Google guidance for generative AI search](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)
- [Google structured data policies](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
- [Google JavaScript SEO basics](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)
- [Google ecommerce SEO guidance](https://developers.google.com/search/docs/specialty/ecommerce)
- [Google Product structured data](https://developers.google.com/search/docs/appearance/structured-data/product)
- [Google Business Profile representation guidelines](https://support.google.com/business/answer/3038177)
- [Bing Webmaster Guidelines and tools](https://www.bing.com/webmasters/help/)
- [Bing sitemaps and AI-powered search](https://blogs.bing.com/webmaster/July-2025/Keeping-Content-Discoverable-with-Sitemaps-in-AI-Powered-Search)
- [Bing AI Performance](https://blogs.bing.com/webmaster/February-2026/Introducing-AI-Performance-in-Bing-Webmaster-Tools-Public-Preview)
- [Schema.org](https://schema.org/)
- [Core Web Vitals](https://web.dev/articles/vitals)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)

For each quarterly review, record source changes, affected rules, reviewer, date, and decision in repository history.

---

## Playbook metadata

| Field | Value |
|---|---|
| Category | Search, content, AI discovery, and conversion governance |
| Scope | Portfolio and full-site playbook |
| Supported models | Service, SaaS, local, ecommerce, publishing |
| Stack | Stack-flexible; automate mechanical checks only |
| Related specs | [Paid subscriptions](../../systems/paid-subscription-system/SPEC.md), [email templates](../../systems/email-template-system/SPEC.md) |
