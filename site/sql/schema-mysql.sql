-- Cavemen Africa — MySQL schema (import in phpMyAdmin or run once)
-- Charset: utf8mb4
--
-- DAHK: The Experience → table dahk_seasons_registrations
-- Asali Poetry Sessions → table asali_registrations
-- After import, set MYSQL_* in site/.env so PHP uses this database (not SQLite).

CREATE TABLE IF NOT EXISTS kanti_products (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  title VARCHAR(512) NOT NULL,
  short_description TEXT NOT NULL,
  category VARCHAR(64) NOT NULL,
  image VARCHAR(1024) NOT NULL,
  flutterwave_url VARCHAR(1024) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asali_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  gender VARCHAR(64) NOT NULL,
  discovery VARCHAR(512) NOT NULL,
  attendance_type VARCHAR(32) NOT NULL,
  ticket_price_naira INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  payment_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  tx_ref VARCHAR(128) NULL,
  ticket_code VARCHAR(64) NULL,
  flutterwave_transaction_id VARCHAR(128) NULL,
  ticket_email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_asali_tx_ref (tx_ref),
  KEY idx_asali_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dahk_seasons_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  gender VARCHAR(64) NOT NULL,
  discovery VARCHAR(512) NOT NULL,
  attendance_type VARCHAR(32) NOT NULL,
  ticket_price_naira INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  payment_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  tx_ref VARCHAR(128) NULL,
  ticket_code VARCHAR(64) NULL,
  flutterwave_transaction_id VARCHAR(128) NULL,
  ticket_email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dahk_tx_ref (tx_ref),
  KEY idx_dahk_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
