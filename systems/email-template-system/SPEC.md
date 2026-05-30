# System: Customizable Transactional Email Template System

Admin-managed system that lets an administrator view and customize every transactional email the app sends (verification, password reset, status changes, invitations, admin notifications) from a single admin page — no code changes, no redeploy. Each email = **frame** (shared outer shell) + **content template** (per-email body), with code-defined defaults and DB-stored overrides. Dynamic values injected via bracketed `[tag]` placeholders resolved at send time.

**Type:** full feature subsystem (data model + API + rendering engine + admin UI). Product-neutral — swap placeholder tag names, accent color, and default copy per target.

---

## Integration Prompt

> Paste everything below this line into the target project.

---

You are given a task to build a **customizable transactional email template system** in the codebase.

Target stack (reference — map onto equivalents if the project differs):
- **Frontend:** React (function components + hooks), lightweight SVG icon set, toast notifications, no rich-text editor.
- **HTTP:** shared API client with auth token injection.
- **Backend:** Python + FastAPI router, async handlers, admin-only auth dependency.
- **Database:** document store (MongoDB-style). One collection for frame + per-template overrides, keyed by template key.
- **Email delivery:** transactional provider SDK (e.g. SendGrid) with API key from env; supports From, Reply-To, BCC, HTML body.
- **Logging:** separate collection recording every send attempt.

If the project lacks any of these, set them up first or substitute the project's equivalent (e.g. SQL table instead of document collection, Node/Express instead of FastAPI).

### 1. Architecture

Two layers per email:
- **Frame (outer wrapper):** one shared HTML shell around every email — logo header, content container, footer. Edited once, applies to all.
- **Content template (inner body):** per-email subject line + HTML body injected into the frame.

Both ship with code-defined defaults. Admin edits stored as DB overrides; reset deletes the override → falls back to default.

**Send request flow:**
1. App calls universal sender with `template_key`, recipient, dict of replacement values.
2. Engine loads template: code default, overlay DB override if present.
3. Engine resolves global/company context tags, merges with caller replacements + automatic `[date]` tag.
4. String-replace all tags in subject + body HTML.
5. Inject rendered body into frame at `[email_content]`; fill `[year]`.
6. Apply global tags to the frame (header logo, footer, links resolve).
7. Tag-resolve frame email settings (From / Reply-To / BCC), pass to provider.
8. Provider sends; attempt (success/failure, status code, error) logged.

### 2. Data Model

**Default template (code-defined registry — source of truth for which emails exist):**

| Field | Description |
|-------|-------------|
| `template_key` | Stable unique id, e.g. `"password_reset"`. Used in API path + override key. |
| `title` | Human-readable name in admin list. |
| `description` | One-line: when this email is sent. |
| `category` | Grouping key for filtering + color-coding (§7). |
| `subject` | Default subject. May contain tags. |
| `body_html` | Default inner body HTML. May contain tags. Does NOT include frame. |
| `available_tags` | Valid tags = template-specific tags + shared global tags. |

**Override document (DB — stored only when customized). Collection keyed by `template_key`. Frame stored under reserved key e.g. `"_email_frame"`:**

| Field | Description |
|-------|-------------|
| `template_key` | Matches a default key, or reserved frame key. |
| `subject` / `body_html` | Overridden content (content templates). |
| `body_html` | Overridden frame HTML (frame doc). Must contain `[email_content]`. |
| `from_email` / `reply_to_email` / `bcc_emails` | Frame doc only. Global send settings, may contain tags. BCC comma-separated. |
| `updated_at` / `updated_by` | Audit metadata. |

**Merge rule (on read):**
```python
if key in overrides:
    merged = { **default, **override }
    merged["is_customized"] = True
else:
    merged = { **default, "is_customized": False }
```
Reset = delete the override doc → next read returns pure default.

### 3. Tag / Placeholder System

Square-bracket convention `[tag_name]`. Literal string substitution at send time on subject + body. Three classes:

**Template-specific** — declared per template in `available_tags`, supplied by caller, e.g. `[firstname]`, `[reset_link]`, `[verification_code]`, `[status]`, `[rejection_reason]`, `[invite_link]`.

