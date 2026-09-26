-- Password change time (sessions started before it are ended), and saved delivery addresses.
ALTER TABLE users ADD COLUMN pw_changed_at INT UNSIGNED NULL AFTER email_verified_at;

CREATE TABLE addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  street VARCHAR(200) NOT NULL,
  city VARCHAR(80) NOT NULL,
  region VARCHAR(40) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
