-- Quote requests for institutions and businesses: the request, its lines, a message thread and price offers.
-- Text only: there are no file attachments (uploads are not approved yet, CTL-UPL-001). Times are unix seconds.
CREATE TABLE quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(20) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  org_name VARCHAR(120) NULL,
  status ENUM('new','in_progress','quoted','accepted','declined','closed') NOT NULL DEFAULT 'new',
  needed_by INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  accepted_offer_id INT UNSIGNED NULL,
  accepted_at INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_quotes_ref (ref),
  KEY idx_quotes_user (user_id, created_at),
  KEY idx_quotes_status (status, updated_at),
  CONSTRAINT fk_quotes_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  note VARCHAR(200) NULL,
  KEY idx_quote_items_quote (quote_id),
  CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NULL,
  author_role VARCHAR(10) NOT NULL,
  body VARCHAR(2000) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_quote_messages_quote (quote_id, id),
  CONSTRAINT fk_quote_messages_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  total_pesewas INT UNSIGNED NOT NULL,
  valid_until INT UNSIGNED NOT NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_quote_offers_quote (quote_id, id),
  CONSTRAINT fk_quote_offers_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_offer_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  offer_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  unit_pesewas INT UNSIGNED NOT NULL,
  KEY idx_offer_lines_offer (offer_id),
  CONSTRAINT fk_offer_lines_offer FOREIGN KEY (offer_id) REFERENCES quote_offers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TRIGGER quote_messages_no_update BEFORE UPDATE ON quote_messages FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'quote_messages is append-only';

CREATE TRIGGER quote_messages_no_delete BEFORE DELETE ON quote_messages FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'quote_messages is append-only';

CREATE TRIGGER quote_offers_no_update BEFORE UPDATE ON quote_offers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'quote_offers is append-only';

CREATE TRIGGER quote_offers_no_delete BEFORE DELETE ON quote_offers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'quote_offers is append-only';
