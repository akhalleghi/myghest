<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\IranMobile;
use Illuminate\Support\Facades\Log;

final class AdminLoginNotifySmsService
{
    public const TYPE_MANAGERS = 'admin-login-notify';

    public const TYPE_SELF = 'admin-login-self-notify';

    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function notifyOnLogin(Admin $loggedInAdmin): void
    {
        $this->notifyManagersOnLogin($loggedInAdmin);
        $this->notifySelfOnLogin($loggedInAdmin);
    }

    public function notifyManagersOnLogin(Admin $loggedInAdmin): void
    {
        $settings = $this->smsSettings->adminLoginNotifySettings();
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

        $loggedInId = (int) $loggedInAdmin->id;
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

        $message = $this->renderMessage($template, $loggedInAdmin, $this->appDisplayName());

        foreach ($recipients as $recipient) {
            $mobile = IranMobile::normalize((string) ($recipient->mobile ?? ''));
            if ($mobile === null) {
                Log::warning('admin_login_notify_skipped_invalid_mobile', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'logged_in_admin_id' => $loggedInId,
                ]);

                continue;
            }

            try {
                $result = $this->rawSms->send($mobile, $message, self::TYPE_MANAGERS, [
                    'logged_in_admin_id' => $loggedInId,
                    'notify_admin_id' => (int) $recipient->id,
                ]);
                if (! ($result['ok'] ?? false)) {
                    Log::warning('admin_login_notify_sms_undelivered', [
                        'recipient_admin_id' => (int) $recipient->id,
                        'logged_in_admin_id' => $loggedInId,
                        'message' => $result['message'] ?? '',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('admin_login_notify_sms_failed', [
                    'recipient_admin_id' => (int) $recipient->id,
                    'logged_in_admin_id' => $loggedInId,
                    'error' => $e->getMessage(),
                ]);
            }

            // فاصلهٔ کوتاه بین ارسال‌ها برای جلوگیری از محدودیت پنل پیامک
            usleep(350_000);
        }
    }

    public function notifySelfOnLogin(Admin $loggedInAdmin): void
    {
        $settings = $this->smsSettings->adminLoginSelfNotifySettings();
        if (! $this->smsSettings->isSettingEnabled($settings['enabled'])) {
            return;
        }

        $template = trim($settings['message_template']);
        if ($template === '') {
            return;
        }

        $mobile = IranMobile::normalize((string) ($loggedInAdmin->mobile ?? ''));
        if ($mobile === null) {
            Log::warning('admin_login_self_notify_skipped_invalid_mobile', [
                'admin_id' => (int) $loggedInAdmin->id,
            ]);

            return;
        }

        $message = $this->renderMessage($template, $loggedInAdmin, $this->appDisplayName());

        try {
            $result = $this->rawSms->send($mobile, $message, self::TYPE_SELF, [
                'notify_admin_id' => (int) $loggedInAdmin->id,
                'scope' => 'self',
            ]);
            if (! ($result['ok'] ?? false)) {
                Log::warning('admin_login_self_notify_sms_undelivered', [
                    'admin_id' => (int) $loggedInAdmin->id,
                    'message' => $result['message'] ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('admin_login_self_notify_sms_failed', [
                'admin_id' => (int) $loggedInAdmin->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function renderMessage(string $template, Admin $admin, string $appName): string
    {
        $fullName = $admin->fullName();
        $username = trim((string) ($admin->username ?? ''));

        $replacements = [
            '{admin_full_name}' => $fullName !== '' ? $fullName : $username,
            '{admin_name}' => $fullName !== '' ? $fullName : $username,
            '{admin_first_name}' => trim((string) ($admin->first_name ?? '')),
            '{admin_last_name}' => trim((string) ($admin->last_name ?? '')),
            '{admin_username}' => $username,
            '{app_name}' => $appName,
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $template);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public function defaultMessageTemplate(): string
    {
        return 'کاربر {admin_full_name} ({admin_username}) وارد پنل مدیریت {app_name} شد.';
    }

    public function defaultSelfMessageTemplate(): string
    {
        return '{admin_full_name} عزیز، ورود شما به پنل مدیریت {app_name} با موفقیت انجام شد.';
    }

    private function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $name = is_scalar($value) ? trim((string) $value) : '';

        return $name !== '' ? $name : 'سامانه';
    }

}
