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

### Skills (`skills/`)

The full [jeffallan/claude-skills](https://github.com/jeffallan/claude-skills) collection (66 dev skills, MIT — see `skills/LICENSE-upstream`, sync state in `skills/UPSTREAM.md`) plus my own:

| Skill | Purpose |
|-------|---------|
| **dmcode** | Dev workflow entrypoint: checks this hub + upstream for updates, asks which modules/blocks/skills the task needs, installs them, enforces delivery-gate |
| **delivery-gate** | Mandatory gap hunt before any "done" claim: static scan → build/tests → click-through walkthrough → adversarial review → Gap Report |

Install a skill: copy `skills/<name>/` to `~/.claude/skills/<name>/` (or `dmcode` does it for you).

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
| Software licensing + SDK system | Licensing / anti-piracy | Express + TS + MySQL + Ed25519 + React | [SPEC.md](systems/software-licensing-system/SPEC.md) |
| CDN / object storage integration | Infrastructure / file storage | DO Spaces (S3) + PHP/Node/Python + MySQL | [SPEC.md](systems/cdn-object-storage/SPEC.md) |
| Vault module (biz info + credential manager) | Admin / company records / secrets | PHP + MySQL + vanilla JS (stack-neutral spec) | [SPEC.md](systems/vault-module/SPEC.md) |

### Playbooks

| Playbook | Category | Stack | Spec |
|----------|----------|-------|------|
| Content + SEO | Content strategy + SEO + GEO — moved to own repo | Stack-flexible | [MASTER-SEO-STRATEGY](https://github.com/fabiodemelo/MASTER-SEO-STRATEGY) |

## Stack assumptions

**Blocks** target shadcn + Tailwind + TypeScript. Each block spec includes setup-fallback instructions if the target project lacks these.

**Systems** declare their own reference stack per spec (map onto project equivalents).

**Playbooks** are stack-flexible strategy guides applied across an entire site; swap `[BRACKET]` placeholders for target details.
