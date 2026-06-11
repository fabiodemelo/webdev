# System: Cancellation + Retention Module

SaaS subscription cancellation flow with: configurable advance-notice window, one-time stay/retention offers (status-aware: trial vs paid), once-only enforcement per customer, platform-admin-editable policy (no code changes to tune), and optional activity-based seat billing.

**Type:** feature subsystem (subscription doc additions + policy config + API + Stripe wiring + frontend UX). Bolts onto an existing subscription product.

**Reference stack:** FastAPI (Python) + MongoDB-style doc store + React + Stripe. Replace `[Brand]` / `[brand]` and Stripe references as needed.

> **Related:** extends an existing subscription/billing system — see [paid-subscription-system](../paid-subscription-system/SPEC.md). This module adds the cancel/retain layer on top, reusing its `membership_plans`, subscription state block, and Stripe adapter.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap `[Brand]`/`[brand]`, plan IDs, and tune defaults.

---

You are given a task to build a **cancellation + retention module** for a SaaS subscription product.

Reference stack (map onto equivalents):
- **Backend:** Python + FastAPI, async handlers, Pydantic models. Routes restricted to subscription owner (or admin where noted) via an auth dependency returning `sub` (user id), tenant/subscriber id, and role.
- **Database:** document store (MongoDB-style). Cancellation + offer fields added to the existing subscription state block; policy lives on the `platform_settings` singleton.
- **Frontend:** React (function components + hooks), shared API client with JWT auto-attach, toast notifications.
- **Payments:** Stripe (Checkout already wired; this module adds cancel/coupon ops).

### 1. Business Rules

| Rule | Default | Why |
|------|---------|-----|
| Cancellation requires advance notice | 60 days | Predictable revenue tail, time to win-back |
| Subscription stays fully active during notice window | Always | Customer never loses access prematurely |
| Trial subscribers get a one-time stay offer | 10% off / 60 days | Soft incentive to convert |
| Paid subscribers get a one-time retention offer | 30% off / 3 months | Harder save on real revenue |
| Each offer is once-only per customer | Always | No discount stacking, predictable economics |
| Trial offer claimable proactively | Yes | Owner can self-serve before trial ends |
| Paid offer requires an active cancellation request | Yes | Don't burn discount on customers who weren't leaving |
| Reactivation clears cancellation but keeps offer slot | Yes | Customer can change mind without consuming offer |
| Active-seat billing threshold (optional) | 4 worked hours / month | Bill only engaged users; founder seat exempt |

### 2. Data Model — subscription state additions

These fields are added to the subscriber's **subscription state block** (the same embedded block from [paid-subscription-system](../paid-subscription-system/SPEC.md) §3.3). MongoDB has no fixed schema — just write the fields:

| Field | Type | Description |
|-------|------|-------------|
| `cancel_at_period_end` | bool | Default false. Mirrors Stripe's flag. |
| `cancel_reason` | str\|null | Stored verbatim from the cancel request. |
| `cancellation_requested_at` | datetime\|null | Set on cancel; cleared on reactivate/accept-offer. |
| `scheduled_cancellation_date` | datetime\|null | `now + notice_days`. |
| `retention_offer_used_at` | datetime\|null | One-time PAID retention slot. |
| `retention_offer_expires_at` | datetime\|null | When the paid discount stops applying. |
| `trial_offer_used_at` | datetime\|null | One-time TRIAL stay slot (independent of paid). |
| `trial_offer_expires_at` | datetime\|null | When the trial discount stops applying. |

**Why two independent offer slots:** a single customer can be incentivized once during trial (10%/60d) AND once when they later try to cancel as a paid customer (30%/3mo). Sharing a single flag would lock them out of the second.

Suggested index (for ops/reporting on pending cancellations): `{ subscription_status, scheduled_cancellation_date }`.

### 3. Platform Policy (admin-editable JSON)

Stored on the `platform_settings` singleton under a `billing_policy` key (reuse the settings doc from paid-subscription-system §3.5). Loaded on every cancel / accept-offer / seat-usage request.

```json
{
  "cancellation_notice_days": 60,
  "seat_min_hours_per_month": 4,
  "trial_offer": {
    "enabled": true,
    "percent_off": 10,
    "duration_months": 2
  },
  "retention_offer": {
    "enabled": true,
    "percent_off": 30,
    "duration_months": 3
  }
}
```

