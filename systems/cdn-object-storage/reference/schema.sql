-- CDN / Object Storage — reference MySQL metadata schema.
-- Bytes live in Spaces/S3; every byte op is mirrored by a row op here.
-- MySQL is the source of truth for listings (fast, searchable, taggable).

-- General file browser metadata
CREATE TABLE files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  object_key VARCHAR(500) NOT NULL UNIQUE,   -- full Spaces key incl. prefix
  name VARCHAR(255) NOT NULL,
  size BIGINT UNSIGNED NULL,
  content_type VARCHAR(150) NULL,
  path VARCHAR(500) NULL,                     -- parent "folder" prefix
  tags TEXT NULL,
  notes TEXT NULL,
  uploaded_by VARCHAR(120) NULL,
  uploaded_by_id INT UNSIGNED NULL,
  original_key VARCHAR(500) NULL,            -- pre-trash key for restore
  trashed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (name), INDEX (path), INDEX (uploaded_by_id), INDEX (trashed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attachments pinned to a record (RFP #464's documents, employee #12's docs, …)
CREATE TABLE entity_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(20) NOT NULL,          -- 'rfp' | 'employee' | 'contract' | …
  entity_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  object_key VARCHAR(500) NULL,              -- Spaces key
  file_path VARCHAR(500) NOT NULL,           -- legacy/local fallback path
  file_size INT NOT NULL DEFAULT 0,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
  uploaded_by INT NULL,
  uploaded_by_name VARCHAR(255) NULL,
  notes TEXT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (entity_type, entity_id), INDEX (object_key), INDEX (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
