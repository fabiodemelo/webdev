# System: Support Ticket System

Full internal helpdesk / support ticket system. Authenticated users open tickets, exchange threaded **comments**, attach **multiple files**, and admins triage with **assignees, members/watchers, priorities, departments, configurable statuses, time tracking, an activity log, and stats**. Status changes, assignment, and updates fire **email + in-app + push notifications**. Tickets can be **private**, carry **due dates** with overdue reminders, and are scoped by **role-based visibility levels**. The admin lands on a **dashboard** with per-status stat cards, **saved-per-user filters** (status, priority, and a Mine / My group / All / Private scope), and a **ticket efficiency meter** that scores how long closed tickets stayed assigned to each person.

**Type:** full feature subsystem (tickets + 6 child entities + configurable settings + dual API + admin/mobile UI + notifications).

**Reference implementations:**
- **A — Alta Apps (richer, this build):** PHP + MySQL + React admin UI (`tickets-ui`) + JWT mobile API + S3-style object storage + push (Expo/FCM). The feature-complete reference.
- **B — Trados (simpler origin):** FastAPI + MongoDB + React. Minimal 3-status single-thread variant (see history) — a subset of this spec.

> ## ⚠️ Integration target: demelos.com admin portal
> This ticket system is **not a standalone app** — it must be **fully integrated into the demelos.com admin portal** (the [admin-portal-system](../admin-portal-system/SPEC.md) chassis: Express + TypeScript + MySQL + React), sharing that portal's auth/roles, layout/sidebar, design tokens, notification stack, and `uploads/` storage.
>
> The demelos admin already ships a native ticket module to build on/reconcile with:
> - `backend/src/routes/tickets.ts` — Express/TS routes
> - `frontend/src/pages/Tickets.tsx` + `tickets.css` — React admin UI
> - `sql/02-tickets-v2.sql` — MySQL schema
> - `uploads/tickets/<ticket_number>/` — attachment storage on disk
>
> When porting the Alta (PHP) feature set below, **fold it into that Express/TS module** — do not add a parallel PHP service. Match the portal's `requireAdmin()` gating, envelope helpers, and settings singleton. Reference DDL in [reference/schema.sql](reference/schema.sql) is the target-shape schema; align `02-tickets-v2.sql` to it.

> **Related:** notifications reuse [email-template-system](../email-template-system/SPEC.md) (`new_ticket`, `update_ticket`, `private_ticket`, `support_reply` template keys + per-recipient `notifyOwner`).

> **Stack-neutral:** field names below follow the MySQL reference. Substitute "user" / "tenant" / "company" for the target's noun. Map MySQL child tables onto embedded sub-documents if using a document store.

---

## Visual Reference

Built-result screenshots from the source app — visual guideline for re-implementation.

| View | Preview |
|------|---------|
| Admin inbox — search, status filter, ticket table (§11.2) | ![Admin inbox](preview/admin-inbox.png) |
| Admin thread — bubbles, admin styling, attachment chip, reply box, Close (§11.4) | ![Admin thread](preview/admin-thread.png) |

---

## Integration Prompt

> Paste everything below this line into the target project. Swap "user/tenant" for the right noun. Single-tenant apps can flatten the visibility levels to admin-vs-owner.

---

You are given a task to build a **support ticket / helpdesk system** in the codebase.

Reference stack (map onto project equivalents):
- **Backend:** PHP + MySQL (reference A) or FastAPI + document store (reference B). A data-access class exposes the public interface in §9.
- **Frontend:** React admin UI + (optional) a mobile/JWT client. Shared API client, toast notifications.
- **File storage:** S3-compatible object storage; DB stores attachment metadata + `object_key`, presigned URLs generated on read.
- **Notifications:** existing email template system + in-app notifications + push tokens.

### 1. Overview / Roles

- **End user / requester** → creates tickets, sees tickets they created / are assigned to / are a member of. Comments, uploads, deletes own comments. Cannot reassign or close (unless permitted).
- **Assignee** → a user the ticket is assigned to (many per ticket; first = primary). Gets push on assignment.
- **Member / watcher** → a collaborator added to a ticket without being the assignee. Gets push on add + on updates.
- **Admin (level ≤ 1 = super admin)** → sees ALL tickets, triages, reassigns, changes status, logs time, manages settings.

