<?php
/**
 * DMLicense — demelos license SDK for PHP / WordPress (single file).
 *
 * Phones home to the demelos license server on a schedule (default: every
 * check the server dictates, typically twice a month), verifies the
 * Ed25519-SIGNED response against the public key EMBEDDED below, and
 * stores the signed receipt locally. Forged servers, DNS spoofing, and
 * replayed responses all fail signature/nonce verification.
 *
 * Fail-closed: if no fresh valid receipt exists past the grace window,
 * isValid() returns false and your product should disable its features.
 *
 * WordPress usage (in your plugin main file):
 *
 *   require_once __DIR__ . '/DMLicense.php';
 *   $dm_license = new DMLicense([
 *       'product'     => 'my-plugin-slug',          // dmadmin product key_slug
 *       'license_key' => get_option('myplugin_license_key', ''),
 *       'storage'     => 'wp',                       // receipts in wp_options
 *   ]);
 *   $dm_license->scheduleWordPressCron();            // twice-daily opportunistic check
 *   if (!$dm_license->isValid()) {
 *       add_action('admin_notices', function () use ($dm_license) {
 *           echo '<div class="notice notice-error"><p>My Plugin: ' .
 *                esc_html($dm_license->statusMessage()) . '</p></div>';
 *       });
 *       return; // stop loading plugin features
 *   }
 *
 * Plain PHP usage:
 *
 *   $lic = new DMLicense(['product' => 'my-app', 'license_key' => $key,
 *                         'storage' => '/var/lib/my-app/license.json']);
 *   if (!$lic->isValid()) { exit('License invalid: ' . $lic->statusMessage()); }
 *
 * Requires PHP 7.2+ (libsodium bundled) and curl.
 */
class DMLicense
{
    /** demelos license server — Ed25519 public key (key id dm-…). Do not edit. */
    const PUBLIC_KEY_PEM = <<<'PEM'
{{PUBLIC_KEY_PEM}}
PEM;

    const SERVER_URL = 'https://demelos.com/admin/api/license/v1/validate';
    const TRIAL_URL  = 'https://demelos.com/admin/api/license/v1/trial';
    const SDK_VERSION = 'php-1.0.0';

    private $product;
    private $licenseKey;
    private $storage;     // 'wp' or absolute file path
    private $lastStatus = 'unknown';

    public function __construct(array $opts)
    {
        $this->product = (string)($opts['product'] ?? '');
        $this->licenseKey = strtoupper(trim((string)($opts['license_key'] ?? '')));
        $this->storage = $opts['storage'] ?? 'wp';
    }

    /** True when a fresh, signature-verified 'valid' receipt exists (grace included). */
    public function isValid(): bool
    {
        if ($this->product === '' || $this->licenseKey === '') {
            $this->lastStatus = 'missing_key';
            return false;
        }
        $receipt = $this->readReceipt();
        $now = time();

        if ($receipt !== null) {
            $payload = $this->verifyReceipt($receipt);
            if ($payload !== null) {
                // Clock rollback → distrust the cache, force a live check.
                if ($now + 86400 < (int)$payload['issued_at']) {
                    $payload = null;
                } elseif ($payload['status'] === 'valid') {
                    $deadline = (int)$payload['next_check_before'] + (int)($payload['grace_seconds'] ?? 0);
                    if ($now <= (int)$payload['next_check_before']) {
                        $this->lastStatus = 'valid';
                        return true; // fresh — no network needed
                    }
                    if ($now <= $deadline) {
                        // In grace: try to refresh, but keep working if offline.
                        $this->check();
                        $fresh = $this->readReceipt();
                        $freshPayload = $fresh ? $this->verifyReceipt($fresh) : null;
                        if ($freshPayload !== null && $freshPayload['status'] !== 'valid') {
                            $this->lastStatus = $freshPayload['status'];
                            return false;
                        }
                        $this->lastStatus = 'valid';
                        return true;
                    }
                    // Past grace → must succeed online.
                } else {
                    $this->lastStatus = $payload['status'];
                    return false; // signed revoked/expired/etc — hard stop
                }
            }
        }

        // No usable receipt: live check decides.
        $this->check();
        $receipt = $this->readReceipt();
        $payload = $receipt ? $this->verifyReceipt($receipt) : null;
        if ($payload !== null && $payload['status'] === 'valid') {
            $this->lastStatus = 'valid';
            return true;
        }
        $this->lastStatus = $payload['status'] ?? 'unreachable';
        return false;
    }

    /** Human-readable reason for the last isValid() result. */
    public function statusMessage(): string
    {
        $map = [
            'valid' => 'License valid.',
            'missing_key' => 'No license key entered.',
            'not_found' => 'License key not recognized.',
            'expired' => 'License expired — please renew.',
            'revoked' => 'License revoked.',
            'suspended' => 'License suspended — contact support@demelos.com.',
            'activation_limit' => 'Activation limit reached for this license.',
            'product_mismatch' => 'License belongs to a different product.',
            'deactivated' => 'This installation was deactivated.',
            'unreachable' => 'License server unreachable and grace period exhausted.',
        ];
        return $map[$this->lastStatus] ?? ('License problem: ' . $this->lastStatus);
    }

