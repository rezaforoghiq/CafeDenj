<?php

declare(strict_types=1);

require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Product.php';

/**
 * classes/Order.php
 * -----------------------------------------------------------------------
 * مسئولیت این کلاس: مدیریت سبد خرید مهمان، ثبت سفارش، و بازیابی
 * وضعیت سفارش‌ها.
 *
 * در فاز ۱ (Guest Checkout)، سبد خرید و سفارش‌ها بر اساس guest_token
 * (یک رشتهٔ hex 32 کاراکتری) مدیریت می‌شوند، نه customer_id.
 * -----------------------------------------------------------------------
 */
class Order
{
    // ====================================================================
    // CART — Guest-token–based
    // ====================================================================

    /**
     * بازیابی محتوای سبد خرید برای یک guest_token.
     *
     * @param string $guestToken توکن جلسهٔ مهمان
     * @return array<int, array<string, mixed>> آرایهٔ آیتم‌های سبد خرید
     */
    public static function cart(string $guestToken): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT c.product_id, c.quantity, p.name, p.price, p.image,
                    p.discount_enabled, p.discount_type, p.discount_value,
                    p.discount_starts_at, p.discount_ends_at
             FROM carts c
             JOIN products p ON p.id = c.product_id
             WHERE c.guest_token = :token AND p.status = "active"'
        );
        $stmt->execute(['token' => $guestToken]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $discount = Product::calculateDiscount($row);
            $items[]  = [
                'product_id'       => (int)   $row['product_id'],
                'quantity'         => (int)   $row['quantity'],
                'name'             => (string) $row['name'],
                'image'            => $row['image'],
                'original_price'   => (float)  $row['price'],
                'price'            => (float)  $discount['final'],
                'has_discount'     => (bool)   $discount['has_discount'],
                'discount_percent' => (int)    $discount['percent'],
                'discount_amount'  => (float)  ($discount['original'] - $discount['final']),
            ];
        }

        return $items;
    }

    /**
     * افزودن یک محصول به سبد خرید مهمان.
     *
     * @param string $guestToken توکن جلسهٔ مهمان
     * @param int    $productId  شناسهٔ محصول
     * @param int    $qty        تعداد (پیش‌فرض: ۱)
     * @throws RuntimeException اگر محصول موجود نباشد
     */
    public static function add(string $guestToken, int $productId, int $qty = 1): void
    {
        $product = Product::find($productId);
        if (!$product || $product['status'] !== 'active') {
            throw new RuntimeException('محصول در دسترس نیست.');
        }

        $qty  = max(1, min(99, $qty));
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO carts (guest_token, product_id, quantity)
             VALUES (:token, :product, :qty)
             ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + VALUES(quantity))'
        );
        $stmt->execute(['token' => $guestToken, 'product' => $productId, 'qty' => $qty]);
    }

    /**
     * به‌روزرسانی تعداد یک آیتم در سبد خرید مهمان.
     * اگر qty صفر یا کمتر باشد، آیتم حذف می‌شود.
     *
     * @param string $guestToken توکن جلسهٔ مهمان
     * @param int    $productId  شناسهٔ محصول
     * @param int    $qty        تعداد جدید (۰ = حذف)
     */
    public static function updateCart(string $guestToken, int $productId, int $qty): void
    {
        $pdo = Database::getConnection();

        if ($qty < 1) {
            $pdo->prepare('DELETE FROM carts WHERE guest_token = :token AND product_id = :product')
                ->execute(['token' => $guestToken, 'product' => $productId]);
            return;
        }

        $q    = max(1, min(99, $qty));
        $stmt = $pdo->prepare(
            'INSERT INTO carts (guest_token, product_id, quantity)
             VALUES (:token, :product, :qty)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute(['token' => $guestToken, 'product' => $productId, 'qty' => $q]);
    }

    // ====================================================================
    // ORDER PLACEMENT
    // ====================================================================

    /**
     * ثبت سفارش جدید برای یک مهمان.
     *
     * جریان کامل (درون یک تراکنش PDO):
     *  1. اعتبارسنجی سبد خرید و شماره میز
     *  2. اعمال کوپن تخفیف (در صورت ارسال)
     *  3. درج رکورد اصلی سفارش با guest_token و table_number
     *  4. درج آیتم‌های سفارش
     *  5. اگر اطلاعات CRM ارسال شده: پیدا کردن یا ایجاد رکورد customers
     *     و به‌روزرسانی orders.customer_id
     *  6. پاک‌سازی سبد خرید
     *  7. Commit تراکنش
     *
     * @param string      $guestToken  توکن جلسهٔ مهمان
     * @param string      $tableNumber شماره میز (الزامی)
     * @param string      $note        توضیحات سفارش (اختیاری)
     * @param string|null $couponCode  کد کوپن (اختیاری)
     * @param string|null $phone       شماره تماس CRM (اختیاری)
     * @param string|null $firstName   نام CRM (اختیاری)
     * @param string|null $lastName    نام‌خانوادگی CRM (اختیاری)
     * @return int شناسهٔ سفارش ثبت‌شده
     * @throws RuntimeException در صورت ناموفق بودن ثبت
     */
    public static function place(
        string  $guestToken,
        string  $tableNumber,
        string  $note       = '',
        ?string $couponCode = null,
        ?string $phone      = null,
        ?string $firstName  = null,
        ?string $lastName   = null
    ): int {
        // --- اعتبارسنجی ورودی‌ها ---
        $tableNumber = trim($tableNumber);
        if ($tableNumber === '') {
            throw new RuntimeException('شماره میز الزامی است.');
        }
        if (mb_strlen($tableNumber) > 50) {
            throw new RuntimeException('شماره میز نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.');
        }

        $pdo   = Database::getConnection();
        $items = self::cart($guestToken);

        if (!$items) {
            throw new RuntimeException('سبد خرید خالی است.');
        }

        $pdo->beginTransaction();

        try {
            self::acquireOrderNumberLock($pdo);
            self::rebuildSequentialOrderNumbers($pdo);

            // --- محاسبهٔ مجموع قیمت ---
            $total = 0;
            foreach ($items as $item) {
                $total += (int) $item['price'] * (int) $item['quantity'];
            }

            // --- اعمال کوپن ---
            $appliedCouponCode    = null;
            $appliedCouponPercent = null;
            $discountAmount       = 0;

            if ($couponCode !== null && trim($couponCode) !== '') {
                require_once __DIR__ . '/Coupon.php';
                $coupon = Coupon::findByCode(trim($couponCode));
                if (!$coupon || !Coupon::isValidCoupon($coupon)) {
                    throw new RuntimeException('کد کوپن نامعتبر یا منقضی شده است.');
                }
                $appliedCouponCode    = $coupon['code'];
                $appliedCouponPercent = (int) $coupon['percent'];
                $discountAmount       = (int) round($total * $appliedCouponPercent / 100);
            }

            $finalTotal = max(0, $total - $discountAmount);

            // --- درج رکورد اصلی سفارش ---
            $orderNumber = self::nextSequentialOrderNumber($pdo);
            $ins         = $pdo->prepare(
                'INSERT INTO orders
                    (guest_token, table_number, order_number, total_price, customer_note,
                     coupon_code, coupon_percent, discount_amount)
                 VALUES
                    (:token, :table, :number, :total, :note,
                     :ccode, :cpercent, :damount)'
            );
            $ins->execute([
                'token'    => $guestToken,
                'table'    => $tableNumber,
                'number'   => $orderNumber,
                'total'    => $finalTotal,
                'note'     => mb_substr(trim($note), 0, 500),
                'ccode'    => $appliedCouponCode,
                'cpercent' => $appliedCouponPercent,
                'damount'  => $discountAmount,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // --- درج آیتم‌های سفارش ---
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, product_name, quantity,
                     original_price, discount_percent, discount_amount, price)
                 VALUES
                    (:order, :product, :name, :qty, :orig, :dp, :da, :price)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'order'   => $orderId,
                    'product' => $item['product_id'],
                    'name'    => $item['name'],
                    'qty'     => $item['quantity'],
                    'orig'    => $item['original_price'] ?? $item['price'],
                    'dp'      => $item['discount_percent'] ?? 0,
                    'da'      => $item['discount_amount']  ?? 0,
                    'price'   => $item['price'],
                ]);
            }

            // --- CRM: پیدا کردن یا ایجاد رکورد مشتری ---
            $customerId = null;
            if ($phone !== null && trim($phone) !== '') {
                require_once __DIR__ . '/Customer.php';
                $customerId = Customer::findOrCreate(
                    trim($phone),
                    $firstName !== null ? trim($firstName) : null,
                    $lastName  !== null ? trim($lastName)  : null,
                    'checkout'
                );
                // به‌روزرسانی orders.customer_id
                $pdo->prepare('UPDATE orders SET customer_id = :cid WHERE id = :oid')
                    ->execute(['cid' => $customerId, 'oid' => $orderId]);
            }

            // --- پاک‌سازی سبد خرید ---
            $pdo->prepare('DELETE FROM carts WHERE guest_token = :token')
                ->execute(['token' => $guestToken]);

            $pdo->commit();

            // --- ثبت لاگ ---
            $userLabel = $customerId !== null
                ? (trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: 'مشتری')
                : 'مهمان';
            ActivityLog::record(
                'order_create',
                'customer',
                $customerId,
                $userLabel,
                $orderId,
                'ثبت سفارش ' . $orderNumber . ' — میز ' . $tableNumber
            );

            return $orderId;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::releaseOrderNumberLock($pdo);
        }
    }

    // ====================================================================
    // ORDER ACCESS — Guest-token–based
    // ====================================================================

    /**
     * بازیابی یک سفارش خاص با تطبیق guest_token (برای دسترسی موقت مهمان).
     * دسترسی ادمین/باریستا باید از Order::findById() استفاده کند.
     *
     * @param int    $id         شناسهٔ سفارش
     * @param string $guestToken توکن جلسهٔ مهمان
     * @return array<string, mixed>|null null اگر سفارش یافت نشد یا توکن مطابقت ندارد
     */
    public static function findByGuestToken(int $id, string $guestToken): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM orders WHERE id = :id AND guest_token = :token'
        );
        $stmt->execute(['id' => $id, 'token' => $guestToken]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $items = Database::getConnection()->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $items->execute(['id' => $id]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    /**
     * بازیابی تمام سفارش‌های یک جلسهٔ مهمان (برای صفحهٔ «سفارش‌های من»).
     *
     * @param string $guestToken توکن جلسهٔ مهمان
     * @return array<int, array<string, mixed>>
     */
    public static function findByGuestTokenAll(string $guestToken): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM orders WHERE guest_token = :token ORDER BY id DESC'
        );
        $stmt->execute(['token' => $guestToken]);
        return $stmt->fetchAll();
    }

    /**
     * حذف یک سفارش در وضعیت «در انتظار» توسط مهمان (با تطبیق guest_token).
     *
     * @param int    $id         شناسهٔ سفارش
     * @param string $guestToken توکن جلسهٔ مهمان
     * @return bool true اگر حذف موفق باشد
     */
    public static function deletePending(int $id, string $guestToken): bool
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare(
                "SELECT order_number FROM orders
                 WHERE id = :id AND guest_token = :token AND status = 'pending'
                 FOR UPDATE"
            );
            $select->execute(['id' => $id, 'token' => $guestToken]);
            $order = $select->fetch();

            if (!$order) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('DELETE FROM print_jobs WHERE order_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM orders WHERE id = :id')->execute(['id' => $id]);
            self::rebuildSequentialOrderNumbers($pdo);
            $pdo->commit();

            ActivityLog::record(
                'order_delete',
                'customer',
                null,
                'مهمان',
                $id,
                'حذف سفارش ' . ($order['order_number'] ?? '')
            );

            return true;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ActivityLog::record(
                'order_delete',
                'system',
                null,
                'system',
                $id,
                'خطا هنگام حذف سفارش: ' . mb_substr($e->getMessage(), 0, 200)
            );
            return false;
        }
    }

    // ====================================================================
    // ADMIN-FACING — unchanged signatures
    // ====================================================================

    /**
     * بازیابی یک سفارش با شناسه (برای ادمین/باریستا/رسید).
     *
     * @param int $id شناسهٔ سفارش
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT o.*,
                    COALESCE(c.phone, \'\') AS phone,
                    IFNULL(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), \'مهمان\') AS customer_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $items = Database::getConnection()->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $items->execute(['id' => $id]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    /**
     * بازیابی تمام سفارش‌ها (برای پنل ادمین).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::getConnection()->query(
            'SELECT o.*,
                    COALESCE(c.phone, \'\') AS phone,
                    IFNULL(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), \'مهمان\') AS customer_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             ORDER BY o.id DESC'
        )->fetchAll();
    }

    /**
     * تغییر وضعیت سفارش (ادمین/باریستا).
     *
     * @param int         $id            شناسهٔ سفارش
     * @param string      $status        وضعیت جدید
     * @param int|null    $actorId       شناسهٔ کاربر عامل
     * @param string|null $actorLabel    نام کاربر عامل
     * @param string      $role          نقش کاربر عامل ('admin' | 'barista')
     * @param string|null $paymentMethod روش پرداخت (الزامی در وضعیت completed)
     */
    public static function status(
        int     $id,
        string  $status,
        ?int    $actorId       = null,
        ?string $actorLabel    = null,
        string  $role          = 'admin',
        ?string $paymentMethod = null
    ): void {
        if (!in_array($status, ['pending', 'approved', 'rejected', 'completed'], true)) {
            throw new RuntimeException('وضعیت نامعتبر است.');
        }

        $actorId    = $actorId    ?? Auth::id();
        $actorLabel = $actorLabel ?? Auth::username();
        $pdo        = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $sel = $pdo->prepare('SELECT status, barista_id FROM orders WHERE id = :i FOR UPDATE');
            $sel->execute(['i' => $id]);
            $order = $sel->fetch();

            if (!$order) {
                $pdo->rollBack();
                throw new RuntimeException('سفارش پیدا نشد.');
            }

            $currentStatus   = $order['status'];
            $currentBaristaId = $order['barista_id'] ? (int) $order['barista_id'] : null;

            // برگشت به pending — فقط ادمین یا باریستای مسئول
            if ($status === 'pending') {
                if ($role === 'barista' && $currentBaristaId !== $actorId) {
                    $pdo->rollBack();
                    throw new RuntimeException('شما اجازهٔ برگرداندن سفارش به حالت در انتظار تأیید را ندارید.');
                }
                $pdo->prepare('UPDATE orders SET status = :s, barista_id = NULL, payment_method = NULL WHERE id = :i')
                    ->execute(['s' => $status, 'i' => $id]);
                $pdo->commit();
                ActivityLog::record('order_status', $role, $actorId, $actorLabel, $id, 'تغییر وضعیت سفارش به «pending» و حذف تخصیص باریستا');
                return;
            }

            // approve / reject / complete
            if (in_array($status, ['approved', 'rejected', 'completed'], true)) {
                if ($role === 'barista') {
                    if ($currentStatus === 'pending') {
                        if ($currentBaristaId === null) {
                            $pdo->prepare('UPDATE orders SET barista_id = :b WHERE id = :i')
                                ->execute(['b' => $actorId, 'i' => $id]);
                            ActivityLog::record('order_barista', 'barista', $actorId, $actorLabel, $id, 'باریستا سفارش را پذیرفت');
                            $currentBaristaId = $actorId;
                        } elseif ($currentBaristaId !== $actorId) {
                            $pdo->rollBack();
                            throw new RuntimeException('این سفارش اکنون توسط باریستا دیگری در دسترس است.');
                        }
                    } elseif ($currentBaristaId !== $actorId) {
                        $pdo->rollBack();
                        throw new RuntimeException('شما اجازهٔ تغییر وضعیت این سفارش را ندارید.');
                    }
                }

                if ($status === 'approved') {
                    $pdo->prepare('UPDATE orders SET status = :s, approved_at = NOW() WHERE id = :i')
                        ->execute(['s' => $status, 'i' => $id]);
                } elseif ($status === 'completed') {
                    $pdo->prepare('UPDATE orders SET status = :s, approved_at = NOW(), payment_method = :pm WHERE id = :i')
                        ->execute(['s' => $status, 'pm' => $paymentMethod, 'i' => $id]);
                } else {
                    $pdo->prepare('UPDATE orders SET status = :s, payment_method = NULL WHERE id = :i')
                        ->execute(['s' => $status, 'i' => $id]);
                }

                $pdo->commit();

                if ($status === 'approved') {
                    try {
                        require_once __DIR__ . '/PrintJob.php';
                        PrintJob::createForOrder($id);
                    } catch (Throwable $printErr) {
                        ActivityLog::record('order_status', 'system', null, 'system', $id, 'Print job error: ' . mb_substr($printErr->getMessage(), 0, 200));
                    }
                }

                $actionMap = ['approved' => 'order_approve', 'rejected' => 'order_reject', 'completed' => 'order_complete'];
                ActivityLog::record($actionMap[$status] ?? 'order_status', $role, $actorId, $actorLabel, $id, 'تغییر وضعیت سفارش به «' . $status . '»');
                return;
            }

            $pdo->rollBack();
            throw new RuntimeException('عملیات نامشخص وضعیت سفارش');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * تخصیص باریستا به سفارش (ادمین).
     *
     * @param int      $id          شناسهٔ سفارش
     * @param int|null $baristaId   شناسهٔ باریستا (null = حذف تخصیص)
     * @param int|null $actorId     شناسهٔ ادمین
     * @param string|null $actorLabel نام ادمین
     */
    public static function assignBarista(int $id, ?int $baristaId, ?int $actorId = null, ?string $actorLabel = null): void
    {
        Database::getConnection()
            ->prepare('UPDATE orders SET barista_id = :b WHERE id = :i')
            ->execute(['b' => $baristaId, 'i' => $id]);

        ActivityLog::record(
            'order_barista',
            'admin',
            $actorId    ?? Auth::id(),
            $actorLabel ?? Auth::username(),
            $id,
            $baristaId ? 'اختصاص باریستا به سفارش' : 'حذف باریستای سفارش'
        );
    }

    /**
     * حذف سفارش توسط ادمین.
     *
     * @param int         $id         شناسهٔ سفارش
     * @param int|null    $actorId    شناسهٔ ادمین
     * @param string|null $actorLabel نام ادمین
     * @return bool
     */
    public static function delete(int $id, ?int $actorId = null, ?string $actorLabel = null): bool
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();
            $select = $pdo->prepare('SELECT order_number FROM orders WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $id]);
            $order = $select->fetch();

            if (!$order) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('DELETE FROM print_jobs WHERE order_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM orders WHERE id = :id')->execute(['id' => $id]);
            self::rebuildSequentialOrderNumbers($pdo);
            $pdo->commit();

            ActivityLog::record('order_delete', 'admin', $actorId ?? Auth::id(), $actorLabel ?? Auth::username(), $id, 'حذف سفارش ' . ($order['order_number'] ?? ''));
            return true;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ActivityLog::record('order_delete', 'system', null, 'system', $id, 'خطا هنگام حذف سفارش: ' . mb_substr($e->getMessage(), 0, 200));
            return false;
        }
    }

    /**
     * گزارش سفارش‌ها با جستجو/فیلتر/مرتب‌سازی — برای صفحهٔ مدیریت سفارش‌ها.
     *
     * @param array<string, mixed> $f فیلترها
     * @return array<int, array<string, mixed>>
     */
    public static function report(array $f = []): array
    {
        $where  = [];
        $params = [];

        $baristaPendingAll   = !empty($f['barista_pending_all']);
        $statusFilterProvided = !empty($f['status']);

        if ($baristaPendingAll && !$statusFilterProvided) {
            $where[]              = '((o.status = \'pending\') OR (o.barista_id = :barista_id))';
            $params['barista_id'] = (int) $f['barista_id'];

            if (!empty($f['q'])) {
                $where[]        = '(o.order_number LIKE :q1 OR c.phone LIKE :q2 OR CONCAT_WS(" ", c.first_name, c.last_name) LIKE :q3 OR o.table_number LIKE :q4)';
                $params['q1']   = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $f['q'] . '%';
            }
            if (!empty($f['from'])) { $where[] = 'o.created_at >= :from'; $params['from'] = $f['from'] . ' 00:00:00'; }
            if (!empty($f['to']))   { $where[] = 'o.created_at <= :to';   $params['to']   = $f['to']   . ' 23:59:59'; }
        } else {
            if (!empty($f['status']))     { $where[] = 'o.status = :status';         $params['status']     = $f['status']; }
            if (!empty($f['barista_id'])) { $where[] = 'o.barista_id = :barista_id'; $params['barista_id'] = (int) $f['barista_id']; }
            if (!empty($f['q'])) {
                $where[]        = '(o.order_number LIKE :q1 OR c.phone LIKE :q2 OR CONCAT_WS(" ", c.first_name, c.last_name) LIKE :q3 OR o.table_number LIKE :q4)';
                $params['q1']   = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $f['q'] . '%';
            }
            if (!empty($f['from'])) { $where[] = 'o.created_at >= :from'; $params['from'] = $f['from'] . ' 00:00:00'; }
            if (!empty($f['to']))   { $where[] = 'o.created_at <= :to';   $params['to']   = $f['to']   . ' 23:59:59'; }
        }

        if (!empty($f['after_id'])) { $where[] = 'o.id > :after_id'; $params['after_id'] = (int) $f['after_id']; }

        $sortMap = [
            'date_desc'   => 'o.created_at DESC',
            'date_asc'    => 'o.created_at ASC',
            'amount_desc' => 'o.total_price DESC',
            'amount_asc'  => 'o.total_price ASC',
        ];
        $orderBy = $sortMap[$f['sort'] ?? 'date_desc'] ?? $sortMap['date_desc'];

        $sql = 'SELECT o.*,
                       COALESCE(c.phone, \'\') AS phone,
                       IFNULL(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), \'مهمان\') AS customer_name,
                       b.full_name AS barista_name
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN baristas b ON b.id = o.barista_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $orderBy;

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * آمار سفارش‌های امروز/در انتظار/تأییدشده/تکمیل‌شده — برای داشبورد.
     *
     * @return array<string, int>
     */
    public static function dashboardCounts(): array
    {
        $pdo    = Database::getConnection();
        $today  = (int) $pdo->query('SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()')->fetch()['c'];
        $rows   = $pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetchAll();
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }
        return ['today' => $today] + $counts;
    }

    /**
     * تعداد سفارش در هر روز — برای نمودار داشبورد.
     *
     * @param int $days تعداد روزهای گذشته
     * @return array<string, int> آرایه با کلید تاریخ (Y-m-d)
     */
    public static function dailyCounts(int $days = 14): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT DATE(created_at) d, COUNT(*) c FROM orders WHERE created_at >= :from GROUP BY DATE(created_at)'
        );
        $stmt->execute(['from' => date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]);
        $rows = array_column($stmt->fetchAll(), 'c', 'd');
        $out  = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d       = date('Y-m-d', strtotime("-$i days"));
            $out[$d] = (int) ($rows[$d] ?? 0);
        }
        return $out;
    }

    // ====================================================================
    // PRIVATE HELPERS
    // ====================================================================

    /** @throws RuntimeException اگر قفل دریافت نشود */
    private static function acquireOrderNumberLock(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:name, 10)');
        $stmt->execute(['name' => 'order_number_lock']);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('امکان قفل شماره‌گذاری سفارش وجود ندارد.');
        }
    }

    private static function releaseOrderNumberLock(PDO $pdo): void
    {
        $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => 'order_number_lock']);
    }

    private static function rebuildSequentialOrderNumbers(PDO $pdo): void
    {
        $rows = $pdo->query('SELECT id FROM orders ORDER BY created_at ASC, id ASC')->fetchAll();
        foreach ($rows as $row) {
            $tmp = 'temp-' . (int) $row['id'];
            $pdo->prepare('UPDATE orders SET order_number = :n WHERE id = :id')->execute(['n' => $tmp, 'id' => (int) $row['id']]);
            $pdo->prepare('UPDATE print_jobs SET order_number = :n WHERE order_id = :id')->execute(['n' => $tmp, 'id' => (int) $row['id']]);
        }
        $seq = 1;
        foreach ($rows as $row) {
            $pdo->prepare('UPDATE orders SET order_number = :n WHERE id = :id')->execute(['n' => (string) $seq, 'id' => (int) $row['id']]);
            $pdo->prepare('UPDATE print_jobs SET order_number = :n WHERE order_id = :id')->execute(['n' => (string) $seq, 'id' => (int) $row['id']]);
            $seq++;
        }
    }

    /** @return string شمارهٔ ترتیبی بعدی */
    private static function nextSequentialOrderNumber(PDO $pdo): string
    {
        $count = $pdo->query('SELECT COUNT(*) c FROM orders')->fetch()['c'];
        return (string) ((int) $count + 1);
    }
}