# System: Support Ticket System

Authenticated end users open tickets, exchange threaded messages with admins, optionally attach files. Admins see all tickets within their tenant (or globally for super-admins), search/filter, reply (triggers an email to the user), and close. Minimal three-status workflow, single conversation thread per ticket — no priorities, queues, or categories.

**Type:** full feature subsystem (ticket + message store + attachment pipeline + tenant scoping + admin/user UI + API).

**Reference stack:** FastAPI (Python) + MongoDB-style doc store + React + S3-compatible object storage. Reuses the transactional email template system for reply notifications.

> **Related:** admin-reply notification reuses [email-template-system](../email-template-system/SPEC.md) (template key `"support_reply"`). No direct email-provider dependency.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap "tenant" for the right noun (company / team / workspace / account). Single-tenant apps can ignore the tenant scoping section.

---

You are given a task to build a **support ticket system** in the codebase.

Reference stack (map onto project equivalents):
- **Frontend:** React (function components + hooks), shared API client with auth-token injection, toast notifications. No chat library — plain message list + textarea + file input.
- **Backend:** Python + FastAPI, async handlers. Two routers: user router (`/support`) + admin router (`/admin/support`). `BackgroundTasks` for email notification.
- **Database:** document store (MongoDB-style). Collections: `support_tickets`, `ticket_messages`, `app_settings` (allow-list singleton).
- **File storage:** S3-compatible (DigitalOcean Spaces / AWS S3). Storage helper uploads bytes → returns public CDN URL. Feature flag (`SPACES_ENABLED`-style) gates the endpoint with 503 when storage not configured.
- **Email:** existing transactional-email template system (key `"support_reply"`).

### 1. Overview / Roles

- **End user** → creates own tickets, sees only own tickets, sends replies + attachments. Cannot close.
- **Tenant admin** → sees every ticket from any user belonging to their tenant (including users found via referral/parent tree). Replies, closes.
- **Super admin** → sees every ticket across all tenants.

Each ticket = subject + single chronological message thread. Each message ≤ one file attachment, validated against an admin-configurable allow-list. An admin reply auto-promotes an `open` ticket to `in_progress` and emails the requester. Closing is admin-only and one-way (no auto-reopen on new message).

### 2. Architecture

| Component | Responsibility |
|-----------|----------------|
| Ticket store | Doc collection. One doc per ticket: subject + status + tenant scope + audit timestamps. |
| Message store | Separate collection keyed by `ticket_id`. One doc per message. Sorted ascending by `created_at` when rendered. |
| Attachment storage | Object storage (S3-compatible). Message doc stores metadata + public URL; bytes NOT in DB. |
| Allow-list of extensions | Loaded from `app_settings` doc (change without redeploy); falls back to code default. |
| Tenant scope helper | Pure async `build_ticket_query(user, base_query)` → correct filter per role. Used by every admin endpoint. |
| Notification hook | Background task → email template system sends "support reply" to user when admin replies. |
| UI | Two pages: user inbox + thread; admin inbox + thread + close action. |

### 3. Domain Model

**3.1 Ticket** — `support_tickets`:

| Field | Description |
|-------|-------------|
| `_id` | Identifier (e.g. ObjectId). |
| `subject` | Required free-text subject line. |
| `user_id` | Owner / requester. |
| `tenant_id` | Tenant the ticket belongs to. Read from requester at creation, from **auth context — NOT request body**. |
| `status` | `"open" \| "in_progress" \| "closed"`. Default `"open"`. |
| `created_at` / `updated_at` | Audit. `updated_at` bumped on every new message + on close. |
| `closed_at` / `closed_by` | Set on close; `closed_by` = admin's user_id. |

**3.2 Message** — `ticket_messages`. Thread reconstructed by querying `{ ticket_id }` sorted `created_at` asc:

| Field | Description |
|-------|-------------|
| `_id` | Message id. |
| `ticket_id` | FK to parent ticket (stored as string for index simplicity). |
| `sender_id` | Author. |
| `message` | Plain text body. Rendered as text on client (no HTML). |
| `attachment` | Optional nested metadata (§3.3). Null when no file. |
| `created_at` | Author timestamp. |

