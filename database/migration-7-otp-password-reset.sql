-- migration-7-otp-password-reset.sql
-- Add password_reset support to existing OTP purpose values.

ALTER TABLE `otp_codes`
  MODIFY COLUMN `purpose` ENUM('registration','login','password_reset') NOT NULL;