Each ticket has a sequential **ticket number**, a single chronological **comment** thread, **multiple attachments**, an **activity log**, optional **time entries**, a **priority**, a **department**, a **configurable status**, an optional **due date**, and an `is_private` flag.

### 2. Architecture

| Component | Responsibility |
|-----------|----------------|
| Ticket store | `tickets` table — core fields + audit. Sequential `ticket_number`. |
| Child stores | `ticket_assignees`, `ticket_members`, `ticket_comments`, `ticket_time_entries`, `ticket_activity_log`, `ticket_attachments`. Loaded in one batched pass per list (avoid N+1). |
| Settings store | `ticket_settings` — admin-editable statuses, priorities, departments with `label`, `color`, `sort_order`. Seeded with defaults if empty. |
| Object storage | S3-style. Attachment bytes live there; DB holds metadata + `object_key`. Presigned URL on read (`attachment_url`). |
| Filter translator | MongoDB-style filter syntax (`$in`/`$nin`/`$or`/`$and`/`$text`) → SQL WHERE + JOINs. Lets one query layer serve both reference stacks. |
| Notification hooks | Email templates + in-app notifications + push (`push_send_to_users`). Fire on create / assign / member-add / update / status-change. |
| Preference store | `ticket_user_prefs` — one JSON row per user holding their dashboard filters, scope and layout. Written on every change (debounced), read on load, so a user's view follows them to any browser. |
| Efficiency scorer | Replays each closed ticket's `reassigned` activity rows to credit elapsed time to whoever was assigned during each segment, then bands the per-user average. |
| Dual API | Admin API (session auth) + user/mobile API (JWT auth), mirrored action logic. |

### 3. Domain Model

**3.1 Ticket** — `tickets`:

| Field | Description |
|-------|-------------|
| `id` / `_id` | Identifier. |
| `ticket_number` | Sequential int, zero-padded for display (`#00042`). |
| `title` | Required. |
| `description` | Body. |
| `department` | From `ticket_settings` (default `General`). |
| `priority` | `low` \| `medium` \| `high` (configurable; default `medium`). |
| `status` | Configurable; defaults `open` \| `in_progress` \| `in_review` \| `closed`. |
| `is_private` | Bool. Restricts visibility + flips notifications to the `private_ticket` template. |
| `due_date` | Optional datetime. Drives overdue reminders. |
| `created_by` / `created_by_name` | Requester. |
| `created_at` / `updated_at` | Audit. `updated_at` bumped on every child write. |
| `closed_at` / `closed_by` / `closed_by_name` | Stamped when status → `closed`. |
| `reminder_sent` / `overdue_sent` | Flags so due-date reminder + overdue cron fire once each. |

**3.2 Assignees / Members** — `ticket_assignees`, `ticket_members` (both `ticket_id, user_id, user_name`). Many-to-many. Primary assignee = first. Members = watchers/collaborators.

**3.3 Comment** — `ticket_comments`: `id, ticket_id, author_id, author_name, content, created_at`. Plain text. The conversation thread.

**3.4 Time entry** — `ticket_time_entries`: `id, ticket_id, logged_by, logged_by_name, duration_minutes, description, logged_at`. Time tracking per ticket.

**3.5 Activity log** — `ticket_activity_log`: `id, ticket_id, actor_id, actor_name, action, old_value, new_value, created_at`. Actions: `created`, `status_changed`, `reassigned`, `members_changed`, `commented`, `time_logged`, `attachment_added`, `attachment_deleted`.

**3.6 Attachment** — `ticket_attachments`: `id, ticket_id, name, object_key, size, content_type, uploaded_by, uploaded_by_id, uploaded_at`. **Multiple per ticket.** Bytes in object storage; read via presigned URL.

**3.7 Setting** — `ticket_settings`: `id, type, value, label, color, sort_order`. `type` ∈ `status | priority | department`. Admin-editable; unique on `(type, value)`. Seeded defaults:
- **status:** open (`#3b82f6`), in_progress (`#f59e0b`), in_review (`#8b5cf6`), closed (`#10b981`)
- **priority:** low (`#059669`), medium (`#d97706`), high (`#dc2626`)
- **department:** General, IT, Sales, Operations, Finance

