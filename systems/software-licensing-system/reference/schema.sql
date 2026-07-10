-- Software Licensing System — reference MySQL schema
-- Source: demelos.com admin portal (/admin/licenses). InnoDB, utf8mb4.
-- Validation responses are Ed25519-signed; the keypair lives in
-- dmadmin_settings (private key masked by the settings API, never shipped).

-- ─────────────────────────────────────────────────────────────
-- Products you sell (each shipped app/plugin embeds one product slug)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dmadmin_products` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_slug`   VARCHAR(80)  NOT NULL,                 -- product identifier the SDK sends
  `name`       VARCHAR(160) NOT NULL,
  `type`       ENUM('wordpress_plugin','wordpress_theme','web_app','desktop_app','mobile_app','api','other') NOT NULL DEFAULT 'other',
  `status`     ENUM('active','retired') NOT NULL DEFAULT 'active',
  `trial_days` INT NOT NULL DEFAULT 0,                -- 0 = trials disabled for this product
  `notes`      TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`key_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Licenses (one key per customer purchase)
-- key format: DM-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX (Crockford base32, 120-bit)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dmadmin_licenses` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_key`         VARCHAR(40)  NOT NULL,
  `product_id`          INT UNSIGNED NOT NULL,
  `customer_email`      VARCHAR(190) NOT NULL,
  `customer_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `purchased_at`        DATE NULL,                    -- explicit purchase date
  `order_ref`           VARCHAR(120) NOT NULL DEFAULT '',
  `user_id`             INT UNSIGNED NULL,
  `status`              ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
  `expires_at`          DATETIME NULL,                -- NULL = perpetual
  `check_interval_days` INT NOT NULL DEFAULT 15,      -- how often the SDK must re-validate
  `grace_days`          INT NOT NULL DEFAULT 5,       -- offline tolerance past a missed check
  `max_activations`     INT NOT NULL DEFAULT 1,       -- concurrent install cap
  `notes`               TEXT NULL,
  `last_check_at`       DATETIME NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`license_key`),
  KEY `idx_product` (`product_id`),
  KEY `idx_email` (`customer_email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Activations (one row per install fingerprint; enforces max_activations)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dmadmin_license_activations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id`  INT UNSIGNED NOT NULL,
  `fingerprint` CHAR(64) NOT NULL,                    -- sha256 of domain / machine id
  `label`       VARCHAR(255) NOT NULL DEFAULT '',     -- human hint: domain, hostname
  `status`      ENUM('active','deactivated') NOT NULL DEFAULT 'active',
  `first_seen`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_license_fp` (`license_id`,`fingerprint`),
  KEY `idx_license` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Check log (audit of every validation attempt)
-- result: valid|expired|revoked|suspended|not_found|activation_limit|product_mismatch|deactivated
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dmadmin_license_checks` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id`  INT UNSIGNED NULL,
  `license_key` VARCHAR(40) NOT NULL,
  `fingerprint` CHAR(64) NOT NULL DEFAULT '',
  `ip`          VARCHAR(45) NOT NULL DEFAULT '',
  `result`      VARCHAR(30) NOT NULL,
  `meta`        VARCHAR(255) NOT NULL DEFAULT '',     -- sdk version etc.
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_license` (`license_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Trials (free, no license key; keyed per product + install fingerprint)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dmadmin_trials` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED NOT NULL,
  `fingerprint` CHAR(64) NOT NULL,
  `label`       VARCHAR(255) NOT NULL DEFAULT '',
  `extra_days`  INT NOT NULL DEFAULT 0,               -- admin-granted extension
  `status`      ENUM('active','ended') NOT NULL DEFAULT 'active',
  `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_fp` (`product_id`,`fingerprint`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The Ed25519 signing keypair is stored in the settings singleton table:
--   dmadmin_settings.k = 'license_signing_secret'  (private PEM — masked by settings API)
--   dmadmin_settings.k = 'license_signing_public'  (public  PEM — embedded in SDKs)
--   dmadmin_settings.k = 'license_signing_key_id'  (short id, e.g. dm-ab12cd34ef56)
-- Generated + persisted on first use. See lib/licensing.ts.
