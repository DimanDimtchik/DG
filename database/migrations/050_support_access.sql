-- Support-Freigabe (Kundeninstanz) + Signaling + Hub-Spiegel (KDV)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_support_access (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  duration_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  started_by INT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  end_reason VARCHAR(40) NULL,
  screen_share_enabled TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_support_status_exp (status, expires_at),
  KEY idx_support_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_support_signals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  access_id INT UNSIGNED NOT NULL,
  direction VARCHAR(20) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  consumed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_support_sig_access (access_id, consumed_at, id),
  CONSTRAINT fk_support_sig_access FOREIGN KEY (access_id) REFERENCES dg_support_access (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_kdv_support_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  domain VARCHAR(191) NOT NULL,
  company_name VARCHAR(191) NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kdv_sup_domain (domain),
  KEY idx_kdv_sup_status_exp (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
