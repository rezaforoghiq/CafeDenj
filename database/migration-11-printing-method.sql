-- ---------------------------------------------------------------------------
-- Migration 11: Add default printing_method setting
-- Values: 'automatic' (default, Windows Print Bridge API) or 'manual' (Browser Print 80mm)
-- ---------------------------------------------------------------------------

INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('printing_method', 'automatic')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;