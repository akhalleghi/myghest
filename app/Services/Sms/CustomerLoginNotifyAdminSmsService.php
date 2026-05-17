<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\IranMobile;
use Illuminate\Support\Facades\Log;

final class CustomerLoginNotifyAdminSmsService
{
    public const TYPE = 'customer-login-notify-admin';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyAdminsOnLogin(Customer $customer): void
    {
        $settings = $this->smsSettings->customerLoginNotifyAdminSettings();
        if (! $this->smsSettings->isSettingEnabled($settings['enabled'])) {
            return;
        }

        $recipientIds = $settings['recipient_ids'];
        if ($recipientIds === []) {
            return;
        }

        $template = trim($settings['message_template']);
        if ($template === '') {
            return;
        }

        $customerId = (int) $customer->id;
        $recipientIds = array_values(array_filter(
            $recipientIds,
            static fn (int $id): bool => $id > 0
        ));
        if ($recipientIds === []) {
            return;
        }

        $recipients = Admin::query()
            ->whereIn('id', $recipientIds)
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->get(['id', 'first_name', 'last_name', 'name', 'username', 'mobile']);

        if ($recipients->isEmpty()) {
            return;
        }

        $message = $this->renderMessage($template, $customer, $this->appDisplayName());

        foreach ($recipients as $recipient) {
            $mobile = IranMobile::normalize((string) ($recipient->mobile ?? ''));
            if ($mobile === null) {
                Log::warning('customer_login_notify_admin_skipped_invalid_mobile', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'customer_id' => $customerId,
                ]);

                continue;
            }

            try {
                $result = $this->rawSms->send($mobile, $message, self::TYPE, [
                    'customer_id' => $customerId,
                    'notify_admin_id' => (int) $recipient->id,
                ]);
                if (! ($result['ok'] ?? false)) {
                    Log::warning('customer_login_notify_admin_sms_undelivered', [
                        'recipient_admin_id' => (int) $recipient->id,
                        'customer_id' => $customerId,
                        'message' => $result['message'] ?? '',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('customer_login_notify_admin_sms_failed', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(350_000);
        }
    }

    public function renderMessage(string $template, Customer $customer, string $appName): string
    {
        $fullName = $customer->fullName();
        $username = trim((string) ($customer->username ?? ''));

        $replacements = [
            '{customer_full_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_name}' => $fullName !== '' ? $fullName : $username,
            '{customer_first_name}' => trim((string) ($customer->first_name ?? '')),
            '{customer_last_name}' => trim((string) ($customer->last_name ?? '')),
            '{customer_username}' => $username,
            '{app_name}' => $appName,
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $template);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public function defaultMessageTemplate(): string
    {
        return 'مشتری {customer_full_name} وارد پنل کاربری خود شد.';
    }

    private function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : 'سامانه';
    }
}
