-- Legal Compliance Kit — reference MySQL schema. InnoDB, utf8mb4.
-- Consent proof, versioned legal docs, cookie inventory, DSAR workflow,
-- email suppression, newsletter double-opt-in.

-- Append-only proof of consent (GDPR + CPRA require this be demonstrable).
CREATE TABLE `consent_records` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_key`    VARCHAR(64) NOT NULL,               -- hashed visitor id or user_id
  `jurisdiction`   VARCHAR(16) NOT NULL,               -- EU|UK|DACH|US-CA|US|ROW
  `mode`           ENUM('opt_in','opt_out','notice') NOT NULL,
  `categories`     JSON NOT NULL,                      -- {necessary:1,functional:0,analytics:1,advertising:0}
  `gpc`            TINYINT(1) NOT NULL DEFAULT 0,       -- Sec-GPC honored
  `policy_version` VARCHAR(20) NOT NULL DEFAULT '',
  `cmp_version`    VARCHAR(20) NOT NULL DEFAULT '',
  `method`         ENUM('banner','gpc','api') NOT NULL DEFAULT 'banner',
  `ip_hash`        CHAR(64) NOT NULL DEFAULT '',        -- sha256, never raw IP
  `user_agent`     VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject_key`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Versioned legal pages.
CREATE TABLE `legal_documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_type`       ENUM('privacy','cookie','terms','accessibility','impressum') NOT NULL,
  `locale`         VARCHAR(10) NOT NULL DEFAULT 'en',
  `version`        VARCHAR(20) NOT NULL,
  `effective_date` DATE NOT NULL,
  `body_html`      MEDIUMTEXT NOT NULL,
  `is_current`     TINYINT(1) NOT NULL DEFAULT 0,
  `published_by`   INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_locale_ver` (`doc_type`,`locale`,`version`),
  KEY `idx_current` (`doc_type`,`locale`,`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cookie/script registry — drives banner categories + Cookie Policy page.
CREATE TABLE `cookie_inventory` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`   ENUM('necessary','functional','analytics','advertising') NOT NULL,
  `name`       VARCHAR(160) NOT NULL,
  `vendor`     VARCHAR(160) NOT NULL DEFAULT '',
  `purpose`    VARCHAR(500) NOT NULL DEFAULT '',
  `duration`   VARCHAR(60) NOT NULL DEFAULT '',
  `domain`     VARCHAR(190) NOT NULL DEFAULT '',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data-subject access requests (CCPA/GDPR).
CREATE TABLE `dsar_requests` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_type`       ENUM('access','delete','correct','opt_out','portability') NOT NULL,
  `subject_email`      VARCHAR(190) NOT NULL,
  `subject_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `jurisdiction`       VARCHAR(16) NOT NULL DEFAULT '',
  `status`             ENUM('new','verifying','in_progress','completed','rejected') NOT NULL DEFAULT 'new',
  `details`            TEXT NULL,
  `verification_token` CHAR(48) NULL,
  `due_at`             DATETIME NULL,                   -- SLA: 30d GDPR / 45d CCPA
  `handled_by`         INT UNSIGNED NULL,
  `resolution_note`    TEXT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT current_timestamp(),
  `updated_at`         DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`subject_email`),
  KEY `idx_due` (`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CAN-SPAM / GDPR unsubscribe + bounce/complaint suppression. Checked pre-send.
CREATE TABLE `email_suppression` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `reason`     ENUM('unsubscribe','bounce','complaint') NOT NULL DEFAULT 'unsubscribe',
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter double opt-in.
CREATE TABLE `newsletter_optins` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(190) NOT NULL,
  `status`       ENUM('pending','confirmed') NOT NULL DEFAULT 'pending',
  `confirm_token` CHAR(48) NULL,
  `confirmed_at` DATETIME NULL,
  `created_at`   DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
