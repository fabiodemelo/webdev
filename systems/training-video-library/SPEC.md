# System: Training Video Library

Training/educational video library backed by an external video host (YouTube in reference; generalizes to any oEmbed/iframe-friendly provider). Short embedded videos in a manually-ordered grid, with a **two-tier visibility system**: platform videos (super-admin, cross-tenant subject to opt-in) and tenant videos (tenant admin, tenant-only). Public viewers see active platform videos only.

**Type:** full feature subsystem (single collection + two-tier visibility + embed adapter + admin CRUD + UI).

**Reference stack:** FastAPI (Python) + MongoDB-style doc store (single collection `educational_videos`) + React + YouTube embeds.

> **Related:** reads `settings.show_platform_videos` on the tenant document (tenant-settings UI, out of scope). No email/notifications.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap "tenant" for the right noun; replace the embed adapter with your provider's URL pattern. Single-tenant apps ignore the tenant tier (treat all videos as platform-level).

---

You are given a task to build a **training video library** in the codebase.

Reference stack (map onto equivalents):
- **Frontend:** React (function components + hooks), plain forms, native `<iframe>` embed, toast notifications.
- **Backend:** Python + FastAPI. Two routers: public router (`/videos`, optional auth) + admin router (`/admin/videos`, requires admin).
- **Database:** document store (MongoDB-style). One collection `educational_videos`.
- **Video provider:** YouTube (embed `https://www.youtube.com/embed/<id>`; thumbnail `https://img.youtube.com/vi/<id>/mqdefault.jpg`). Vimeo / Wistia / Loom / self-hosted MP4 fit by replacing the URL-to-embed adapter.

### 1. Overview

Two tiers:
- **Platform videos** — created by super-admin, visible across all tenants (subject to per-tenant opt-in).
- **Tenant videos** — created by tenant admin, visible only inside that tenant.

End users see a curated library mixing the two tiers based on the tenant's preference. Admins see/curate only the tier they own. Public (logged-out) viewers see active platform videos only (marketing site / public learning hub). Sort is manual via integer `display_order`. Activation is a soft visibility flag (`is_active`) — toggling off hides without deleting.

### 2. Architecture

| Component | Responsibility |
|-----------|----------------|
| Video store | Single collection `educational_videos`. Null/missing `tenant_id` marks a platform video; any other value marks a tenant video. |
| Tenant opt-in flag | Boolean on tenant doc (`settings.show_platform_videos`; reference uses `settings.show_super_admin_videos`). When false, platform videos hidden from this tenant's users. |
| Embed adapter | Pure client-side function converting a provider URL → embed URL + thumbnail URL. Easy to replace per provider. |
| Tenant scope helper | Existing `build_tenant_query` — super-admins see all platform videos; tenant admins see only theirs. |
| Admin CRUD | Single endpoint per HTTP verb; manual ordering by writing an integer to `display_order`. |

### 3. Domain Model — `educational_videos` collection

| Field | Description |
|-------|-------------|
| `_id` | Video id. |
| `title` | Required. |
| `provider_url` | Pasted URL from the host (e.g. `https://www.youtube.com/watch?v=ABC`). Stored verbatim; conversion to embed/thumbnail at render time. |
| `description` | Optional short blurb beneath the embed. |
| `display_order` | Integer; ascending sort. New videos default to `max(existing within scope)+1`. |
| `is_active` | Bool. Inactive hidden from end users but visible in admin list. |
| `tenant_id` | Null/missing for platform videos; tenant id for tenant videos. |
| `created_by` | Author admin's user_id. |
| `created_at` / `updated_at` | Audit. |

> Reference stores the field as `youtube_url`. For provider-neutrality the spec uses `provider_url`. Keep the legacy name for zero-migration deployment.

### 4. Two-Tier Visibility Model

**4.1 Public read endpoint (optional auth)** — `GET /videos` behaves differently per caller. No token = "public viewer":
```python
if not current_user:
    # Public viewer — only active platform videos.
    return active_videos_where(tenant_id IS NULL)

if current_user.is_super_admin:
    # Super-admin browsing public catalog — only platform videos.
    return active_videos_where(tenant_id IS NULL)

if current_user.tenant_id:
    tenant = load_tenant(current_user.tenant_id)
    show_platform = tenant.settings.show_platform_videos == True
    conditions  = [ tenant_id == current_user.tenant_id ]
    if show_platform:
        conditions.append( tenant_id IS NULL )
    return active_videos_where( $or: conditions )

# Authenticated user with no tenant — only platform videos.
return active_videos_where(tenant_id IS NULL)
```
All branches return videos sorted ascending by `display_order`. `is_active` filtering happens server-side for the public endpoint; admins read through a separate endpoint that bypasses `is_active`.

