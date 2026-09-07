<?php
/**
 * classes/Category.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: تمام عملیات مربوط به «دسته‌بندی محصولات» — شامل
 * CRUD کامل و جلوگیری از حذف دسته‌ای که هنوز محصول دارد.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Category
{
    // ---------------------------------------------------------------
    // خواندن اطلاعات
    // ---------------------------------------------------------------

    /**
     * تعداد کل دسته‌بندی‌های ثبت‌شده.
     */
    public static function count(): int
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    }

    /**
     * لیست همهٔ دسته‌بندی‌ها به‌همراه تعداد محصولات هر کدام، مرتب بر اساس ترتیب نمایش.
     *
     * @return array
     */
    public static function all(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * یافتن یک دسته‌بندی با شناسه.
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ---------------------------------------------------------------
    // نوشتن اطلاعات
    // ---------------------------------------------------------------

    /**
     * ساخت دسته‌بندی جدید.
     *
     * @return int شناسهٔ دستهٔ تازه‌ساخته‌شده
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, sort_order) VALUES (:name, :slug, :sort_order)'
        );
        $stmt->execute([
            'name'       => $data['name'],
            'slug'       => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * ویرایش یک دسته‌بندی موجود.
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE categories SET name = :name, slug = :slug, sort_order = :sort_order WHERE id = :id'
        );
        return $stmt->execute([
            'name'       => $data['name'],
            'slug'       => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'id'         => $id,
        ]);
    }

    /**
     * حذف یک دسته‌بندی — فقط اگر هیچ محصولی به آن وصل نباشد.
     *
     * دلیل این محدودیت: کلید خارجی products.category_id با ON DELETE CASCADE
     * تعریف شده، یعنی اگر این بررسی نباشد، حذف یک دسته باعث حذف خاموش و
     * بی‌هشدار همهٔ محصولات آن دسته هم می‌شود. اینجا قبل از حذف واقعی
     * بررسی می‌کنیم تا مدیر آگاهانه تصمیم بگیرد.
     *
     * @throws RuntimeException اگر دسته هنوز محصول داشته باشد
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
        $countStmt->execute(['id' => $id]);

        if ((int) $countStmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'این دسته‌بندی هنوز محصول دارد. ابتدا محصولات آن را حذف یا به دستهٔ دیگری منتقل کنید.'
            );
        }

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // ---------------------------------------------------------------
    // اعتبارسنجی
    // ---------------------------------------------------------------

    /**
     * اعتبارسنجی سمت سرور دادهٔ فرم دسته‌بندی.
     *
     * @param array    $data
     * @param int|null $ignoreId  هنگام ویرایش، شناسهٔ خود رکورد از چک یکتا بودن slug مستثنا می‌شود
     * @return array<string,string> آرایهٔ خطاها؛ اگر خالی باشد یعنی دیتا معتبر است
     */
    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'نام دسته‌بندی الزامی است.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'نام دسته‌بندی نباید بیشتر از ۱۰۰ کاراکتر باشد.';
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $errors['slug'] = 'شناسهٔ انگلیسی (Slug) الزامی است.';
        } elseif (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            $errors['slug'] = 'Slug فقط می‌تواند شامل حروف انگلیسی کوچک، عدد و خط تیره باشد (مثل: cold-drinks).';
        } elseif (self::slugExists($slug, $ignoreId)) {
            $errors['slug'] = 'این Slug قبلاً برای دستهٔ دیگری استفاده شده است.';
        }

        return $errors;
    }

    /**
     * آیا این slug از قبل در جدول categories وجود دارد؟ (برای اعتبارسنجی یکتا بودن)
     */
    private static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $pdo = Database::getConnection();

        $sql    = 'SELECT COUNT(*) FROM categories WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * بروزرسانی ترتیب نمایش دسته‌بندی‌ها
     */
    public static function updateSortOrder(array $orderedIds): bool
    {
        $pdo = Database::getConnection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');
            foreach ($orderedIds as $index => $id) {
                $stmt->execute([
                    'sort_order' => $index + 1,
                    'id' => (int) $id,
                ]);
            }
            if (!$inTx) {
                $pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }
}