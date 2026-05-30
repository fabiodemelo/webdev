# System: Coupon Code System

Platform-owner-managed discount codes for paid membership/subscription plans. Two coupon types: **percentage** (X% off for first Y months of a recurring sub) and **free_lifetime** (100% off forever, bypasses payment processor, activates subscription directly). Usage limits, expiration, optional per-plan restrictions, full usage tracking + analytics. Only main admin creates/manages; any authenticated paying user validates + redeems at checkout.

**Type:** full feature subsystem (data model + API + Stripe + admin UI + checkout UI). Multi-tenant SaaS.

**Reference stack:** FastAPI (Python) + MongoDB + React + Stripe.

---

## Integration Prompt

> Paste everything below this line into the target project.

---

You are given a task to build a **coupon code system** for paid subscription plans in the codebase.

Reference stack (map onto project equivalents if different):
- **Backend:** FastAPI (Python), async handlers.
- **Database:** MongoDB (`coupons` collection). Use SQL table if project is relational.
- **Frontend:** React, shared axios API client with JWT auto-attach.
- **Payments:** Stripe (Checkout Sessions + webhook).

If the project lacks these, set up equivalents first.

### 1. Overview

Main admin issues discount codes for paid plans. Two types:
1. **PERCENTAGE** — X% off the first Y months of a recurring subscription.
2. **FREE_LIFETIME** — 100% off forever; bypasses the payment processor, activates the subscription directly.

### 2. Data Model — MongoDB collection `coupons`

One document per coupon:

| Field | Type | Description |
|-------|------|-------------|
| `_id` | ObjectId | Mongo id (returned to API as `"id"` string). |
| `code` | string | Unique, UPPERCASE. Custom or auto-generated. |
| `coupon_type` | string | `"percentage"` \| `"free_lifetime"`. |
| `discount_percent` | int\|null | 1–100. For free_lifetime stored as 100. |
| `duration_months` | int\|null | Months the % discount applies. null for free_lifetime. |
| `max_uses` | int | Max total redemptions (≥1). |
| `current_uses` | int | Redemptions so far. Starts at 0. |
| `expires_at` | datetime | UTC expiration. |
| `applicable_plan_ids` | list[str] | Plan IDs valid for. EMPTY = all plans. |
| `description` | string | Internal note (e.g. "Summer 2026 campaign"). |
| `is_active` | bool | Soft-delete flag. |
| `created_at` | datetime | UTC. |
| `created_by` | string | User id (JWT `sub`) of creating admin. |
| `updated_at` | datetime | UTC last-modified (added on update). |
| `total_discount_given` | number | Running sum of dollar value discounted (analytics). |
| `usage_history` | list[obj] | Append-only redemption log. |

**`usage_history` entry:**

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | string | Redeeming user. |
| `company_id` | string | Redeeming user's tenant/company. |
| `plan_id` | string | Plan applied to. |
| `plan_name` | string | Snapshot of plan name at redemption. |
| `original_price` | number | Plan price before discount. |
| `discount_amount` | number | Dollar value discounted this redemption. |
| `redeemed_at` | datetime | UTC. |

**Indexes (at startup):**
```python
db.coupons.create_index("code", unique=True)   # enforce unique codes
db.coupons.create_index("is_active")            # fast active-coupon lookups
```

### 3. Code Generation Rules

- Blank code field → backend auto-generates.
- Default length: 10 chars.
- Charset: uppercase A–Z + digits 0–9, with confusing chars REMOVED — `O 0 I 1 L` excluded (transcription errors).
- All codes normalized UPPERCASE + trimmed before storage and lookup → case-insensitive.
- Codes must be unique — create/update fails HTTP 400 on duplicate.

```python
chars = string.ascii_uppercase + string.digits
chars = chars.replace('O','').replace('0','').replace('I','').replace('1','').replace('L','')
code = ''.join(random.choices(chars, k=10))
```

### 4. Backend API (FastAPI)

Router prefix `/coupons`, mounted under global `/api` → all paths begin `/api/coupons`. File `backend/routes/coupons.py`.

**Auth deps:**
- `get_main_admin` — role ∈ `admin | main_admin | super_admin` (else 403).
- `get_current_user` — any authenticated user (validate/redeem).

**Admin CRUD (main admin only):**

`GET /coupons?include_inactive=false` — list (active only by default), sorted `created_at` desc.

`POST /coupons` — create. Body (`CouponCreate`):
```
code?               string   (optional; auto-generated if omitted)
coupon_type         string   "percentage" | "free_lifetime"  (required)
discount_percent?   int      1–100  (required if percentage)
duration_months?    int      >=1    (required if percentage)
max_uses            int      >=1    (required)
expires_at          datetime ISO    (required)
applicable_plan_ids? list[str]       (default [] = all plans)
description?        string
```
Validation: percentage requires `discount_percent` AND `duration_months` (else 400). free_lifetime ignores those → `discount_percent` forced 100, `duration_months` forced null. Duplicate code → 400. Returns created coupon.