**3.8 User preference** — `ticket_user_prefs`: `user_id` (PK), `prefs` JSON, `created_at`, `updated_at`. One row per user. Holds `filterStatus[]`, `filterPriority[]`, `filterScope`, `sortBy`, `columns`, `showTeam`. See §13.

### 4. Ticket Lifecycle

Statuses are **configurable** (not hardcoded), but the default flow:
```
(create) → open ──admin works──► in_progress ──► in_review ──► closed
                                     ▲                            │
                                     └──────── reopen (set status) ┘
```
- Status change writes a `status_changed` activity row (old → new).
- Setting status `closed` stamps `closed_at`, `closed_by`, `closed_by_name`.
- No auto-reopen; status is explicit. Comments are always accepted regardless of status.

### 5. Access Model & Visibility Levels

Role carries a numeric `level`. Lower = more powerful.
```php
function visibility_filter(int $level, int $uid): array {
    if ($level <= 1) return [];          // super admin → no filter, sees all
    return ['$or' => [
        ['created_by'   => $uid],
        ['assignee_ids' => $uid],
        ['member_ids'   => $uid],
    ]];
}
```
- **Super admin (`level <= 1`)** → sees/acts on every ticket.
- **Everyone else** → sees only tickets they created, are assigned to, or are a member of. Enforced on list (filter) AND on single-read (`user_can_see_ticket`) → 403/404 otherwise.
- **Private tickets** (`is_private`) further restrict + switch update notifications to the `private_ticket` template.

**Filter syntax** (translated to SQL or Mongo): scalar equality, `$in`, `$nin`, `$or`, `$and`, `$text` (LIKE on title+description), plus `assignee_ids` / `member_ids`. Sort by `created_at` / `updated_at` / `ticket_number` / `due_date`. Pagination via `offset` + `limit`.

> ⚠️ **`assignee_ids` / `member_ids` must compile to `EXISTS`, never a `JOIN`.** Both bugs below were shipped and fixed in the reference build:
> - The filter nests (a scope clause AND the visibility clause both reference assignees), so a `JOIN` emits the same alias twice — `Not unique table/alias: 'ta'`, a hard 500 for every non-admin.
> - An inner `JOIN` inside an `$or` also drops tickets that have **no** assignee row at all, even when another branch of the `$or` (e.g. `created_by`) matches — so scoped users silently see *fewer* tickets than they are entitled to.
>
> ```sql
> EXISTS (SELECT 1 FROM ticket_assignees x WHERE x.ticket_id = t.id AND x.user_id = ?)
> ```
> An empty `$in` list must compile to a false constant, not to an empty `IN ()`.

### 6. Attachment Pipeline

1. Client POSTs `multipart/form-data` (`upload` action) with the file.
2. Validate type/size (extension allow-list + size cap).
3. Generate `object_key` (namespaced by ticket), upload bytes to object storage.
4. Insert `ticket_attachments` metadata row; write `attachment_added` activity; bump `updated_at`.
5. Read: `get` / `get_by_number` enrich each attachment with a short-lived **presigned URL** and an `is_image` flag, so the thread renders thumbnails without a second round trip. `attachment_url` returns a URL for a single key as a fallback.
6. **Gate attachment reads on the ticket's own visibility, not on a file-module permission.** Staff who legitimately see a ticket often have no rights in the general file browser; gating on the latter 403s them out of their own attachments.
7. Delete: `delete_attachment` removes the row + writes `attachment_deleted` activity.

Multiple attachments per ticket. Image types render inline as thumbnails; others as file chips.

### 7. Comments, Time Tracking, Activity

- **Comments** — add (`comment`) / list / delete. A non-author may delete only if super admin (`level <= 1`). Adding a comment writes a `commented` activity row + bumps `updated_at`.
- **Time entries** — `time_entry` action logs `duration_minutes` + description against the ticket; writes `time_logged` activity. Enables per-ticket effort reporting.
- **Activity log** — append-only, surfaced in the thread as a system timeline (who changed status, reassigned, added members, logged time, attached/removed files).

### 8. Notifications

