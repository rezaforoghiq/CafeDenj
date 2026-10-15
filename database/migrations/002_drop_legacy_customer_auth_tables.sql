-- =========================================================================
-- Migration 002: Safe Deprecation & Cleanup of Legacy Customer Authentication
-- =========================================================================
-- Drops the obsolete customer authentication tables that are no longer
-- used in the Guest Checkout flow (Phase 2).
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop obsolete tables
DROP TABLE IF EXISTS `customer_accounts`;
DROP TABLE IF EXISTS `otp_codes`;
DROP TABLE IF EXISTS `pending_registrations`;

SET FOREIGN_KEY_CHECKS = 1;
