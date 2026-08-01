# System: Software Licensing + SDK System

A self-hosted software licensing server: issue license keys for the products you sell, validate them from any shipped app via drop-in SDKs, enforce activation limits per install, support free trials, and audit every check. Every validation response is **Ed25519-signed** — the SDK embeds the public key and verifies before trusting any status, so a forged, spoofed, or replayed "valid" is impossible. Fail-closed: no fresh signed receipt past the grace window → the product disables itself.

**Type:** feature subsystem (signing core + license/trial data model + public validation API + admin CRUD + multi-language SDK clients). Mounts into the [admin-portal-system](../admin-portal-system/SPEC.md) as its Licenses section (`/admin/licenses`, `/admin/licenses/sdk`).

**Reference stack:** Express (TypeScript, zod) + MySQL + React admin UI + Node's built-in `crypto` (Ed25519). SDKs ship for Node, PHP/WordPress, and reference snippets for Electron, C#, Python, Swift, Kotlin, RN/Flutter.

> **Source build:** demelos.com admin portal — [https://demelos.com/admin/licenses/sdk](https://demelos.com/admin/licenses/sdk) is the live implementation guide this spec documents.

---

## Reference files (in this block)

| File | What it is |
|------|-----------|
| [reference/schema.sql](reference/schema.sql) | Exact MySQL DDL — 5 tables (products, licenses, activations, checks, trials). |
| [reference/dm-license.mjs](reference/dm-license.mjs) | Node SDK client (zero-dep, ESM). Embed public key → `isValid()`. |
| [reference/DMLicense.php](reference/DMLicense.php) | PHP / WordPress SDK client (single file, libsodium + curl). |

---

## Integration Prompt

> Paste everything below this line into the target project. Swap `demelos` / `DM-` / the server URL for your brand.

---

You are given a task to build a **software licensing server + client SDK** so you can sell and license products (WordPress plugins/themes, web/desktop/mobile apps, APIs).

Reference stack (map onto equivalents):
- **Backend:** Express + TypeScript + zod + MySQL. Ed25519 via Node `crypto` (or any Ed25519 lib).
- **Admin UI:** React (mounts in an existing admin portal).
- **SDK:** one small file per language, embedding the server's public key.

### 1. Trust Model (the core idea)

The server holds an **Ed25519 keypair**. The **private key never leaves the server** (stored in the settings singleton, masked by the settings API). Every product ships with the **public key embedded** in its SDK.

- SDK POSTs `{ license_key, product, fingerprint, nonce }` to the server.
- Server replies with an exact JSON `payload` string + `signature` + `key_id`.
- SDK verifies the signature over the **raw payload bytes** with the embedded public key, checks the `nonce` it sent came back (anti-replay), and only then trusts `status`.
- A forged server, DNS spoof, or replayed response all fail signature/nonce verification.

Because trust rides on the signature, the validation endpoint needs **no auth** — the license key is the credential, and the signature makes the answer unforgeable.

### 2. Data Model (MySQL — full DDL in [reference/schema.sql](reference/schema.sql))

- **`dmadmin_products`** — each product you sell: `key_slug` (the identifier the SDK sends), `name`, `type` (wordpress_plugin/theme, web_app, desktop_app, mobile_app, api, other), `status`, `trial_days` (0 = no trial).
- **`dmadmin_licenses`** — `license_key`, `product_id`, `customer_email`/`name`, `purchased_at`, `order_ref`, `status` (active/suspended/revoked), `expires_at` (NULL = perpetual), `check_interval_days` (default 15 — twice a month), `grace_days` (default 5), `max_activations` (default 1), `last_check_at`.
- **`dmadmin_license_activations`** — one row per install `fingerprint` (sha256 of domain/machine id) + `label`, `status` (active/deactivated). Unique `(license_id, fingerprint)`. Enforces `max_activations`.
- **`dmadmin_license_checks`** — append-only audit: every validation with `result`, `ip`, `fingerprint`, `meta` (sdk version).
- **`dmadmin_trials`** — free trial per `(product_id, fingerprint)`: `started_at`, `extra_days` (admin extension), `status`.
- **Signing keypair** — three rows in the settings singleton: `license_signing_secret` (private PEM, masked), `license_signing_public` (public PEM), `license_signing_key_id`. Generated + persisted on first use.

### 3. License Key Format

`DM-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX` — 24 chars in six groups of four, **Crockford base32** (uppercase, no ambiguous `0/O 1/I/L`) so a customer can transcribe it by hand from a receipt email. 120 bits of randomness; the unique index guards the ~0 collision case.
```ts
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
// 15 random bytes → 24 base32 chars, prefix 'DM-', group by 4
```

### 4. Signing Core (`lib/licensing.ts`)

- `signingKeys()` — load the keypair from settings; on first use `crypto.generateKeyPairSync('ed25519')`, export PKCS8/SPKI PEM, derive `key_id = 'dm-' + sha256(pub).slice(0,12)`, persist all three. Cache in-process.
- `signPayload(payloadJson)` — `crypto.sign(null, Buffer.from(payloadJson), privateKey)` → base64 signature + `key_id`. **Sign the exact string the client will verify** (no re-serialization).
- `generateLicenseKey()` — §3.

### 5. Public Validation API (no auth, signed responses)

**`POST /license/v1/validate`** — body `{ license_key, product, fingerprint (sha256 hex), label?, nonce (hex), sdk? }`. Zod-validated. Per `key+ip` rate limit (60/hour). Logic in order → each returns a **signed** status:
1. License not found → `not_found`.
2. `product` slug ≠ license's product → `product_mismatch`.
3. status revoked / suspended → `revoked` / `suspended`.
4. `expires_at` in the past → `expired` (+ `expires_at`).
5. Activation slot: known fingerprint refreshes `last_seen` (or `deactivated` → deny); new fingerprint needs a free slot or → `activation_limit` (+ `max_activations`).
6. Otherwise → `valid` (+ `expires_at`, `next_check_before = now + check_interval_days`, `grace_seconds = grace_days·86400`).

Signed payload shape:
```json
{ "v":1, "status":"valid", "license_key":"…", "product":"…",
  "fingerprint":"…", "nonce":"…", "issued_at":1750000000,
  "expires_at":null, "next_check_before":1751296000, "grace_seconds":432000 }
```
Response envelope: `{ payload: "<exact json string>", signature: "<base64>", key_id: "dm-…" }`. Every attempt is written to `dmadmin_license_checks`.

**`POST /license/v1/trial`** — body `{ product, fingerprint, label?, nonce, sdk? }`. No license key. Looks up/creates a `dmadmin_trials` row per `(product, fingerprint)`; `allowed = product.trial_days + trial.extra_days`; computes `days_left` from `started_at`. Signed status ∈ `trial` (+ `days_left`, `expires_at`, `next_check_before`, `grace_seconds`) | `trial_expired` | `trial_ended` | `trial_disabled` | `product_unknown`. Re-check daily during a trial.

**`GET /license/v1/pubkey`** — returns `{ public_key, key_id, algorithm: "ed25519" }`. For docs/tooling/rotation checks only — SDKs should **embed** the key, not fetch it at runtime.

### 6. Admin API (all `requireAdmin()`)

| Method + path | Purpose |
|---|---|
| `GET/POST /admin/license-products` · `PATCH/DELETE /admin/license-products/:id` | Products CRUD (incl. `trial_days`). |
| `GET /admin/license-products/:id/trials` · `PATCH /admin/trials/:id` | List trials for a product; extend (`extra_days`) or end one. |
| `GET/POST /admin/licenses` · `GET/PATCH /admin/licenses/:id` | Licenses CRUD (generate key on create; edit status/expiry/limits). |
| `PATCH /admin/licenses/:id/activations/:actId` | Deactivate/reactivate a specific install (frees a slot). |
| `POST /admin/licenses/:id/email` | Email the license key to the customer (uses the email-template system, `license_delivery` template). |
| `GET /admin/licensing/keys` | Show public key + key id (never the private key). |

### 7. SDK Client Contract (any language, ~40 lines)

Embed the public key + product slug. On launch and on schedule:
1. Build `fingerprint` = sha256 of a stable per-install id (domain, hostname+user, machine id, app UUID).
2. Generate a random `nonce`; POST to `/license/v1/validate`.
3. Verify signature over the raw `payload` string with the embedded public key; confirm `nonce`, `product`, `fingerprint`, `license_key` match what you sent.
4. Cache the signed receipt to disk.
5. `isValid()` logic: if a cached valid receipt exists and `now <= next_check_before` → valid; within `next_check_before + grace_seconds` → try a refresh, else valid on grace; past grace or non-valid status → **fail closed**. Clock-rollback guard: reject receipts issued in the future.
6. Statuses to handle: `valid, missing_key, not_found, expired, revoked, suspended, activation_limit, product_mismatch, deactivated, unreachable`.

Ship SDKs (this block includes Node + PHP):
- **Node** ([reference/dm-license.mjs](reference/dm-license.mjs)) — zero-dep ESM; `new DMLicense({product, licenseKey, receiptPath})` → `await isValid()` → `startAutoCheck()`. Also `trialStatus()`.
- **PHP / WordPress** ([reference/DMLicense.php](reference/DMLicense.php)) — single file, libsodium + curl; `storage: 'wp'` keeps receipts in `wp_options`, `scheduleWordPressCron()` refreshes twice daily; gate plugin load on `isValid()`.
- **Others** (reference snippets on the SDK page): Electron (Node SDK in main process), C# (NSec/BouncyCastle), Python (`cryptography`), Swift (`CryptoKit`), Kotlin (Tink/BouncyCastle), RN/Flutter (native Ed25519). Same payload + nonce + signature rules.

### 8. Admin UI

- **Licenses page** (`/admin/licenses`) — products + licenses management, activations list, resend-email, trial list/extend.
- **Licenses SDK page** (`/admin/licenses/sdk`) — implementation guide that pulls the **live public key** so every snippet is copy-paste ready: embed-the-key box, the validate/trial request+response contract, per-stack SDK code, and the status list the SDK must handle. This is the page a developer follows to license a new product.

### 9. Security Rules

- Private signing key never leaves the server; masked by the settings API; only the public key + key id are exposed.
- Sign the **exact** payload string the client verifies — never re-serialize.
- `nonce` per request, echoed in the signed payload → anti-replay. SDK rejects a response whose nonce it didn't send.
- SDK **embeds** the public key (does not fetch it) → no trust-on-first-use hole.
- Fail-closed past grace; clock-rollback guard on receipts.
- Validation endpoint is unauthenticated by design (key = credential, signature = integrity) but **rate-limited** per key+ip.
- Activation limit enforced server-side; fingerprints are opaque sha256 (no PII required).
- Admin routes all behind `requireAdmin()`.

### Reproduction Checklist

1. Create the 5 tables ([reference/schema.sql](reference/schema.sql)) + the 3 signing-key settings rows (auto-generated on first use).
2. Implement the signing core: keypair load/generate, `signPayload`, `generateLicenseKey` (Crockford base32).
3. Build `POST /license/v1/validate` with the ordered checks + signed responses + check log + rate limit.
4. Build `POST /license/v1/trial` and `GET /license/v1/pubkey`.
5. Build the admin API (products, licenses, activations, trials, email, keys) behind `requireAdmin()`.
6. Ship the Node + PHP SDKs; stamp the public key into the `{{PUBLIC_KEY_PEM}}` placeholder at build/download time.
7. Build the admin Licenses page + the SDK implementation-guide page (pulls live public key).
8. Wire the `license_delivery` email template for sending keys to customers.
9. Verify end-to-end: create product → issue license → validate from the SDK → tamper the payload (must fail) → exhaust activations (must block) → expire/revoke (must fail closed).

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Licensing / anti-piracy / monetization |
| Backend | Express + TypeScript + zod + MySQL |
| Crypto | Ed25519 (Node `crypto`); signed responses, embedded public key |
| Frontend | React admin (Licenses + SDK guide pages) |
| SDKs | Node, PHP/WordPress (included) + Electron/C#/Python/Swift/Kotlin/RN/Flutter snippets |
| Key concepts | Signed-receipt validation, per-install fingerprint activations, trials, fail-closed grace, offline tolerance |
| Mounts into | [admin-portal-system](../admin-portal-system/SPEC.md) |
| Uses | [email-template-system](../email-template-system/SPEC.md) — `license_delivery` template |
| Source build | demelos.com — /admin/licenses/sdk |
