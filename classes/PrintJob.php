<?php
declare(strict_types=1);

require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Jalali.php';
require_once __DIR__ . '/Order.php';
require_once __DIR__ . '/Setting.php';

class PrintJob
{
    private const DEFAULT_MAX_RETRIES = 3;
    public const TYPE_PREPARATION = 'preparation';
    public const TYPE_CUSTOMER_INVOICE = 'customer_invoice';

    public static function existsForOrder(int $orderId, string $jobType = self::TYPE_PREPARATION): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM print_jobs WHERE order_id = :order_id AND job_type = :job_type LIMIT 1');
        $stmt->execute(['order_id' => $orderId, 'job_type' => $jobType]);
        return (bool) $stmt->fetchColumn();
    }

    public static function getPrintingMethod(): string
    {
        $method = Setting::get('printing_method', 'automatic');
        return in_array($method, ['automatic', 'manual'], true) ? $method : 'automatic';
    }

    public static function isManual(): bool
    {
        return self::getPrintingMethod() === 'manual';
    }

    public static function isAutomatic(): bool
    {
        return self::getPrintingMethod() === 'automatic';
    }

    public static function createForOrder(int $orderId): ?int
    {
        // In manual mode, do not call Windows Print API or create queue records
        if (self::isManual()) {
            return null;
        }

        // avoid duplicate preparation jobs for the same order
        if (self::existsForOrder($orderId, self::TYPE_PREPARATION)) {
            return null;
        }

        $order = Order::findById($orderId);
        if (!$order) {
            return null;
        }

        $payload = self::buildPayload($order);
        return self::insertJob($orderId, self::TYPE_PREPARATION, $payload);
    }

    public static function createCustomerInvoiceForOrder(int $orderId): ?int
    {
        // In manual mode, do not call Windows Print API or create queue records
        if (self::isManual()) {
            return null;
        }

        $order = Order::findById($orderId);
        if (!$order) {
            return null;
        }

        $payload = self::buildInvoicePayload($order);
        return self::insertJob($orderId, self::TYPE_CUSTOMER_INVOICE, $payload);
    }

    private static function insertJob(int $orderId, string $jobType, array $payload): ?int
    {
        $payload['job_type'] = $jobType;
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $jobReference = $jobType === self::TYPE_PREPARATION ? '' : bin2hex(random_bytes(8));

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO print_jobs (order_id, order_number, job_type, job_reference, payload, status, created_at, updated_at) VALUES (:order_id, :order_number, :job_type, :job_reference, :payload, :status, NOW(), NOW())');
        try {
            $stmt->execute([
                'order_id' => $orderId,
                'order_number' => $payload['order_number'] ?? '',
                'job_type' => $jobType,
                'job_reference' => $jobReference,
                'payload' => $payloadJson,
                'status' => 'pending'
            ]);
            $jobId = (int) $pdo->lastInsertId();
            $action = $jobType === self::TYPE_CUSTOMER_INVOICE ? 'order_print_invoice' : 'order_approve';
            ActivityLog::record($action, 'system', null, 'system', $orderId, 'Print job created #' . $jobId . ' (' . $jobType . ')');
            return $jobId;
        } catch (Throwable $e) {
            $action = $jobType === self::TYPE_CUSTOMER_INVOICE ? 'order_print_invoice' : 'order_approve';
            ActivityLog::record($action, 'system', null, 'system', $orderId, 'Failed creating print job: ' . mb_substr($e->getMessage(), 0, 200));
            return null;
        }
    }

    public static function fetchNextPending(): ?array
    {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            // select oldest pending
            $select = $pdo->prepare("SELECT id FROM print_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
            $select->execute();
            $row = $select->fetch();
            if (!$row) {
                $pdo->commit();
                return null;
            }
            $id = (int) $row['id'];
            // claim it
            $update = $pdo->prepare('UPDATE print_jobs SET status = :processing, updated_at = NOW() WHERE id = :id AND status = :pending');
            $update->execute(['processing' => 'processing', 'id' => $id, 'pending' => 'pending']);
            if ($update->rowCount() !== 1) {
                // someone else claimed it
                $pdo->commit();
                return null;
            }
            $pdo->commit();

            // return the claimed job
            $stmt = $pdo->prepare('SELECT * FROM print_jobs WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $job = $stmt->fetch();
            if (!$job) return null;

            // decode payload
            $job['payload'] = $job['payload'] ? json_decode($job['payload'], true) : null;
            ActivityLog::record('order_approve', 'system', null, 'system', (int)$job['order_id'], 'Print job claimed #' . $job['id']);
            return $job;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ActivityLog::record('order_approve', 'system', null, 'system', null, 'Error claiming print job: ' . mb_substr($e->getMessage(), 0, 200));
            return null;
        }
    }

    public static function markCompleted(int $jobId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE print_jobs SET status = :status, printed_at = NOW(), updated_at = NOW() WHERE id = :id');
        $ok = $stmt->execute(['status' => 'completed', 'id' => $jobId]);
        if ($ok) {
            // record activity
            $row = $pdo->prepare('SELECT order_id FROM print_jobs WHERE id = :id');
            $row->execute(['id' => $jobId]);
            $orderId = $row->fetchColumn();
            ActivityLog::record('order_approve', 'system', null, 'system', $orderId ? (int)$orderId : null, 'Print job completed #' . $jobId);
        }
        return (bool) $ok;
    }

    public static function markFailed(int $jobId, string $error): bool
    {
        $pdo = Database::getConnection();
        $maxRetries = (int) (Setting::get('print_bridge_max_retries') ?? self::DEFAULT_MAX_RETRIES);
        // increment retry_count and set last_error; if retry_count >= max then set status=failed else set status=pending for retry
        $pdo->beginTransaction();
        try {
            $select = $pdo->prepare('SELECT retry_count, order_id FROM print_jobs WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $jobId]);
            $r = $select->fetch();
            if (!$r) { $pdo->commit(); return false; }
            $retry = (int) $r['retry_count'] + 1;
            $newStatus = $retry > $maxRetries ? 'failed' : 'pending';
            $update = $pdo->prepare('UPDATE print_jobs SET retry_count = :retry, last_error = :err, status = :status, updated_at = NOW() WHERE id = :id');
            $update->execute(['retry' => $retry, 'err' => mb_substr($error, 0, 200), 'status' => $newStatus, 'id' => $jobId]);
            $pdo->commit();

            ActivityLog::record('order_approve', 'system', null, 'system', (int)$r['order_id'], 'Print job failed #' . $jobId . ' (' . ($newStatus === 'failed' ? 'final' : 'will retry') . '): ' . mb_substr($error, 0, 200));
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ActivityLog::record('order_approve', 'system', null, 'system', null, 'Error marking print job failed: ' . mb_substr($e->getMessage(), 0, 200));
            return false;
        }
    }

    public static function buildPayload(array $order): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $order['id']]);
        $items = $stmt->fetchAll();

        $customerName = trim(($order['customer_name'] ?? '') ?: 'مشتری');
        $customerPhone = $order['phone'] ?? '-';
        $orderNumber = $order['order_number'] ?? '';
        $dateJalali = Jalali::formatDate($order['created_at']);
        $time = $order['created_at'] ? date('H:i', strtotime($order['created_at'])) : '';

        $itemsData = [];
        $total = 0;
        foreach ($items as $it) {
            $origPrice = isset($it['original_price']) && (float)$it['original_price'] > 0
                ? (float)$it['original_price']
                : (float)$it['price'];
            $discPercent = isset($it['discount_percent']) ? (int)$it['discount_percent'] : 0;
            $discAmount = isset($it['discount_amount']) && (float)$it['discount_amount'] > 0
                ? (float)$it['discount_amount']
                : max(0.0, $origPrice - (float)$it['price']);

            $itemsData[] = [
                'product_id' => (int) $it['product_id'],
                'product_name' => $it['product_name'],
                'quantity' => (int) $it['quantity'],
                'original_price' => $origPrice,
                'discount_percent' => $discPercent,
                'discount_amount' => $discAmount,
                'price' => (float) $it['price']
            ];
            $total += (float)$it['price'] * (int)$it['quantity'];
        }

        // Build print-friendly Persian text (no prices) for thermal printer
        $lines = [];
        $lines[] = "------------------------------";
        $lines[] = "کافه دنج";
        $lines[] = "------------------------------";
        $lines[] = "سفارش:";
        $lines[] = $orderNumber;
        $lines[] = "تاریخ:";
        $lines[] = Jalali::formatDate($order['created_at']);
        $lines[] = "زمان:";
        $lines[] = $time ?: '—';
        $lines[] = "مشتری:";
        $lines[] = $customerName ?: '-';
        $lines[] = "وضعیت:";
        $lines[] = 'سفارش جدید';
        $lines[] = "------------------------------";
        foreach ($itemsData as $it) {
            $lines[] = $it['product_name'];
            $lines[] = 'تعداد: ' . $it['quantity'];
            $lines[] = "------------------------------";
        }
        $customerNote = trim((string)($order['customer_note'] ?? ($order['notes'] ?? ($order['note'] ?? ''))));

        $lines[] = "یادداشت:";
        $lines[] = $customerNote !== '' ? $customerNote : '-';
        $lines[] = "------------------------------";
        $lines[] = "برای آماده‌سازی سفارش";
        $lines[] = "------------------------------";

        $printText = implode("\n", $lines);

        return [
            'order_id' => (int) $order['id'],
            'order_number' => $orderNumber,
            'date_jalali' => $dateJalali,
            'time' => $time,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'items' => $itemsData,
            'total_amount' => (float) ($order['total_price'] ?? $total),
            'notes' => $customerNote !== '' ? $customerNote : null,
            'customer_note' => $customerNote !== '' ? $customerNote : null,
            'job_type' => self::TYPE_PREPARATION,
            'print_text' => $printText
        ];
    }

    public static function buildInvoicePayload(array $order): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $order['id']]);
        $items = $stmt->fetchAll();

        $customerName = trim(($order['customer_name'] ?? '') ?: 'مشتری');
        $customerPhone = $order['phone'] ?? '-';
        $orderNumber = $order['order_number'] ?? '';
        $dateJalali = Jalali::formatDate($order['created_at']);
        $time = $order['created_at'] ? date('H:i', strtotime($order['created_at'])) : '';

        $itemsData = [];
        $subtotal = 0;
        foreach ($items as $it) {
            $lineTotal = (float)$it['price'] * (int)$it['quantity'];
            $origPrice = isset($it['original_price']) && (float)$it['original_price'] > 0
                ? (float)$it['original_price']
                : (float)$it['price'];
            $discPercent = isset($it['discount_percent']) ? (int)$it['discount_percent'] : 0;
            $discAmount = isset($it['discount_amount']) && (float)$it['discount_amount'] > 0
                ? (float)$it['discount_amount']
                : max(0.0, $origPrice - (float)$it['price']);

            $itemsData[] = [
                'product_id' => (int) $it['product_id'],
                'product_name' => $it['product_name'],
                'quantity' => (int) $it['quantity'],
                'original_price' => $origPrice,
                'discount_percent' => $discPercent,
                'discount_amount' => $discAmount,
                'price' => (float) $it['price'],
                'line_total' => $lineTotal
            ];
            $subtotal += $lineTotal;
        }

        $discount = (float) ($order['discount_amount'] ?? 0);
        $total = (float) ($order['total_price'] ?? $subtotal);
        $paymentMethodMap = ['card' => 'کارتخوان', 'cash' => 'نقدی', 'transfer' => 'کارت به کارت'];
        $paymentMethod = $paymentMethodMap[$order['payment_method'] ?? ''] ?? '-';

        $lines = [];
        $lines[] = "------------------------------";
        $lines[] = "کافه دنج";
        $lines[] = "------------------------------";
        $lines[] = "فاکتور مشتری";
        $lines[] = "------------------------------";
        $lines[] = "سفارش:";
        $lines[] = $orderNumber;
        $lines[] = "تاریخ:";
        $lines[] = $dateJalali;
        $lines[] = "زمان:";
        $lines[] = $time ?: '—';
        $lines[] = "مشتری:";
        $lines[] = $customerName ?: '-';
        $lines[] = "تلفن:";
        $lines[] = $customerPhone;
        $lines[] = "------------------------------";
        foreach ($itemsData as $it) {
            $lines[] = $it['product_name'];
            $lines[] = 'تعداد: ' . $it['quantity'] . ' × ' . number_format($it['price']) . ' تومان';
            $lines[] = 'جمع: ' . number_format($it['line_total']) . ' تومان';
            $lines[] = "------------------------------";
        }
        $lines[] = 'جمع کل: ' . number_format($subtotal) . ' تومان';
        if ($discount > 0) {
            $lines[] = 'تخفیف: ' . number_format($discount) . ' تومان';
        }
        $lines[] = 'مبلغ نهایی: ' . number_format($total) . ' تومان';
        $lines[] = 'روش پرداخت: ' . $paymentMethod;
        if (!empty($order['customer_note'])) {
            $lines[] = "------------------------------";
            $lines[] = "یادداشت مشتری:";
            $lines[] = $order['customer_note'];
        }
        $lines[] = "------------------------------";
        $lines[] = "با تشکر از خرید شما";
        $lines[] = "------------------------------";

        $printText = implode("\n", $lines);

        return [
            'order_id' => (int) $order['id'],
            'order_number' => $orderNumber,
            'date_jalali' => $dateJalali,
            'time' => $time,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'items' => $itemsData,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
            'notes' => $order['customer_note'] ?? null,
            'job_type' => self::TYPE_CUSTOMER_INVOICE,
            'print_text' => $printText
        ];
    }
}