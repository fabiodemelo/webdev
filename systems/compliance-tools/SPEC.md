# System: Compliance Tools (US + EU/UK)

A reusable compliance layer applied to **every** site: cookie consent + CMP, geo-aware legal notices, the mandatory legal pages (Privacy, Cookie, Terms, Accessibility, Impressum), Do-Not-Sell/GPC handling, a DSAR (data-subject request) intake + workflow, consent-record storage, an email-compliance helper, and a WCAG 2.1 AA baseline checklist. One module, dropped into any site, that satisfies the common US and EU/UK statutory requirements.

## Design Reference

Shared, themeable UI (one look, recolored per site via `--lc-*` tokens) — interactive preview: [reference/consent-ui-kit-preview.html](reference/consent-ui-kit-preview.html). Drop-in production component (banner + preferences modal): [reference/consent-ui.html](reference/consent-ui.html). Covers the cookie banner, preferences modal, accessibility widget, and Do-Not-Sell / GPC controls.

**Type:** cross-cutting compliance subsystem (consent engine + geo logic + legal pages + DSAR workflow + WCAG baseline + email rules). Mounts into marketing sites (Astro/WP) and apps (React) alike; the admin lives in the [admin-portal-system](../admin-portal-system/SPEC.md).

**Reference stack:** stack-flexible. Consent UI = vanilla JS or React (framework-agnostic banner). Backend = FastAPI/Express/PHP + MySQL (consent + DSAR tables). Legal pages = MDX/HTML with a versioned store. Geo = edge/CDN header or IP lookup.

> ⚠️ **Not legal advice.** This module encodes widely-accepted requirements as of 2026 to give every site a compliant baseline. Jurisdiction thresholds, sector rules (HIPAA/GLBA/COPPA), and exact policy wording must be confirmed by counsel per site. The module makes compliance *implementable and auditable* — it does not replace a lawyer.

---

## Requirement Matrix (what the module enforces)

### United States

| Item | Trigger | What ships |
|------|---------|-----------|
| Privacy Policy | Any personal data + CA reach (CalOPPA) | Versioned Privacy page |
| CCPA/CPRA | Business meets CA thresholds | "Do Not Sell or Share My Personal Information" link, notice-at-collection, opt-out, **honor GPC signal**, sensitive-data limits, rights (access/delete/correct), retention disclosure |
| State laws (VA/CO/CT/UT/TX/OR/MT + ~19 by 2026) | Residents in scope | Opt-out + universal opt-out signal, rights, policy |
| COPPA | Directed at / knowingly collects <13 | Parental-consent gate + kids policy |
| ADA Title III / Section 508 | Public accommodation / gov | **WCAG 2.1 AA** baseline + Accessibility Statement |
| CAN-SPAM | Marketing email | Unsubscribe link + physical postal address + honest headers |
| FTC | Affiliate/ads; subscriptions | Endorsement/affiliate disclosure; Click-to-Cancel (2024) + CA ARL for auto-renewals |
| CalOPPA DNT | Tracking | Do-Not-Track disclosure in policy |

US cookies: **opt-out + notice** model (not opt-in). CPRA treats cross-context ad cookies as "sharing" → must be covered by the opt-out.

### EU / EEA + UK

| Item | Trigger | What ships |
|------|---------|-----------|
| GDPR / UK GDPR | Any EU/UK personal data | Lawful basis, Art 13/14 Privacy Policy, rights (access/erasure/portability/rectification/objection), 72h breach process, processor DPAs, SCC transfer safeguards, records of processing |
| ePrivacy / PECR "Cookie Law" | Non-essential cookies | **Prior opt-in** consent, granular categories, **reject as easy as accept**, no scripts before consent, no cookie walls |
| CMP + consent log | Cookies/ad-tech | Consent Management Platform + stored proof of consent (IAB TCF for ad-tech) |
| European Accessibility Act (EAA) | Private e-commerce/banking/e-books/transport — **in force 28 Jun 2025** | **EN 301 549 / WCAG 2.1 AA** + Accessibility Statement |
| Imprint / Impressum | DE/AT/CH operators (mandatory) | Operator identity, contact, register no., VAT ID |
| DSA | Online platforms / marketplaces | Notice-and-action + transparency reporting |
| Consumer law (e-commerce) | Selling to EU consumers | 14-day right of withdrawal, order confirmation, **"order with obligation to pay" button**, prices incl. VAT, shipping/delivery terms |
| Newsletter | Email marketing | Double opt-in (de-facto) |

> The EU **ODR platform shut down July 2025** — do NOT ship the old "ODR link." Kept here as an explicit anti-item.

---

## 1. Architecture

