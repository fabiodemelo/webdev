# System: Financial Dashboard, Forecast & Reports

Super-admin financial analytics suite for a multi-tenant SaaS: MRR/ARR overview, subscriber billing table with overage math, payment history + failed-payment dunning queue with admin actions, 12-month revenue forecast from growth trend, and receipt history with itemized email resend. Reads billing state written by the subscription/webhook layer — this system is the reporting + operations surface on top of it.

**Type:** medium full-feature subsystem (analytics API + 5 admin pages + shared tab nav + CSV export + dunning/receipt emails).

**Reference stack:** FastAPI (Python) + MySQL + React + Tailwind + lucide-react icons + Stripe (Balance, Invoices). No chart library — all charts are CSS bar charts.

> **Related:** [paid-subscription-system](../paid-subscription-system/SPEC.md) owns the data this dashboard reads: plans, checkout, the webhook reconciler that maintains `subscription_status` / `is_paid` / `payment_retry_count` / `grace_period_ends` on each subscriber, and the `payment_transactions` ledger. Build that first (or an equivalent). This spec includes the webhook dunning-state logic it depends on (§7) so it can stand alone if needed.

---

## Integration Prompt

> Paste everything below this line into the target project.

---

You are given a task to build a **financial dashboard with forecast and reports** in the codebase.

Reference stack (map onto project equivalents if different):
- **Frontend:** React (function components + hooks), react-router, Tailwind, lucide-react, toast notifications (sonner). No charting library — bar charts are plain divs with percentage widths.
- **Backend:** Python + FastAPI, async handlers. One admin router, super-admin gated on every endpoint.
- **Database:** MySQL. Reads: subscriber rows (`companies` table), `membership_plans`, `payment_transactions`, `platform_settings`. Writes: dunning/grace columns on subscriber rows, `receipt_sent` on transaction rows.
- **Payment provider:** Stripe (Balance retrieve, Invoice list/pay). Optional — every Stripe call is wrapped so the dashboard degrades gracefully with no provider connected.
- **Email:** transactional email service with an HTML template wrapper (reference: SendGrid).

Replace "company" with the right subscriber noun for the product (user, account, team, workspace).

### 1. Overview

Five admin pages sharing one tab navigation, backed by one route file:

| Tab | Route | Purpose |
|-----|-------|---------|
| Overview | `/admin/financial` | MRR/ARR/overage/lifetime revenue, Stripe balance, status counts, conversion + churn, revenue-by-plan bars, 6-month subscriber trend, billing health |
| Subscribers | `/admin/financial/subscribers` | Filterable billing table per company: plan, status, MRR, overages, limits usage, CC on file. CSV export |
| Payments | `/admin/financial/payments` | Sub-tabs: transaction history (filterable) + failed-payment queue with Retry / Remind / +7 days grace actions |
| Forecast | `/admin/financial/forecast` | 12-month history + 12-month projection of subscribers and MRR from growth trend; risk cards |
| Receipts | `/admin/financial/receipts` | Paid-transaction receipt log, itemized receipt email resend |

### 2. Architecture

| Component | Responsibility |
|-----------|----------------|
| Financial router | Single backend route file, prefix `/admin/financial`, tag `financial`. Every handler: auth dependency + explicit super-admin role check (403 otherwise). |
| Metric calculators | MRR/ARR/overage/churn/conversion computed on request from live tables/queries — no denormalized metrics tables, no caching. Acceptable at small subscriber counts (see §10 performance note). |
| Provider adapter reuse | Stripe calls import the existing `configure_stripe()` helper from the payments module — API keys stay in one place (platform settings, test/live aware). |
| Graceful provider degradation | Stripe balance fetch wrapped in try/except → returns `null`; UI renders "—  Not connected" card. Dashboard fully works without Stripe. |
| Tab nav component | One shared `FinancialTabNav` renders page title + 5 tab buttons via router navigation. Each page imports it above content — tabs are separate routes/pages, NOT client-side tab state, so each tab is deep-linkable and loads only its own data. |
| Dunning actions | Admin-triggered: retry open invoice via provider, send tiered reminder email, extend grace period. State transitions mirror the webhook reconciler's. |
| Email builders | Two pure functions returning `(subject, html_body)`: tiered dunning email (content escalates with retry count), itemized receipt email (plan + overage rows + coupon discount + total). Wrapped by the app's standard email template helper. |
| CSV export | Backend streams CSV (`StreamingResponse`, `text/csv`, dated filename) by reusing the subscribers endpoint function directly — one source of truth for row math. |

