<?php

declare(strict_types=1);

class PendingRegistration
{
    public static function create(PDO $pdo, string $phone, string $firstName, string $lastName, string $passwordHash = ''): int
    {
        if ($passwordHash === '') {
            $passwordHash = bin2hex(random_bytes(32));
        }
        $expiresAt = date('Y-m-d H:i:s', time() + max(300, (int) env('OTP_EXPIRATION_SECONDS', 300)) + 600);
        $stmt = $pdo->prepare(
            'INSERT INTO pending_registrations (phone, first_name, last_name, password_hash, expires_at) VALUES (:phone, :first_name, :last_name, :password_hash, :expires_at)'
        );
        $stmt->execute([
            'phone' => $phone,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'password_hash' => $passwordHash,
            'expires_at' => $expiresAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM pending_registrations WHERE id = :id AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM pending_registrations WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function deleteByPhone(PDO $pdo, string $phone): void
    {
        $stmt = $pdo->prepare('DELETE FROM pending_registrations WHERE phone = :phone');
        $stmt->execute(['phone' => $phone]);
    }
}