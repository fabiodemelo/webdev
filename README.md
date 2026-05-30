# webdev — Block Specs

Paste-ready specs for full web page blocks. Each block is a self-contained `SPEC.md` you drop into any shadcn + Tailwind + TypeScript project. An AI agent reads the spec, installs deps, copies the component, and wires it in.

Think of these as **skills, but for UI blocks.**

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

| Block | Category | Deps | Spec |
|-------|----------|------|------|
| Testimonials (animated columns) | Social proof | `motion` | [SPEC.md](blocks/testimonials-columns/SPEC.md) |

## Stack assumption

All blocks target:
- **shadcn** project structure
- **Tailwind CSS**
- **TypeScript**

Each spec includes setup-fallback instructions if the target project lacks these.