**Validation (Pydantic):**
- `cancellation_notice_days`: int, 0–365
- `seat_min_hours_per_month`: int, 0–200
- `percent_off`: int, 1–100
- `duration_months`: int, 1–24
- `enabled`: bool

A `get_billing_policy()` normalizer must fall back to defaults (§11) when the key is missing or partial, and ignore unknown keys without raising.

### 4. API Surface

Router prefix `/billing`, mounted under global `/api`. All routes restricted to subscription owner (or admin where noted).

**4.1 `GET /api/billing/subscription`** (owner / admin) — returns full subscription state plus the currently applicable offer:
```json
{
  "plan": { "name": "...", "base_price_cents": 3200, "seat_price_cents": 700 },
  "status": "trialing | active | canceled",
  "cancellation_requested_at": null,
  "scheduled_cancellation_date": null,
  "retention_offer_used_at": null,
  "retention_offer_expires_at": null,
  "trial_offer_used_at": null,
  "trial_offer_expires_at": null,
  "policy": { /* full policy block */ },
  "offer": {
    "kind": "trial" | "retention",
    "available": true,
    "percent_off": 10,
    "duration_months": 2,
    "label": "10% off for 60 days"
  }
}
```
`offer.kind` decided by `status == "trialing"`. `available` = `policy[kind].enabled and not subscription[f"{kind}_offer_used_at"]`. `label` dynamic from policy (`offer_label()` helper).

**4.2 `POST /api/billing/cancel`** (owner) — body `{ reason: str (1–500), feedback?: str }`:
1. Load `policy.cancellation_notice_days`.
2. Compute `scheduled_date = now + timedelta(days=notice_days)`.
3. Stripe: `stripe.Subscription.modify(id, cancel_at=int(scheduled_date.timestamp()), cancellation_details={"feedback": "other", "comment": reason})`. Use `cancel_at` (epoch seconds), NOT `cancel_at_period_end`, so the date precisely matches the policy.
4. DB `$set`: `cancellation_requested_at=now, scheduled_cancellation_date=scheduled_date, cancel_reason=reason, cancel_at_period_end=True`.
5. Audit log: `action="subscription.cancel"`, after payload includes scheduled date.

Response: `{ subscription, scheduled_cancellation_date, retention_offer_available: true }`.

**4.3 `POST /api/billing/retention-offer/accept`** (owner) — no body:
1. Load policy.
2. `is_trialing = status == "trialing"`.
3. `offer_cfg = policy["trial_offer"] if is_trialing else policy["retention_offer"]`.
4. **404** if `not offer_cfg["enabled"]`.
5. **409** if matching `{kind}_offer_used_at` already set.
6. **400** if `not is_trialing and not cancellation_requested_at` (paid offer requires an open cancellation).
7. Compute `expires_at = now + relativedelta(months=offer_cfg["duration_months"])`.
8. Stripe coupon: stable ID `[brand]-{trial-stay|retention}-{percent_off}off-{duration_months}mo`. Retrieve, fall back to create with `percent_off, duration="repeating", duration_in_months`.
9. Stripe subscription: `modify(id, cancel_at=None, cancel_at_period_end=False, coupon=coupon_id)`.
10. DB: clear cancellation fields, `$set {kind}_offer_used_at=now, {kind}_offer_expires_at=expires_at`.
11. Audit log: `subscription.trial-stay-accept` or `subscription.retention-accept`.

Response: `{ subscription, offer_kind: "trial", expires_at }`.

**4.4 `POST /api/billing/reactivate`** (owner) — rescinds an open cancellation WITHOUT consuming the offer slot:
- Stripe: `modify(id, cancel_at=None, cancel_at_period_end=False)`.
- DB `$set`: `cancel_at_period_end=False, cancel_reason=None, cancellation_requested_at=None, scheduled_cancellation_date=None`. Leave `{kind}_offer_used_at` untouched.
- Audit: `subscription.reactivate`.

**4.5 `GET /api/billing/seat-usage?year=YYYY&month=MM`** (owner / admin) — optional. Billable seat count from a `time_entries` collection (clock-in/out docs), using the policy hour threshold. MongoDB aggregation:
```python
pipeline = [
  { "$match": {
      "company_id": company_id,
      "clock_out_at": { "$ne": None },
      "clock_in_at": { "$gte": month_start, "$lt": month_end },
  }},
  { "$group": {
      "_id": "$user_id",
      "secs": { "$sum": { "$divide": [
          { "$subtract": ["$clock_out_at", "$clock_in_at"] }, 1000 ] } },  # ms → secs
  }},
]
```
Count users with `secs / 60 >= policy.seat_min_hours_per_month * 60`. Owner seat always counted.
Response: `{ year, month, min_billable_hours_per_month, rule, billable_seats, total_active_users, non_billable_users }`.