**Global** — available in EVERY template, resolved automatically from tenant/company context (caller does NOT pass). Reference set:
```
[platform_logo]  -> default platform/brand logo URL
[company_logo]   -> tenant logo URL (falls back to platform logo)
[company_name]   -> tenant display name (falls back to platform name)
[company_url]    -> tenant URL / subdomain (falls back to main URL)
[company_email]  -> tenant contact email (falls back to default sender)
```
A context resolver looks up tenant by id, returns these with safe fallbacks when no tenant in scope. This is what makes one template render per-tenant in multi-tenant apps.

**Frame-only:**
- `[email_content]` — REQUIRED in frame. Marks body injection point. Saving frame without it is rejected (validate client + server).
- `[year]` — current year (footer copyright).

**Automatic:** `[date]` always injected by sender (formatted current date); any template may use it without caller passing.

**Resolution order:**
1. Build replacement map = `{ [date], ...global tags, ...caller replacements }` (caller values win on collision).
2. Replace all tags in subject + body.
3. Inject body into frame (`[email_content]`); replace `[year]`.
4. Apply global tags to assembled frame HTML.
5. Resolve global tags inside From / Reply-To / BCC settings.

### 4. The Frame (Shared Wrapper)

Centered fixed-width container, logo header, light rounded content panel containing `[email_content]`, muted footer with `[year]` + brand + link. Reference default:

```html
<div style="font-family: Arial, sans-serif; max-width: 600px;
            margin: 0 auto; padding: 20px;">
  <div style="text-align: center; margin-bottom: 30px;">
    <img src="[platform_logo]" alt="Logo"
         style="width: 250px; height: auto; display: block;
                margin: 0 auto 10px;" />
  </div>
  <div style="background: #f8f9fa; border-radius: 12px;
              padding: 30px;">
    [email_content]
  </div>
  <p style="color: #9ca3af; font-size: 11px; text-align: center;
            margin-top: 20px;">
    &copy; [year] [company_name]
    <a href="[company_url]" style="color: #9ca3af;">[company_url]</a>
  </p>
</div>
```

**Frame email settings** (attached to frame doc, applied to every send, each may contain tags):

| Field | Description |
|-------|-------------|
| From Email | Envelope/sender. Empty = SMTP default. |
| Reply-To Email | Where replies go. Supports tags, e.g. `[company_email]`. |
| BCC Emails | Comma-separated. Supports tags. Each entry validated to contain `@`. |

### 5. Rendering Engine & Caching

