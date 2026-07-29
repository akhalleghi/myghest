<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelCreditResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SepahanGostarGateway implements SmsPanelGateway
{
    private const API_URL = 'http://login.sepahangostar.com/sendSmsViaURL.aspx';

    private const REST_API_BASE = 'https://api.sepahansms.com';

    public function providerKey(): string
    {
        return 'sepahan-gostar';
    }

    public function displayName(): string
    {
        return 'پنل سپاهان گستر';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function testConnection(string $username, string $password, array $config = []): SmsPanelConnectionResult
    {
        $receiverNumber = trim((string) ($config['receiver_number'] ?? '09100000000'));

        return $this->sendTestMessage(
            $username,
            $password,
            $receiverNumber,
            'پیام تست اتصال سامانه',
            [
                'domain_name' => (string) ($config['domain_name'] ?? 'sepahansms'),
                // اتصال پنل باید مستقل از شماره فرستنده تست شود.
                // شماره فرستنده ممکن است در پنل غیرفعال/جابجا شده باشد و نتیجه تست اعتبار را خراب کند.
                'sender_number' => '50003300',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $config
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

    /**
     * ایجاد توکن WebAPI طبق مستند GenerateApiKey (صرفاً یک‌بار و سپس ذخیره).
     *
     * @return array{ok:bool,token:?string,message:string}
     */
    public function generateApiToken(
        string $username,
        string $password,
        string $domainName = 'sepahansms',
        string $title = 'MyGhest Credit',
    ): array {
        $username = trim($username);
        $password = trim($password);
        $domainName = trim($domainName) !== '' ? trim($domainName) : 'sepahansms';
        $title = trim($title) !== '' ? trim($title) : 'MyGhest Credit';

        if ($username === '' || $password === '') {
            return ['ok' => false, 'token' => null, 'message' => 'نام کاربری یا رمز عبور پنل برای دریافت توکن ناقص است.'];
        }

        try {
            $response = $this->restHttp()
                ->acceptJson()
                ->asJson()
                ->post(self::REST_API_BASE.'/api/Auth/GenerateApiKey', [
                    'title' => $title,
                    'userName' => $username,
                    'password' => $password,
                    'domainName' => $domainName,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'token' => null, 'message' => 'خطا در دریافت توکن WebAPI سپاهان‌گستر: '.$e->getMessage()];
        }

        $payload = $this->decodeJsonPayload($response);
        if ($payload === null) {
            return [
                'ok' => false,
                'token' => null,
                'message' => 'پاسخ نامعتبر هنگام دریافت توکن WebAPI (HTTP '.$response->status().').',
            ];
        }

        $isSuccess = (bool) ($payload['isSuccess'] ?? false);
        $token = null;
        $value = $payload['value'] ?? null;
        if (is_array($value) && isset($value['token']) && is_scalar($value['token'])) {
            $token = trim((string) $value['token']);
        } elseif (is_string($value) && trim($value) !== '') {
            $token = trim($value);
        }

        if ($isSuccess && $token !== null && $token !== '') {
            return ['ok' => true, 'token' => $token, 'message' => 'توکن WebAPI با موفقیت دریافت شد.'];
        }

        $error = $this->extractRestErrorMessage($payload);

        return [
            'ok' => false,
            'token' => null,
            'message' => $error !== '' ? $error : 'دریافت توکن WebAPI ناموفق بود.',
        ];
    }

    /**
     * دریافت اعتبار باقیمانده طبق متد credit مستند WebAPI.
     */
    public function fetchRemainingCredit(string $apiToken): SmsPanelCreditResult
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            return new SmsPanelCreditResult(false, null, 'توکن WebAPI پنل پیامک موجود نیست.');
        }

        try {
            $response = $this->restHttp()
                ->withHeaders(['Authorization' => $apiToken])
                ->acceptJson()
                ->get(self::REST_API_BASE.'/api/SmsReport/credit');
        } catch (\Throwable $e) {
            return new SmsPanelCreditResult(false, null, 'خطا در استعلام اعتبار سپاهان‌گستر: '.$e->getMessage());
        }

        $payload = $this->decodeJsonPayload($response);
        if ($payload === null) {
            return new SmsPanelCreditResult(
                false,
                null,
                'پاسخ نامعتبر هنگام استعلام اعتبار (HTTP '.$response->status().').'
            );
        }

        $isSuccess = (bool) ($payload['isSuccess'] ?? false);
        $rawValue = $payload['value'] ?? null;
        $credit = null;
        if (is_int($rawValue)) {
            $credit = $rawValue;
        } elseif (is_float($rawValue) && floor($rawValue) === $rawValue) {
            $credit = (int) $rawValue;
        } elseif (is_string($rawValue) && preg_match('/^-?\d+$/', trim($rawValue)) === 1) {
            $credit = (int) trim($rawValue);
        }

        if ($isSuccess && $credit !== null) {
            return new SmsPanelCreditResult(true, $credit, 'اعتبار باقیمانده با موفقیت دریافت شد.');
        }

        $error = $this->extractRestErrorMessage($payload);
        if ($error === '' && is_int($rawValue) && $rawValue < 0) {
            $error = $this->restAuthErrorMessage((string) $rawValue);
        }

        return new SmsPanelCreditResult(
            false,
            null,
            $error !== '' ? $error : 'دریافت اعتبار باقیمانده ناموفق بود.'
        );
    }

    /**
     * کلاینت REST سپاهان‌گستر.
     * گواهی SSL فعلی api.sepahansms.com منقضی/ناقص است؛ بدون این تنظیم استعلام اعتبار ممکن نیست.
     * فقط برای همین دامنه اعمال می‌شود.
     */
    private function restHttp(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(20)->withoutVerifying();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(Response $response): ?array
    {
        $json = $response->json();
        if (is_array($json)) {
            return $json;
        }

        $raw = trim((string) $response->body());
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractRestErrorMessage(array $payload): string
    {
        $error = $payload['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }
        if (is_array($error)) {
            $message = $error['message'] ?? $error['Message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
            $code = $error['code'] ?? $error['Code'] ?? null;
            if (is_scalar($code)) {
                return $this->restAuthErrorMessage((string) $code);
            }
        }
        if (is_scalar($error)) {
            return $this->restAuthErrorMessage((string) $error);
        }

        return '';
    }

    private function restAuthErrorMessage(string $code): string
    {
        return match (trim($code)) {
            '-1' => 'توکن WebAPI نامعتبر است.',
            '-2' => 'توکن WebAPI تایید نشده است.',
            '-3' => 'آی‌پی سرور برای توکن WebAPI مجاز نیست.',
            default => 'خطای WebAPI سپاهان‌گستر (کد: '.$code.').',
        };
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