### 3. Data Model (read + written)

**3.1 Subscriber row (`companies` table) — billing fields this system reads:**

| Field | Meaning |
|-------|---------|
| `subscription_status` | `"active" \| "trialing" \| "past_due" \| "cancelled" \| null/none/""` |
| `subscription_type` | `"free_lifetime"` marks comped accounts — excluded from all revenue math, counted separately |
| `membership_plan_id` | ref → `membership_plans` |
| `stripe_subscription_id` | provider subscription ref (needed for invoice retry) |
| `is_paid`, `cc_valid`, `cc_last4`, `cc_expiry` | card-on-file state maintained by webhooks |
| `payment_retry_count` | dunning counter (webhook increments, success resets to 0) |
| `last_payment_failure` (datetime), `last_payment_error` (string) | shown in failed queue |
| `grace_period_ends` (datetime \| null) | active grace window; admin can extend |
| `subscription_started_at` | used to reconstruct subscriber-count history |

**3.2 Plan (`membership_plans`):** `name`, `price` (monthly), `yearly_price`, `lifetime_price`, `billing_cycle` (`monthly|yearly|one_time`), `user_limit` (admin seats), `client_limit` (customers).

**3.3 Transaction ledger (`payment_transactions`):** `session_id`, `company_id`, `plan_id`, `amount_total` (provider cents) or `amount` (dollars fallback), `currency`, `payment_status` (`paid|pending|failed|expired`), `status`, `stripe_subscription_id`, `receipt_sent` (bool), `receipt_sent_at`, `created_at`, `updated_at`.

> Amount rule everywhere: `amount_total / 100` if present, else `amount` as-is.

**3.4 Settings singleton (`platform_settings` table, row `id = 'membership_settings'`):** `overage_price_per_customer` (default 5.0), `overage_price_per_user` (default 10.0), `grace_period_days` (default 14).

### 4. Metric Formulas (exact)

**MRR** — sum over ACTIVE subscribers, skipping `free_lifetime` and plan-less:
- monthly plan → `price`
- yearly plan → `(yearly_price or price × 12 × 0.8) / 12` (0.8 = assumed 20% yearly discount when no explicit yearly price)
- one_time plan → contributes 0 to MRR; `lifetime_price or price` accumulates into a separate `lifetime_total`

**ARR** = `MRR × 12`.

**Overage revenue estimate** — per active subscriber:
```
admin_count    = active users − customer-role users
overage_users     = max(0, admin_count − user_limit)      if user_limit > 0 else 0
overage_customers = max(0, customer_count − client_limit)  if client_limit > 0 else 0
overage_amount = overage_users × overage_user_price + overage_customers × overage_customer_price
```
Limit of 0 = unlimited (UI shows `∞`). `total_mrr = mrr + Σ overage_amount`.

**Trial→paid conversion** = `active / count(status ∈ {active, trialing, cancelled, past_due}) × 100`.

**Churn (monthly)** = `cancelled_last_30d / (active + cancelled_last_30d) × 100` where cancelled_last_30d = status "cancelled" AND `updated_at` ≥ now−30d.

**Subscriber history** (no snapshots stored) — for each of the last N months: `count(status="active" AND subscription_started_at ≤ month_date)`. Approximation: months = 30-day steps; cancelled companies vanish from history retroactively. Good enough for trend, not accounting.