    /**
     * Free trial check (no license key). Returns the verified trial payload
     * array (status: trial|trial_expired|trial_ended|trial_disabled, days_left,
     * expires_at) or null if unreachable / forged. status 'trial' = run in trial.
     */
    public function trialStatus(): ?array
    {
        $nonce = bin2hex(random_bytes(16));
        $body = json_encode([
            'product' => $this->product,
            'fingerprint' => $this->fingerprint(),
            'label' => $this->label(),
            'nonce' => $nonce,
            'sdk' => self::SDK_VERSION,
        ]);
        $ch = curl_init(self::TRIAL_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw)) return null;
        $data = (json_decode($raw, true)['data'] ?? null);
        if (!is_array($data) || !isset($data['payload'], $data['signature'])) return null;
        $sig = base64_decode($data['signature'] ?? '', true);
        $pub = self::publicKeyRaw();
        if ($pub === null || $sig === false || strlen($sig) !== 64
            || !sodium_crypto_sign_verify_detached($sig, $data['payload'], $pub)) {
            return null;
        }
        $payload = json_decode($data['payload'], true);
        if (!is_array($payload) || ($payload['nonce'] ?? '') !== $nonce
            || ($payload['product'] ?? '') !== $this->product) {
            return null;
        }
        return $payload;
    }

    /** Contact the server now and store the signed receipt. */
    public function check(): void
    {
        $nonce = bin2hex(random_bytes(16));
        $body = json_encode([
            'license_key' => $this->licenseKey,
            'product' => $this->product,
            'fingerprint' => $this->fingerprint(),
            'label' => $this->label(),
            'nonce' => $nonce,
            'sdk' => self::SDK_VERSION,
        ]);
        $ch = curl_init(self::SERVER_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw)) {
            return; // offline — existing receipt + grace governs
        }
        $resp = json_decode($raw, true);
        $data = $resp['data'] ?? null;
        if (!is_array($data) || !isset($data['payload'], $data['signature'])) {
            return;
        }
        // Verify BEFORE storing; reject wrong nonce (replay) outright.
        $payload = $this->verifyReceipt($data);
        if ($payload === null || ($payload['nonce'] ?? '') !== $nonce) {
            return;
        }
        $this->writeReceipt($data);
    }

    /** Verify signature + basic shape; returns decoded payload or null. */
    private function verifyReceipt(array $receipt): ?array
    {
        $payloadJson = $receipt['payload'] ?? '';
        $sig = base64_decode($receipt['signature'] ?? '', true);
        if (!is_string($payloadJson) || $sig === false || strlen($sig) !== 64) {
            return null;
        }
        $pub = self::publicKeyRaw();
        if ($pub === null || !sodium_crypto_sign_verify_detached($sig, $payloadJson, $pub)) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)
            || ($payload['license_key'] ?? '') !== $this->licenseKey
            || ($payload['product'] ?? '') !== $this->product
            || ($payload['fingerprint'] ?? '') !== $this->fingerprint()) {
            return null;
        }
        return $payload;
    }

    /** Extract the raw 32-byte Ed25519 key from the embedded SPKI PEM. */
    private static function publicKeyRaw(): ?string
    {
        $pem = trim(self::PUBLIC_KEY_PEM);
        $b64 = preg_replace('/-----[A-Z ]+-----|\s/', '', $pem);
        $der = base64_decode($b64, true);
        if ($der === false || strlen($der) < 32) {
            return null;
        }
        return substr($der, -32); // SPKI: 12-byte header + raw key
    }

    private function fingerprint(): string
    {
        if ($this->storage === 'wp' && function_exists('home_url')) {
            $seed = 'wp:' . home_url();
        } else {
            $seed = 'host:' . php_uname('n') . ':' . __DIR__;
        }
        return hash('sha256', $seed);
    }

    private function label(): string
    {
        if ($this->storage === 'wp' && function_exists('home_url')) {
            return substr(home_url(), 0, 255);
        }
        return substr(php_uname('n'), 0, 255);
    }

    private function storageKey(): string
    {
        return 'dm_license_receipt_' . md5($this->product . '|' . $this->licenseKey);
    }

    private function readReceipt(): ?array
    {
        if ($this->storage === 'wp' && function_exists('get_option')) {
            $v = get_option($this->storageKey());
            return is_array($v) ? $v : null;
        }
        if (is_string($this->storage) && is_readable($this->storage)) {
            $v = json_decode((string)file_get_contents($this->storage), true);
            return is_array($v) ? $v : null;
        }
        return null;
    }

    private function writeReceipt(array $receipt): void
    {
        if ($this->storage === 'wp' && function_exists('update_option')) {
            update_option($this->storageKey(), $receipt, false);
            return;
        }
        if (is_string($this->storage)) {
            @file_put_contents($this->storage, json_encode($receipt), LOCK_EX);
        }
    }

    /** WordPress: twice-daily cron that refreshes the receipt when due. */
    public function scheduleWordPressCron(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        $hook = 'dm_license_check_' . md5($this->product);
        add_action($hook, function () {
            $receipt = $this->readReceipt();
            $payload = $receipt ? $this->verifyReceipt($receipt) : null;
            if ($payload === null || time() >= (int)($payload['next_check_before'] ?? 0)) {
                $this->check();
            }
        });
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time() + 300, 'twicedaily', $hook);
        }
    }
}
