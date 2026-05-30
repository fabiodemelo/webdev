# System: Cancellation + Retention Module

SaaS subscription cancellation flow with: configurable advance-notice window, one-time stay/retention offers (status-aware: trial vs paid), once-only enforcement per customer, platform-admin-editable policy (no code changes to tune), and optional activity-based seat billing.

**Type:** feature subsystem (DB schema additions + policy config + API + Stripe wiring + frontend UX). Bolts onto an existing subscription product.

**Reference stack:** Prisma/SQL (Subscription + PlatformConfig models) + Node API + React + Stripe. Replace `[Brand]` / `[brand]` and Stripe references as needed.

> **Related:** extends an existing subscription/billing system — see [paid-subscription-system](../paid-subscription-system/SPEC.md). This module adds the cancel/retain layer on top.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap `[Brand]`/`[brand]`, plan IDs, and tune defaults.

---

You are given a task to build a **cancellation + retention module** for a SaaS subscription product.

Reference stack (map onto equivalents):
- **DB:** Prisma/SQL — add fields to `Subscription` + `PlatformConfig`.
- **API:** Node (apps/api). Routes restricted to subscription owner (or admin where noted).
- **Frontend:** React (apps/web).
- **Payments:** Stripe.

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

### 2. Database Schema (Subscription model additions)

```prisma
model Subscription {
  // … existing fields …
  // Cancellation
  cancelAtPeriodEnd          Boolean   @default(false)
  cancelReason               String?
  cancellationRequestedAt    DateTime?
  scheduledCancellationDate  DateTime?
  // One-time PAID retention offer
  retentionOfferUsedAt       DateTime?
  retentionOfferExpiresAt    DateTime?
  // One-time TRIAL stay offer (independent of paid retention)
  trialOfferUsedAt           DateTime?
  trialOfferExpiresAt        DateTime?
}
```

**Why two independent offer slots:** a single customer can be incentivized once during trial (10%/60d) AND once when they later try to cancel as a paid customer (30%/3mo). Sharing a single flag would lock them out of the second.

### 3. Platform Policy (admin-editable JSON)

Stored on a singleton config row (`PlatformConfig.billingPolicy` or equivalent). Loaded on every cancel / accept-offer / seat-usage request.

```json
{
  "cancellationNoticeDays": 60,
  "seatMinHoursPerMonth": 4,
  "trialOffer": {
    "enabled": true,
    "percentOff": 10,
    "durationMonths": 2
  },
  "retentionOffer": {
    "enabled": true,
    "percentOff": 30,
    "durationMonths": 3
  }
}
```

**Validation (Zod or equivalent):**
- `cancellationNoticeDays`: int, 0–365
- `seatMinHoursPerMonth`: int, 0–200
- `percentOff`: int, 1–100
- `durationMonths`: int, 1–24
- `enabled`: boolean

Normalizer must fall back to defaults when JSON is missing or partial.

### 4. API Endpoints

All routes restricted to subscription owner (or admin where noted).

**4.1 `GET /api/v1/billing/subscription`** (owner / admin) — returns full subscription state plus the currently applicable offer:
```json
{
  "plan": { "name": "...", "basePriceCents": 3200, "seatPriceCents": 700 },
  "status": "trialing | active | canceled",
  "cancellationRequestedAt": null,
  "scheduledCancellationDate": null,
  "retentionOfferUsedAt": null,
  "retentionOfferExpiresAt": null,
  "trialOfferUsedAt": null,
  "trialOfferExpiresAt": null,
  "policy": { /* full policy block */ },
  "offer": {
    "kind": "trial" | "retention",
    "available": true,
    "percentOff": 10,
    "durationMonths": 2,
    "label": "10% off for 60 days"
  }
}
```
`offer.kind` decided by `subscription.status === 'trialing'`. `available` = `policy.<kind>.enabled && !subscription.<kind>OfferUsedAt`. `label` dynamic from policy.

**4.2 `POST /api/v1/billing/cancel`** (owner) — body `{ reason: string (1–500), feedback?: string }`:
1. Load `policy.cancellationNoticeDays`.
2. Compute `scheduledDate = now + policy.cancellationNoticeDays days`.
3. Stripe: `subscriptions.update(id, { cancel_at: floor(scheduledDate.getTime()/1000), cancellation_details: { feedback: 'other', comment } })`. Use `cancel_at` (epoch seconds), NOT `cancel_at_period_end`, so the date precisely matches the policy.
4. DB: set `cancellationRequestedAt = now`, `scheduledCancellationDate = scheduledDate`, `cancelReason = reason`, `cancelAtPeriodEnd = true`.
5. Audit log: `action='subscription.cancel'`, after payload includes scheduled date.

Response: `{ subscription, scheduledCancellationDate, retentionOfferAvailable: true }`.