`GET /coupons/{coupon_id}` — fetch one. 404 if not found.

`PUT /coupons/{coupon_id}` — partial update (`CouponUpdate`, any subset): `code, coupon_type, discount_percent, duration_months, max_uses, expires_at, applicable_plan_ids, description, is_active`. Rename to existing code → 400. Sets `updated_at`. Returns updated.

`DELETE /coupons/{coupon_id}` — SOFT delete, sets `is_active=false` (keeps doc). Returns `{"message": "Coupon deactivated"}`.

`GET /coupons/stats/overview` — returns `total_coupons, active_coupons, total_uses, total_discount_given, percentage_coupons, free_lifetime_coupons, recent_used_coupons: [{code, uses, type}]` (top 5 by `updated_at`).

**Validation & redemption (any authenticated user):**

`POST /coupons/validate` — body `{ code, plan_id }`. Checks in order:
1. Coupon exists AND `is_active` → 404 "Invalid coupon code"
2. Not expired (timezone-safe compare) → 400 "This coupon has expired"
3. `current_uses < max_uses` → 400 "...reached its usage limit"
4. plan_id allowed (if restricted) → 400 "...not valid for the selected plan"
5. Plan exists → 404 "Plan not found"

On success returns discount preview: `valid, code, coupon_type, description, remaining_uses, expires_at`, plus —
- percentage: `discount_percent, duration_months, original_price, discounted_price, savings_per_month, total_savings, message`
- free_lifetime: `discount_percent=100, duration_months=null, original_price, discounted_price=0, savings_per_month=price, total_savings="lifetime", message="Free lifetime membership"`

**READ-ONLY — does not increment usage.**

`POST /coupons/redeem` — body `{ code, plan_id }`. Re-runs all validation, then records redemption:
- `$inc current_uses` by 1
- `$inc total_discount_given` by `discount_amount`
- `$push usage_history` entry
- `$set updated_at`

Returns `{ redeemed: true, coupon_type, discount_percent, discount_amount, duration_months, is_free_lifetime }`. Called at checkout (§6).

### 5. Payment / Stripe Integration

File `backend/routes/stripe_payments.py`.

`POST /payments/subscribe` — body `{ plan_id, origin_url, coupon_code? }`. Creates Stripe Checkout Session (monthly/yearly/one-time). **Price read from DB (`membership_plans`), never trusted from client.** Writes `payment_transactions` record (status "pending") before redirect. Returns `{ checkout_url, session_id }`.

`POST /payments/activate-free` — body `{ plan_id, coupon_code }` (FREE_LIFETIME). Bypasses Stripe:
1. Re-validate coupon (active, type free_lifetime, not expired, under limit, plan allowed).
2. Record coupon usage (same `$inc`/`$push` as `/redeem`).
3. Update company doc: `membership_plan_id, subscription_status="active", subscription_type="free_lifetime", subscription_started_at, coupon_code_used`.
4. Insert `payment_transactions` record (amount 0, status "completed", type "free_lifetime").

Returns `{ success: true, message, plan_name }`.

Stripe webhook `POST /webhook/stripe` handles `checkout.session.completed`/`expired` to finalize paid subs (standard flow, not coupon-specific).

> **⚠️ KNOWN GAP — close this when replicating.** `/payments/subscribe` accepts `coupon_code` but the current code does NOT translate a PERCENTAGE coupon into a Stripe discount (no Stripe coupon/promotion code attached, `/coupons/redeem` not called for the percentage path). Frontend updates displayed price + passes the code, but the actual Stripe charge is **full price**. Only FREE_LIFETIME is fully wired (via `/payments/activate-free`).
>
> To make percentage discounts real:
> - **(a)** create a Stripe Coupon/Promotion Code, attach to Checkout Session — `checkout_params["discounts"] = [{"coupon": stripe_coupon_id}]`, duration `"repeating"` for `duration_months`; OR
> - **(b)** compute + charge the discounted `unit_amount` for the first Y months via a Stripe subscription schedule.
>
> AND call `/coupons/redeem` (or replicate its `$inc`/`$push`) on successful payment so usage counters + analytics stay accurate.

### 6. End-to-End Process Flows

**A) Admin creates a coupon**
1. Main admin opens `/admin/coupons`.
2. "Create Coupon", picks type (Percentage / Free Lifetime).
3. Fills code (optional), discount %, duration, max uses, expiry, plan restrictions, description. Live "Preview" shows resulting offer.
4. Submit → `POST /coupons`. Backend validates, normalizes code, stores.
5. List refreshes; admin can copy, edit, deactivate, or expand for usage history + analytics.

**B) User redeems a PERCENTAGE coupon (current behavior)**
1. User opens Billing, types code, "Apply Coupon" on a plan.
2. Frontend → `POST /coupons/validate` → shows discounted price + savings.
3. Subscribe → `POST /payments/subscribe` with `coupon_code` → Stripe Checkout. (See §5 gap: discount not yet enforced at Stripe layer.)