**Forecast algorithm** (linear, trend-based):
```
avg_recent = mean(subscribers of last 3 months)
avg_older  = mean(subscribers of months 4-6 back)
growth_per_month   = (avg_recent − avg_older) / 3           (0 if avg_older == 0)
monthly_growth_%   = (avg_recent − avg_older) / avg_older × 100
avg_mrr_per_sub    = current_mrr / current_subscribers
for m in 1..12:
    projected_subscribers = max(0, round(current + growth_per_month × m))
    projected_mrr         = projected_subscribers × avg_mrr_per_sub
```
Risk block: count of `trialing` (may churn at trial end) + count of `past_due`.

### 5. API Endpoints (all super-admin only → 403 otherwise)

| Method | Path | Returns |
|--------|------|---------|
| GET | `/admin/financial/overview` | `{companies:{total,active,trialing,past_due,cancelled,no_subscription,paid,cc_valid,grace_period,free_lifetime}, revenue:{mrr,arr,overage_estimate,total_mrr,lifetime_total,by_plan:{name:{mrr,count,type}}}, metrics:{conversion_rate,churn_rate,recent_payments,failed_payments}, stripe_balance:{available,pending,currency}\|null, mrr_history:[{month,subscribers}×6]}` |
| GET | `/admin/financial/subscribers` | query: `status` (incl. sentinel `no_subscription` → `(subscription_status IS NULL OR subscription_status IN ('none',''))`), `plan`, `is_paid`, `search` (`name LIKE ? OR subdomain LIKE ?`, case-insensitive collation), `sort_by` (whitelist: name/created_at/subscription_status), `sort_dir`, `limit` (≤200), `skip` → `ORDER BY ... LIMIT ? OFFSET ?`. Returns `{subscribers:[...per-company row with full billing math, see §3-§4], total}` |
| GET | `/admin/financial/subscribers/export` | CSV stream, filename `subscribers_YYYYMMDD.csv`. Calls the subscribers handler function directly (limit 200, sorted by name) and writes DictWriter rows |
| GET | `/admin/financial/revenue-by-plan` | `{plans:[{plan, mrr, subscribers}]}` sorted by MRR desc (chart feed) |
| GET | `/admin/financial/payments` | query: `status`, `limit` (≤200), `skip`. `{transactions:[{id, company_name, plan_name, amount, currency, payment_status, created_at, ...}], total}` — names resolved per row |
| GET | `/admin/financial/failed-payments` | companies where `status="past_due"` OR `payment_retry_count>0` OR `last_payment_failure≠null`, sorted by last failure desc. `{companies:[...], count}` |
| POST | `/admin/financial/retry-payment/{company_id}` | Lists provider open invoices for the company's subscription, pays the newest. On success resets ALL dunning state (`is_paid=true, cc_valid=true, status=active, retry_count=0, grace=null, errors=null`). Card decline → `{status:"failed", message}` (200, not exception). No open invoice → `{status:"no_invoice"}` |
| POST | `/admin/financial/extend-grace/{company_id}` | body `{days}` (default 7). `new_end = (grace_period_ends or now) + days` |
| POST | `/admin/financial/send-reminder/{company_id}` | Finds company's active admin user, builds tiered dunning email from `payment_retry_count`, sends |
| GET | `/admin/financial/receipts` | query: `company_id`, `limit`, `skip`. Transactions with `payment_status="paid"`, company/plan names + `receipt_sent` flag resolved |
| POST | `/admin/financial/receipts/{transaction_id}/resend` | Rebuilds itemized receipt email, sends to company admin, sets `receipt_sent=true, receipt_sent_at` |

Response convention: plain JSON objects; errors via HTTP status + `detail`. Webhook-driven state (below) always returns 200.

### 6. Email Builders

**6.1 Tiered dunning email** — `build_dunning_email(user_name, company_name, retry_count)`:

