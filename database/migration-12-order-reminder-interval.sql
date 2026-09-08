-- ---------------------------------------------------------------------------
-- Migration 12: Add default order_reminder_interval setting
-- Values: Integer between 1 and 50 (seconds, default: 7)
-- ---------------------------------------------------------------------------

INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('order_reminder_interval', '7')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;