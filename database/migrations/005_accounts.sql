-- Accounts, emailed one-time codes and rate limits (CTL-AUTH-001, CTL-AUTH-002). Times are unix seconds.
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','staff','owner') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auth_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose VARCHAR(20) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_auth_codes_user (user_id, purpose),
  CONSTRAINT fk_auth_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE throttle (
  key_hash CHAR(64) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
