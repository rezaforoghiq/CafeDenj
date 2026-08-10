<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../classes/ActivityLog.php';

function customerLogin(array $account): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $account['customer_id'];
    $_SESSION['customer_account_id'] = (int) $account['account_id'];
    // Store customer display name (first + last) for session use
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM customers WHERE id = :id');
    $stmt->execute(['id' => $account['customer_id']]);
    $row = $stmt->fetch();
    $display = '';
    if ($row) {
        $display = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
    $_SESSION['customer_display_name'] = $display;
    ActivityLog::record('login', 'customer', (int) $account['customer_id'], $display ?: null);
}

function customerLogout(): void
{
    if (customerIsLoggedIn()) {
        ActivityLog::record('logout', 'customer', (int) $_SESSION['customer_id'], (string) ($_SESSION['customer_display_name'] ?? ''));
    }
    unset($_SESSION['customer_id'], $_SESSION['customer_account_id'], $_SESSION['customer_display_name']);
    session_regenerate_id(true);
}

function passwordResetAuthorize(int $customerId, string $phone): void
{
    session_regenerate_id(true);
    $_SESSION['password_reset_auth'] = [
        'customer_id' => $customerId,
        'phone' => $phone,
        'expires_at' => time() + 600,
    ];
}

function passwordResetGetContext(): ?array
{
    $context = $_SESSION['password_reset_auth'] ?? null;
    if (!is_array($context)
        || empty($context['customer_id'])
        || empty($context['phone'])
        || empty($context['expires_at'])
        || time() > (int) $context['expires_at']) {
        return null;
    }

    return $context;
}

function passwordResetClear(): void
{
    unset($_SESSION['password_reset_auth']);
}

function customerIsLoggedIn(): bool
{
    return isset($_SESSION['customer_id'], $_SESSION['customer_account_id']);
}

function requireCustomerLogin(): void
{
    if (!customerIsLoggedIn()) {
        header('Location: login');
        exit;
    }
}