- Universal send function: `(template_key, to_email, replacements, tenant_id, email_type)` → full resolve→wrap→send pipeline.
- Frame doc cached in-process after first load (avoid DB hit per send).
- Cache invalidated explicitly whenever frame is saved or reset.
- Provider call runs off the event loop with ~30s timeout (slow provider can't hang the request).
- Every attempt logged: recipient, subject, type, status, provider status code, error message, timestamp.
- Success = 2xx; non-2xx + exceptions logged with extracted provider error message.

### 6. Editor (raw HTML, NOT WYSIWYG)

Deliberate: raw HTML editor keeps full control over email-safe inline-styled HTML and avoids heavy deps.

- Body edited in monospaced `<textarea>` (~12–14 rows); frame uses taller (~14 rows).
- **Live Preview** renders current HTML beneath the editor by injecting into the DOM, substituting sample values for tags.
- Tags shown as clickable chips above editor; click copies tag to clipboard.
- Subject = single-line input above body.
- Save disabled until something changes (dirty-check vs originally loaded values).
- Reset to Default offered only when customized; confirms, then deletes override.

**Email HTML authoring conventions:** inline styles only (no `<style>` blocks / external CSS), table- or div-based layouts at fixed ~600px max-width, absolute image URLs. Buttons = styled anchor tags with inline padding/background/border-radius.

### 7. Admin UI / UX

**Page structure (top→bottom):**
1. Header: icon + title + one-line subtitle.
2. Info box: Available Tags + Global Tags as code chips + short explanation. Tinted background, left icon.
3. Frame editor: collapsible card (collapsed default), layout icon, 'Customized' badge when overridden, frame tags (click-to-copy), email-settings grid (3 inputs), frame HTML textarea, Show/Hide Preview toggle, Save/Reset.
4. Filters: search input (title/description/key) + category dropdown.
5. Template list: one collapsible row per template.

**Template row (collapsed):** leading mail icon (accent-tinted if customized, gray otherwise), title + category pill (color per category) + 'Customized' pill, description line, chevron.

**Template row (expanded):** available tags as click-to-copy chips, subject input, body HTML textarea (monospaced), live preview panel, right-aligned actions (Reset to Default if customized, Save Template with spinner).

**Behavior:** only one template expanded at a time; expanding pre-fills from current values. Tag chip click copies + toast. Save shows success/error toast; list refreshes on success. Frame Save blocked client-side if `[email_content]` missing, with explanatory toast. Reset confirms via dialog.

### 8. Visual Design Tokens

| Token | Value / usage |
|-------|---------------|
| Accent (email buttons/links) | `#2B4B6A` — CTA buttons + primary links in email bodies. |
| Frame content panel | `#f8f9fa` bg, 12px radius, 30px padding. |
| Footer text | `#9ca3af`, 11px, centered. |
| Admin primary action | Indigo for Frame editor (Save Frame), Blue for content templates (Save Template). |
| Info box | Light blue tint, blue border + text. |
| Tag chips | Body: neutral gray. Global: green. Frame: indigo. Monospace, rounded, small. |
| Container width | Admin content max-width ~6xl centered; email frame 600px. |

**Category color map** (tinted pill, light bg / dark text):

| Category | Pill |
|----------|------|
| authentication | blue |
| status / verification | orange |
| account / company | green |
| support | purple |
| referral | pink |
| admin notification | red |
| transactions / quotes | indigo |
| access | teal |
| invites | amber |
| (fallback) | gray |

### 9. API Surface (all admin-only, `/email-templates` prefix)

| Endpoint | Purpose |
|----------|---------|
| `GET /email-templates` | List all (defaults merged with overrides; each flagged `is_customized`). |
| `GET /email-templates/{key}` | Fetch one merged template. |
| `PUT /email-templates/{key}` | Upsert override (subject and/or body_html). |
| `POST /email-templates/{key}/reset` | Delete override → revert to default. |
| `GET /email-templates/frame` | Frame HTML + email settings + is_customized + frame tags. |
| `PUT /email-templates/frame` | Save frame HTML + From/Reply-To/BCC. Rejects body without `[email_content]`. Invalidates cache. |
| `POST /email-templates/frame/reset` | Delete frame override → default. Invalidates cache. |

### 10. Access Control

- All template + frame endpoints require admin role; non-admins → 403.
- Multi-tenant: restrict template management to the highest (platform) admin role so tenant admins can't alter system-wide emails.
- Per-tenant variation achieved through global tags resolving by tenant context — NOT by giving each tenant its own editor.

### Reproduction Checklist

1. Define default template registry in code (key, title, description, category, subject, body_html, available_tags).
2. Define default frame HTML with required `[email_content]` + `[year]`; choose global tag names.
3. Create one DB collection for overrides keyed by template_key + reserved frame key.
4. Implement merge-over-defaults read + delete-to-reset semantics.
5. Build tenant/global-tag context resolver with safe fallbacks.
6. Build universal send function: resolve tags → wrap in frame → resolve frame tags → resolve send settings → dispatch → log.
7. Add in-process frame caching with explicit invalidation on save/reset.
8. Build admin UI: info box, collapsible frame editor (email settings + preview), filters, per-template accordion editor with live preview + click-to-copy chips.
9. Gate all endpoints behind admin role.
10. Add send-attempt log collection.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Admin / transactional email infrastructure |
| Backend | FastAPI (async) + document store + transactional email SDK |
| Frontend | React + hooks + toasts, raw-HTML editor (no WYSIWYG) |
| Key concept | Frame + content layers, code defaults + DB overrides, `[tag]` resolution at send time |
| Multi-tenant | Yes — global tags resolve per tenant context |
| Customize per product | Swap tag names, accent `#2B4B6A`, default copy |
