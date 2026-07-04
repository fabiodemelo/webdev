# System: Admin Dev Tracker

Development progress tracker page for an admin portal: 100% of a project's planned features listed in one screen, grouped by category, each with a sequential stage pipeline (WIP → Coded → Tested → Pending action → Verified) rendered as **auto-saving checkboxes** — no save button anywhere. Verification is reserved for the product owner (super_admin): the server rejects a "Verified" check from anyone else, so an AI/dev claiming "done" can never mark its own work verified. Below the feature list, a **Recommendations & Concerns** feed collects feature ideas, tech-debt notes, UX issues, and security worries; accepted ideas convert into new tracked feature rows.

**Type:** full feature subsystem (two tables/collections + API + one admin page). Designed to mount on the `admin-portal-system` chassis (same tokens, primitives, guards) but works in any admin.

**Reference stack:** any REST backend (PHP/FastAPI/Express) + SQL or document store + React (function components + hooks). JWT roles: `admin`, `super_admin`.

> Purpose: kill the "half-built feature discovered by the owner" failure mode. The tracker is the human view of the project's traceability matrix; optionally two-way-synced with a `features.yaml` in the repo so CI and humans read the same truth.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap category values for the project's domains. If the project has a `features.yaml`/requirements doc, seed the table from it.

---

You are given a task to build an **Admin Dev Tracker** in the codebase.

Reference stack (map onto project equivalents):
- **Frontend:** React function components + hooks, the project's existing UI primitives (Card, Badge, Table, toast). lucide-react icons.
- **Backend:** existing REST conventions — response envelope, JWT guard middleware (`requireAdmin`, `requireSuperAdmin`).
- **Database:** two tables/collections: `features`, `feature_events` (append-only), plus `recommendations`.

### 1. Overview

- Route: `/admin/dev-tracker`, sidebar item under the PLATFORM group.
- One row per planned feature. Every feature the project intends to ship is here — if it's not in the tracker, it doesn't exist.
- **Owner-verified**: the final stage can only be checked by the product owner's super_admin account. This is the whole point of the block — enforce it server-side.
- Zero save buttons. Every interaction persists immediately.

### 2. Data Model

```sql
features (
  id            PK,
  feature_key   VARCHAR UNIQUE,          -- e.g. F-AUD-4, stable id used in specs/CI
  name          VARCHAR NOT NULL,
  description   TEXT,
  category      VARCHAR NOT NULL,        -- project-specific enum; reference set:
                                         -- site, admin, security, core, ai, billing,
                                         -- integrations, server, domain, database
  phase         VARCHAR,                 -- optional grouping (Phase 2, MVP, v1.1…)
  stage         ENUM('backlog','wip','coded','tested','pending_action','verified')
                NOT NULL DEFAULT 'backlog',
  pending_note  TEXT NULL,               -- REQUIRED when stage = pending_action
  evidence_url  VARCHAR NULL,            -- link to test output / screenshots bundle
  verified_by   VARCHAR NULL,            -- user id; set only by server on verify
  verified_at   DATETIME NULL,
  sort_order    INT DEFAULT 0,
  created_at / updated_at
)

feature_events (                          -- append-only audit trail, no UPDATE/DELETE
  id PK, feature_id FK,
  from_stage VARCHAR, to_stage VARCHAR,
  actor VARCHAR,                          -- user id or 'ci' / 'agent'
  note TEXT NULL,
  created_at
)

recommendations (
  id PK,
  type          ENUM('feature_idea','concern','tech_debt','ux_issue','security'),
  title         VARCHAR NOT NULL,
  body          TEXT,
  priority      ENUM('low','medium','high','critical') DEFAULT 'medium',
  source        VARCHAR,                  -- user id or 'agent'
  status        ENUM('open','accepted','dismissed') DEFAULT 'open',
  dismissed_reason TEXT NULL,             -- REQUIRED when dismissed
  feature_id    FK NULL,                  -- set when accepted → converted to a feature
  created_at / updated_at
)
```

### 3. Stage Rules (server-enforced — the API is the wall)

- Stages are **sequential**: `backlog → wip → coded → tested → pending_action(optional detour) → verified`. Moving forward more than one step at a time is allowed for import/backfill by super_admin only; everyone else steps one at a time.
- `pending_action` requires a non-empty `pending_note` (400 otherwise) — "blocked" without a reason is banned.
- **`verified` transitions:**
  - Only `requireSuperAdmin` may set it (403 for plain admins, agents, CI tokens).
  - Server stamps `verified_by`/`verified_at` itself — never accepted from the request body.
  - Any non-owner attempt is still recorded in `feature_events` with a rejection note (visibility into who tried).
- **Regression rule:** an automated caller (CI role) may move `verified → pending_action` with a note (e.g. "gate G6 failed on commit abc123") — the ONLY backward transition automation may make. UI shows a red "regressed" badge until re-verified.
- Every transition writes a `feature_events` row. The events table is append-only; guard mutations at the API layer.

### 4. API

```
GET    /admin/dev-tracker/features?category=&stage=&phase=&q=
POST   /admin/dev-tracker/features                 (super_admin — add feature)
PATCH  /admin/dev-tracker/features/:id             (name/description/category/evidence/sort)
POST   /admin/dev-tracker/features/:id/stage       { stage, note? }  → rules in §3
GET    /admin/dev-tracker/features/:id/events
GET    /admin/dev-tracker/summary                  → totals per category + per stage (for progress bars)
GET    /admin/dev-tracker/recommendations?type=&status=
POST   /admin/dev-tracker/recommendations
PATCH  /admin/dev-tracker/recommendations/:id      (status changes; accept may create a feature)
```

