<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\InstallmentSmsReminderDispatch;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class InstallmentSmsReminderService
{
    public function __construct(
        private readonly SmsSettingsService $smsSettings,
        private readonly SmsTemplateRenderer $templateRenderer,
        private readonly InstallmentSmsTemplateVarsBuilder $varsBuilder,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function run(?Carbon $now = null, bool $dryRun = false, bool $ignoreSendWindow = false): InstallmentSmsReminderRunResult
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $settings = $this->smsSettings->reminderSettings();

        if (! $this->isEnabled($settings['reminder_enabled'] ?? '')) {
            return InstallmentSmsReminderRunResult::skipped('reminder_disabled');
        }

        if (! $ignoreSendWindow && ! $this->isWithinDailySendWindow($settings['reminder_send_time'] ?? '', $now)) {
            return InstallmentSmsReminderRunResult::skipped('outside_send_window');
        }

        $lock = Cache::lock('installment_sms_reminders', 900);
        if (! $lock->get()) {
            return InstallmentSmsReminderRunResult::skipped('lock_busy');
        }

        try {

            $preDueSent = 0;
            $dueDaySent = 0;
            $overdueSent = 0;
            $skipped = 0;
            $failed = 0;

            $beforeDueDays = max(0, $this->parseInt($settings['before_due_days'] ?? '', 0));
            $overdueDaysAfter = max(0, $this->parseInt($settings['overdue_days_after'] ?? '', 0));
            $overdueRepeatMode = strtolower(trim((string) ($settings['overdue_repeat_mode'] ?? 'once')));
            $overdueRepeatIntervalDays = max(2, $this->parseInt($settings['overdue_repeat_interval_days'] ?? '', 7));

            $beforeDueTemplateId = $this->parsePositiveInt($settings['before_due_template_id'] ?? '');
            $dueDayTemplateId = $this->parsePositiveInt($settings['due_day_template_id'] ?? '');
            $overdueTemplateId = $this->parsePositiveInt($settings['overdue_template_id'] ?? '');

            $beforeDueEnabled = $this->isEnabled($settings['before_due_enabled'] ?? '');
            $dueDayEnabled = $this->isEnabled($settings['due_day_enabled'] ?? '');

            CustomerLoanInstallment::query()
                ->with([
                    'loanFile.customer',
                    'loanFile.loanType',
                ])
                ->whereColumn('paid_amount_toman', '<', 'amount_toman')
                ->whereHas('loanFile', static function ($q): void {
                    $q->whereNull('revoked_at')
                        ->where('is_settled', false)
                        ->whereHas('loanType', static fn ($lt) => $lt->where('sms_reminder_enabled', true));
                })
                ->orderBy('id')
                ->chunkById(150, function ($installments) use (
                    $today,
                    $dryRun,
                    $beforeDueEnabled,
                    $dueDayEnabled,
                    $beforeDueDays,
                    $beforeDueTemplateId,
                    $dueDayTemplateId,
                    $overdueTemplateId,
                    $overdueDaysAfter,
                    $overdueRepeatMode,
                    $overdueRepeatIntervalDays,
                    &$preDueSent,
                    &$dueDaySent,
                    &$overdueSent,
                    &$skipped,
                    &$failed,
                ): void {
                    foreach ($installments as $installment) {
                        $loanFile = $installment->loanFile;
                        $customer = $loanFile?->customer;
                        if ($loanFile === null || $customer === null) {
                            $skipped++;

                            continue;
                        }

                        $mobile = $this->normalizeMobile((string) ($customer->mobile ?? ''));
                        if (! $this->isValidIranMobile($mobile)) {
                            $skipped++;

                            continue;
                        }

                        $due = Carbon::parse($installment->due_date)->startOfDay();
                        $daysPastDue = $today->gt($due) ? (int) $due->diffInDays($today) : 0;

                        if ($beforeDueEnabled && $beforeDueTemplateId !== null && $beforeDueDays > 0) {
                            $preDueDate = $due->copy()->subDays($beforeDueDays);
                            if ($today->equalTo($preDueDate)) {
                                $sent = $this->trySend(
                                    $installment,
                                    $customer,
                                    $loanFile,
                                    $mobile,
                                    InstallmentSmsReminderDispatch::KIND_PRE_DUE,
                                    $today,
                                    $beforeDueTemplateId,
                                    'installment_pre_due',
                                    $dryRun
                                );
                                $this->tallySendResult($sent, $preDueSent, $skipped, $failed);
                            }
                        }

                        if ($dueDayEnabled && $dueDayTemplateId !== null && $today->equalTo($due)) {
                            $sent = $this->trySend(
                                $installment,
                                $customer,
                                $loanFile,
                                $mobile,
                                InstallmentSmsReminderDispatch::KIND_DUE_DAY,
                                $today,
                                $dueDayTemplateId,
                                'installment_due',
                                $dryRun
                            );
                            $this->tallySendResult($sent, $dueDaySent, $skipped, $failed);
                        }

                        if ($overdueTemplateId !== null && $daysPastDue >= $overdueDaysAfter) {
                            $firstOverdueDay = $due->copy()->addDays($overdueDaysAfter);
                            if (! $this->shouldSendOverdueToday($today, $firstOverdueDay, $overdueRepeatMode, $overdueRepeatIntervalDays)) {
                                continue;
                            }

                            $businessDate = $overdueRepeatMode === 'once' ? $firstOverdueDay : $today;
                            $sent = $this->trySend(
                                $installment,
                                $customer,
                                $loanFile,
                                $mobile,
                                InstallmentSmsReminderDispatch::KIND_OVERDUE,
                                $businessDate,
                                $overdueTemplateId,
                                'installment_overdue',
                                $dryRun
                            );
                            $this->tallySendResult($sent, $overdueSent, $skipped, $failed);
                        }
                    }
                });

            return new InstallmentSmsReminderRunResult(
                status: 'completed',
                preDueSent: $preDueSent,
                dueDaySent: $dueDaySent,
                overdueSent: $overdueSent,
                skipped: $skipped,
                failed: $failed,
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return 'sent'|'skipped'|'failed'
     */
    private function trySend(
        CustomerLoanInstallment $installment,
        Customer $customer,
        CustomerLoanFile $loanFile,
        string $mobile,
        string $kind,
        Carbon $businessDate,
        int $templateId,
        string $smsLogType,
        bool $dryRun,
    ): string {
        $exists = InstallmentSmsReminderDispatch::query()
            ->where('customer_loan_installment_id', $installment->id)
            ->where('kind', $kind)
            ->whereDate('business_date', $businessDate->toDateString())
            ->exists();
        if ($exists) {
            return 'skipped';
        }

        if (! $customer->receivesOutboundSms()) {
            return 'skipped';
        }

        $template = SmsTemplate::query()->find($templateId);
        if ($template === null) {
            Log::warning('installment_sms_reminder_missing_template', [
                'template_id' => $templateId,
                'installment_id' => $installment->id,
                'kind' => $kind,
            ]);

            return 'failed';
        }

        $vars = $this->varsBuilder->build($customer, $loanFile, $installment, $businessDate, $smsLogType);
        $body = trim($this->templateRenderer->render((string) $template->body, $vars));
        if ($body === '') {
            return 'failed';
        }

        if ($dryRun) {
            return 'sent';
        }

        $result = $this->rawSms->send($mobile, $body, $smsLogType, [
            'installment_id' => $installment->id,
            'loan_file_id' => $loanFile->id,
            'customer_id' => $customer->id,
            'reminder_kind' => $kind,
            'template_id' => $templateId,
            'business_date' => $businessDate->toDateString(),
            'automated' => true,
        ]);

        if (! $result['ok']) {
            return 'failed';
        }

        DB::transaction(function () use ($installment, $kind, $businessDate, $result): void {
            InstallmentSmsReminderDispatch::query()->create([
                'customer_loan_installment_id' => $installment->id,
                'kind' => $kind,
                'business_date' => $businessDate->toDateString(),
                'sms_log_id' => $result['sms_log_id'],
            ]);
        });

        return 'sent';
    }

    private function shouldSendOverdueToday(
        Carbon $today,
        Carbon $firstOverdueDay,
        string $repeatMode,
        int $intervalDays,
    ): bool {
        if ($today->lt($firstOverdueDay)) {
            return false;
        }

        return match ($repeatMode) {
            'daily' => true,
            'weekly' => ((int) $firstOverdueDay->diffInDays($today)) % 7 === 0,
            'interval' => ((int) $firstOverdueDay->diffInDays($today)) % max(2, $intervalDays) === 0,
            default => $today->equalTo($firstOverdueDay),
        };
    }

    /**
     * پنجرهٔ ۹ دقیقه‌ای پس از ساعت تنظیم‌شده (هم‌پوشانی با cron هر ۵ دقیقه برای تلاش مجدد).
     */
    private function isWithinDailySendWindow(string $sendTime, Carbon $now): bool
    {
        $sendTime = trim($sendTime);
        if ($sendTime === '' || ! preg_match('/^\d{1,2}:\d{2}$/', $sendTime)) {
            return false;
        }

        [$hour, $minute] = array_map(intval(...), explode(':', $sendTime, 2));
        $scheduled = $now->copy()->startOfDay()->setTime($hour, $minute);
        $windowEnd = $scheduled->copy()->addMinutes(9);

        return $now->greaterThanOrEqualTo($scheduled) && $now->lessThan($windowEnd);
    }

    private function isEnabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function parseInt(string $value, int $default): int
    {
        $v = filter_var(trim($value), FILTER_VALIDATE_INT);

        return $v === false ? $default : (int) $v;
    }

    private function parsePositiveInt(string $value): ?int
    {
        $v = filter_var(trim($value), FILTER_VALIDATE_INT);
        if ($v === false || $v < 1) {
            return null;
        }

        return (int) $v;
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function isValidIranMobile(string $mobile): bool
    {
        return (bool) preg_match('/^09\d{9}$/', $mobile);
    }

    /**
     * @param  'sent'|'skipped'|'failed'  $result
     */
    private function tallySendResult(string $result, int &$sentCounter, int &$skipped, int &$failed): void
    {
        match ($result) {
            'sent' => $sentCounter++,
            'failed' => $failed++,
            default => $skipped++,
        };
    }
}
