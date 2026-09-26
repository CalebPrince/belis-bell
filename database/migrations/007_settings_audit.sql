-- Encrypted settings and the append-only audit log (CTL-SET-001, CTL-AUDIT-001).
CREATE TABLE settings (
  name VARCHAR(60) NOT NULL PRIMARY KEY,
  value_enc TEXT NOT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  target VARCHAR(80) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';

CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';
