<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sms\InstallmentSmsReminderService;
use Illuminate\Console\Command;

final class SendInstallmentSmsRemindersCommand extends Command
{
    protected $signature = 'sms:send-installment-reminders
                            {--dry-run : فقط شمارش بدون ارسال واقعی}
                            {--force : نادیده گرفتن پنجرهٔ ساعت ارسال (تست)}';

    protected $description = 'ارسال خودکار پیامک‌های یادآوری اقساط طبق تنظیمات پنل';

    public function handle(InstallmentSmsReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $result = $service->run(dryRun: $dryRun, ignoreSendWindow: $force);

        if ($result->status !== 'completed') {
            $this->line('وضعیت: '.$result->status);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'یادآوری اقساط — پیش از موعد: %d | سررسید: %d | معوق: %d | ردشده: %d | ناموفق: %d%s',
            $result->preDueSent,
            $result->dueDaySent,
            $result->overdueSent,
            $result->skipped,
            $result->failed,
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