**4.2 Admin read endpoint** — `GET /admin/videos` applies the standard tenant scope helper. Super-admins see all platform videos (`tenant_id` null); tenant admins see all of their tenant's videos. `is_active` NOT filtered — admins manage both active + inactive in the same view.

### 5. Embed Adapter (provider-specific)

Both the public viewer page and admin list render videos via a tiny client-side adapter turning a pasted provider URL into (a) embed URL and (b) thumbnail URL. Reference (YouTube):
```js
const YOUTUBE_REGEX = /^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;

function extractId(url) {
  const m = url.match(YOUTUBE_REGEX);
  return (m && m[2].length === 11) ? m[2] : null;
}

function embedUrl(id)     { return `https://www.youtube.com/embed/${id}`; }
function thumbnailUrl(id) { return `https://img.youtube.com/vi/${id}/mqdefault.jpg`; }
```
To support another provider, replace `extractId` / `embedUrl` / `thumbnailUrl`:
- **Vimeo:** `/vimeo\.com\/(\d+)/` → `https://player.vimeo.com/video/<id>`
- **Loom:** `/loom\.com\/share\/([a-f0-9]+)/` → `https://www.loom.com/embed/<id>`
- **Self-hosted:** store an mp4 URL, render via `<video src>`.

Storing the original provider URL (not a parsed id) keeps data portable across providers and resilient to provider URL-format changes.

### 6. API Surface

**6.1 Public (`/videos`):**

| Endpoint | Behavior |
|----------|----------|
| `GET /videos` | Optional auth. Returns visible-and-active list per §4.1, sorted by `display_order` asc. |

**6.2 Admin (`/admin/videos`):**

| Endpoint | Behavior |
|----------|----------|
| `GET /admin/videos` | List ALL videos in caller's scope (including inactive), sorted by `display_order` asc. |
| `POST /admin/videos` | Create. Body `{ title, provider_url, description?, display_order?, is_active? }`. Server stamps `tenant_id` from auth (null for super-admins), assigns `display_order = max+1` within scope when not provided. |
| `PUT /admin/videos/{id}` | Patch any of title / provider_url / description / display_order / is_active. Tenant-scoped — admins can't update videos outside their tenant; super-admins can't accidentally edit a tenant's video by id (scope guards prevent it). |
| `DELETE /admin/videos/{id}` | Hard delete, tenant-scoped. |

### 7. Access Model Summary

| Role | Capabilities |
|------|--------------|
| Public viewer | Read-only via `/videos`. Sees active platform videos. |
| Authenticated user (no tenant) | Same as public. |
| Authenticated user (in tenant) | Sees active tenant videos + active platform videos IFF tenant opted in. |
| Tenant admin | Full CRUD inside their tenant. Cannot create/edit platform videos. |
| Super admin | Full CRUD on platform videos. Does NOT see tenant videos through `/admin/videos` (tenant scope filter). |

### 8. Tenant Opt-In for Platform Videos

Per-tenant boolean on the tenant doc under `settings` (e.g. `settings.show_platform_videos`). True → tenant's users see platform videos mixed into their library; false → only their tenant's videos.
- Default value is a product decision: off-by-default protects against unexpected content; on-by-default maximizes discovery.
- Tenant admin toggles it from the tenant-settings page (existing field, not part of this spec).
- Flag only affects READS; tenant admins can never create/modify platform videos regardless of the flag.

### 9. Ordering

`display_order` is the single source of truth inside both tiers. New videos receive `max(existing)+1` within their own scope (platform OR a specific tenant). To move a video, write a new `display_order` via PUT and re-fetch. Optional UX: drag-and-drop cards and bulk-write new `display_order`s. Platform + tenant videos sorted independently inside each scope; on the mixed end-user list they interleave by `display_order`. For platform-first / tenant-first sections, render two separate lists client-side rather than changing the server sort.

### 10. End-User UI