| retry_count | Subject | Tone / CTA color |
|-------------|---------|------------------|
| ≤1 | "Payment failed for {company} — please update your card" | informational, brand-blue button |
| 2 | "Action required: update payment for {company}" | urgent, amber button, "one final attempt" warning |
| ≥3 | "Final notice: {company} account will be downgraded" | red button, downgrade + grace-period consequence |

All link to the app's billing page. HTML inline-styled (email-safe), passed through the app's standard email template wrapper.

**6.2 Itemized receipt** — `build_receipt_email(company_name, plan_name, plan_price, overage_users, overage_user_price, overage_customers, overage_customer_price, discount, coupon_code, total)`:
- Table rows: plan line always; overage-user and overage-customer lines only when count > 0 (shown as `N × $unit`); coupon line only when discount > 0 (green, negative amount); bold total footer.
- Subject: "{Brand} Monthly Receipt — {company}".

### 7. Webhook Dunning State (dependency — lives in payments module)

The failed-queue and grace logic only work if the provider webhook maintains state:

- `invoice.payment_failed` → `retry_count += 1`, stamp `last_payment_failure` + `last_payment_error`, `status = past_due`. When `retry_count ≥ 3` also: `is_paid=false, cc_valid=false, grace_period_ends = now + grace_period_days` (from settings).
- `invoice.paid` → full reset: `is_paid=true, cc_valid=true, status=active, grace=null, retry_count=0, errors=null`.
- `checkout.session.completed` → mark transaction paid, activate subscription, set `is_paid/cc_valid=true`, capture `cc_last4`/`cc_expiry` from the provider's payment-method list.
- `customer.subscription.deleted` → `status=cancelled`.
- Webhook handler always returns 200 (even on internal error) to stop provider retries; verifies signature when a real webhook secret is configured.

### 8. Frontend Pages

**8.1 Shared shell.** Every page: `<AdminLayout><FinancialTabNav /><content /></AdminLayout>`. FinancialTabNav = title row (icon + "Financial Dashboard") + horizontally scrollable tab buttons; active tab = filled accent bg, others gray hover; navigation via router (`navigate(tab.path)`), active detection via `location.pathname`.

**8.2 Every page implements:** loading state (centered spinning icon), empty state (icon + message, e.g. "No failed payments / All companies are in good standing"), error state (toast on fetch failure), `max-w-7xl mx-auto` content column on `bg-gray-50/50` canvas.

**8.3 Overview** — 3 StatCard rows (revenue ×4, status counts ×5, metrics ×4) + 2-col chart section + billing-health strip.
- `StatCard {icon, label, value, sub, color, trend}`: white rounded-xl card, tinted icon chip, big value, small label, optional sub-line and up/down trend arrow.
- Revenue-by-plan chart: horizontal CSS bars — width = `mrr / max_mrr × 100%` (min 4%), color cycled from an 8-color palette array, right-aligned `$X/mo` + subscriber count.
- Subscriber trend: labeled rows with blue bars, count rendered inside bar end.
- Billing health: 4 centered big numbers (valid CC / paid / in grace / free lifetime).

**8.4 Subscribers** — filter bar (debounced search 300ms, status select, paid select, Export CSV button, total count) + 11-column table: Company (+subdomain sub-line), Plan, Status badge, Paid check/cross icon, MRR, Overages (amber when >0), Total/mo, Users `admin_count/limit` (∞ when 0, amber when over), Customers same, CC `•••• 1234`, Since date. Status badge config: active=green/check, trialing=amber/clock, past_due=red/triangle, cancelled+none=gray/x. CSV export: axios `responseType:'blob'` → object URL → programmatic `<a download>` click.