**4.6 `GET / PUT /api/admin/membership/settings`** (platform admin) — reuse the membership-settings endpoint from paid-subscription-system §11; PUT accepts a `billing_policy` block matching §3.

### 5. Stripe Wiring

- Use `cancel_at` (epoch seconds), NOT `cancel_at_period_end`, so the customer's date matches the policy exactly.
- Auto-create coupons with stable IDs the first time each variant is accepted. ID format: `[brand]-{trial-stay|retention}-{percent}off-{months}mo`.
- Idempotent retrieve-or-create:
```python
import stripe
try:
    stripe.Coupon.retrieve(coupon_id)
except stripe.error.InvalidRequestError:
    stripe.Coupon.create(
        id=coupon_id, percent_off=percent_off,
        duration="repeating", duration_in_months=duration_months, name=name,
    )
```
- When admin changes percent/months in the UI, the next acceptance creates a fresh coupon. Existing acceptances keep their original coupon (locked-in pricing).
- Optional: in the existing Stripe webhook (paid-subscription-system §6), clear `cancellation_requested_at` when `customer.subscription.deleted` fires.

### 6. Frontend UX (React)

**6.1 Customer billing page** — three states drive UI:

| State | Banner |
|-------|--------|
| `scheduled_cancellation_date != null` | Amber: "Cancellation scheduled for [date]." + offer CTA (if available) + "Keep my subscription" CTA |
| `retention_offer_expires_at != null && !scheduled_cancellation_date` | Emerald: "30% retention discount active. Applies through [date]." |
| `trial_offer_expires_at != null && !scheduled_cancellation_date` | Emerald: "10% trial-stay discount active. Applies through [date]." |

Cancel button → confirmation dialog:
- Headline: "Cancel subscription"
- Body: "[Brand] requires N days advance notice. Subscription stays active until [date]."
- If offer available: emerald box "One-time offer: stay with [Brand] and get [label]."
- Required reason textarea (min 3 chars)
- Two buttons: "Keep my subscription" (secondary) + "Schedule cancellation" (red primary)

**6.2 Platform admin policy tab** (inside the membership settings page):
- **Rules card** — two inputs side-by-side: cancellation notice (days), seat threshold (hours/month).
- **Trial stay offer card** — toggle (Enabled/Disabled), percent input, months input. Greyed out when disabled.
- **Retention offer card** — same shape.
- Save bar at bottom (PUT membership settings with the `billing_policy` block).
- Show approximate days: `≈ {duration_months * 30} days` under the months input.

### 7. Edge Cases

| Case | Handling |
|------|----------|
| Cancel, accept retention, later cancel again | Allowed. New cancellation creates a new scheduled date. Retention offer no longer available. |
| Used trial offer, converted to paid, later cancels | Trial slot used; paid slot still available. |
| Admin disables an offer mid-cancellation | Banner CTA disappears immediately. Endpoint returns 404 if accept attempted. |
| Admin changes percent 30→25 between request and accept | Accept uses current value at acceptance time (new coupon created). |
| Reactivate during the 60-day window | Stripe `cancel_at=None`, DB cleared, offer slot preserved. |
| Customer downgrades plan during notice window | Allowed; downgrade applies at next period start, cancellation still fires on scheduled date. |
| Refund during notice window | Out of band — Stripe support tools. Don't touch DB fields. |
| Mass/bulk cancel | Not supported. Single-cancel only. |
| Currency / multi-currency | Coupons are percent-based → currency-agnostic. |

### 8. Audit Log Actions

One row per mutation (reuse the platform audit-log collection if one exists):

| Action | When |
|--------|------|
| `subscription.cancel` | Cancellation requested |
| `subscription.reactivate` | Cancellation rescinded |
| `subscription.retention-accept` | Paid retention offer accepted |
| `subscription.trial-stay-accept` | Trial stay offer accepted |
| `platform.billing-policy.update` | Admin saved policy changes |

Each `after` payload should include effective values (scheduled date, expires date, percent, months).

