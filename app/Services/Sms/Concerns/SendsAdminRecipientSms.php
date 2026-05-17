<?php

declare(strict_types=1);

namespace App\Services\Sms\Concerns;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\SmsSettingsService;
use App\Support\IranMobile;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\Log;

trait SendsAdminRecipientSms
{
    /**
     * @param  array{enabled: string, recipient_ids: list<int>, message_template: string}  $settings
     * @param  array<string, mixed>  $meta
     */
    protected function sendAdminRecipientSms(
        SmsSettingsService $smsSettings,
        RawSmsDispatcher $rawSms,
        array $settings,
        string $message,
        string $type,
        array $meta,
        string $logKey,
    ): void {
        if (! $smsSettings->isSettingEnabled($settings['enabled'])) {
            return;
        }

        $recipientIds = array_values(array_filter(
            $settings['recipient_ids'],
            static fn (int $id): bool => $id > 0
        ));
        if ($recipientIds === [] || trim($message) === '') {
            return;
        }

        $recipients = Admin::query()
            ->whereIn('id', $recipientIds)
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->get(['id', 'mobile']);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $mobile = IranMobile::normalize((string) ($recipient->mobile ?? ''));
            if ($mobile === null) {
                Log::warning($logKey.'_skipped_invalid_mobile', array_merge($meta, [
                    'recipient_admin_id' => (int) $recipient->id,
                ]));

                continue;
            }

            try {
                $result = $rawSms->send($mobile, $message, $type, array_merge($meta, [
                    'notify_admin_id' => (int) $recipient->id,
                ]));
                if (! ($result['ok'] ?? false)) {
                    Log::warning($logKey.'_undelivered', array_merge($meta, [
                        'recipient_admin_id' => (int) $recipient->id,
                        'message' => $result['message'] ?? '',
                    ]));
                }
            } catch (\Throwable $e) {
                Log::warning($logKey.'_failed', array_merge($meta, [
                    'recipient_admin_id' => (int) $recipient->id,
                    'error' => $e->getMessage(),
                ]));
            }

            usleep(350_000);
        }
    }

    protected function smsAppDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : 'سامانه';
    }

    protected function smsFormatToman(int $amountToman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $amountToman), 0, '.', ','));
    }

    protected function smsNormalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
