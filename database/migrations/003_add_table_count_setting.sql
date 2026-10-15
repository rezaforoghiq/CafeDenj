-- Migration 003: Add table_count default setting
-- Adds the default cafe table count to the settings table if not already present.

INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('table_count', '10')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