**8.5 Payments** — local sub-tab state (`history` | `failed`; these ARE component state, unlike the top tabs).
- History: status filter select + table (Company, Plan, Amount, payment badge, DateTime). Badge colors: paid/completed green, pending amber, failed red, expired gray, refunded purple.
- Failed queue: red count badge on the sub-tab button; card list per company — name + retry-count pill (color escalates 1→yellow, 2→amber, 3+→red), "Unpaid" pill, plan/price/card/email line, last error (red), grace deadline (amber). Three action buttons with per-action loading state (`actionLoading = companyId + '_retry'` pattern): **Retry** (blue, calls retry endpoint, refetches), **Remind** (amber, sends email), **+7 days** (outline, extends grace).

**8.6 Forecast** — 5 stat cards (Current MRR, Projected ARR, Active Subscribers, Avg MRR/Sub, Monthly Growth % with arrow) + combined 24-row bar chart (12 history solid blue + 12 forecast light-blue dashed-border; legend "Actual / Projected"; count inside bar when wide enough, outside when narrow) + 12-row forecast table (Month, Projected Subscribers, Projected MRR green) + 3 risk cards (Trials Active amber, Past Due red, 12-Month ARR Forecast green = last projected MRR × 12).

**8.7 Receipts** — count line + table (Company + email sub-line, Plan, Amount, Date, Sent check, Send/Resend button with per-row loading).

### 9. Integration Checklist

1. Backend: create the financial route file; register its admin router on the API router.
2. Frontend: 5 page components + `FinancialTabNav` component.
3. Router: 5 routes, each wrapped in the super-admin route guard.
4. Admin nav: add one entry ("Financial", chart icon → `/admin/financial`) to the super-admin sidebar array — and to any duplicated nav-settings arrays if the app keeps a link-manager copy.
5. Confirm the subscription/webhook layer writes the §3.1 fields; if absent, implement §7 first.
6. Email service must expose `send_email` + an HTML template wrapper.

### 10. Gotchas & Edge Cases (learned in source app)

- **Tailwind dynamic classes:** `bg-${color}-50` / `text-${color}-600` in StatCard/badges are runtime-composed — the JIT purge cannot see them. Safelist the used color families (blue, green, amber, red, gray, purple) or switch to a static class map. In the source app these classes happen to exist elsewhere; do not rely on that.
- **Zero-division guards everywhere:** conversion, churn, growth, avg-MRR all guard `denominator > 0` and fall back to 0 — a fresh install with no subscribers must render all-zeros, not 500.
- **`no_subscription` filter sentinel** must map to `(subscription_status IS NULL OR subscription_status IN ('none', ''))` — subscriber rows from different eras store "no plan" three different ways.
- **Amount normalization:** provider webhook writes cents (`amount_total`), legacy/manual rows may hold dollars (`amount`). Normalize at read time (§3.3 rule), never mix.
- **Whitelist `sort_by`** against known fields — it's interpolated into the `ORDER BY` clause.
- **Retry endpoint returns card declines as 200** `{status:"failed", message}` so the UI can toast the provider's human-readable decline reason; only infra errors raise 500.
- **Grace extension** bases on existing `grace_period_ends` when in the future (extends), else on `now` (starts fresh).
- **Performance:** metric endpoints iterate subscribers and issue per-subscriber lookups (plan fetch + 2 user counts) — O(N) queries. Fine below ~500 subscribers; beyond that convert to JOINs / grouped aggregate queries, or cache plans in one upfront `SELECT` (`plan_id → plan` map).
- **History is reconstructed**, not snapshotted (§4) — if accurate historical MRR matters, add a monthly snapshot cron writing `{month, mrr, subscribers}` docs and read those instead.
- **Forecast is linear** on 6 months of approximate data — label it "Based on current growth trend" in UI (source app does) and do not present as financial guidance.
- **CSV export cap 200 rows** (reuses endpoint limit). Raise the limit param or paginate the export loop for larger bases — silent truncation otherwise.
- **`free_lifetime` companies:** appear in `by_plan` counts (with `mrr: 0`) so plan popularity is honest, but never in MRR, forecast, or overage math.
