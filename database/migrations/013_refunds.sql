-- Refunds (CTL-PAY-003). A refund row never changes; its progress is a list of events, and both tables refuse
-- updates and deletes. Only amounts, a short reason and status are kept: never card or mobile money details.
CREATE TABLE refunds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  amount_pesewas INT UNSIGNED NOT NULL,
  reason VARCHAR(200) NOT NULL,
  requested_by INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_refunds_order (order_id),
  KEY idx_refunds_created (created_at),
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE refund_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  refund_id INT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL,
  source VARCHAR(12) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_refund_events_refund (refund_id, id),
  CONSTRAINT fk_refund_events_refund FOREIGN KEY (refund_id) REFERENCES refunds (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TRIGGER refunds_no_update BEFORE UPDATE ON refunds FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refunds is append-only';

CREATE TRIGGER refunds_no_delete BEFORE DELETE ON refunds FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refunds is append-only';

CREATE TRIGGER refund_events_no_update BEFORE UPDATE ON refund_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund_events is append-only';

CREATE TRIGGER refund_events_no_delete BEFORE DELETE ON refund_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund_events is append-only';
