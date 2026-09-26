-- Product page content (PG-039 to PG-043). Text is plain, never HTML. Prices are whole pesewas.
ALTER TABLE products
  ADD COLUMN summary VARCHAR(255) NULL AFTER pack_size,
  ADD COLUMN specs TEXT NULL AFTER usage_notes,
  ADD COLUMN features TEXT NULL AFTER specs,
  ADD COLUMN highlights TEXT NULL AFTER features,
  ADD COLUMN uses VARCHAR(190) NULL AFTER highlights;

-- One row per size. products.price_pesewas and products.pack_size mirror the first (default) size
-- so listing cards keep working.
CREATE TABLE product_variants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL,
  price_pesewas INT UNSIGNED NOT NULL,
  stock_status ENUM('in_stock','low','out') NOT NULL DEFAULT 'in_stock',
  sort_order INT NOT NULL DEFAULT 0,
  is_mock TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_variants_product (product_id, sort_order),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bulk price tiers per size: from min_qty units, each unit costs unit_price_pesewas.
CREATE TABLE bulk_tiers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  min_qty INT UNSIGNED NOT NULL,
  unit_price_pesewas INT UNSIGNED NOT NULL,
  is_mock TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_tier (variant_id, min_qty),
  CONSTRAINT fk_tiers_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