**4.3 `POST /api/v1/billing/retention-offer/accept`** (owner) — no body:
1. Load policy.
2. `isTrialing = subscription.status === 'trialing'`.
3. `offerCfg = isTrialing ? policy.trialOffer : policy.retentionOffer`.
4. **404** if `!offerCfg.enabled`.
5. **409** if matching `<kind>OfferUsedAt` already set.
6. **400** if `!isTrialing && !cancellationRequestedAt` (paid offer requires an open cancellation).
7. Compute `expiresAt = now + offerCfg.durationMonths months`.
8. Stripe coupon: stable ID `[brand]-{trial-stay|retention}-{percentOff}off-{durationMonths}mo`. Retrieve, fall back to create with `{ percent_off, duration: 'repeating', duration_in_months }`.
9. Stripe subscription: `update(id, { cancel_at: null, cancel_at_period_end: false, coupon: couponId })`.
10. DB: clear cancellation fields, set `<kind>OfferUsedAt = now`, `<kind>OfferExpiresAt = expiresAt`.
11. Audit log: `subscription.trial-stay-accept` or `subscription.retention-accept`.

Response: `{ subscription, offerKind: "trial", expiresAt }`.

**4.4 `POST /api/v1/billing/reactivate`** (owner) — rescinds an open cancellation WITHOUT consuming the offer slot:
- Stripe: `subscriptions.update(id, { cancel_at: null, cancel_at_period_end: false })`.
- DB: `cancelAtPeriodEnd=false, cancelReason=null, cancellationRequestedAt=null, scheduledCancellationDate=null`. Leave `<kind>OfferUsedAt` untouched.
- Audit: `subscription.reactivate`.

**4.5 `GET /api/v1/billing/seat-usage?year=YYYY&month=MM`** (owner / admin) — optional. Billable seat count based on policy hour threshold:
```sql
SELECT userId, SUM(TIMESTAMPDIFF(SECOND, clockInAt, clockOutAt)) AS secs
FROM TimeEntry
WHERE companyId = ?
  AND clockOutAt IS NOT NULL
  AND clockInAt >= ?monthStart
  AND clockInAt <  ?monthEnd
GROUP BY userId;
```
Counts users with `secs / 60 >= policy.seatMinHoursPerMonth * 60`. Owner seat always counted.
Response: `{ year, month, minBillableHoursPerMonth, rule, billableSeats, totalActiveUsers, nonBillableUsers }`.

**4.6 `GET / PATCH /api/v1/platform/config`** (platform admin) — standard config CRUD. PATCH accepts `billingPolicy` matching §3 schema.

### 5. Stripe Wiring

- Use `cancel_at` (epoch seconds), NOT `cancel_at_period_end`, so the customer's date matches the policy exactly.
- Auto-create coupons with stable IDs the first time each variant is accepted. ID format: `[brand]-{trial-stay|retention}-{percent}off-{months}mo`.
- Idempotent retrieve-or-create:
```js
try { await stripe.coupons.retrieve(id) }
catch { await stripe.coupons.create({ id, percent_off, duration: 'repeating', duration_in_months, name }) }
```
- When admin changes percent/months in the UI, the next acceptance creates a fresh coupon. Existing acceptances keep their original coupon (locked-in pricing).
- Optional: configure Stripe webhook to clear `cancellationRequestedAt` when `customer.subscription.deleted` fires.

### 6. Frontend UX

**6.1 Customer billing page** — three states drive UI:

| State | Banner |
|-------|--------|
| `scheduledCancellationDate != null` | Amber: "Cancellation scheduled for [date]." + offer CTA (if available) + "Keep my subscription" CTA |
| `retentionOfferExpiresAt != null && !scheduledCancellationDate` | Emerald: "30% retention discount active. Applies through [date]." |
| `trialOfferExpiresAt != null && !scheduledCancellationDate` | Emerald: "10% trial-stay discount active. Applies through [date]." |

Cancel button → confirmation dialog:
- Headline: "Cancel subscription"
- Body: "[Brand] requires N days advance notice. Subscription stays active until [date]."
- If offer available: emerald box "One-time offer: stay with [Brand] and get [label]."
- Required reason textarea (min 3 chars)
- Two buttons: "Keep my subscription" (secondary) + "Schedule cancellation" (red primary)

**6.2 Platform admin policy tab:**
- **Rules card** — two inputs side-by-side: cancellation notice (days), seat threshold (hours/month).
- **Trial stay offer card** — toggle (Enabled/Disabled), percent input, months input. Greyed out when disabled.
- **Retention offer card** — same shape.
- Save bar at bottom.
- Show approximate days: `≈ {durationMonths * 30} days` under the months input.

### 7. Edge Cases

