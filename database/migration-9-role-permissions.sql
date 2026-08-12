-- migration-9-role-permissions.sql
-- Add permission tables for unified admin/barista access

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(60) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS `barista_permissions` (
  `barista_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`barista_id`, `permission_id`),
  KEY `idx_barista_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_barista_permissions_barista`
    FOREIGN KEY (`barista_id`) REFERENCES `baristas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_barista_permissions_permission`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

INSERT IGNORE INTO `permissions` (`slug`, `label`) VALUES
  ('orders.view', 'مشاهده سفارش‌ها'),
  ('orders.view_details', 'مشاهده جزئیات سفارش'),
  ('orders.update_status', 'تغییر وضعیت سفارش'),
  ('orders.print', 'چاپ سفارش'),
  ('reports.view', 'مشاهده گزارش‌ها'),
  ('products.view', 'مشاهده محصولات'),
  ('products.manage', 'مدیریت محصولات'),
  ('users.manage', 'مدیریت کاربران'),
  ('settings.view', 'مشاهده تنظیمات');

INSERT INTO `barista_permissions` (`barista_id`, `permission_id`)
SELECT b.id, p.id
FROM `baristas` b
JOIN `permissions` p ON p.slug IN ('orders.view', 'orders.view_details', 'orders.update_status', 'orders.print')
LEFT JOIN `barista_permissions` bp ON bp.barista_id = b.id AND bp.permission_id = p.id
WHERE bp.barista_id IS NULL;