**C) User redeems a FREE_LIFETIME coupon (fully working)**
1. Apply code → `/coupons/validate` confirms "Free lifetime membership".
2. Subscribe. Frontend detects `coupon_type === 'free_lifetime'`:
   - `POST /coupons/redeem` (records usage)
   - `POST /payments/activate-free` (activates sub, no Stripe)
3. Company now on plan with `subscription_type` "free_lifetime".

### 7. Frontend (React)

**Admin management page** — `frontend/src/pages/AdminCoupons.js`, route `/admin/coupons` (wrapped in `MainAdminRoute`). Sidebar nav `{ id:'coupons', path:'/admin/coupons', icon:'Ticket', label:'Coupon Codes' }`.
- Stats cards: Total Coupons, Active, Total Uses, Total Savings Given.
- Search box + "Show inactive" toggle.
- Coupon list: type icon (Percent / Crown), code + copy button, status badges (Inactive / Expired / Depleted), uses x/y, expiry.
- Expandable row: applicable plans, remaining uses, total discount given, created date, last 5 usage_history entries.
- Create/Edit modal: type selector, code (auto-gen hint), %/duration (percentage only), max uses, expiry (min today), plan checkboxes (empty = all), description, live preview.
- Default new-coupon expiry = today + 30 days.

**User checkout page** — `frontend/src/pages/Billing.js`.
- "Have a coupon code?" input (auto-uppercases).
- Per-plan "Apply Coupon" → `validateCoupon(planId)`.
- Applied state: green banner with code + message + remove (X).
- Plan card: strikethrough original + discounted price + savings + "Coupon Applied!" badge.
- `handleSubscribe` branches: free_lifetime → redeem + activate-free; otherwise → `/payments/subscribe` with `coupon_code`.

**API client** — shared axios instance `frontend/src/services/api.js` (JWT auto-attached; never raw axios).

### 8. Security & Multi-Tenancy

- Coupon CRUD + stats restricted to main admin (platform owner) only. Company admins explicitly denied.
- validate/redeem available to any authenticated user.
- Plan price ALWAYS read from DB server-side, never client (prevent price tampering).
- Codes globally unique (not per-tenant) — one shared `coupons` collection, not scoped by company_id.
- Redemption records capture user_id + company_id for auditability.
- Deletion is soft (`is_active=false`); history preserved.

### 9. Tests

File `backend/tests/test_coupon_system.py` (pytest + requests against the API). Covers:
- Main admin can list; company admin denied (403/401).
- Stats endpoint shape.
- Create percentage / free_lifetime / auto-generated-code coupons.
- Update, get single, soft-delete (deactivate).
- Validate existing, invalid code → 404, case-insensitive lookup.
- Seeded sample coupons exist: `NPBFWHWHQ3` = 50% off for 3 months (percentage); `VIPFREE2026` = free lifetime.

Run: `cd backend && python -m pytest tests/test_coupon_system.py -v`

### 10. File Inventory

| File | Purpose |
|------|---------|
| `backend/routes/coupons.py` | Core coupon API (CRUD, validate, redeem, stats). |
| `backend/routes/stripe_payments.py` | `/payments/subscribe` + `/payments/activate-free`. |
| `backend/database_mongo.py` | `coupons` collection indexes. |
| `backend/server.py` | Router registration (`coupons_router` @ `/api`). |
| `backend/auth.py` | `get_main_admin` / `get_current_user` deps. |
| `frontend/src/pages/AdminCoupons.js` | Admin management UI. |
| `frontend/src/pages/Billing.js` | User-facing apply/redeem UI. |
| `frontend/src/App.js` | Route `/admin/coupons` (MainAdminRoute). |
| `frontend/src/components/Sidebar.js` | Nav entry "Coupon Codes". |
| `backend/tests/test_coupon_system.py` | Test suite. |

### Quick-Start Checklist

1. Create `coupons` collection + unique index on `code` + index on `is_active`.
2. Port coupon schema (§2) + code-gen rules (§3).
3. Implement API endpoints (§4): admin CRUD + stats, validate, redeem.
4. Gate CRUD/stats behind admin role; validate/redeem behind auth.
5. Wire payment layer (§5): free_lifetime → activate without processor; percentage → attach a real Stripe coupon/discount AND call redeem on successful payment (close §5 gap).
6. Build admin management UI (§7) + checkout apply/redeem UI.
7. Always read plan price server-side; normalize codes uppercase/trim.
8. Port tests (§9) + seed sample coupons.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Billing / discounts / subscriptions |
| Backend | FastAPI (async) + MongoDB |
| Frontend | React + shared axios client |
| Payments | Stripe (Checkout + webhook) |
| Coupon types | percentage (X% off Y months), free_lifetime (100% off, no processor) |
| Multi-tenant | Yes — codes global, redemptions capture company_id |
| Known gap | Percentage discount NOT enforced at Stripe layer in source — close on replication (§5) |
