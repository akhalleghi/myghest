<?php

declare(strict_types=1);

use App\Models\LoanRequestStatusDefinition;
use App\Models\SmsTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            'initial' => [
                'title' => 'پیامک وضعیت: ایجاد اولیه',
                'body' => "{{customer_name}} گرامی؛ درخواست وام شما ثبت شد. وضعیت فعلی: {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'documents_complete' => [
                'title' => 'پیامک وضعیت: تکمیل مدارک',
                'body' => "{{customer_name}} گرامی؛ مدارک درخواست وام تکمیل شد. وضعیت: {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'documents_incomplete' => [
                'title' => 'پیامک وضعیت: مدارک ناقص',
                'body' => "{{customer_name}} گرامی؛ مدارک درخواست وام ناقص است. وضعیت: {{loan_request_status_title}}.\nلطفاً نسبت به تکمیل اقدام فرمایید.\n{{app_name}}",
            ],
            'pending_expert_review' => [
                'title' => 'پیامک وضعیت: در انتظار کارشناس',
                'body' => "{{customer_name}} گرامی؛ درخواست وام در انتظار بررسی کارشناس است. {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'needs_followup' => [
                'title' => 'پیامک وضعیت: نیاز به پیگیری',
                'body' => "{{customer_name}} گرامی؛ درخواست وام نیازمند پیگیری است. {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'expert_re_review' => [
                'title' => 'پیامک وضعیت: بررسی مجدد',
                'body' => "{{customer_name}} گرامی؛ درخواست وام در حال بررسی مجدد است. {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'rejected' => [
                'title' => 'پیامک وضعیت: رد شده',
                'body' => "{{customer_name}} گرامی؛ وضعیت درخواست وام: {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'prioritized' => [
                'title' => 'پیامک وضعیت: اولویت‌بندی شده',
                'body' => "{{customer_name}} گرامی؛ درخواست وام اولویت‌بندی شد. {{loan_request_status_title}}.\n{{app_name}}",
            ],
            'paid' => [
                'title' => 'پیامک وضعیت: پرداخت شده',
                'body' => "{{customer_name}} گرامی؛ وضعیت درخواست وام: {{loan_request_status_title}}.\n{{app_name}}",
            ],
        ];

        foreach ($templates as $code => $tpl) {
            $key = 'loan_request_status_'.$code;
            $placeholders = ['customer_name', 'loan_request_status_title', 'app_name'];
            SmsTemplate::query()->updateOrCreate(
                ['template_key' => $key],
                [
                    'is_system' => true,
                    'title' => $tpl['title'],
                    'category' => 'loan_request_status',
                    'body' => $tpl['body'],
                    'placeholders' => $placeholders,
                    'updated_at' => $now,
                ]
            );
        }

        $slotByCode = [
            'initial' => 'before_documents',
            'documents_complete' => 'documents_step',
            'documents_incomplete' => 'documents_step',
            'pending_expert_review' => 'after_documents',
            'needs_followup' => 'after_documents',
            'expert_re_review' => 'after_documents',
            'rejected' => 'after_documents',
            'prioritized' => 'after_documents',
            'paid' => 'after_documents',
        ];

        foreach ($slotByCode as $code => $slot) {
            $tid = SmsTemplate::query()->where('template_key', 'loan_request_status_'.$code)->value('id');
            if ($tid === null) {
                continue;
            }
            LoanRequestStatusDefinition::query()
                ->where('code', $code)
                ->update([
                    'stage_slot' => $slot,
                    'sms_template_id' => (int) $tid,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        $keys = [
            'loan_request_status_initial',
            'loan_request_status_documents_complete',
            'loan_request_status_documents_incomplete',
            'loan_request_status_pending_expert_review',
            'loan_request_status_needs_followup',
            'loan_request_status_expert_re_review',
            'loan_request_status_rejected',
            'loan_request_status_prioritized',
            'loan_request_status_paid',
        ];

        LoanRequestStatusDefinition::query()->update(['sms_template_id' => null]);

        SmsTemplate::query()->whereIn('template_key', $keys)->delete();

        $legacySlots = [
            'initial' => 'after_initial',
            'documents_complete' => 'after_documents',
            'documents_incomplete' => 'after_documents',
            'pending_expert_review' => 'after_expert',
            'needs_followup' => 'after_expert',
            'expert_re_review' => 'after_expert',
            'rejected' => 'terminal',
            'prioritized' => 'pre_disbursement',
            'paid' => 'terminal',
        ];
        foreach ($legacySlots as $code => $slot) {
            LoanRequestStatusDefinition::query()->where('code', $code)->update(['stage_slot' => $slot]);
        }
    }
};
