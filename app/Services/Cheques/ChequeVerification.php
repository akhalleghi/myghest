<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\SmsTemplateRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChequeVerification
{
    public function preview(string $purpose, array $context, string $mobile, string $name, array $vars = []): array
    {
        $key = ['customer' => 'cheque_identity_otp', 'owner' => 'cheque_owner_otp', 'return' => 'cheque_return_otp'][$purpose];
        $template = SmsTemplate::where('template_key', $key)->firstOrFail();
        $token = (string) Str::uuid();
        $body = app(SmsTemplateRenderer::class)->render($template->body, ['customer_name' => $name, 'code' => '{{code}}', ...$vars]);
        $summary = $purpose === 'owner' ? 'تأیید خرید چکی برای '.($vars['cheque_buyer_name'] ?? '').' به مبلغ '.($vars['cheque_amount'] ?? '') : '';
        if ($summary !== '') {
            $body = $summary."\n".$body;
        }
        Cache::put('cheque_preview:'.$token, ['purpose' => $purpose, 'context' => $context, 'mobile' => $mobile, 'summary' => $summary, 'admin_id' => auth('admin')->id()], now()->addMinutes(10));

        return ['body' => $body, 'preview_token' => $token, 'mobile' => $mobile];
    }

    public function send(string $token, string $body): array
    {
        $result = Cache::lock('cheque_preview_send:'.$token, 120)->get(fn () => $this->sendOnce($token, $body));
        if (! is_array($result)) {
            throw ValidationException::withMessages(['body' => 'ارسال این پیامک در حال انجام است؛ منتظر نتیجه بمانید.']);
        }

        return $result;
    }

    private function sendOnce(string $token, string $body): array
    {
        $payload = Cache::get('cheque_preview:'.$token);
        if (! is_array($payload) || $payload['admin_id'] !== auth('admin')->id() || ! preg_match('/\{\{\s*code\s*\}\}/i', $body)) {
            throw ValidationException::withMessages(['body' => 'پیش‌نمایش منقضی است یا عبارت {{code}} حذف شده است.']);
        }
        $code = (string) random_int(100000, 999999);
        $session = (string) Str::uuid();
        if (! empty($payload['summary']) && ! str_starts_with($body, $payload['summary'])) {
            $body = $payload['summary']."\n".$body;
        }
        $result = app(RawSmsDispatcher::class)->send($payload['mobile'], app(SmsTemplateRenderer::class)->render($body, ['code' => $code]), 'cheque-verification', ['module' => 'cheque_sales', 'admin_id' => auth('admin')->id()]);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['body' => $result['message']]);
        }
        Cache::forget('cheque_preview:'.$token);
        Cache::put('cheque_otp:'.$session, [...$payload, 'code_hash' => hash('sha256', $code), 'attempts' => 0, 'expires_at' => now()->addMinutes(5)->timestamp], now()->addMinutes(5));

        return ['session' => $session, 'message' => 'کد تأیید ارسال شد.'];
    }

    public function verify(string $session, string $code): array
    {
        return Cache::lock('cheque_otp_lock:'.$session, 10)->block(2, function () use ($session, $code): array {
            $payload = Cache::get('cheque_otp:'.$session);
            if (! is_array($payload) || $payload['admin_id'] !== auth('admin')->id() || $payload['attempts'] >= 5) {
                throw ValidationException::withMessages(['code' => 'کد منقضی است؛ دوباره ارسال کنید.']);
            }
            $payload['expires_at'] ??= now()->addMinutes(5)->timestamp;
            if (now()->timestamp >= $payload['expires_at']) {
                Cache::forget('cheque_otp:'.$session);
                throw ValidationException::withMessages(['code' => 'کد منقضی است؛ دوباره ارسال کنید.']);
            }
            $payload['attempts']++;
            Cache::put('cheque_otp:'.$session, $payload, now()->addSeconds($payload['expires_at'] - now()->timestamp));
            if (! hash_equals($payload['code_hash'], hash('sha256', $code))) {
                throw ValidationException::withMessages(['code' => 'کد تأیید صحیح نیست.']);
            }
            Cache::forget('cheque_otp:'.$session);
            $token = (string) Str::uuid();
            Cache::put('cheque_verified:'.$token, $payload, now()->addMinutes(10));

            return ['verification_token' => $token, 'message' => 'تأیید شد.'];
        });
    }

    public function consume(string $token, string $purpose, array $context): void
    {
        $payload = Cache::pull('cheque_verified:'.$token);
        if (! is_array($payload) || $payload['admin_id'] !== auth('admin')->id() || $payload['purpose'] !== $purpose || $payload['context'] !== $context) {
            throw ValidationException::withMessages(['verification_token' => 'تأیید پیامکی معتبر نیست یا اطلاعات پس از تأیید تغییر کرده است.']);
        }
    }
}
