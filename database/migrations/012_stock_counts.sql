-- Stock as a counted number per size (CTL-BIZ-002). The count is converted from the old label as a PLACEHOLDER
-- (in stock = 100, low = 5, out = 0); replace each with the real count in the admin product screen.
ALTER TABLE product_variants ADD COLUMN stock_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER stock_status;

UPDATE product_variants SET stock_qty = CASE stock_status WHEN 'in_stock' THEN 100 WHEN 'low' THEN 5 ELSE 0 END;

CREATE TABLE stock_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  delta INT NOT NULL,
  qty_after INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL,
  reason VARCHAR(200) NOT NULL,
  order_id INT UNSIGNED NULL,
  changed_by INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_stock_history_variant (variant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TRIGGER stock_history_no_update BEFORE UPDATE ON stock_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_history is append-only';

CREATE TRIGGER stock_history_no_delete BEFORE DELETE ON stock_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_history is append-only';

INSERT INTO stock_history (variant_id, delta, qty_after, kind, reason, created_at) SELECT id, stock_qty, stock_qty, 'initial', 'Converted from the old stock label (placeholder count, replace with the real one)', UNIX_TIMESTAMP() FROM product_variants;