| Component | Responsibility |
|-----------|----------------|
| Consent banner (client) | Framework-agnostic UI: accept-all / reject-all (equal prominence) / granular categories. Blocks non-essential scripts until consent. Persists choice + version. |
| Category registry | Cookie/script inventory grouped: `necessary` (always on), `functional`, `analytics`, `advertising`. Each entry: name, vendor, purpose, duration, domain. |
| Geo resolver | Edge/CDN geo header (preferred) or IP lookup → jurisdiction bucket `EU | UK | DACH | US-CA | US | ROW`. Drives which mode (opt-in vs opt-out), which pages, which banner text. |
| Consent store (DB) | Append-only proof of consent (who/when/what/version/jurisdiction/method). GDPR + CPRA require demonstrable consent. |
| Legal page store | Versioned Privacy / Cookie / Terms / Accessibility / Impressum documents. Each publish snapshots a version + effective date. |
| DSAR intake + workflow | Public request form → queue → admin fulfillment (access/delete/correct/opt-out/portability) with SLA timers + audit log. |
| GPC / DNT handler | Reads `Sec-GPC` / `navigator.globalPrivacyControl` → auto-applies opt-out of sale/sharing; reflected in consent store. |
| Email-compliance helper | Enforces unsubscribe link + postal address on every marketing send; double-opt-in confirm flow; suppression list. |
| WCAG baseline | Shared components + CI checks enforcing WCAG 2.1 AA (see §7). |
| Admin | Manage cookie inventory, publish policies, work the DSAR queue, view consent logs. Lives in admin-portal-system. |

## 2. Consent Modes (geo-driven)

```
resolveMode(jurisdiction):
  EU | UK | DACH        -> "opt-in"   (no non-essential scripts until explicit consent; reject == accept prominence)
  US-CA | US-<state>    -> "opt-out"  (scripts may run; show "Do Not Sell/Share" + honor GPC → auto opt-out)
  ROW                   -> "notice"   (banner informs; sensible default = analytics on, ads off)
```
- **GPC always wins**: if `Sec-GPC: 1`, force opt-out of sale/sharing regardless of mode, and record it.
- Consent version bump (policy or inventory change) → re-prompt.
- No cookie walls: content is reachable whether or not the user accepts.

## 3. Data Model (MySQL)

Full DDL in [reference/schema.sql](reference/schema.sql). Tables:

- **`consent_records`** — append-only: `id`, `subject_key` (hashed visitor id / user_id), `jurisdiction`, `mode`, `categories` (JSON: which accepted), `gpc` (TINYINT), `policy_version`, `cmp_version`, `method` (`banner`/`gpc`/`api`), `ip_hash`, `user_agent`, `created_at`. Never updated — a new choice = a new row.
- **`legal_documents`** — `id`, `doc_type` (`privacy`/`cookie`/`terms`/`accessibility`/`impressum`), `locale`, `version`, `effective_date`, `body_html`, `is_current` (TINYINT), `published_by`, `created_at`. Unique `(doc_type, locale, version)`.
- **`cookie_inventory`** — `id`, `category` (`necessary`/`functional`/`analytics`/`advertising`), `name`, `vendor`, `purpose`, `duration`, `domain`, `is_active`, `sort_order`. Drives the banner categories + Cookie Policy page.
- **`dsar_requests`** — `id`, `request_type` (`access`/`delete`/`correct`/`opt_out`/`portability`), `subject_email`, `subject_name`, `jurisdiction`, `status` (`new`/`verifying`/`in_progress`/`completed`/`rejected`), `details`, `verification_token`, `due_at` (SLA: 45 days CCPA / 30 days GDPR), `handled_by`, `resolution_note`, `created_at`, `updated_at`.
- **`email_suppression`** — `id`, `email`, `reason` (`unsubscribe`/`bounce`/`complaint`), `created_at`. Unique `(email)`. Checked before every marketing send.
- **`newsletter_optins`** — `id`, `email`, `status` (`pending`/`confirmed`), `confirm_token`, `confirmed_at`, `created_at` — double opt-in.

## 4. API Surface

**Public**
| Method+path | Purpose |
|---|---|
| `GET /compliance/config` | Returns resolved `{ jurisdiction, mode, categories[], policy_version }` for the caller (uses geo + GPC header). |
| `POST /compliance/consent` | Record a consent choice → row in `consent_records`. Body: categories, mode, cmp_version. |
| `GET /legal/:doc_type` | Current published legal doc for the caller's locale. |
| `POST /dsar` | Submit a data-subject request → email verification link. |
| `GET /dsar/verify/:token` | Confirm request ownership → moves to `verifying`→`in_progress`. |
| `POST /newsletter/subscribe` | Double opt-in: create `pending` + send confirm email. |
| `GET /newsletter/confirm/:token` | Confirm → `confirmed`. |
| `GET /unsubscribe/:token` | One-click unsubscribe → add to `email_suppression`. |

**Admin** (`requireAdmin`)
| Method+path | Purpose |
|---|---|
| `GET/POST/PUT /admin/cookie-inventory` | Manage cookie/script registry. |
| `GET/POST /admin/legal/:doc_type` | Draft + publish a new versioned legal doc (snapshots version + effective date; flips `is_current`). |
| `GET /admin/dsar?status=` | DSAR queue. |
| `PUT /admin/dsar/:id` | Advance status, record resolution, stop SLA timer. |
| `GET /admin/consent-log?from=&to=` | Export consent proof (audit/regulator). |

