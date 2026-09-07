<?php
/**
 * classes/Product.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: تمام عملیات مربوط به «محصولات منو» — شامل CRUD کامل
 * و مدیریت آپلود/حذف تصویر محصول.
 *
 * قانون مسئولیت‌پذیری: منطق آپلود عکس هم اینجا نگه داشته شده (نه در یک
 * کلاس جدا) چون فقط محصولات عکس دارند و این منطق فقط همین‌جا لازم است؛
 * ساخت یک کلاس مجزا برای این حجم کد توجیهی نداشت.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

class Product
{
    /** فرمت‌های مجاز برای آپلود تصویر محصول */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // ---------------------------------------------------------------
    // خواندن اطلاعات
    // ---------------------------------------------------------------

    /**
     * تعداد کل محصولات (فعال + غیرفعال).
     */
    public static function count(): int
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    /**
     * تعداد محصولات فعال (یعنی در منوی عمومی دیده می‌شوند).
     */
    public static function countActive(): int
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    }

    /**
     * لیست محصولات برای پنل مدیریت، با امکان فیلتر بر اساس دسته و جستجو در نام.
     *
     * @param array{category_id?: int, search?: string} $filters
     * @return array
     */
    public static function all(array $filters = []): array
    {
        $pdo = Database::getConnection();

        $sql    = 'SELECT p.*, c.name AS category_name
                    FROM products p
                    JOIN categories c ON c.id = p.category_id
                    WHERE 1 = 1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND p.name LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY c.sort_order ASC, p.sort_order ASC, p.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * یافتن یک محصول با شناسه.
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ---------------------------------------------------------------
    // نوشتن اطلاعات
    // ---------------------------------------------------------------

    /**
     * ساخت محصول جدید.
     *
     * @param array $data     name, description, price, category_id, badge, status
     * @param array|null $file    آرایهٔ $_FILES['image'] در صورت آپلود عکس
     * @return int             شناسهٔ محصول تازه‌ساخته‌شده
     * @throws RuntimeException در صورت خطای اعتبارسنجی یا آپلود
     */
    public static function create(array $data, ?array $file = null): int
    {
        $imageName = null;

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imageName = self::handleImageUpload($file);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO products
                (category_id, name, description, price, image, badge, status,
                 discount_enabled, discount_type, discount_value, discount_starts_at, discount_ends_at)
             VALUES
                (:category_id, :name, :description, :price, :image, :badge, :status,
                 :discount_enabled, :discount_type, :discount_value, :discount_starts_at, :discount_ends_at)'
        );

        $stmt->execute([
            'category_id'        => $data['category_id'],
            'name'               => $data['name'],
            'description'        => $data['description'] ?: null,
            'price'              => $data['price'],
            'image'              => $imageName,
            'badge'              => $data['badge'] ?: null,
            'status'             => $data['status'],
            'discount_enabled'   => !empty($data['discount_enabled']) ? 1 : 0,
            'discount_type'      => $data['discount_type'] ?: null,
            'discount_value'     => $data['discount_value'] !== '' && $data['discount_value'] !== null ? $data['discount_value'] : null,
            'discount_starts_at' => $data['discount_starts_at'] ?: null,
            'discount_ends_at'   => $data['discount_ends_at'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * ویرایش محصول موجود. اگر عکس جدیدی ارسال شده باشد، عکس قبلی حذف می‌شود.
     *
     * @param int $id
     * @param array $data
     * @param array|null $file
     * @param bool $removeImage   اگر true باشد و عکس جدیدی هم نیامده باشد، عکس فعلی حذف می‌شود
     * @return bool
     */
    public static function update(int $id, array $data, ?array $file = null, bool $removeImage = false): bool
    {
        $existing = self::find($id);
        if ($existing === null) {
            throw new RuntimeException('محصول مورد نظر پیدا نشد.');
        }

        $imageName = $existing['image'];

        $newImageUploaded = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($newImageUploaded) {
            $imageName = self::handleImageUpload($file);
            self::deleteImageFile($existing['image']);
        } elseif ($removeImage && $imageName !== null) {
            self::deleteImageFile($imageName);
            $imageName = null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE products SET
                category_id        = :category_id,
                name               = :name,
                description        = :description,
                price              = :price,
                image              = :image,
                badge              = :badge,
                status             = :status,
                discount_enabled   = :discount_enabled,
                discount_type      = :discount_type,
                discount_value     = :discount_value,
                discount_starts_at = :discount_starts_at,
                discount_ends_at   = :discount_ends_at
             WHERE id = :id'
        );

        return $stmt->execute([
            'category_id'        => $data['category_id'],
            'name'               => $data['name'],
            'description'        => $data['description'] ?: null,
            'price'              => $data['price'],
            'image'              => $imageName,
            'badge'              => $data['badge'] ?: null,
            'status'             => $data['status'],
            'discount_enabled'   => !empty($data['discount_enabled']) ? 1 : 0,
            'discount_type'      => $data['discount_type'] ?: null,
            'discount_value'     => $data['discount_value'] !== '' && $data['discount_value'] !== null ? $data['discount_value'] : null,
            'discount_starts_at' => $data['discount_starts_at'] ?: null,
            'discount_ends_at'   => $data['discount_ends_at'] ?: null,
            'id'                 => $id,
        ]);
    }

    /**
     * حذف محصول (و عکس مرتبط با آن).
     */
    public static function delete(int $id): bool
    {
        $existing = self::find($id);
        if ($existing === null) {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $result = $stmt->execute(['id' => $id]);

        if ($result && $existing['image']) {
            self::deleteImageFile($existing['image']);
        }

        return $result;
    }

    /**
     * تغییر وضعیت فعال/غیرفعال محصول (یک کلیک از لیست محصولات).
     */
    public static function toggleStatus(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE products
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    // ---------------------------------------------------------------
    // اعتبارسنجی
    // ---------------------------------------------------------------

    /**
     * اعتبارسنجی سمت سرور دادهٔ فرم محصول.
     *
     * @param array $data
     * @return array<string,string>  آرایهٔ خطاها؛ اگر خالی باشد یعنی دیتا معتبر است
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'نام محصول الزامی است.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'نام محصول نباید بیشتر از ۱۵۰ کاراکتر باشد.';
        }

        $price = $data['price'] ?? null;
        if ($price === null || $price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors['price'] = 'قیمت باید یک عدد معتبر و مثبت باشد.';
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId <= 0 || Category::find($categoryId) === null) {
            $errors['category_id'] = 'دسته‌بندی انتخاب‌شده معتبر نیست.';
        }

        $status = $data['status'] ?? '';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'وضعیت محصول نامعتبر است.';
        }

        $description = (string) ($data['description'] ?? '');
        if (mb_strlen($description) > 500) {
            $errors['description'] = 'توضیحات نباید بیشتر از ۵۰۰ کاراکتر باشد.';
        }

        // ---------------- اعتبارسنجی تخفیف (اختیاری) ----------------
        if (!empty($data['discount_enabled'])) {
            $discountType = $data['discount_type'] ?? '';
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                $errors['discount_type'] = 'نوع تخفیف را انتخاب کنید.';
            }

            $discountValue = $data['discount_value'] ?? '';
            if ($discountValue === '' || !is_numeric($discountValue) || (float) $discountValue <= 0) {
                $errors['discount_value'] = 'مقدار تخفیف باید یک عدد مثبت باشد.';
            } elseif ($discountType === 'percentage' && (float) $discountValue > 100) {
                $errors['discount_value'] = 'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.';
            }

            $start = $data['discount_starts_at'] ?? '';
            $end   = $data['discount_ends_at'] ?? '';
            if ($start !== '' && $end !== '' && $start > $end) {
                $errors['discount_ends_at'] = 'تاریخ پایان باید بعد از تاریخ شروع باشد.';
            }
        }

        return $errors;
    }

    // ---------------------------------------------------------------
    // محاسبهٔ قیمت نهایی با در نظر گرفتن تخفیف
    // ---------------------------------------------------------------

    /**
     * محاسبهٔ وضعیت تخفیف یک محصول در همین لحظه (با توجه به تاریخ شروع/پایان).
     * بعد از پایان بازهٔ تخفیف، خودکار به قیمت اصلی برمی‌گردد چون این متد
     * هر بار زمان فعلی را با discount_starts_at/discount_ends_at مقایسه می‌کند —
     * نیازی به یک Job یا Cron برای «غیرفعال کردن خودکار» نیست.
     *
     * @return array{has_discount: bool, original: float, final: float, percent: int}
     */
    public static function calculateDiscount(array $product): array
    {
        $original = (float) $product['price'];

        $result = [
            'has_discount' => false,
            'original'     => $original,
            'final'        => $original,
            'percent'      => 0,
        ];

        if (empty($product['discount_enabled']) || empty($product['discount_type']) || empty($product['discount_value'])) {
            return $result;
        }

        $today = date('Y-m-d');

        if (!empty($product['discount_starts_at']) && $product['discount_starts_at'] > $today) {
            return $result; // هنوز شروع نشده
        }

        if (!empty($product['discount_ends_at']) && $product['discount_ends_at'] < $today) {
            return $result; // تمام شده — خودکار به قیمت اصلی برمی‌گردد
        }

        $value = (float) $product['discount_value'];

        if ($product['discount_type'] === 'percentage') {
            $final = $original - ($original * $value / 100);
        } else {
            $final = $original - $value;
        }

        $final = max(0, round($final));

        if ($final >= $original) {
            return $result;
        }

        $result['has_discount'] = true;
        $result['final']        = $final;
        $result['percent']      = $original > 0 ? (int) round((($original - $final) / $original) * 100) : 0;

        return $result;
    }

    // ---------------------------------------------------------------
    // مدیریت آپلود تصویر (خصوصی)
    // ---------------------------------------------------------------

    /**
     * بررسی و ذخیرهٔ فایل آپلودشده؛ نام یکتای فایل را برمی‌گرداند.
     *
     * @param array $file    آرایهٔ $_FILES['image']
     * @return string        نام فایل ذخیره‌شده
     * @throws RuntimeException در صورت نامعتبر بودن فایل
     */
    private static function handleImageUpload(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('خطا در آپلود تصویر. لطفاً دوباره تلاش کنید.');
        }

        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $maxMb = round(UPLOAD_MAX_SIZE / 1024 / 1024, 1);
            throw new RuntimeException("حجم تصویر نباید بیشتر از {$maxMb} مگابایت باشد.");
        }

        // تشخیص نوع فایل از روی محتوای واقعی آن، نه از روی پسوند (امنیت بیشتر)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new RuntimeException('فرمت تصویر باید JPG، PNG یا WEBP باشد.');
        }

        if (!is_dir(UPLOAD_DIR_PATH) && !mkdir(UPLOAD_DIR_PATH, 0755, true) && !is_dir(UPLOAD_DIR_PATH)) {
            throw new RuntimeException('پوشهٔ آپلود در دسترس نیست.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $fileName  = uniqid('product_', true) . '.' . $extension;
        $destination = rtrim(UPLOAD_DIR_PATH, '/') . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('ذخیرهٔ فایل آپلودشده با خطا مواجه شد.');
        }

        return $fileName;
    }

    /**
     * حذف فیزیکی یک فایل تصویر از پوشهٔ uploads (در صورت وجود).
     */
    private static function deleteImageFile(?string $fileName): void
    {
        if (!$fileName) {
            return;
        }

        $path = rtrim(UPLOAD_DIR_PATH, '/') . '/' . $fileName;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * بروزرسانی ترتیب نمایش محصولات یک دسته‌بندی
     */
    public static function updateSortOrder(int $categoryId, array $orderedIds): bool
    {
        $pdo = Database::getConnection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('UPDATE products SET sort_order = :sort_order WHERE id = :id AND category_id = :category_id');
            foreach ($orderedIds as $index => $id) {
                $stmt->execute([
                    'sort_order' => $index + 1,
                    'id' => (int) $id,
                    'category_id' => $categoryId,
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