<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Services\Sms\SmsPanelManager;
use Illuminate\Support\Facades\Crypt;

final class RawSmsDispatcher
{
    public function __construct(
        private readonly SmsPanelManager $panelManager,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function send(string $recipient, string $messageText, string $type = 'customer-credentials'): array
    {
        $active = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($active === null) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک فعال نیست).'];
        }

        $providerOptions = $this->panelManager->providerOptions();
        $providerKey = $active->provider;
        if (! isset($providerOptions[$providerKey])) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک پیکربندی نشده).'];
        }

        $password = $this->decryptPasswordOrEmpty((string) $active->password);
        if ($password === '') {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (رمز پنل ذخیره نشده).'];
        }

        $gateway = $this->panelManager->gateway($providerKey);
        $result = $gateway->sendTestMessage(
            (string) $active->username,
            $password,
            $recipient,
            $messageText,
            [
                'domain_name' => (string) ($active->domain_name ?: 'sepahansms'),
                'sender_number' => (string) ($active->sender_number ?: '50003300'),
            ]
        );

        SmsLog::query()->create([
            'sms_panel' => (string) ($providerOptions[$providerKey] ?? $providerKey),
            'status' => $result->ok ? SmsLog::STATUS_DELIVERED : SmsLog::STATUS_UNDELIVERED,
            'sent_at' => now(),
            'message_text' => $messageText,
            'recipient' => $recipient,
            'type' => $type,
            'cost' => 0,
            'meta' => [
                'provider' => $providerKey,
                'response_code' => $result->code,
                'result_message' => $result->message,
            ],
        ]);

        return [
            'ok' => $result->ok,
            'message' => $result->ok
                ? 'پیامک ارسال شد.'
                : 'عملیات انجام شد اما ارسال پیامک ناموفق بود: '.$result->message,
        ];
    }

    private function decryptPasswordOrEmpty(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
