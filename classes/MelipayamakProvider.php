<?php

declare(strict_types=1);

require_once __DIR__ . '/SmsProviderInterface.php';

class MelipayamakProvider implements SmsProviderInterface
{
    public function send(string $phone, array $textVars, int $bodyId): array
    {
        $username = (string) env('MELIPAYAMAK_USERNAME', '');
        $password = (string) env('MELIPAYAMAK_PASSWORD', '');

        if ($username === '' || $password === '' || $bodyId <= 0) {
            error_log('Melipayamak provider is not configured.');
            return [
                'success' => false,
                'provider_rec_id' => null,
                'error_code' => null,
                'message' => 'ارسال پیامک پیکربندی نشده است.',
            ];
        }

        if (!class_exists('SoapClient')) {
            error_log('SoapClient extension is not available.');
            return [
                'success' => false,
                'provider_rec_id' => null,
                'error_code' => null,
                'message' => 'ارسال پیامک در این محیط ممکن نیست.',
            ];
        }

        try {
            $soap = new SoapClient('http://api.payamak-panel.com/post/Send.asmx?wsdl', ['encoding' => 'UTF-8']);
            $payload = [
                'username' => $username,
                'password' => $password,
                'text' => $textVars,
                'to' => $phone,
                'bodyId' => $bodyId,
            ];
            $result = $soap->SendByBaseNumber($payload)->SendByBaseNumberResult;
            $resultText = (string) $result;

            if (is_numeric($resultText) && strlen($resultText) > 15) {
                return [
                    'success' => true,
                    'provider_rec_id' => $resultText,
                    'error_code' => null,
                    'message' => null,
                ];
            }

            return [
                'success' => false,
                'provider_rec_id' => null,
                'error_code' => $resultText,
                'message' => 'ارسال پیامک با خطا مواجه شد.',
            ];
        } catch (Throwable $e) {
            error_log('Melipayamak provider error: ' . $e->getMessage());
            return [
                'success' => false,
                'provider_rec_id' => null,
                'error_code' => null,
                'message' => 'ارسال پیامک با خطا مواجه شد.',
            ];
        }
    }
}
