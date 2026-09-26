-- Subcategories (one level) and brands (PG-046, PG-047, PG-051). Brand is optional plain text.
ALTER TABLE categories
  ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
  ADD KEY idx_categories_parent (parent_id),
  ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE CASCADE;

ALTER TABLE products
  ADD COLUMN subcategory_id INT UNSIGNED NULL AFTER category_id,
  ADD COLUMN brand VARCHAR(80) NULL AFTER name,
  ADD KEY idx_products_brand (brand),
  ADD KEY idx_products_price (price_pesewas),
  ADD CONSTRAINT fk_products_subcategory FOREIGN KEY (subcategory_id) REFERENCES categories (id) ON DELETE SET NULL;
