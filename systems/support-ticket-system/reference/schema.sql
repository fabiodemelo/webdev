-- Support Ticket System — reference MySQL schema
-- Source: Alta Apps portal (app.altajan.com). Cleaned for fresh installs:
-- legacy `mongo_id` columns + auto-increment seeds from the original
-- Mongo→MySQL port have been removed. Engine: InnoDB, utf8mb4.

-- ─────────────────────────────────────────────────────────────
-- Core ticket
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `tickets` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number`   INT UNSIGNED NOT NULL,
  `title`           VARCHAR(500) NOT NULL,
  `description`     TEXT DEFAULT NULL,
  `department`      VARCHAR(100) DEFAULT 'General',
  `priority`        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `status`          VARCHAR(50)  NOT NULL DEFAULT 'open',   -- value from ticket_settings
  `is_private`      TINYINT(1)   NOT NULL DEFAULT 0,
  `due_date`        VARCHAR(20)  DEFAULT NULL,              -- 'YYYY-MM-DD'
  `created_by`      INT          NOT NULL DEFAULT 0,
  `created_by_name` VARCHAR(255) DEFAULT NULL,
  `closed_at`       DATETIME     DEFAULT NULL,
  `closed_by`       INT          DEFAULT NULL,
  `closed_by_name`  VARCHAR(255) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT current_timestamp(),
  `updated_at`      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reminder_sent`   TINYINT(1) NOT NULL DEFAULT 0,          -- due-date reminder cron, one-shot
  `overdue_sent`    TINYINT(1) NOT NULL DEFAULT 0,          -- overdue cron, one-shot
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_number` (`ticket_number`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_department` (`department`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Assignees (many per ticket; first = primary)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_assignees` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id`   INT NOT NULL,
  `user_name` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_user` (`ticket_id`,`user_id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Members / watchers (collaborators, not assignees)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_members` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id`   INT NOT NULL,
  `user_name` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_user` (`ticket_id`,`user_id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Comments (the conversation thread)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_comments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`   INT UNSIGNED NOT NULL,
  `author_id`   INT NOT NULL DEFAULT 0,
  `author_name` VARCHAR(255) DEFAULT NULL,
  `content`     TEXT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Time tracking
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_time_entries` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`        INT UNSIGNED NOT NULL,
  `logged_by`        INT NOT NULL DEFAULT 0,
  `logged_by_name`   VARCHAR(255) DEFAULT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 0,
  `description`      TEXT DEFAULT NULL,
  `logged_at`        DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Activity log (append-only audit timeline)
-- action ∈ created | status_changed | reassigned | members_changed
--        | commented | time_logged | attachment_added | attachment_deleted
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT UNSIGNED NOT NULL,
  `actor_id`   INT NOT NULL DEFAULT 0,
  `actor_name` VARCHAR(255) DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL DEFAULT 'update',
  `old_value`  TEXT DEFAULT NULL,
  `new_value`  TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Attachments (multiple per ticket; bytes in object storage)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_attachments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`      INT UNSIGNED NOT NULL,
  `name`           VARCHAR(500) NOT NULL,
  `object_key`     VARCHAR(1000) NOT NULL,                 -- S3-style key; presign on read
  `size`           INT NOT NULL DEFAULT 0,
  `content_type`   VARCHAR(200) DEFAULT NULL,
  `uploaded_by`    VARCHAR(255) DEFAULT NULL,
  `uploaded_by_id` INT NOT NULL DEFAULT 0,
  `uploaded_at`    DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Settings (admin-editable statuses / priorities / departments)
-- type ∈ status | priority | department
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ticket_settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`       VARCHAR(50) NOT NULL,
  `value`      VARCHAR(100) NOT NULL,
  `label`      VARCHAR(100) NOT NULL,
  `color`      VARCHAR(20) DEFAULT '#6b7280',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_value` (`type`,`value`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- Seed defaults (statuses / priorities / departments)
-- ─────────────────────────────────────────────────────────────
INSERT INTO `ticket_settings` (`type`,`value`,`label`,`color`,`sort_order`) VALUES
  ('status','open','Open','#3b82f6',1),
  ('status','in_progress','In Progress','#f59e0b',2),
  ('status','in_review','In Review','#8b5cf6',3),
  ('status','closed','Closed','#10b981',4),
  ('priority','low','Low','#059669',1),
  ('priority','medium','Medium','#d97706',2),
  ('priority','high','High','#dc2626',3),
  ('department','General','General','#6b7280',1),
  ('department','IT','IT','#3b82f6',2),
  ('department','Sales','Sales','#f59e0b',3),
  ('department','Operations','Operations','#8b5cf6',4),
  ('department','Finance','Finance','#10b981',5);