## 5. Legal Pages (mandatory set)

Each is a versioned `legal_documents` row rendered at a stable route. Ship starter templates (placeholders in `[BRACKETS]` — fill per site, review by counsel):

- `/privacy` — Privacy Policy (GDPR Art 13/14 + CCPA disclosures + DNT + retention)
- `/cookies` — Cookie Policy (auto-rendered from `cookie_inventory`)
- `/terms` — Terms of Service (+ e-commerce consumer terms where selling)
- `/accessibility` — Accessibility Statement (conformance target WCAG 2.1 AA, contact for issues)
- `/legal/impressum` — Imprint (shown for DE/AT/CH; operator identity, VAT ID, register)
- `/do-not-sell` — CCPA opt-out landing (US-CA) — or a footer control wired to the consent store

Footer must always link: Privacy · Cookies · Terms · Accessibility · (Impressum if DACH) · (Do Not Sell/Share if US).

## 6. DSAR Workflow

```
submit → email-verify → in_progress → (fulfil: access pack / delete / correct / opt-out / export) → completed
                                    ↘ rejected (identity unverified / not applicable) with reason
```
- SLA timer per jurisdiction: **GDPR 30 days**, **CCPA 45 days** (extendable once). `due_at` set on verification.
- Every state change writes an audit row (reuse the portal audit log).
- Delete requests cascade to app data + `consent_records` retained as proof-of-request (legal hold), email added to suppression.

## 7. WCAG 2.1 AA Baseline (enforced, not optional)

Ship a checklist + shared components + CI gate:
- Semantic HTML landmarks; one `<h1>`, ordered headings
- All images `alt`; decorative = `alt=""`
- Full keyboard operability; visible focus ring; logical tab order; skip-to-content link
- Color contrast ≥ 4.5:1 text / 3:1 large + UI
- Every form control labeled; errors announced (aria-live); no color-only signaling
- `prefers-reduced-motion` respected; no seizure-risk motion
- ARIA only where semantics fall short; name/role/value correct on custom widgets
- Responsive to 320px; 200% zoom without loss; target size ≥ 24px
- CI: `axe-core` / `pa11y` on key routes fails the build on new AA violations

## 8. Security & Data-Protection Hooks (GDPR "appropriate measures")

- HTTPS/TLS everywhere; **HSTS** + restrictive **CSP** (see cdn-object-storage/security headers pattern)
- Hash/pseudonymize `subject_key` + `ip_hash` in consent records — no raw PII where a hash suffices
- Encrypt DSAR PII at rest; access-controlled; auto-purge verification tokens
- Data-retention policy per data class, enforced by a cron
- Processor DPAs tracked (Stripe, SendGrid, DO Spaces, analytics…) — list maintained in admin

## Reproduction Checklist

1. Create the 6 tables ([reference/schema.sql](reference/schema.sql)).
2. Build the geo resolver (edge header first, IP fallback) → jurisdiction bucket + mode.
3. Ship the consent banner (accept/reject equal prominence, granular categories, script-blocking until consent in opt-in mode); wire `GET /compliance/config` + `POST /compliance/consent`.
4. Implement GPC/DNT auto-opt-out and record it.
5. Seed `cookie_inventory`; auto-render the Cookie Policy from it.
6. Publish the 5 legal-page templates as versioned docs; wire the footer link set (geo-conditional Impressum + Do-Not-Sell).
7. Build the DSAR intake form + email verification + admin queue with SLA timers + audit.
8. Wire email-compliance: unsubscribe token + postal address on every marketing template; double-opt-in for newsletter; suppression check pre-send.
9. Apply the WCAG 2.1 AA baseline components + add `axe`/`pa11y` CI gate.
10. Add HSTS + CSP + TLS; hash consent identifiers; add the retention-purge cron.
11. Per-site: fill `[BRACKET]` placeholders in legal templates and **have counsel review** before launch.

---

## System Metadata

| Field | Value |
|-------|-------|
| Name | Compliance Tools |
| Category | Legal / privacy / accessibility compliance |
| Jurisdictions | US (CCPA/CPRA + ~19 state laws, ADA, CAN-SPAM, COPPA, FTC) + EU/EEA (GDPR, ePrivacy, EAA, DSA) + UK (UK GDPR, PECR) + DACH Impressum |
| Backend | FastAPI/Express/PHP + MySQL |
| Frontend | Framework-agnostic consent banner + versioned legal pages (MDX/HTML) |
| Key concepts | Geo-driven opt-in vs opt-out, GPC honoring, proof-of-consent log, DSAR workflow with SLA, WCAG 2.1 AA gate |
| Mounts into | [admin-portal-system](../admin-portal-system/SPEC.md); pairs with [email-template-system](../email-template-system/SPEC.md) for unsubscribe/double-opt-in |
| Disclaimer | Not legal advice — counsel review required per site |
| Anti-item | No EU ODR link (platform shut down Jul 2025); no cookie walls |
