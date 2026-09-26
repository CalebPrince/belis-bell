-- Packing and delivery progress for paid orders (staff set it in the admin order view).
ALTER TABLE orders ADD COLUMN fulfilment ENUM('new','packed','out_for_delivery','delivered') NOT NULL DEFAULT 'new' AFTER status;
