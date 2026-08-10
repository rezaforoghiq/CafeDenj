<?php

declare(strict_types=1);

interface SmsProviderInterface
{
    /**
     * @param string $phone
     * @param array<int, string> $textVars
     * @param int $bodyId
     * @return array{success: bool, provider_rec_id: ?string, error_code: ?string, message: ?string}
     */
    public function send(string $phone, array $textVars, int $bodyId): array;
}
