-- migration-5-print-jobs.sql
-- Create print_jobs table for automatic thermal printing queue

CREATE TABLE IF NOT EXISTS `print_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `order_number` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `payload` LONGTEXT NULL,
  `retry_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `printed_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `uq_print_jobs_order` UNIQUE (`order_id`),
  KEY `idx_print_jobs_status` (`status`),
  KEY `idx_print_jobs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
