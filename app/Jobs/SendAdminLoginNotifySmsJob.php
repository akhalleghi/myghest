<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Admin;
use App\Services\Sms\AdminLoginNotifySmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * ارسال پیامک‌های ورود ادمین پس از پاسخ HTTP — ورود کاربر منتظر پنل پیامک نمی‌ماند.
 */
final class SendAdminLoginNotifySmsJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $adminId,
    ) {}

    public function handle(AdminLoginNotifySmsService $loginNotifySms): void
    {
        $admin = Admin::query()->find($this->adminId);
        if ($admin === null || ! $admin->is_active) {
            return;
        }

        try {
            $loginNotifySms->notifyOnLogin($admin);
        } catch (\Throwable $e) {
            Log::warning('admin_login_notify_job_failed', [
                'admin_id' => $this->adminId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