Three channels fire on mutations:
- **Email** (via [email-template-system](../email-template-system/SPEC.md)) — `new_ticket` on create, `update_ticket` on update, `private_ticket` for private tickets, plus `notifyOwner` to the requester. Template vars: `ticket_number, title, description, status, priority, department, due_date, created_by_name, assignee_names, member_names, is_private, url`.
- **In-app notifications** — written to the notifications collection/table for the bell feed.
- **Push** (`push_send_to_users`) — events: `ticket_assigned` (→ new assignees), `ticket_member_added` (→ new members), `ticket_updated` (→ watchers on content change). **Never push the creator/actor about their own action.**

All notification sends are best-effort: a delivery failure must NOT roll back the ticket write.

**Due-date reminders:** a cron scans tickets with a `due_date`; sends a reminder once (`reminder_sent`) before due and an overdue notice once (`overdue_sent`) after.

### 9. Data-Access Interface

A single class (`MySQLTickets` / `MongoTickets`) exposes a stack-agnostic interface — swap the storage engine without touching callers:
```
createTicket(doc) -> id
getTicket(id) -> ticket | null            # eager-loads all 6 child collections
updateTicket(id, fields, actorId) -> bool # diffs assignees/members, writes activity
deleteTicket(id) -> bool                  # cascades all child rows
listTickets(filter, sort, limit) -> []    # batched child load, no N+1
countTickets(filter) -> int
addComment(id, {author_id,author_name,content})
addTimeEntry(id, {logged_by,logged_by_name,duration_minutes,description})
addActivity(id, {actor_id,actor_name,action,old_value,new_value})
addAttachment(id, {name,object_key,size,content_type,uploaded_by,uploaded_by_id})
removeAttachment(id, object_key)
searchTickets(q, extraFilter) -> []       # $text on title+description
getStats(filter) -> { status: count }
getSettings(type) -> []
saveSetting(doc) -> id                     # upsert on (type,value)
deleteSetting(id)
```

### 10. API Surface (dual: admin session + user/mobile JWT, mirrored)

**Admin API** (session auth) — `GET ?action=`:

| Action | Behavior |
|--------|----------|
| `list` | Tickets in caller's scope, with filters + sort + pagination. |
| `get` | One ticket + all children. |
| `get_by_number` | Lookup by `ticket_number`. |
| `attachment_url` | Presigned URL for an attachment `object_key`. |
| `stats` | `{status: count}` overall + "mine" (assigned to caller). |
| `search` | `$text` over title + description. |
| `employees` | Assignable users list (for assignee/member pickers). |
| `settings` | Get settings by `type` (status/priority/department). |
| `prefs` | The caller's saved dashboard preferences (`{}` on first use). |
| `efficiency` | Per-user closed-ticket efficiency (§14). Team-wide — deliberately ignores the caller's filters. |

`list` and `search` both accept `status`, `priority` (CSV) and `scope` (§13.2). Applying scope must never widen access — the visibility filter (§5) still runs on top of whatever scope returns.

`POST ?action=`: `create`, `update` (diffs status/assignees/members → activity + notifications), `comment`, `time_entry`, `upload`, `delete_attachment`, `save_prefs`.

**User / mobile API** (JWT auth, `require_auth`) — mirrors admin logic, scoped by visibility level:

| Endpoint | Behavior |
|----------|----------|
| `GET /tickets` | List visible tickets — filters (`status`, `priority`, `assigned_to`, `mine`), pagination, total. |
| `GET /tickets/{id}` | One ticket + children (visibility-checked). |
| `GET /tickets/stats` | `{status: count}` for caller + "mine". |
| `GET /tickets/{id}/comments` | List comments. |
| `POST /tickets/{id}/comments` | Add comment (`body`), writes `commented` activity. |
| `DELETE /tickets/comments/{commentId}` | Delete a comment (author or super admin only). |
| `POST /tickets` | Create (assignees/members from `assignees[]`/`members[]` or single `assigned_to`). |
| `POST /tickets/{id}` (update) | Mirrors admin update where the role permits. |

### 11. Admin UI

**11.1 Dashboard** (landing view) — per-status stat cards showing the org count plus the caller's own ("My Desk") count; clicking a card jumps to the list filtered to that status. Search box. The filter bar (§13). Then "My Assigned Tickets", and a second list whose title follows the scope ("Recent Team Tickets" / "Tickets I'm Part Of" / "My Private Tickets"). Under scope *Mine* the second list is hidden — it would only repeat the first. A gear opens Dashboard Settings: order by, 1–3 columns, show/hide the second list. Bottom: the efficiency meter (§14).