On read, messages enriched with sender's `email`, `role`, display `name`, and `is_admin` bool (true for any admin-class role). Computed at read time → always reflect current user state.

**3.3 Attachment** — embedded in message doc:

| Field | Description |
|-------|-------------|
| `file_name` | Original filename. |
| `stored_filename` | Server-generated: `{ticket_id}_{YYYYMMDD_HHMMSS}_{original}`. |
| `file_url` | Public CDN URL (from storage helper). |
| `file_size` | Bytes. |
| `content_type` | MIME reported by upload. |
| `uploaded_at` | When stored. |

**3.4 Allow-list settings (singleton)** — `app_settings`:
```json
{
  "_id": "app_settings",
  "allowed_extensions": [".pdf", ".doc", ".docx", ".xls", ".xlsx",
                         ".ppt", ".pptx", ".txt", ".csv", ".rtf",
                         ".jpg", ".jpeg", ".png", ".gif", ".webp",
                         ".zip", ".rar"]
}
```
Absent doc → loader falls back to same list as code default. Functional out of the box.

### 4. Ticket Lifecycle

```
(create)
   │
   ▼
 open ──user replies────► open (status unchanged)
   │
   │  first admin reply
   ▼
 in_progress ──user replies / admin replies────► in_progress
   │
   │  admin closes
   ▼
 closed (terminal — no automatic reopen on new message)
```

- **Open:** created by end user. Visible to user, tenant admins, super-admins.
- **In progress:** any admin reply on an open ticket promotes it. User's own replies do NOT change status.
- **Closed:** admin used close action. `closed_at` + `closed_by` recorded. Message-add endpoints still accept replies, but status stays `closed`. (Reopen-on-reply = one-line policy change in `add_message`.)

### 5. Access Model & Multi-Tenant Scoping

Enforced by a single async helper returning the correct MongoDB filter for the caller, combined with any per-endpoint base filter. Every admin endpoint runs through it.

**5.1 `build_ticket_query(user, base_query)`:**
```python
if is_super_admin(user):
    return base_query                      # no tenant filter

tenant_id = user.tenant_id
if not tenant_id:
    raise 403  # tenant admin without a tenant id MUST NOT see all tickets

user_ids_in_tenant = collect_user_ids(tenant_id)
scope = {
  "$or": [
    { "tenant_id":          tenant_id },
    { "referred_by_tenant": tenant_id },
    { "user_id": { "$in": user_ids_in_tenant } }
  ]
}
return { "$and": [base_query, scope] } if base_query else scope
```

**5.2 Walking the user tree** — `collect_user_ids` walks the referral/parent tree so tickets opened by users with a mis-set `tenant_id` or invited indirectly are still visible to the right tenant admin:
1. **Seed:** users with `tenant_id == T` OR `referred_by_tenant == T`.
2. **Frontier expansion** (breadth-first, capped at 5 levels): users whose `parent_id` or `referred_by_user` is in the current frontier AND whose `tenant_id` is either T or invalid/missing (prevents cross-tenant leak).
3. Union all collected ids, return list.

The 5-level cap is a safety bound (prevents pathological graphs blowing up the query), not a business rule. The cross-tenant guard ("tenant_id must be T or invalid") is the load-bearing isolation rule.

**5.3 Role matrix:**

| Role | Capabilities |
|------|--------------|
| End user | Create (auto-scoped to tenant). List own. Read own ticket + thread. Reply (±attachment). Cannot close. |
| Tenant admin | Read/list via `build_ticket_query`. Reply (admin-styled). Close. |
| Super admin | Same as tenant admin minus the tenant filter — sees/acts on every ticket. |

On any read or reply, non-admins are filtered with a hard `{ user_id == self }` predicate — they CANNOT access any ticket they didn't open, regardless of tenant membership.

### 6. Attachment Pipeline

