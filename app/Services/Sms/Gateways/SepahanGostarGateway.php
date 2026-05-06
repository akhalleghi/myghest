<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use Illuminate\Support\Facades\Http;

final class SepahanGostarGateway implements SmsPanelGateway
{
    private const API_URL = 'http://login.sepahangostar.com/sendSmsViaURL.aspx';

    public function providerKey(): string
    {
        return 'sepahan-gostar';
    }

    public function displayName(): string
    {
        return 'پنل سپاهان گستر';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function testConnection(string $username, string $password, array $config = []): SmsPanelConnectionResult
    {
        $receiverNumber = trim((string) ($config['receiver_number'] ?? '09100000000'));

        return $this->sendTestMessage(
            $username,
            $password,
            $receiverNumber,
            'پیام تست اتصال سامانه',
            $config
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function sendTestMessage(
        string $username,
        string $password,
        string $recipient,
        string $message,
        array $config = [],
    ): SmsPanelConnectionResult {
        $domainName = trim((string) ($config['domain_name'] ?? 'sepahansms'));
        $senderNumber = trim((string) ($config['sender_number'] ?? '50003300'));
        $recipient = trim($recipient);
        $message = trim($message);

        $params = [
            'userName' => $username,
            'password' => $password,
            'domainName' => $domainName,
            'smsText' => $message,
            'reciverNumber' => $recipient,
            'senderNumber' => $senderNumber,
        ];

        try {
            $response = Http::timeout(20)
                ->accept('text/plain')
                ->get(self::API_URL, $params);
        } catch (\Throwable $e) {
            return new SmsPanelConnectionResult(false, 'خطا در اتصال به سرویس سپاهان‌گستر: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return new SmsPanelConnectionResult(false, 'پاسخ ناموفق HTTP از سپاهان‌گستر: '.$response->status());
        }

        $raw = trim((string) $response->body());
        $code = $this->extractResponseCode($raw);
        if ($code === null) {
            return new SmsPanelConnectionResult(false, 'پاسخ وب‌سرویس قابل تشخیص نبود: '.$raw);
        }

        if ($code === '-1') {
            return new SmsPanelConnectionResult(false, 'نام کاربری یا رمز عبور سپاهان‌گستر نامعتبر است.', $code);
        }

        if (str_starts_with($code, '-')) {
            return new SmsPanelConnectionResult(
                false,
                'ارسال ناموفق بود (کد پاسخ: '.$code.' - '.$this->errorMessageForCode($code).').',
                $code
            );
        }

        return new SmsPanelConnectionResult(true, 'پیام تست با موفقیت ارسال شد. کد رهگیری: '.$code, $code);
    }

    private function extractResponseCode(string $response): ?string
    {
        if (preg_match('/^(-?\d+)/', $response, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function errorMessageForCode(string $code): string
    {
        return match ($code) {
            '-2' => 'اعتبار حساب ناکافی',
            '-3' => 'پارامترها ناقص هستند',
            '-4' => 'شماره فرستنده نامعتبر است',
            '-5' => 'شماره گیرنده نامعتبر است',
            '-6' => 'دامنه نامعتبر است',
            '0' => 'خطای عمومی در ارسال',
            default => 'پاسخ شناخته نشده',
        };
    }
}