Envelope, zod-style validation, and status codes follow the host project's conventions (200/201, 400 validation, 401, 403, 404).

### 5. Page Layout (`/admin/dev-tracker`)

Top → bottom:

1. **Header**: title, total progress bar "X of Y verified", global stage-count chips (wip/coded/tested/pending/verified), search box, filters (category, stage, phase, "needs my verification" — super_admin quick filter). Filters live in URL params.
2. **Category sections** (collapsible, default open): section header = category name + per-category progress bar + count. Reference categories: `site, admin, security, core, ai, billing, integrations, server, domain, database` — rename per project.
3. **Feature rows**: feature_key (mono), name, stage checkbox strip, pending-note inline (when applicable), evidence link icon, updated-at (mono), expand chevron.
   - **Checkbox strip**: five checkboxes labeled WIP / Coded / Tested / Pending action / Verified. Checking one sets that stage (and visually fills all earlier ones). The Verified checkbox is disabled with a tooltip ("Owner only") for non-super_admins.
   - **Expanded row**: description, evidence link(s), full event history timeline (actor, from→to, note, mono timestamp).
4. **Recommendations & Concerns** (same page, below): type-filter tabs (All / Ideas / Concerns / Tech debt / UX / Security), add form (type, title, body, priority), card list with type badge + priority pill + source. Actions per card: **Accept** (opens prefilled "new feature" modal → creates feature row + links it, status→accepted), **Dismiss** (requires reason via ConfirmDialog), edit for the author.

### 6. Auto-save Behavior (no save button — non-negotiable)

- **Checkbox click** → optimistic UI (flips instantly) + immediate `POST /stage`; 12px inline spinner replaces the checkbox while in flight; on error revert + error toast with the server message.
- **Checkbox semantics** (proven in the reference implementation): the strip renders cumulative — reaching a stage fills all earlier checkboxes. Clicking an unchecked stage advances to it; clicking the *currently highest checked* stage steps back one (uncheck = previous stage, or backlog from WIP). Clicking `Pending action` never saves directly — it opens a modal requiring the blocking note first (server 400s without it).
- **Pending-note / description edits** → debounced 800ms PATCH, "Saved ✓" micro-indicator fades in/out next to the field.
- **Reorder (drag)** → PATCH sort_order on drop.
- **Concurrency guard**: every mutation sends the row's `updated_at`; server returns 409 if stale → UI refetches the row and shows "Updated elsewhere — refreshed" info toast. Prevents two people (or a person + CI) silently overwriting each other.
- **Stale-list guard**: sequence counter / AbortController on list fetches so slow responses never clobber fresh state.

### 7. Optional: `features.yaml` two-way sync (CI integration)

If the repo keeps a traceability matrix file:
- `sync push`: DB → yaml (stage field only) on deploy/checkpoint.
- `sync pull`: yaml → DB for new feature entries (never stage downgrades, never touches `verified`).
- CI may call `POST /stage` with its token for `wip/coded/tested/pending_action` transitions per §3; CI can NEVER verify.

### 8. Visual & Interaction Rules

- Follow host admin's tokens/primitives; stage pills via the shared `statusTone()` helper: verified → success, tested/coded → info, wip → warning, pending_action → danger, backlog → neutral.
- Mono font + tabular numerals for feature keys, counts, timestamps.
- `data-testid` on every checkbox (`devtracker-<feature_key>-<stage>`), filter, and card action.
- Four mandatory data-states on both the list and the feed: Skeleton loading, ErrorState with Retry, EmptyState ("No features yet — seed from your requirements doc"), loaded.
- Responsive: table collapses to stacked cards <768px; checkbox strip stays one row (horizontal scroll if needed).

### 9. E2E Checklist (Playwright)

1. Admin sees list; checkbox click persists across reload (auto-save).
2. Plain admin: Verified checkbox disabled; direct API verify attempt → 403 + event logged.
3. Super_admin verifies → verified_by/at stamped, progress bars update.
4. pending_action without note → 400, UI blocks with inline error.
5. Regression: CI token drops verified → pending_action with note; red badge shows.
6. Recommendation accept → feature row created + linked; dismiss requires reason.
7. Concurrent edit → 409 path shows refresh toast, no silent overwrite.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Admin / project management / dev process |
| Backend | Any REST (PHP / FastAPI / Express reference) |
| Frontend | React + host admin's primitives |
| Data shape | `features`, `feature_events` (append-only), `recommendations` |
| Key concepts | Sequential stage pipeline, owner-only Verified (server-enforced), auto-save optimistic checkboxes, regression backward-transition for CI only, recommendations→feature conversion, optional features.yaml sync |
| Composes with | `admin-portal-system` (chassis §8/§12); any CI traceability gate |
| States | loading / empty / error / loaded on list + feed |
| Origin | Sabanha Dev Tracker spec (generalized) |
| Reference implementation | `fabiodemelo/sabanha` — API: `api/routes/devtracker.php` (PHP 8 + MySQL, single `saba_db`); UI: `frontend/src/admin/DevTracker.tsx` (React + Tailwind v4). Browser-verified: auto-save persistence, owner-only verify 403 + logged rejection, regression path. |