1. Client sends `multipart/form-data` to the with-attachment endpoint: `{ message (form field), file (optional) }`.
2. Server extracts file extension, looks up allow-list from `app_settings` (or default).
3. Reject (400) if extension not allowed.
4. Read bytes; reject (400) if empty; reject (400) if size > 10 MB.
5. If object-storage feature flag OFF → return 503 with clear message ("File storage is not configured") so failure is diagnosable.
6. Generate `stored_filename = {ticket_id}_{YYYYMMDD_HHMMSS}_{file.filename}`.
7. Upload to object storage, receive public CDN URL.
8. Persist message with attachment metadata block.

Two endpoints (plain reply vs reply-with-attachment) kept separate: plain accepts JSON, attachment requires multipart. Client decides based on whether a file is selected.

### 7. Notifications

When an admin posts a message (±attachment), a background task emails the ticket owner via the email template system.
- Reference key: `"support_reply"`. Variables: `[firstname]`, `[ticket_subject]`, `[reply_message]` (truncated), plus global tags (logo, tenant name) from the email system.
- Fire-and-forget; a delivery failure must NOT roll back the message write. Send results recorded in the email log collection (handled by the email subsystem, out of scope here).
- Optional extension: a `"ticket closed"` template the close endpoint can trigger. Not in reference but trivial.

### 8. API Surface

**8.1 End-user (`/support`):**

| Endpoint | Behavior |
|----------|----------|
| `GET /tickets` | List caller's own tickets, sorted `created_at` desc. |
| `POST /tickets` | Create. Body `{ subject, message? }`. Server sets `user_id` + `tenant_id` from auth. Initial message (if provided) inserted into `ticket_messages`. |
| `GET /tickets/{id}` | Return `{ ticket, messages[] }`. Messages enriched with `sender_email, sender_role, sender_name, is_admin`. Non-admins get 404 unless they own the ticket. |
| `POST /tickets/{id}/messages` | Add plain text reply (JSON body). |
| `POST /tickets/{id}/messages/with-attachment` | Add reply with optional file (`multipart/form-data`). |

**8.2 Admin (`/admin/support`):**

| Endpoint | Behavior |
|----------|----------|
| `GET /tickets?status=&search=` | List all tickets visible to caller (super-admin global; tenant admin scoped). `status` filtered server-side. `search` applied after fetch, matching subject + user_email + initial message + concatenated message bodies. |
| `PUT /tickets/{id}/close` | Set `status=closed, closed_at=now, closed_by=admin_id`. Guarded by `build_ticket_query` so admins can't close tickets outside scope. |

### 9. End-User UI

**9.1 Inbox** — header + "+ New Ticket"; list of caller's tickets newest first. Each row: status pill, subject, created date, message count (computed on read), latest-activity timestamp (`updated_at`). Empty state: friendly explanation + "+ New Ticket" CTA.

**9.2 New ticket modal** — single-line subject input, multi-line message textarea, submit creates ticket + opens thread view. Both fields validated client-side; backend also validates.

**9.3 Thread view** — header: subject + status pill + breadcrumb to inbox. Chronological message list; each bubble: sender display name, role hint, timestamp, body. Admin messages styled distinctly (different background, optional badge). Attachments inside the bubble: image types (jpg/jpeg/png/gif/webp/bmp/svg) render as thumbnails opening full-size in new tab; other types render as a small file chip (name + icon) linking to CDN URL. Reply box at bottom: textarea + single-file picker + Send. Send disabled while in-flight; toast on success/error. Disabled/hidden for closed tickets if product policy is no-reply-after-close (optional).

### 10. Admin UI

**10.1 Admin inbox** — debounced search input (~300ms) filtering server-side across subject + user email + message bodies. Status filter: All / Open / In progress / Closed. Table sorted `created_at` desc. Columns: status pill, subject, user email, message count, last-activity (`updated_at`). Row click → thread view.

**10.2 Status pill palette:**

| Status | Pill |
|--------|------|
| open | blue — "Open" |
| in_progress | amber — "In Progress" |
| closed | slate — "Closed" |

**10.3 Admin thread view** — identical chronological thread to user view. Admin messages visually flagged (accent background, "Admin" pill). Reply box always available (+ file picker). "Close ticket" button visible while `status ≠ closed`; asks confirmation; on success updates local state without full refetch.