- Header: title ("Training Videos" / "Learn") + brief subtitle.
- Responsive grid of cards (1 / 2 / 3 columns by viewport).
- Each card: 16:9 iframe (embed URL from adapter) on top, title beneath, optional description.
- No actions for end users — just the embed.
- Empty state: friendly message + brand-tinted illustration.
- Loading state: skeleton cards matching the final grid.

### 11. Admin UI

**11.1 List** — header with title, subtitle ("Manage embedded videos"), primary "+ Add Video". Grid or list of cards/rows. Each row: thumbnail (from adapter), title, short URL preview, `display_order`, `is_active` pill, actions menu (Edit / Toggle / Delete). Inactive videos styled muted for quick scanning.

**11.2 Add / Edit modal** — title input (required); provider URL input (required, placeholder shows sample URL for the provider); description textarea (optional); display order numeric (defaults max+1); active toggle. Save calls POST or PUT; on success closes modal + re-fetches list.

**11.3 Toggle-active inline action** — one-tap on each row flips `is_active` and re-saves with a single PUT; quick way to take a video offline without opening the editor.

### 12. Visual Design Tokens

| Token | Value / usage |
|-------|---------------|
| Card | rounded-xl border, white bg, subtle shadow on hover |
| Embed area | aspect-video, rounded top, black bg while loading |
| Primary action | brand accent — "+ Add Video", "Save" |
| Active pill | green (`#D1FAE5` / `#065F46`) |
| Inactive pill | gray (`#F3F4F6` / `#4B5563`) |
| Inactive card | opacity 60% in admin list |
| Container | max-width ~6xl, centered, p-6/p-8 |
| Thumbnail tile | 16:9 ratio, `object-cover` so non-standard provider thumbnails crop correctly |

### 13. Security Rules

- `tenant_id` captured from authenticated session at create — admins can't create a video for a tenant they don't belong to.
- Read endpoint is OPTIONAL-auth; the §4.1 visibility cases enforced server-side. Client can't bypass to see another tenant's videos by omitting the token.
- Admin endpoints apply the standard tenant scope helper, so a tenant admin's PUT/DELETE on a platform or other-tenant video resolves to 404 — never a leaking 403.
- Provider URL stored verbatim; the embed iframe trusts only the adapter's known patterns. Rendering NEVER passes user-supplied URLs straight into iframe `src` — only the adapter's parsed-id template — so a malicious URL can't escape into an arbitrary embed.
- Description + title rendered as text, not HTML — user content can't inject markup.

### Reproduction Checklist

1. Create `educational_videos`. Index `{ tenant_id, display_order }` for public read, `{ tenant_id, is_active, display_order }` for active filter.
2. Add/reuse the tenant `settings.show_platform_videos` boolean on the tenant doc.
3. Implement `GET /videos` with optional auth + visibility branches (§4.1).
4. Implement `GET /admin/videos` (auth required) with the tenant scope helper, including inactive entries.
5. Implement `POST /admin/videos`: stamp `tenant_id` from auth (null for super-admin), default `display_order = max+1` within scope, default `is_active = true`.
6. Implement `PUT /admin/videos/{id}` + `DELETE /admin/videos/{id}` with tenant-scope predicates so cross-tenant ops resolve to 404.
7. Implement the provider URL adapter (`extractId` / `embedUrl` / `thumbnailUrl`). Validate `extractId` returns null on invalid input and refuse to render in that case.
8. Build end-user grid: cards with iframe embeds, title, description. Skeletons during fetch.
9. Build admin list with thumbnails, status pill, Edit / Toggle / Delete actions per row.
10. Build Add/Edit modal: title / provider URL / description / display_order / is_active.
11. (Optional) Drag-to-reorder via a small batch of PUTs on drop, or a single bulk-update endpoint.
12. (Optional) Add search input wired to a future `?q=` parameter or client-side filtering.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Education / training / content |
| Backend | FastAPI (async) + single doc collection |
| Frontend | React + native iframe embeds |
| Provider | YouTube (swap embed adapter for Vimeo/Loom/Wistia/MP4) |
| Visibility | Two-tier: platform (cross-tenant, opt-in) + tenant-only |
| Ordering | Manual `display_order` integer |
| Multi-tenant | Yes — tenant scope helper + per-tenant opt-in flag |
| Reads | `settings.show_platform_videos` on tenant doc |
