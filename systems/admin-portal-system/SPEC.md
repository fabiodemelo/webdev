# System: Admin Portal System

Complete SaaS administration portal: dark grouped sidebar, role-gated shell (admin / super-admin), KPI dashboard, full users CRUD, a generic module-CRUD engine for any record type, and a polished design system with mandatory loading/empty/error states. Product-neutral — the domain features plug in as **blocks** (see §12) from this repo's other systems; the portal is the chassis they mount onto.

**Type:** large full feature subsystem (design system + admin shell + auth/roles + dashboard + users CRUD + generic module engine + block mounting points).

**Reference stack:** Express (TypeScript, async handlers, zod) + JSON-file or document store + React 18 (function components + hooks) + Tailwind CSS v4 + react-router v7 + lucide-react + framer-motion. Map onto project equivalents if different (FastAPI/Mongo, Next.js, etc.).

> **Origin:** extracted from the Dataly admin portal. Product-specific features (upload review, credit ledger, data search) are deliberately excluded — they illustrate the *module pattern* (§8) but are not part of this spec.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap the brand name, accent color, and module list.

---

You are given a task to build a **complete admin portal** in the codebase.

Reference stack (map onto project equivalents if different):
- **Frontend:** React function components + hooks, react-router, Tailwind utility classes driven by design tokens, lucide-react icons ONLY (never emojis), framer-motion for the few signature animations, toast notifications.
- **Backend:** Express + TypeScript, async handlers (install `express-async-errors` — Express 4 silently crashes the process on rejected async handlers otherwise), zod on every body/param, bearer-token sessions.
- **Database:** any. Reference uses JSON-file stores with atomic queued writes; swap for SQL/Mongo freely. The contracts below are storage-agnostic.

### 1. Design System — "Dark Command" tokens

One token system, two surfaces: a **dark chrome** (sidebar, badges on dark) and a **light workspace** (content area). Define as CSS variables / Tailwind theme tokens:

| Token | Reference value | Usage |
|-------|-----------------|-------|
| `ink-950 … ink-600` | `#060a14 → #2a3650` | Sidebar, dark chrome, avatar chips |
| `accent-300 … accent-700` | brand accent scale (Dataly: emerald `#10b981`) | Active nav bar, primary buttons, highlights. **Parameterize per product.** |
| `surface-0/1/2` | `#ffffff / #f8fafc / #f1f5f9` | Content background layers |
| `line` / `line-strong` | `#e2e8f0 / #cbd5e1` | Borders (always visible in light mode) |
| `body / muted / faint` | `#0f172a / #475569 / #64748b` | Text hierarchy — never lighter than `muted` for body copy (AA contrast) |
| `signal-{red,amber,blue,violet}` | semantic | Status, alerts; violet is reserved for the super-admin badge |
| `font-display` | Space Grotesk | Page titles, card titles, wordmark |
| `font-sans` | Inter | Body, UI |
| `font-mono` | JetBrains Mono + `tabular-nums` | **Every** id, email, money amount, count, date-time — the single biggest "feels professional" lever |
| `shadow-card / shadow-pop` | subtle / strong | Cards / modals & sticky bars |

Global CSS: visible `:focus-visible` ring in the accent color, `prefers-reduced-motion` kill-switch for all animation, thin scrollbars on the sidebar.

**Signature elements (use all of them):**
1. Active nav item = `bg-white/10` + 2px accent bar on the left edge.
2. KPI stat tiles: uppercase 11px tracking-wide label, 26px mono value, optional delta line, icon in a tinted accent square.
3. Status pills via a single `statusTone(status)` helper (§5) — never ad-hoc colors.
4. Super-admin role badge in violet; plain admin in accent.
5. Section labels in the sidebar: 11px bold uppercase `text-white/40`.

### 2. UI Primitives Kit

Build once in `components/ui/`, import everywhere. No external component library.

