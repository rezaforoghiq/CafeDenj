-- migration-10-order-items-discount.sql
-- Add original_price, discount_percent, and per-unit discount_amount to order_items for historical accuracy and invoice printing.

ALTER TABLE `order_items`
  ADD COLUMN `original_price` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0 AFTER `quantity`,
  ADD COLUMN `discount_percent` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `original_price`,
  ADD COLUMN `discount_amount` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0 AFTER `discount_percent`;

-- Backfill existing historical rows: set original_price to price if not set, with 0 discount
UPDATE `order_items`
  SET `original_price` = `price`
  WHERE `original_price` = 0;