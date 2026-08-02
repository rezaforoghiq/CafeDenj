<?php declare(strict_types=1);

class Coupon {
    public static function all(): array {
        $pdo = Database::getConnection();
        return $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array {
        $stmt = Database::getConnection()->prepare('SELECT * FROM coupons WHERE id = :id');
        $stmt->execute(['id'=>$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static function findByCode(string $code): ?array {
        $stmt = Database::getConnection()->prepare('SELECT * FROM coupons WHERE code = :code LIMIT 1');
        $stmt->execute(['code'=>$code]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO coupons (code, percent, expires_at, is_active) VALUES (:code, :percent, :expires_at, :is_active)');
        $stmt->execute([
            'code' => $data['code'],
            'percent' => (int)$data['percent'],
            'expires_at' => $data['expires_at'] ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE coupons SET code = :code, percent = :percent, expires_at = :expires_at, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'code' => $data['code'],
            'percent' => (int)$data['percent'],
            'expires_at' => $data['expires_at'] ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void {
        $stmt = Database::getConnection()->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute(['id'=>$id]);
    }

    public static function validate(array $data): array {
        $errors = [];
        $code = trim($data['code'] ?? '');
        if ($code === '') $errors['code'] = 'کد کوپن را وارد کنید.';
        if (!isset($data['percent']) || !is_numeric($data['percent']) || (int)$data['percent'] <= 0 || (int)$data['percent'] > 100) $errors['percent'] = 'درصد کوپن باید عددی بین 1 و 100 باشد.';
        if (!empty($data['expires_at'])) {
            $d = DateTime::createFromFormat('Y-m-d', $data['expires_at']);
            if (!$d) $errors['expires_at'] = 'تاریخ انقضا نامعتبر است.';
        }
        return $errors;
    }

    public static function isValidCoupon(array $coupon): bool {
        if (!$coupon) return false;
        if ((int)$coupon['is_active'] !== 1) return false;
        if (!empty($coupon['expires_at']) && $coupon['expires_at'] < date('Y-m-d')) return false;
        return true;
    }

    public static function applyPercentToAmount(float $amount, int $percent): int {
        $discount = (int) round($amount * $percent / 100);
        $final = max(0, (int) round($amount - $discount));
        return $final;
    }
}