| Primitive | Contract |
|-----------|----------|
| `Button` | variants `primary` (accent), `secondary` (bordered), `ghost`, `danger`, `inverse` (dark), `blue` (#2563EB — billing surfaces); sizes sm/md/lg; `loading` prop renders a spinner and disables; always `cursor-pointer`, 200ms color transitions |
| `Badge` | pill, uppercase 11.5px bold; tones neutral/success/warning/danger/info/violet/accent |
| `statusTone(status)` | string → tone map: approved/active/verified/paid/completed → success; pending/processing/draft/queued/open → warning; rejected/banned/suspended/failed/expired → danger; else neutral |
| `Card` / `CardHeader` | rounded-xl, `border-line`, `shadow-card`, p-6; header = display-font title + muted description + optional action slot |
| `Field` + `Input/Select/Textarea` | label above, 13px semibold; error line in red with `role="alert"`; hint line in faint; h-11 inputs, accent focus border |
| `Stat` | the KPI tile (§1.2) |
| `Table` kit | `Table/THead/Th/TBody/Tr/Td` — bordered rounded container with horizontal scroll, uppercase 11.5px header row, row hover only when clickable |
| `Modal` | portal, ink overlay + blur, Escape closes, body scroll locked, max-h scroll area, footer slot |
| `ConfirmDialog` | Modal preset: message + Cancel + danger/primary confirm with loading |
| `ToastProvider/useToast` | bottom-right stack, dark cards, success/error/info icons, ~4s auto-dismiss |
| `Spinner / Skeleton / EmptyState / ErrorState` | the four mandatory data-states (§10) |

### 3. Admin Shell (`AdminLayout`)

Fixed **264px sidebar**, `ink-900`, full height:
- **Brand block** top: accent icon chip + display-font wordmark + "Admin" in accent, links to `/admin`.
- **Grouped nav**: groups with uppercase section labels. Reference grouping (rename per product): `COMMAND` (Dashboard, trackers, reports), `CUSTOMERS` (Users, + installed billing blocks), `DATA` (product-specific modules), `PLATFORM` (Settings, API keys, Permissions, Compliance, + installed platform blocks, Audit logs). Each item: lucide icon + label, NavLink active state per §1.1.
- **Footer**: avatar initials chip, full name, role badge (violet for super_admin), Sign out button.
- **Mobile (<lg)**: sidebar becomes an off-canvas drawer (slide + overlay) behind a hamburger in a top bar.

Main area: light `surface-1`; sticky top bar with path-derived breadcrumb, "View site" external link, current-admin chip; content `px-8 py-8 max-w-[1400px]`.

**Guard behavior** (in the layout, not per page): no admin token → redirect `/admin/login`; validate session against `GET /admin/me` on mount; 401/403 → clear session + redirect; other errors → retryable ErrorState; spinner while validating. Pass the admin user to pages via router outlet context (`useAdminUser()` hook) so pages never re-fetch it.

### 4. Auth & Roles

Two-door login: a shared `/login` that routes by role after authentication (admin roles → `/admin`, everyone else → the app), and a direct `/admin/login` staff door that **rejects** non-admin accounts with an explicit error and stores nothing.

- Roles: at minimum `user`, `admin`, `super_admin`. Sessions: bearer token per scope (user/admin) in sessionStorage; admin-role logins store both.
- Backend guards in one module: `requireUser` (401 if no valid session), `requireAdmin` (401 unauthenticated, **403** authenticated-but-not-admin), `requireSuperAdmin` (layered after requireAdmin).
- Central 401 handling in the API client: clear the scope's session and hard-redirect to the matching login. No dead-end error screens with useless Retry buttons.
- Sign-out clears **both** scopes (an admin's leftover user token must not silently re-authenticate them, and vice versa).
- **Last-super-admin guard**: the API refuses to demote or suspend the final active super_admin (and refuses self-deletion) — an org must never lock itself out of admin management.
- UI mirrors server permissions: buttons a role cannot use are disabled with a title tooltip, not hidden errors after a filled form.

### 5. Backend Conventions

- Response envelope everywhere: `{ data }` on success, `{ error: { message } }` on failure. Status codes: 200/201, 400 validation, 401 unauthenticated, 403 forbidden, 404 missing, 409 conflict where applicable.
- zod-parse every body and params; `express-async-errors` so thrown ZodErrors become 400s instead of process crashes.
- Storage layer (if file-based): atomic writes (temp file + rename), per-file write queue (no lost updates), seed defaults **only on ENOENT** — a corrupt store must throw, never be silently replaced (that wipes real data).
- Every list endpoint supports the filters its UI exposes (search/role/status as query params).

### 6. Dashboard Page

- KPI Stat grid (4–6 tiles) from a `GET /admin/overview` endpoint.
- Two-column cards: operational queues (counts + links), compliance/SLA list, recent audit-events timeline (actor, action, entity, mono timestamp).
- Optional but recommended: a **build-status board** — features × stage chips (pending/coded/tested/verified) toggled with optimistic PATCH + revert-on-error, "X of Y verified" progress bar, super-admin reset. Brilliant during development; remove or repurpose at launch.

### 7. Users Management Page

Full CRUD against `/admin/users`:
- Toolbar: debounced search + role filter + status filter (all server-side query params).
- Table: avatar initials, name, mono email, company, role badge (violet super_admin / accent admin / neutral), status pill, created date, edit/delete actions.
- Create/Edit modal: email, password ("leave blank to keep" on edit), full name, company, role select, status select; client validation mirrors zod.
- Role-assignment restriction: only super_admins may grant admin/super_admin (UI disables the options; server enforces).
- Delete: ConfirmDialog (danger) noting session revocation; self-delete and last-super-admin cases blocked server-side (§4).
- **Stale-response guard**: a sequence counter (or AbortController) on the list fetch so a slow response for an old query never overwrites fresh results.

### 8. Generic Module Engine (the workhorse)

One route `/admin/m/:moduleKey` + one page component renders a polished CRUD surface for ANY record type, driven by a config map — this is how the portal ships "complete" before any block is installed:

```ts
const MODULES: Record<string, {
  title: string; description: string;   // write real, opinionated descriptions
  icon: LucideIcon; recordNoun: string;
  statusOptions?: string[];
  superAdminOnly?: boolean;             // settings, permissions, pricing config
  appendOnly?: boolean;                 // audit logs, ledgers — no human edits
}> = { /* every sidebar module key */ };
```

Record shape: `{ id, title, status, owner, detail, metadata: Record<string, string|number|boolean>, createdAt, updatedAt }` behind generic endpoints `GET/POST /admin/modules/:key/records`, `PATCH/DELETE /admin/modules/:key/records/:id`.

Page: header (icon, title, description, count badge, Create), search + status filter (client-side), table with metadata shown as `key: value` chips (max 3 + "+N"), create/edit modal with a **metadata key-value row editor** (add/remove rows), delete ConfirmDialog.

**Enforcement is server-side, not just UI**: a `guardModuleMutation` middleware rejects mutations on `appendOnly` modules entirely and requires super_admin for `superAdminOnly` keys. The UI shows an amber read-only banner / hides actions to match — but the API is the wall.

When a block (§12) is installed, its dedicated page **replaces** the generic module page: repoint the sidebar link, keep the generic engine for everything else.

### 9. Visual & Interaction Rules (non-negotiable)

- lucide-react icons only; consistent 16–20px sizing; never emojis.
- `cursor-pointer` on every interactive element; hover feedback by color/bg shift (150–300ms `transition-colors`), never layout-shifting scale.
- `data-testid` on every interactive element and key region — E2E suites depend on it.
- Mono font + tabular numerals for ids/emails/amounts/counts/timestamps.
- Tables get `min-w` + horizontal scroll on mobile; the sidebar goes off-canvas; test at 375/768/1024/1440.
- Destructive or money-affecting actions always confirm, with copy that states the consequence ("Removes the account and revokes sessions").

### 10. The Four Data-States (mandatory on every fetch)

1. **Loading** — Skeleton blocks matching final layout (no spinner-only pages).
2. **Error** — ErrorState with the message + a working Retry.
3. **Empty** — EmptyState with icon, friendly title, one-line guidance, and a CTA when there's an obvious next action.
4. **Loaded** — the content.

A page missing any of these is not done.

### 11. Settings Surfaces Pattern

For configuration pages (provider keys, policies — anything an admin edits repeatedly):
- **Connection-health tiles** at top: environment (with pulse dot), credentials saved?, connection verified? (with an inline one-click Test action), integration receiving? — at-a-glance state before any scrolling.
- **Setup checklist** strip (numbered steps → green checks) that disappears once complete.
- Secrets: password inputs, masked placeholders showing last 4 (`sk_live_••••1234`), blank/masked submit = keep current, never echoed back in plaintext, show/hide eye toggles.
- Mode toggles (test/live) with prefix-vs-mode validation server-side that refuses mismatched keys.
- **Sticky save bar** (bottom, blurred card) so the primary action stays in reach.

### 12. Pluggable Feature Blocks — ASK THE USER

This portal is the chassis. Domain features install as blocks from `https://github.com/fabiodemelo/webdev` (`systems/<block>/SPEC.md`).

**Agent instructions — do this during integration, before building any feature pages:**

1. **List the live catalog** — fetch the current contents of the repo's `systems/` directory (e.g. `gh api repos/fabiodemelo/webdev/contents/systems --jq '.[].name'`). Do NOT rely on a hardcoded list; new blocks are added over time. Known at time of writing:
   - `email-template-system` — admin-editable transactional emails (frame + [tag] templates + send log)
   - `coupon-code-system` — discount codes with usage limits and analytics
   - `paid-subscription-system` — Stripe plans, checkout, webhooks, dunning, billing admin
   - `cancellation-retention-module` — notice-period cancellation + one-time retention offers
   - `support-ticket-system` — user/admin ticket threads
   - `personal-task-system` — tasks
   - `training-video-library` — curated onboarding videos
2. **Ask the user which blocks to install** with this admin (multi-select). Present each with its one-line description from its SPEC.md header. Also ask for the brand accent color and product name if not yet known.
3. For each selected block: read its full SPEC.md, implement it **on this portal's conventions** (tokens §1, primitives §2, guards §4, envelope §5, data-states §10), build its dedicated admin page, and repoint the corresponding sidebar link from the generic module page (§8) to it.
4. Blocks not selected keep their generic module page (or are omitted from the sidebar entirely — ask).
5. Note cross-block wiring where specs reference each other (e.g. paid-subscription + cancellation-retention share the settings singleton; email-template-system is the send layer for the others' notifications).

### 13. Routing Map (reference)

```
/login                    shared, role-routed
/admin/login              staff door, rejects non-admins
/admin                    dashboard
/admin/users              users CRUD
/admin/m/:moduleKey       generic module engine
/admin/<block-pages>      dedicated pages added per installed block
```

### Reproduction Checklist

1. Define tokens (§1) with the product's accent scale; load Space Grotesk / Inter / JetBrains Mono.
2. Build the UI primitives kit (§2) — everything else composes from it.
3. Backend: guards module (§4), response envelope + zod + async-error safety (§5), users store with last-super-admin protection.
4. Build `AdminLayout` (§3) with guard, grouped sidebar, off-canvas mobile, outlet context.
5. Auth pages: shared role-routed login + admin staff door.
6. Dashboard (§6) with overview endpoint.
7. Users CRUD (§7) with role-gated assignment + stale-response guard.
8. Generic module engine (§8) with server-side `guardModuleMutation`.
9. Sweep every page for the four data-states (§10) + interaction rules (§9).
10. **Fetch the block catalog from fabiodemelo/webdev and ask the user which blocks to install (§12); integrate each per its SPEC.md.**
11. E2E (Playwright): admin login, users CRUD, module CRUD, append-only gating, anonymous-redirect guard.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Admin / platform chassis |
| Backend | Express + TS (reference) — any equivalent |
| Frontend | React + Tailwind v4 + react-router + lucide |
| Key concepts | Dark grouped sidebar, role-gated shell, statusTone pills, generic module engine with server-side gating, four mandatory data-states, connection-health settings pattern, pluggable blocks with user prompt |
| Composes with | All other systems in this repo (§12) |
| Origin | Dataly admin portal (product features excluded) |
