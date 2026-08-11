-- migration-8-print-job-types.sql
-- Add explicit job type support for print jobs and allow multiple customer invoice reprints.

ALTER TABLE `print_jobs`
  ADD COLUMN `job_type` ENUM('preparation','customer_invoice') NOT NULL DEFAULT 'preparation' AFTER `order_number`,
  ADD COLUMN `job_reference` VARCHAR(64) NOT NULL DEFAULT '' AFTER `job_type`,
  DROP INDEX `uq_print_jobs_order`,
  ADD UNIQUE KEY `uq_print_jobs_order_type_reference` (`order_id`,`job_type`,`job_reference`);