**11.2 Inbox / all tickets** — debounced search (subject/user/content), the same filter bar, ticket table (number, subject, requester, status pill, priority, department, assignees, last activity). Row → thread. Switching between dashboard and inbox must **not** reset the filters.

**11.3 Settings page** (`ticket_settings.php`) — three cards (Statuses, Priorities, Departments). Each row: value, colored label badge, color picker, sort order, Edit/Delete. Add form upserts a setting. Drives every status/priority/department pill app-wide.

**11.4 Thread view** — header: title + `#number` + status pill + priority + department + Close/assign controls. Chronological comments + interleaved activity-log timeline. Multiple attachment chips/thumbnails (presigned URLs). Assignee + member pickers (from `employees`). Time-entry logger. Reply box + multi-file upload. Status dropdown (from settings). Private toggle.

**11.5 Mobile/user client** — list with filters + status pills, ticket detail with comments + attachments, create form, push-notification deep links (`ticket_id`).

### 12. Reference Schema (MySQL DDL)

Exact build-ready schema — full file: [reference/schema.sql](reference/schema.sql) (InnoDB, utf8mb4; includes the seed `INSERT` for default statuses/priorities/departments). Core tables:

```sql
CREATE TABLE `tickets` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number`   INT UNSIGNED NOT NULL,
  `title`           VARCHAR(500) NOT NULL,
  `description`     TEXT,
  `department`      VARCHAR(100) DEFAULT 'General',
  `priority`        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `status`          VARCHAR(50)  NOT NULL DEFAULT 'open',  -- value from ticket_settings
  `is_private`      TINYINT(1)   NOT NULL DEFAULT 0,
  `due_date`        VARCHAR(20),                           -- 'YYYY-MM-DD'
  `created_by`      INT NOT NULL DEFAULT 0,
  `created_by_name` VARCHAR(255),
  `closed_at`       DATETIME, `closed_by` INT, `closed_by_name` VARCHAR(255),
  `created_at`      DATETIME NOT NULL DEFAULT current_timestamp(),
  `updated_at`      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reminder_sent`   TINYINT(1) NOT NULL DEFAULT 0,         -- due-date cron, one-shot
  `overdue_sent`    TINYINT(1) NOT NULL DEFAULT 0,         -- overdue cron, one-shot
  PRIMARY KEY (`id`), UNIQUE KEY `uq_ticket_number` (`ticket_number`),
  KEY (`status`), KEY (`priority`), KEY (`department`), KEY (`created_by`), KEY (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Child tables (full DDL in the .sql file):
- `ticket_assignees` / `ticket_members` — `(ticket_id, user_id, user_name)`, unique `(ticket_id,user_id)`, indexed both ways.
- `ticket_comments` — `(ticket_id, author_id, author_name, content TEXT, created_at)`.
- `ticket_time_entries` — `(ticket_id, logged_by, logged_by_name, duration_minutes, description, logged_at)`.
- `ticket_activity_log` — `(ticket_id, actor_id, actor_name, action, old_value, new_value, created_at)`.
- `ticket_attachments` — `(ticket_id, name, object_key VARCHAR(1000), size, content_type, uploaded_by, uploaded_by_id, uploaded_at)`.
- `ticket_settings` — `(type, value, label, color, sort_order)`, unique `(type,value)`, indexed `type`.
- `ticket_user_prefs` — `(user_id PK, prefs JSON, created_at, updated_at)`. Per-user dashboard filters/scope/layout (§13.3).

> Document-store equivalent: collapse the child tables into embedded arrays on the ticket document (`comments[]`, `time_entries[]`, `activity_log[]`, `attachments[]`, `assignee_ids[]`, `member_ids[]`); keep `ticket_settings` a small collection. The data-access interface (§9) is identical either way.

### 13. Filters, Scope & Saved Preferences

**13.1 Filter bar.** One shared component, rendered identically on the dashboard and the inbox, so a selection means the same thing in both places:

| Group | Behaviour |
|-------|-----------|
| Status | Multi-select chips, one per configured status (Open / In Progress / In Review / Closed). |
| Priority | Multi-select chips (high / medium / low), tinted with the priority colour when active. |
| Scope | **Single** choice — visually separated (divider + a distinct active colour) so it doesn't read as another toggle. |
| Clear | Appears only when something is set; resets status, priority and scope. |

**13.2 Scope.** Sent as `scope=` on `list` and `search`:

| Scope | Meaning | Filter |
|-------|---------|--------|
| `mine` | Tickets assigned to me | `assignee_ids = uid` |
| `group` | Tickets I'm a member of | `member_ids = uid` |
| `all` (default) | All public tickets | `is_private = 0` |
| `private` | My private tickets | `is_private = 1` AND (`created_by` \| `assignee_ids` \| `member_ids` = uid) |

`private` **must** carry the ownership clause. Without it a super-admin (who has no visibility filter) would see everyone's private tickets under a scope labelled "my private tickets".

**13.3 Persistence.** Filters and layout auto-save — no save button:

- Debounce writes (~600ms) so a burst of chip clicks is one round trip.
- Persist **server-side**, keyed by user, so the view follows them to another browser or device. `localStorage` is only a first-paint cache to avoid a flash of unfiltered content.
- Do not save until the initial load has completed, or the defaults will overwrite what was stored.
- `save_prefs` **allow-lists every key and every value** (status values, priority values, scope, sort mode, column count 1–3, boolean) and silently drops anything else. The client never dictates what lands in the database.
- Migrate old shapes on read — the reference build carried a boolean `filterMine` before scope existed, and maps `true → mine` on load.

### 14. Efficiency Meter

Scores how long closed tickets stayed assigned to each person. The point is behavioural: it makes leaving your name on finished work visible, so the UI ships an explicit reminder — *"when you finish your part, remove your name from Assigned to; efficiency only counts the time a ticket is assigned to you."*

**14.1 Scoring.** Time is credited **per person, not per ticket**. A ticket's assignee set changes over its life, so each closed ticket is replayed as a series of segments:

1. Take closed tickets. Use `closed_at`, falling back to `updated_at` where the column was populated later (don't silently drop the older rows).
2. Read that ticket's `reassigned` activity rows (oldest first). Each stores the old and new assignee id lists.
3. The set in place before the first change is that row's `old_value`. With no history at all, the current assignees held the ticket for its whole life.
4. Walk creation → each event → close, crediting each segment's elapsed seconds to every user holding the ticket during it. Clamp event timestamps into the ticket's own lifetime.
5. Per user: average their credited seconds over the tickets they held. Users with none report "no data" rather than scoring zero.

**14.2 Bands.** Thresholds in **days of assigned time**, defined in one constant and returned to the client so the UI never hardcodes them:

| Band | Default | Colour | Icon |
|------|---------|--------|------|
| Great | ≤ 2d | green | zap |
| Good | ≤ 5d | blue | thumbs-up |
| Poor | ≤ 10d | amber | warning triangle |
| Bad | > 10d | red | flame |

**14.3 UI.** A card per user, ranked best → worst, each carrying its **placing in a circle** (1, 2, 3…) before the name — the top three wear a gold / silver / bronze medal disc (metallic gradient, white numeral - not emoji, so it renders identically everywhere), and the rest are outlined in their band colour. Users with no closed tickets show a muted dash instead of a number: no data is not last place. Then: name, band chip (colour + icon), a four-zone meter bar with a marker positioned by band (each band owns a fixed quarter of the bar, so someone far past the last threshold doesn't distort it), the average in days, and the ticket count the score rests on. The legend and the threshold scale are printed alongside so the number is self-explanatory. The meter is **team-wide** — it deliberately ignores the dashboard filters.

**14.4 Exclusions & honesty.** Service accounts are excluded by id (the reference build hides an "Apple" App Store review account). Accuracy is bounded by how far back the `reassigned` history goes: tickets closed before assignment history existed credit their whole lifetime to the current assignees, which inflates early averages. Say so in the UI or the rollout note rather than presenting the number as exact.

### 15. Security Rules

- Visibility enforced on BOTH list (filter) and single-read (`user_can_see_ticket`) — non-privileged users get 403/404 for tickets they don't own/assign/member.
- Super-admin gate is `level <= 1`; everyone else is scoped.
- `created_by` / `tenant` set from the authenticated session, never the request body.
- Comment delete restricted to author or super admin.
- Attachments: extension allow-list + size cap; bytes in object storage; reads via short-lived presigned URLs; `object_key` namespaced by ticket (no cross-ticket guessing); empty/oversized rejected.
- Comments + descriptions rendered as plain text — no HTML eval (no XSS via ticket content).
- All SQL parameter-escaped (`mysqli_real_escape_string`) / prepared statements on the raw paths.
- Notification failures never roll back the ticket write.
- Scope (§13.2) narrows only — the visibility filter still runs on top of it, and the `private` scope carries its own ownership clause so a super admin cannot read other people's private tickets through it.
- `save_prefs` allow-lists every key and value; the stored JSON is app-owned, never a passthrough of the client payload.
- `assignee_ids`/`member_ids` compile to `EXISTS`, not `JOIN` (§5) — a nested filter otherwise 500s on alias collision or silently under-reports.

### Reproduction Checklist

1. Create `tickets` + 6 child tables (`ticket_assignees`, `ticket_members`, `ticket_comments`, `ticket_time_entries`, `ticket_activity_log`, `ticket_attachments`) + `ticket_settings`. Index `ticket_id` on every child; index `(status)`, `(ticket_number)` on tickets.
2. Seed `ticket_settings` defaults (statuses, priorities, departments) if empty.
3. Implement the data-access class (§9) with batched child loading (no N+1) and the filter translator (`$in/$nin/$or/$and/$text` + assignee/member joins).
4. Implement visibility (`level <= 1` = all; else created/assignee/member) on list + single-read.
5. Build the admin API actions (§10) incl. `attachment_url` presigning, `stats`, `settings`.
6. Build the JWT user/mobile API mirroring admin logic, scoped by visibility.
7. Wire the attachment pipeline to object storage (allow-list, size cap, presigned reads).
8. Wire notifications: email templates (`new_ticket`/`update_ticket`/`private_ticket` + owner), in-app, and push (`ticket_assigned`/`ticket_member_added`/`ticket_updated`, never the actor).
9. Write activity rows on every state change (status/assignees/members/comment/time/attachment).
10. Add the due-date reminder + overdue cron (one-shot via `reminder_sent`/`overdue_sent`).
11. Build the admin UI: inbox, settings page (color-coded statuses/priorities/departments), thread view (comments + activity timeline + assignees/members + time entries + multi-attachment).
12. Build the mobile/user client with push deep links.
13. Add `ticket_user_prefs` + the `prefs` / `save_prefs` actions; wire the shared filter bar (status, priority, scope) with debounced auto-save and an allow-listed write path (§13).
14. Add the `efficiency` action + meter: replay `reassigned` history to credit assigned time per user, band it, and show the "take your name off finished work" reminder (§14).

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Support / helpdesk / ITSM |
| Backend | PHP + MySQL (ref A) or FastAPI + document store (ref B) |
| Frontend | React admin UI + JWT mobile client |
| Storage | S3-compatible object storage (presigned reads) |
| Entities | tickets + assignees + members + comments + time entries + activity log + attachments + settings + per-user preferences |
| Beyond the simple variant | ticket numbers, priorities, departments, configurable color-coded statuses, assignees, members/watchers, time tracking, activity log, multiple attachments, push + in-app notifications, private tickets, due-date/overdue reminders, stats, visibility levels, dual admin+mobile API, dashboard with saved-per-user filters + Mine/My group/All/Private scope, per-user efficiency meter |
| Multi-tenant | Role-based visibility levels + private tickets |
| Depends on | [email-template-system](../email-template-system/SPEC.md) — `new_ticket` / `update_ticket` / `private_ticket` / `support_reply` |
| **Integration target** | **Must be fully integrated into the demelos.com admin portal ([admin-portal-system](../admin-portal-system/SPEC.md), Express/TS/MySQL/React) — fold into its existing `tickets.ts` / `Tickets.tsx` / `02-tickets-v2.sql`, not a standalone service** |
| Source build | Alta Apps portal (`app.altajan.com`) — PHP/MySQL helpdesk |
