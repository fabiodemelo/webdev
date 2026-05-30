# webdev — Build Specs

Paste-ready specs for web UI and features. Each spec is self-contained — drop it into any project and an AI agent reads it, installs deps, writes the code, and wires it in.

Think of these as **skills, but for things you build.** Two tiers:

- **blocks/** — single UI page sections (testimonial, hero, pricing). Target stack: shadcn + Tailwind + TypeScript.
- **systems/** — full features / subsystems (data model + API + UI). Stack varies per spec.

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

### Systems

| System | Category | Stack | Spec |
|--------|----------|-------|------|
| Transactional email template system | Admin / email infra | FastAPI + doc store + email SDK + React | [SPEC.md](systems/email-template-system/SPEC.md) |
| Coupon code system | Billing / discounts | FastAPI + MongoDB + React + Stripe | [SPEC.md](systems/coupon-code-system/SPEC.md) |

## Stack assumptions

**Blocks** target shadcn + Tailwind + TypeScript. Each block spec includes setup-fallback instructions if the target project lacks these.

**Systems** declare their own reference stack per spec (map onto project equivalents).
