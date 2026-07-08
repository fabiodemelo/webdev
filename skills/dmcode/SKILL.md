---
name: dmcode
description: This skill should be used at the START of any coding/development task ("dmcode", "let's code", "build X", new feature or project work). It is Fabio's personal dev workflow entrypoint backed by the hub repo github.com/fabiodemelo/webdev. On every invocation it (1) checks the hub and its upstream (jeffallan/claude-skills) for updated code and offers to sync, (2) asks which webdev modules/blocks (blocks, patterns, systems, playbooks, skills) the task needs and installs/applies them, and (3) enforces the delivery-gate skill before any work is called done.
---

# dmcode — Fabio's dev workflow entrypoint

The hub: **https://github.com/fabiodemelo/webdev** — three kinds of content:
- `blocks/`, `patterns/`, `systems/`, `playbooks/` — paste-ready build specs (each folder has a `SPEC.md`; the Integration Prompt section is what gets applied to a project)
- `skills/` — the full claude-skills collection (66 dev skills, mirrored from upstream `jeffallan/claude-skills`) plus Fabio's own skills (`dmcode`, `delivery-gate`)
- `skills/UPSTREAM.md` — records the upstream commit the mirror was last synced to

## Invocation flow (run all three steps, in order)

### Step 1 — Update check (always, before anything else)
Run `scripts/check_updates.sh`. It reports:
- whether the local hub clone is behind `origin/main` (Fabio's own latest specs)
- whether upstream `jeffallan/claude-skills` has commits newer than the hash recorded in `skills/UPSTREAM.md`

If the hub is behind → pull. If upstream is ahead → tell Fabio what changed (`git log` summary) and ask whether to sync the mirror now (`scripts/sync_upstream.sh`); never sync silently. After a sync, copy updated skills into `~/.claude/skills/` and commit + push the hub.

### Step 2 — Module picker (always ask, never assume)
Read the hub catalog (README.md tables + directory listing) and ask which modules/blocks the current task needs, via AskUserQuestion with real options from the catalog, grouped:
- **Systems** (full features: admin portal, subscriptions, coupons, support tickets, email templates, …)
- **Blocks / Patterns / Playbooks** (UI sections, layout patterns, site strategies)
- **Skills** (which specialist skills fit the stack: react-expert, fastapi-expert, security-reviewer, …)
- **None** (plain task, no hub modules)

For each chosen spec: open its `SPEC.md`, follow the Integration Prompt exactly (full working code, no placeholders — hub rule). For each chosen skill not yet in `~/.claude/skills/`: install with `scripts/install_module.sh <name>`.

### Step 3 — Work under the gate
Do the task. Before ANY claim of done/complete/delivered, invoke the `delivery-gate` skill and deliver its Gap Report format. This is not optional; dmcode work without a Gap Report is unfinished by definition.

## Local hub clone
Keep a working clone at `~/.claude/webdev-hub` (scripts create it on first run). All scripts operate on that clone; never mutate the GitHub repo without showing Fabio the diff summary first.

## Adding to the hub
When Fabio says "add this to the hub / webdev": follow `TEMPLATE.md` conventions, full working code only, update the README catalog table, commit with a descriptive message, push.
