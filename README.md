# webdev — Build Specs

Paste-ready specs for web UI and features. Each spec is self-contained — drop it into any project and an AI agent reads it, installs deps, writes the code, and wires it in.

Think of these as **skills, but for things you build.** Three tiers:

- **blocks/** — single UI page sections (testimonial, hero, pricing). Target stack: shadcn + Tailwind + TypeScript.
- **patterns/** — small reusable UI/layout patterns (shared chrome, wiring tricks). Stack-agnostic.
- **systems/** — full features / subsystems (data model + API + UI). Stack varies per spec.
- **playbooks/** — multi-page strategies applied across a whole site (content, SEO, pricing, trust pages). Stack-flexible.

## How to use

1. Find the block you want in `blocks/`.
2. Open its `SPEC.md`.
3. Copy everything below the **Integration Prompt** line.
4. Paste into your target project (Claude Code, Cursor, etc.).
5. The agent builds it end-to-end.

## Add a new block

1. Copy [`TEMPLATE.md`](TEMPLATE.md) → `blocks/<your-block>/SPEC.md`.
2. Fill in: description, component code, demo, deps, metadata.
3. No placeholders, no TODOs — full working code only.
4. Optional: drop a `preview.png` in the folder.

## Catalog

### Blocks

| Block | Category | Deps | Spec |
|-------|----------|------|------|
| Testimonials (animated columns) | Social proof | `motion` | [SPEC.md](blocks/testimonials-columns/SPEC.md) |

### Patterns

| Pattern | Category | Stack | Spec |
|---------|----------|-------|------|
| Global brand bar | UI / layout chrome | React Native + PHP/HTML/CSS | [SPEC.md](patterns/global-brand-bar/SPEC.md) |

### Systems

| System | Category | Stack | Spec |
|--------|----------|-------|------|
| Admin portal system (chassis) | Admin shell / design system | Express + TS + React + Tailwind v4 | [SPEC.md](systems/admin-portal-system/SPEC.md) |
| Admin Dev Tracker | Admin / dev process | Any REST + SQL/doc store + React | [SPEC.md](systems/admin-dev-tracker/SPEC.md) |
| Transactional email template system | Admin / email infra | FastAPI + doc store + email SDK + React | [SPEC.md](systems/email-template-system/SPEC.md) |
| Coupon code system | Billing / discounts | FastAPI + MongoDB + React + Stripe | [SPEC.md](systems/coupon-code-system/SPEC.md) |
| Paid subscription / membership system | Billing / subscriptions (full stack) | FastAPI + MongoDB + React + Stripe | [SPEC.md](systems/paid-subscription-system/SPEC.md) |
| Support ticket system (full helpdesk) | Support / helpdesk / ITSM | PHP + MySQL + React + JWT + S3 | [SPEC.md](systems/support-ticket-system/SPEC.md) |
| Personal task / to-do system | Productivity / tasks | FastAPI + MongoDB + React | [SPEC.md](systems/personal-task-system/SPEC.md) |
| Training video library | Education / content | FastAPI + MongoDB + React + YouTube | [SPEC.md](systems/training-video-library/SPEC.md) |
| Cancellation + retention module | Billing / retention / churn | FastAPI + MongoDB + React + Stripe | [SPEC.md](systems/cancellation-retention-module/SPEC.md) |

### Playbooks

| Playbook | Category | Stack | Spec |
|----------|----------|-------|------|
| Content + SEO | Content strategy + SEO + GEO | Stack-flexible (ref: Astro/Next + Zod) | [SPEC.md](playbooks/content-seo/SPEC.md) |

## Stack assumptions

**Blocks** target shadcn + Tailwind + TypeScript. Each block spec includes setup-fallback instructions if the target project lacks these.

**Systems** declare their own reference stack per spec (map onto project equivalents).

**Playbooks** are stack-flexible strategy guides applied across an entire site; swap `[BRACKET]` placeholders for target details.
