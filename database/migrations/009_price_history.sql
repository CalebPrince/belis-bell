-- Price and tier history for every change (CTL-BIZ-001), and a detail line on audit entries (field names only, never secrets).
CREATE TABLE price_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL,
  old_value VARCHAR(500) NULL,
  new_value VARCHAR(500) NOT NULL,
  changed_by INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_price_history_variant (variant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TRIGGER price_history_no_update BEFORE UPDATE ON price_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'price_history is append-only';

CREATE TRIGGER price_history_no_delete BEFORE DELETE ON price_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'price_history is append-only';

ALTER TABLE audit_log ADD COLUMN detail VARCHAR(255) NULL AFTER target;
