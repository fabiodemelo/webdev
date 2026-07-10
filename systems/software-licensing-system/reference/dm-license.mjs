/**
 * dm-license — demelos license SDK for Node.js (ESM, zero dependencies).
 *
 * Validates against the demelos license server, verifies the Ed25519-signed
 * response with the EMBEDDED public key, stores the signed receipt on disk,
 * and fails closed when the receipt is stale past grace.
 *
 * Usage:
 *   import { DMLicense } from './dm-license.mjs';
 *   const lic = new DMLicense({
 *     product: 'my-app',                       // dmadmin product key_slug
 *     licenseKey: process.env.MYAPP_LICENSE,
 *     receiptPath: '/var/lib/my-app/license-receipt.json',
 *   });
 *   if (!await lic.isValid()) {
 *     console.error('License problem:', lic.statusMessage());
 *     process.exit(1);
 *   }
 *   lic.startAutoCheck();   // refresh in the background while running
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

/** demelos license server — Ed25519 public key (key id dm-…). Do not edit. */
const PUBLIC_KEY_PEM = `{{PUBLIC_KEY_PEM}}`;

const SERVER_URL = 'https://demelos.com/admin/api/license/v1/validate';
const TRIAL_URL = 'https://demelos.com/admin/api/license/v1/trial';
const SDK_VERSION = 'node-1.0.0';

export class DMLicense {
  constructor({ product, licenseKey, receiptPath }) {
    this.product = String(product || '');
    this.licenseKey = String(licenseKey || '').trim().toUpperCase();
    this.receiptPath = receiptPath || path.join(os.homedir(), `.dm-license-${this.product}.json`);
    this.publicKey = crypto.createPublicKey(PUBLIC_KEY_PEM);
    this.lastStatus = 'unknown';
    this._timer = null;
  }

  fingerprint() {
    return crypto.createHash('sha256')
      .update('node:' + os.hostname() + ':' + os.userInfo().username)
      .digest('hex');
  }

  async isValid() {
    if (!this.product || !this.licenseKey) { this.lastStatus = 'missing_key'; return false; }
    const now = Math.floor(Date.now() / 1000);
    let payload = this.#verifiedReceipt();

    if (payload) {
      if (now + 86_400 < payload.issued_at) payload = null;       // clock rollback
      else if (payload.status !== 'valid') { this.lastStatus = payload.status; return false; }
      else {
        const deadline = payload.next_check_before + (payload.grace_seconds || 0);
        if (now <= payload.next_check_before) { this.lastStatus = 'valid'; return true; }
        if (now <= deadline) {
          await this.check();                                      // grace: refresh if possible
          const fresh = this.#verifiedReceipt();
          if (fresh && fresh.status !== 'valid') { this.lastStatus = fresh.status; return false; }
          this.lastStatus = 'valid';
          return true;
        }
      }
    }

    await this.check();
    const fresh = this.#verifiedReceipt();
    if (fresh?.status === 'valid') { this.lastStatus = 'valid'; return true; }
    this.lastStatus = fresh?.status ?? 'unreachable';
    return false;
  }

  statusMessage() {
    const map = {
      valid: 'License valid.',
      missing_key: 'No license key configured.',
      not_found: 'License key not recognized.',
      expired: 'License expired — please renew.',
      revoked: 'License revoked.',
      suspended: 'License suspended — contact support@demelos.com.',
      activation_limit: 'Activation limit reached for this license.',
      product_mismatch: 'License belongs to a different product.',
      deactivated: 'This installation was deactivated.',
      unreachable: 'License server unreachable and grace period exhausted.',
    };
    return map[this.lastStatus] ?? `License problem: ${this.lastStatus}`;
  }

  /** Contact the server now; store the receipt only if its signature + nonce verify. */
  async check() {
    const nonce = crypto.randomBytes(16).toString('hex');
    let resp;
    try {
      const r = await fetch(SERVER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          license_key: this.licenseKey,
          product: this.product,
          fingerprint: this.fingerprint(),
          label: os.hostname(),
          nonce,
          sdk: SDK_VERSION,
        }),
        signal: AbortSignal.timeout(15_000),
      });
      resp = (await r.json())?.data;
    } catch { return; }                                            // offline → grace governs
    if (!resp?.payload || !resp?.signature) return;
    const payload = this.#verify(resp);
    if (!payload || payload.nonce !== nonce) return;               // forged or replayed
    fs.mkdirSync(path.dirname(this.receiptPath), { recursive: true });
    fs.writeFileSync(this.receiptPath, JSON.stringify(resp));
  }

  /** Re-validate on an interval while the process runs (default: daily). */
  startAutoCheck(intervalMs = 86_400_000, onInvalid = null) {
    this._timer = setInterval(async () => {
      const okNow = await this.isValid();
      if (!okNow && typeof onInvalid === 'function') onInvalid(this.statusMessage());
    }, intervalMs);
    this._timer.unref?.();
    return this._timer;
  }

  /**
   * Free trial (no license key). Returns the signed, verified trial payload
   * { status: 'trial'|'trial_expired'|'trial_ended'|'trial_disabled', days_left, expires_at }
   * or null if unreachable / forged. status === 'trial' means run in trial mode.
   */
  async trialStatus() {
    const nonce = crypto.randomBytes(16).toString('hex');
    let resp;
    try {
      const r = await fetch(TRIAL_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product: this.product, fingerprint: this.fingerprint(), label: os.hostname(), nonce, sdk: SDK_VERSION }),
        signal: AbortSignal.timeout(15_000),
      });
      resp = (await r.json())?.data;
    } catch { return null; }
    if (!resp?.payload || !resp?.signature) return null;
    try {
      const okSig = crypto.verify(null, Buffer.from(resp.payload, 'utf8'), this.publicKey, Buffer.from(resp.signature, 'base64'));
      if (!okSig) return null;
      const p = JSON.parse(resp.payload);
      if (p.nonce !== nonce || p.product !== this.product || p.fingerprint !== this.fingerprint()) return null;
      return p;
    } catch { return null; }
  }

  #verifiedReceipt() {
    try {
      const receipt = JSON.parse(fs.readFileSync(this.receiptPath, 'utf8'));
      return this.#verify(receipt);
    } catch { return null; }
  }

  #verify(receipt) {
    try {
      const sig = Buffer.from(receipt.signature, 'base64');
      const okSig = crypto.verify(null, Buffer.from(receipt.payload, 'utf8'), this.publicKey, sig);
      if (!okSig) return null;
      const payload = JSON.parse(receipt.payload);
      if (payload.license_key !== this.licenseKey) return null;
      if (payload.product !== this.product) return null;
      if (payload.fingerprint !== this.fingerprint()) return null;
      return payload;
    } catch { return null; }
  }
}
