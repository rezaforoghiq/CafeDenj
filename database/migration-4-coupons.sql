-- Migration 4: Remove customer_discounts table and add coupons + order coupon columns

-- Drop old customer_discounts table if present (safe to run)
DROP TABLE IF EXISTS `customer_discounts`;

-- Create coupons table
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`           VARCHAR(64) NOT NULL,
  `percent`        INT NOT NULL,
  `expires_at`     DATE NULL DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- Add coupon columns to orders if they don't exist (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `coupon_code` VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `coupon_percent` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0;