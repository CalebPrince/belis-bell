-- Product detail fields shown on the product page (PG-020). Text is plain, never HTML.
ALTER TABLE products
  ADD COLUMN description TEXT NULL AFTER pack_size,
  ADD COLUMN usage_notes TEXT NULL AFTER description;