### 9. Test Checklist

**Functional:**
- Cancel + accept retention → scheduled date cleared, coupon present in Stripe, retention slot stamped
- Cancel + accept retention + cancel again → second cancel returns `retention_offer_available: false`
- Trial user accepts trial offer proactively (no cancellation) → succeeds
- Paid user attempts trial offer endpoint → 400
- Paid user attempts retention offer without cancellation → 400
- Admin disables trial offer → trial user GET shows `offer.available: false`
- Admin changes percent → next acceptance creates new coupon ID, old coupon still attached to prior acceptance
- Reactivate clears scheduled date but `retention_offer_used_at` unchanged

**Edge / regression:**
- Stripe `cancel_at` matches `scheduled_cancellation_date` to within 1 second
- Audit log entries written for every mutation
- Policy JSON fully-missing → defaults applied
- Policy JSON with extra unknown keys → ignored, no validation error
- Concurrent requests to accept-offer → second returns 409 (use a guarded `find_one_and_update` on the `{kind}_offer_used_at` field for atomicity)

**Activity-seat (if used):**
- User with 239 minutes → not billed
- User with 240 minutes → billed
- Owner with 0 minutes → still billed
- Sum across multiple time_entries in same month aggregates correctly

### 10. File Map (typical)

| Path | Purpose |
|------|---------|
| `backend/lib/billing_policy.py` | `get_billing_policy()`, defaults, normalizer, `offer_label()` |
| `backend/routes/billing.py` | `/subscription`, `/cancel`, `/reactivate`, `/retention-offer/accept`, `/seat-usage` |
| `backend/routes/admin_settings.py` | PUT membership settings includes `billing_policy` |
| `backend/server.py` | Router registration (`billing_router` @ `/api`) |
| `backend/auth.py` | Owner / admin auth dependencies |
| `frontend/src/pages/Billing.js` | Customer-facing banner + cancel dialog |
| `frontend/src/pages/admin/MembershipSettings.js` | Admin "Billing Policy" tab |
| `frontend/src/services/api.js` | Shared axios client (JWT auto-attach) |

### 11. Defaults (recommended starting point)

```python
DEFAULT_BILLING_POLICY = {
    "cancellation_notice_days": 60,
    "seat_min_hours_per_month": 4,
    "trial_offer":     { "enabled": True, "percent_off": 10, "duration_months": 2 },
    "retention_offer": { "enabled": True, "percent_off": 30, "duration_months": 3 },
}
```

**Tuning advice:**
- Cancellation notice under 30 days = mostly cosmetic. 60–90 days is the typical B2B SaaS sweet spot. Avoid > 120 days unless you have a clear legal reason.
- Trial offer ≤ 15% keeps it from cannibalizing real revenue. The point is to nudge.
- Retention offer 25–40% for 2–3 months. Lower = stingy, higher = trains customers to threaten cancel.
- Seat threshold 4–8 hours/month. Lower captures too many, higher leaves money on the table.
- A/B: run the same offer at 25% vs 30% on different cohorts via a percentage-based flag (not part of this spec).

### 12. Compliance / Legal Hooks

- Cancellation request must be acknowledged in writing (email) within 24 hours. Wire this off the audit log entry — reuse the [email-template-system](../email-template-system/SPEC.md) with a `cancellation_ack` template key.
- **US FTC Click-to-Cancel rule (2024)** requires cancellation to be at least as easy as signup. The cancel button is on the Billing page; if signup is shorter than the cancel dialog, consider removing the textarea requirement.
- For EU customers, the notice period must be reasonable (case law generally permits 30 days; 60 is defensible for B2B).
- Store the original cancellation reason verbatim in `cancel_reason` — useful for renewal disputes.
- The Stripe `cancellation_details.comment` should mirror the customer's reason for the Stripe-side support record.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Billing / retention / churn |
| Backend | FastAPI (async) + document store |
| Frontend | React + shared axios client |
| Payments | Stripe (`cancel_at` epoch + stable-ID coupons) |
| Key concepts | Advance-notice window, two independent once-only offer slots (trial + paid), admin-editable policy JSON on `platform_settings`, reactivation preserves slot, optional activity-seat billing |
| Bolts onto | [paid-subscription-system](../paid-subscription-system/SPEC.md) (subscription state, settings, Stripe adapter) |
| Compliance | FTC Click-to-Cancel (2024), EU notice-period reasonableness |
