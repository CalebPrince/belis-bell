-- Orders, order lines and payment events (CTL-BIZ-001, CTL-PAY-001, CTL-PAY-002). Amounts are whole pesewas.
-- Only provider references and status are kept. No card or mobile money details ever reach these tables.
CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(20) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'GHS',
  subtotal_pesewas INT UNSIGNED NOT NULL,
  delivery_pesewas INT UNSIGNED NOT NULL,
  total_pesewas INT UNSIGNED NOT NULL,
  delivery_method VARCHAR(60) NOT NULL,
  ship_name VARCHAR(120) NOT NULL,
  ship_phone VARCHAR(30) NOT NULL,
  ship_street VARCHAR(200) NOT NULL,
  ship_city VARCHAR(80) NOT NULL,
  ship_region VARCHAR(40) NOT NULL,
  notes VARCHAR(500) NULL,
  payment_reference VARCHAR(40) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  paid_at INT UNSIGNED NULL,
  UNIQUE KEY uq_orders_ref (ref),
  UNIQUE KEY uq_orders_payment_reference (payment_reference),
  KEY idx_orders_user (user_id, created_at),
  KEY idx_orders_status (status, created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  size_label VARCHAR(60) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  unit_pesewas INT UNSIGNED NOT NULL,
  line_pesewas INT UNSIGNED NOT NULL,
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  source VARCHAR(20) NOT NULL,
  outcome VARCHAR(40) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_payment_events_order (order_id),
  CONSTRAINT fk_payment_events_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
