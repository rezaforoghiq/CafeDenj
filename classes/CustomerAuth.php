<?php
declare(strict_types=1);

class CustomerAuth
{
    public static function register(string $phone, string $firstName, string $lastName, string $password): array
    {
        $phone = self::normalizePhone($phone);
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $errors = self::validateRegistration($phone, $firstName, $lastName, $password);
        if ($errors !== []) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $customer = self::findCustomerByPhone($pdo, $phone);
            if ($customer) {
                $accountCheck = $pdo->prepare('SELECT id FROM customer_accounts WHERE customer_id = :customer_id LIMIT 1');
                $accountCheck->execute(['customer_id' => $customer['id']]);
                if ($accountCheck->fetch()) {
                    throw new RuntimeException('برای این شماره موبایل حساب کاربری وجود دارد. وارد شوید.');
                }
                $customerId = (int) $customer['id'];
                $update = $pdo->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name WHERE id = :id');
                $update->execute(['first_name' => $firstName ?: null, 'last_name' => $lastName ?: null, 'id' => $customerId]);
            } else {
                $newCustomer = $pdo->prepare('INSERT INTO customers (first_name, last_name, phone, source) VALUES (:first_name, :last_name, :phone, :source)');
                $newCustomer->execute(['first_name' => $firstName ?: null, 'last_name' => $lastName ?: null, 'phone' => $phone, 'source' => 'account']);
                $customerId = (int) $pdo->lastInsertId();
            }

            $insert = $pdo->prepare('INSERT INTO customer_accounts (customer_id, password_hash) VALUES (:customer_id, :password_hash)');
            $insert->execute([
                'customer_id' => $customerId,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $accountId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return ['customer_id' => $customerId, 'account_id' => $accountId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function attempt(string $identity, string $password): ?array
    {
        $identity = trim($identity);
        $phone = self::normalizePhone($identity);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT ca.id AS account_id, ca.customer_id, ca.password_hash, c.phone
             FROM customer_accounts ca INNER JOIN customers c ON c.id = ca.customer_id
             WHERE c.phone IS NOT NULL'
        );
        $stmt->execute();
        $account = null;
        while ($row = $stmt->fetch()) {
            if (self::normalizePhone((string) ($row['phone'] ?? '')) === $phone) {
                $account = $row;
                break;
            }
        }
        if (!$account || !password_verify($password, $account['password_hash'])) {
            return null;
        }
        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $pdo->prepare('UPDATE customer_accounts SET password_hash = :hash WHERE id = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $account['account_id']]);
        }
        $pdo->prepare('UPDATE customer_accounts SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $account['account_id']]);
        return $account;
    }

    public static function validateRegistration(string $phone, string $firstName, string $lastName, string $password): array
    {
        $errors = [];
        if (!Customer::isValidPhone($phone)) {
            $errors['phone'] = 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.';
        }
        if ($firstName === '' || mb_strlen($firstName) > 100) {
            $errors['first_name'] = 'نام را وارد کنید (حداکثر ۱۰۰ کاراکتر).';
        }
        if ($lastName === '' || mb_strlen($lastName) > 100) {
            $errors['last_name'] = 'نام خانوادگی را وارد کنید (حداکثر ۱۰۰ کاراکتر).';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
        }
        return $errors;
    }
    public static function normalizePhone(string $phone): string
    {
        $normalized = strtr(trim($phone), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $digits = preg_replace('/\D/u', '', $normalized) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '989') && mb_strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '98') && mb_strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && mb_strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    private static function findCustomerByPhone(PDO $pdo, string $phone): ?array
    {
        $normalizedPhone = self::normalizePhone($phone);
        $stmt = $pdo->prepare('SELECT id, phone FROM customers WHERE phone IS NOT NULL');
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            if (self::normalizePhone((string) ($row['phone'] ?? '')) === $normalizedPhone) {
                return $row;
            }
        }

        return null;
    }
}
