<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestStatusLog;
use App\Models\LoanRequestStatusDefinition;
use App\Models\LoanType;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\SmsTemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminCustomerLoanRequestUpdateService
{
    public function __construct(
        private readonly LoanWizardParameterValidator $wizardRules,
        private readonly LoanRequestStatusTransitionLogger $statusLogger,
        private readonly RawSmsDispatcher $rawSms,
        private readonly SmsTemplateRenderer $smsTemplateRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{message: string, sms_note: string|null}
     */
    public function update(CustomerLoanRequest $loanRequest, array $validated, Request $request, ?int $adminId): array
    {
        $smsNote = null;

        DB::transaction(function () use ($loanRequest, $validated, $request, $adminId, &$smsNote): void {
            $plan = LoanType::query()->findOrFail((int) $validated['loan_type_id']);
            $description = trim((string) $loanRequest->description);

            $this->wizardRules->assertPlanAcceptsParameters(
                $plan,
                (int) $validated['amount_toman'],
                (int) $validated['installments_count'],
                (int) $validated['installment_interval_count'],
                (string) $plan->installment_gap_unit,
                $description,
            );

            $fromStatus = (string) $loanRequest->status;
            $toStatus = (string) $validated['status'];
            $statusChanged = $fromStatus !== $toStatus;

            $loanRequest->loan_type_id = $plan->id;
            $loanRequest->amount_toman = (int) $validated['amount_toman'];
            $loanRequest->installments_count = (int) $validated['installments_count'];
            $loanRequest->installment_interval_count = (int) $validated['installment_interval_count'];
            $loanRequest->installment_interval_unit = (string) $plan->installment_gap_unit;
            $loanRequest->profit_calculation_method = (string) $plan->profit_calculation_method;
            $loanRequest->interest_rate = $plan->interest_rate;
            $loanRequest->daily_late_coefficient = $plan->daily_late_coefficient;
            $loanRequest->daily_early_coefficient = $plan->daily_early_coefficient;
            $loanRequest->status = $toStatus;
            $loanRequest->expert_note = $validated['expert_note'] !== null && $validated['expert_note'] !== ''
                ? (string) $validated['expert_note']
                : null;
            $loanRequest->expert_note_customer = $validated['expert_note_customer'] !== null && $validated['expert_note_customer'] !== ''
                ? (string) $validated['expert_note_customer']
                : null;
            $loanRequest->documents_physical_received = (bool) ($validated['documents_physical_received'] ?? false);
            $loanRequest->save();

            if ($statusChanged) {
                $this->statusLogger->log(
                    $loanRequest,
                    $fromStatus,
                    $toStatus,
                    CustomerLoanRequestStatusLog::ACTOR_ADMIN,
                    $adminId,
                    $request,
                );
            }

            $sendSms = (bool) ($validated['send_status_sms'] ?? false);
            if ($sendSms && $statusChanged) {
                $smsNote = $this->sendStatusChangeSms($loanRequest, $toStatus);
            }
        });

        return [
            'message' => 'تغییرات ذخیره شد.',
            'sms_note' => $smsNote,
        ];
    }

    private function sendStatusChangeSms(CustomerLoanRequest $loanRequest, string $toStatusCode): ?string
    {
        $loanRequest->loadMissing(['customer', 'loanType']);
        $customer = $loanRequest->customer;
        if (! $customer instanceof Customer) {
            return 'پیامک ارسال نشد (مشتری یافت نشد).';
        }

        $mobile = preg_replace('/\D+/', '', (string) $customer->mobile) ?? '';
        if ($mobile === '') {
            return 'پیامک ارسال نشد (شماره موبایل مشتری نامعتبر است).';
        }

        $def = LoanRequestStatusDefinition::query()
            ->where('code', $toStatusCode)
            ->with('smsTemplate')
            ->first();

        $template = $def?->smsTemplate;
        if (! $template instanceof SmsTemplate) {
            return 'پیامک ارسال نشد (برای این وضعیت قالب پیامک تنظیم نشده است).';
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $statusTitle = $statusTitles[$toStatusCode] ?? $toStatusCode;

        $body = $this->smsTemplateRenderer->render((string) $template->body, [
            'customer_name' => $customer->fullName(),
            'loan_request_status_title' => $statusTitle,
            'app_name' => $this->appDisplayName(),
        ]);

        if ($body === '') {
            return 'پیامک ارسال نشد (متن قالب خالی است).';
        }

        $result = $this->rawSms->send($mobile, $body, SmsLog::TYPE_LOAN_REQUEST_STATUS, [
            'customer_loan_request_id' => $loanRequest->id,
            'loan_request_status_code' => $toStatusCode,
        ]);

        return $result['ok'] ? null : (string) $result['message'];
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $v : (string) config('app.name');
    }
}
