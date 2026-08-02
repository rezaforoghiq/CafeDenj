<?php
/**
 * public_html/db-test.php
 * -----------------------------------------------------------------------
 * ⚠️ این فایل فقط برای تست اتصال دیتابیس است (لوکال یا هاست).
 * بعد از اطمینان از درست بودن اتصال، این فایل را حتماً حذف کنید —
 * به‌خصوص روی هاست، چون نباید هیچ فایل تستی در دسترس عموم بماند.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
    $count = $pdo->query('SELECT COUNT(*) AS total FROM products')->fetch()['total'];

    echo '✅ اتصال به دیتابیس با موفقیت انجام شد.<br>';
    echo "تعداد محصولات موجود در جدول products: {$count}<br>";
    echo 'محیط فعلی (APP_ENV): ' . APP_ENV;
} catch (Throwable $e) {
    echo '❌ خطا در اتصال یا کوئری: ' . $e->getMessage();
}
