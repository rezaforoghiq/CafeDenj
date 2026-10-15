-- =========================================================================
-- Migration 001: Add guest checkout support and table_number column
-- =========================================================================
-- Adds:
--   orders.table_number   VARCHAR(50) NOT NULL DEFAULT ''
--   orders.guest_token    VARCHAR(64) NULL + INDEX
--   orders.customer_id    changed to NULLABLE (ON DELETE SET NULL)
--   carts.guest_token     VARCHAR(64) NULL + INDEX
--   carts.customer_id     changed to NULLABLE (FK dropped)
--
-- Safe: incremental ALTER — does NOT drop or recreate existing tables.
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- orders table
-- -------------------------------------------------------------------------

-- 1. Add table_number (required field, defaults to empty string)
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `table_number` VARCHAR(50) NOT NULL DEFAULT ''
        AFTER `customer_note`;

-- 2. Add guest_token (nullable — set when order is created via guest flow)
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `guest_token` VARCHAR(64) NULL DEFAULT NULL
        AFTER `table_number`;

-- 3. Index on guest_token for fast lookup
ALTER TABLE `orders`
    ADD INDEX IF NOT EXISTS `idx_orders_guest_token` (`guest_token`);

-- 4. Drop the old NOT NULL / RESTRICT foreign key on customer_id
ALTER TABLE `orders`
    DROP FOREIGN KEY IF EXISTS `fk_orders_customer`;

-- 5. Make customer_id nullable (existing rows keep their values)
ALTER TABLE `orders`
    MODIFY COLUMN `customer_id` INT UNSIGNED NULL DEFAULT NULL;

-- 6. Re-add FK with ON DELETE SET NULL so deleting a customer record
--    does not cascade-delete their historical orders
ALTER TABLE `orders`
    ADD CONSTRAINT `fk_orders_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- -------------------------------------------------------------------------
-- carts table
-- -------------------------------------------------------------------------

-- 7. Add guest_token to carts (replaces customer_id as the cart identity key)
ALTER TABLE `carts`
    ADD COLUMN IF NOT EXISTS `guest_token` VARCHAR(64) NULL DEFAULT NULL
        AFTER `customer_id`;

-- 8. Index on guest_token for fast cart lookups
ALTER TABLE `carts`
    ADD INDEX IF NOT EXISTS `idx_carts_guest_token` (`guest_token`);

-- 9. Drop old UNIQUE key that referenced customer_id + product_id
--    (we will enforce uniqueness via (guest_token, product_id) in application logic)
ALTER TABLE `carts`
    DROP INDEX IF EXISTS `uq_cart_customer_product`;

-- 10. Drop the FK from carts → customers (carts are now guest-owned)
ALTER TABLE `carts`
    DROP FOREIGN KEY IF EXISTS `fk_carts_customer`;

-- 11. Make customer_id nullable (old rows may still reference a customer)
ALTER TABLE `carts`
    MODIFY COLUMN `customer_id` INT UNSIGNED NULL DEFAULT NULL;

-- 12. Add new UNIQUE key: one row per (guest_token, product_id)
ALTER TABLE `carts`
    ADD UNIQUE KEY IF NOT EXISTS `uq_cart_guest_product` (`guest_token`, `product_id`);

SET FOREIGN_KEY_CHECKS = 1;
