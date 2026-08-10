<?php

declare(strict_types=1);

require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/CustomerAuth.php';
require_once __DIR__ . '/MelipayamakProvider.php';

class OtpService
{
    private SmsProviderInterface $provider;

    public function __construct(?SmsProviderInterface $provider = null)
    {
        $this->provider = $provider ?? new MelipayamakProvider();
    }

    public function issueOtp(string $phone, string $purpose, ?string $ipAddress = null): array
    {
        $normalizedPhone = CustomerAuth::normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new InvalidArgumentException('شماره موبایل نامعتبر است.');
        }

        $purpose = $this->normalizePurpose($purpose);
        $pdo = Database::getConnection();

        if (!$this->canSend($pdo, $normalizedPhone, $ipAddress)) {
            throw new RuntimeException('درخواست کد تأیید بیش از حد مجاز است.');
        }

        $active = $this->getActiveRecord($pdo, $normalizedPhone, $purpose);
        if ($active) {
            $cooldownSeconds = max(1, (int) env('OTP_RESEND_COOLDOWN', 60));
            $availableAt = strtotime((string) $active['resend_available_at']);
            if ($availableAt !== false && $availableAt > time()) {
                throw new RuntimeException('کد قبلی هنوز فعال است.');
            }
        }

        $this->invalidateExisting($pdo, $normalizedPhone, $purpose);

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, (int) env('OTP_EXPIRATION_SECONDS', 300)));
        $resendAvailableAt = date('Y-m-d H:i:s', time() + max(1, (int) env('OTP_RESEND_COOLDOWN', 60)));
        $bodyId = (int) env('MELIPAYAMAK_BODY_ID', 0);

        $stmt = $pdo->prepare(
            'INSERT INTO otp_codes (phone, purpose, otp_hash, max_attempts, expires_at, resend_available_at, ip_address) VALUES (:phone, :purpose, :otp_hash, :max_attempts, :expires_at, :resend_available_at, :ip_address)'
        );
        $stmt->execute([
            'phone' => $normalizedPhone,
            'purpose' => $purpose,
            'otp_hash' => $otpHash,
            'max_attempts' => max(1, (int) env('OTP_MAX_ATTEMPTS', 5)),
            'expires_at' => $expiresAt,
            'resend_available_at' => $resendAvailableAt,
            'ip_address' => $ipAddress,
        ]);
        $otpId = (int) $pdo->lastInsertId();

        $result = $this->provider->send($normalizedPhone, [$otp], $bodyId);
        if (!$result['success']) {
            $this->invalidateExisting($pdo, $normalizedPhone, $purpose);
            $this->recordFailure($pdo, $otpId, $result['message'] ?? 'ارسال پیامک ناموفق بود.');
            throw new RuntimeException($result['message'] ?? 'ارسال کد تأیید انجام نشد.');
        }

        ActivityLog::record('otp_send', 'customer', null, $normalizedPhone, null, 'ارسال کد تأیید برای ' . $purpose);

        return [
            'success' => true,
            'id' => $otpId,
            'expires_at' => $expiresAt,
            'resend_available_at' => $resendAvailableAt,
            'phone' => $normalizedPhone,
            'purpose' => $purpose,
        ];
    }

    public function verifyOtp(string $phone, string $purpose, string $otp): array
    {
        $normalizedPhone = CustomerAuth::normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new InvalidArgumentException('شماره موبایل نامعتبر است.');
        }

        $purpose = $this->normalizePurpose($purpose);
        $pdo = Database::getConnection();
        $record = $this->getActiveRecord($pdo, $normalizedPhone, $purpose);
        if (!$record) {
            return ['success' => false, 'message' => 'کد تأیید منقضی یا نامعتبر است.'];
        }

        $recordAttempts = (int) $record['attempts'];
        $maxAttempts = max(1, (int) $record['max_attempts']);
        if ($recordAttempts >= $maxAttempts) {
            $this->invalidateExisting($pdo, $normalizedPhone, $purpose);
            return ['success' => false, 'message' => 'تعداد تلاش‌های کد تأیید بیش از حد شده است.'];
        }

        ++$recordAttempts;
        $stmt = $pdo->prepare('UPDATE otp_codes SET attempts = :attempts WHERE id = :id');
        $stmt->execute(['attempts' => $recordAttempts, 'id' => $record['id']]);

        if (!password_verify($otp, $record['otp_hash'])) {
            return ['success' => false, 'message' => 'کد تأیید نادرست است.'];
        }

        $stmt = $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $record['id']]);
        ActivityLog::record('otp_verified', 'customer', null, $normalizedPhone, null, 'تأیید کد برای ' . $purpose);

        return ['success' => true, 'message' => 'کد تأیید با موفقیت تأیید شد.'];
    }

    public function canResend(string $phone, string $purpose): bool
    {
        $normalizedPhone = CustomerAuth::normalizePhone($phone);
        if ($normalizedPhone === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $record = $this->getActiveRecord($pdo, $normalizedPhone, $this->normalizePurpose($purpose));
        if (!$record) {
            return true;
        }

        $availableAt = strtotime((string) $record['resend_available_at']);
        return $availableAt === false || $availableAt <= time();
    }

    private function canSend(PDO $pdo, string $phone, ?string $ipAddress): bool
    {
        $windowSeconds = max(60, (int) env('OTP_RATE_LIMIT_WINDOW_SECONDS', 3600));
        $maxPerWindow = max(1, (int) env('OTP_RATE_LIMIT_MAX_PER_WINDOW', 5));
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) c FROM otp_codes WHERE phone = :phone AND created_at >= :cutoff'
        );
        $stmt->execute(['phone' => $phone, 'cutoff' => $cutoff]);
        $phoneCount = (int) $stmt->fetchColumn();

        if ($phoneCount >= $maxPerWindow) {
            return false;
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $ipStmt = $pdo->prepare(
                'SELECT COUNT(*) c FROM otp_codes WHERE ip_address = :ip AND created_at >= :cutoff'
            );
            $ipStmt->execute(['ip' => $ipAddress, 'cutoff' => $cutoff]);
            $ipCount = (int) $ipStmt->fetchColumn();
            if ($ipCount >= $maxPerWindow) {
                return false;
            }
        }

        return true;
    }

    private function invalidateExisting(PDO $pdo, string $phone, string $purpose): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM otp_codes WHERE phone = :phone AND purpose = :purpose AND used_at IS NULL'
        );
        $stmt->execute(['phone' => $phone, 'purpose' => $purpose]);
    }

    private function getActiveRecord(PDO $pdo, string $phone, string $purpose): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM otp_codes WHERE phone = :phone AND purpose = :purpose AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['phone' => $phone, 'purpose' => $purpose]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function recordFailure(PDO $pdo, int $otpId, string $message): void
    {
        $stmt = $pdo->prepare('UPDATE otp_codes SET last_error = :message WHERE id = :id');
        $stmt->execute(['message' => mb_substr($message, 0, 255), 'id' => $otpId]);
    }

    private function normalizePurpose(string $purpose): string
    {
        $purpose = trim(strtolower($purpose));

        return match ($purpose) {
            'registration', 'login', 'password_reset' => $purpose,
            default => throw new InvalidArgumentException('هدف OTP نامعتبر است.'),
        };
    }
}