### 11. Search Behavior

Admin list endpoint does a two-pass search to avoid full-text indexes:
1. Run tenant-scoped query (with status filter) against `support_tickets`.
2. For each candidate, fetch its messages from `ticket_messages`, concatenate bodies into a transient `all_messages` string on the ticket dto.
3. Case-insensitive substring match against subject + user_email + initial message + all_messages.
4. Strip `all_messages` from each result before returning.

Fine at small/medium scale. For large datasets, replace the post-fetch substring filter with a text index on `ticket_messages.message` (or a dedicated search index) — the endpoint contract stays the same.

### 12. Visual Design Tokens

| Token | Value / usage |
|-------|---------------|
| Status pill — open | `bg-blue-100 / text-blue-700`, MailOpen / Inbox icon |
| Status pill — in_progress | `bg-amber-100 / text-amber-700`, Clock / Loader icon |
| Status pill — closed | `bg-slate-100 / text-slate-600`, CheckCircle icon |
| User message bubble | neutral surface, left-aligned |
| Admin message bubble | accent-tinted surface + "Admin" pill, right-aligned (or distinctly themed) |
| Attachment chip | rounded, blue text, file icon, truncated name, opens new tab |
| Image attachment | inline thumbnail with filename underneath |
| Primary action | brand accent — "Send", "+ New Ticket" |
| Destructive/Close | subtle slate button with confirm dialog |
| Container | ~3xl / 768px for thread (readable); full-width table for admin inbox |

### 13. Security Rules

- `tenant_id` on a ticket set from authenticated session, NEVER from request body — user can't impersonate another tenant by spoofing a field.
- Non-admin reads always include `{ user_id: self }`; ownership checked on every endpoint.
- Admin reads always run through `build_ticket_query` so a misconfigured tenant admin (no `tenant_id`) gets 403 rather than seeing every ticket.
- File uploads size-limited (10 MB) + extension-limited (admin-configurable allow-list).
- Empty uploads rejected (400).
- Storage misconfiguration returns 503 with clear message instead of silently failing.
- Message bodies stored + rendered as plain text — no HTML/markdown evaluation — eliminates XSS via ticket content.
- Attachment URLs are public CDN links; don't put secrets in filenames. Filenames namespaced with ticket id so cross-ticket guessing is impractical.

### Reproduction Checklist

1. Create `support_tickets` + `ticket_messages`. Index `{ ticket_id, created_at }` on messages.
2. Create/reuse `app_settings` singleton with `allowed_extensions`. Provide code-defined fallback.
3. Implement `build_ticket_query(user, base_query)` + `collect_user_ids(tenant_id)` with the 5-level frontier walk + cross-tenant guard.
4. Implement user router (`/support`): list own, create, read with messages, add message, add message with attachment.
5. Implement admin router (`/admin/support`): list with status + search filters, close.
6. Implement attachment validator: extension allow-list, 10 MB cap, empty-body check, storage-disabled 503.
7. Wire admin-reply notification: background task → email template engine with key `"support_reply"`.
8. Build user UI: inbox with status pills, "+ New Ticket" modal, thread view with attachment rendering (inline images vs file chips), reply form.
9. Build admin UI: debounced search + status filter + table, thread view with admin styling + Close action.
10. Add plain-text/role enrichment fields on message reads (`sender_email, sender_role, sender_name, is_admin`) so UI doesn't look up users itself.
11. (Optional) Add a "ticket closed" email template, trigger from close endpoint.
12. (Optional) Add admin "reopen" action + policy for whether end-user replies on closed tickets reopen or stay closed.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Support / helpdesk / messaging |
| Backend | FastAPI (async) + document store |
| Frontend | React + shared API client |
| Storage | S3-compatible object storage (feature-flagged) |
| Workflow | 3-status (open → in_progress → closed), single thread, admin-only close |
| Multi-tenant | Yes — `build_ticket_query` + referral-tree walk + cross-tenant guard |
| Depends on | [email-template-system](../email-template-system/SPEC.md) — key `"support_reply"` |