| Case | Handling |
|------|----------|
| Cancel, accept retention, later cancel again | Allowed. New cancellation creates a new scheduled date. Retention offer no longer available. |
| Used trial offer, converted to paid, later cancels | Trial slot used; paid slot still available. |
| Admin disables an offer mid-cancellation | Banner CTA disappears immediately. Endpoint returns 404 if accept attempted. |
| Admin changes percent 30→25 between request and accept | Accept uses current value at acceptance time (new coupon created). |
| Reactivate during the 60-day window | Stripe `cancel_at: null`, DB cleared, offer slot preserved. |
| Customer downgrades plan during notice window | Allowed; downgrade applies at next period start, cancellation still fires on scheduled date. |
| Refund during notice window | Out of band — Stripe support tools. Don't touch DB fields. |
| Mass/bulk cancel | Not supported. Single-cancel only. |
| Currency / multi-currency | Coupons are percent-based → currency-agnostic. |

### 8. Audit Log Actions

One row per mutation:

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
- Cancel + accept retention + cancel again → second cancel returns `retentionOfferAvailable: false`
- Trial user accepts trial offer proactively (no cancellation) → succeeds
- Paid user attempts trial offer endpoint → 400
- Paid user attempts retention offer without cancellation → 400
- Admin disables trial offer → trial user GET shows `offer.available: false`
- Admin changes percent → next acceptance creates new coupon ID, old coupon still attached to prior acceptance
- Reactivate clears scheduled date but `retentionOfferUsedAt` unchanged

**Edge / regression:**
- Stripe `cancel_at` matches `scheduledCancellationDate` to within 1 second
- Audit log entries written for every mutation
- Policy JSON fully-missing → defaults applied
- Policy JSON with extra unknown keys → ignored, no validation error
- Concurrent requests to accept-offer → second returns 409

**Activity-seat (if used):**
- User with 239 minutes → not billed
- User with 240 minutes → billed
- Owner with 0 minutes → still billed
- Sum across multiple TimeEntries in same month aggregates correctly

### 10. File Map (typical)

| Path | Purpose |
|------|---------|
| `packages/db/prisma/schema.prisma` | Subscription + PlatformConfig fields |
| `packages/db/prisma/migrations/<ts>_subscription_retention/migration.sql` | Cancellation + 2 offer-slot fields |
| `packages/db/prisma/migrations/<ts>_billing_policy/migration.sql` | PlatformConfig.billingPolicy JSON column |
| `apps/api/src/lib/billing-policy.ts` | `getBillingPolicy()`, defaults, normalizer, `offerLabel()` |
| `apps/api/src/routes/billing/index.ts` | `/subscription`, `/cancel`, `/reactivate`, `/retention-offer/accept`, `/seat-usage` |
| `apps/api/src/routes/platform/config.ts` | PATCH schema includes billingPolicy |
| `apps/web/src/pages/Billing.tsx` | Customer-facing banner + cancel dialog |
| `apps/web/src/pages/platform/SiteSettings.tsx` | Admin "Billing Policy" tab |
| `apps/web/src/layouts/AppLayout.tsx` | Sidebar link to the policy tab |

### 11. Defaults (recommended starting point)

```js
const DEFAULT_BILLING_POLICY = {
  cancellationNoticeDays: 60,
  seatMinHoursPerMonth: 4,
  trialOffer:     { enabled: true, percentOff: 10, durationMonths: 2 },
  retentionOffer: { enabled: true, percentOff: 30, durationMonths: 3 },
};
```

**Tuning advice:**
- Cancellation notice under 30 days = mostly cosmetic. 60–90 days is the typical B2B SaaS sweet spot. Avoid > 120 days unless you have a clear legal reason.
- Trial offer ≤ 15% keeps it from cannibalizing real revenue. The point is to nudge.
- Retention offer 25–40% for 2–3 months. Lower = stingy, higher = trains customers to threaten cancel.
- Seat threshold 4–8 hours/month. Lower captures too many, higher leaves money on the table.
- A/B: run the same offer at 25% vs 30% on different cohorts via a percentage-based flag (not part of this spec).

### 12. Compliance / Legal Hooks

- Cancellation request must be acknowledged in writing (email) within 24 hours. Wire this off the audit log entry.
- **US FTC Click-to-Cancel rule (2024)** requires cancellation to be at least as easy as signup. The cancel button is on the Billing page; if signup is shorter than the cancel dialog, consider removing the textarea requirement.
- For EU customers, the notice period must be reasonable (case law generally permits 30 days; 60 is defensible for B2B).
- Store the original cancellation reason verbatim in `cancelReason` — useful for renewal disputes.
- The Stripe `cancellation_details.comment` should mirror the customer's reason for the Stripe-side support record.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Billing / retention / churn |
| DB | Prisma/SQL — Subscription + PlatformConfig additions |
| API | Node (apps/api), owner/admin-scoped |
| Frontend | React (apps/web) |
| Payments | Stripe (`cancel_at` epoch + stable-ID coupons) |
| Key concepts | Advance-notice window, two independent once-only offer slots (trial + paid), admin-editable policy JSON, reactivation preserves slot, optional activity-seat billing |
| Bolts onto | [paid-subscription-system](../paid-subscription-system/SPEC.md) |
| Compliance | FTC Click-to-Cancel (2024), EU notice-period reasonableness |
