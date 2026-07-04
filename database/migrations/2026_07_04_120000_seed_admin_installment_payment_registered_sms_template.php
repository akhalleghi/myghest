<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('sms_templates')->upsert(
            [
                [
                    'template_key' => 'default_admin_installment_payment_registered',
                    'is_system' => true,
                    'title' => 'اعلان ثبت واریز قسط (ادمین)',
                    'category' => 'installment',
                    'body' => "{{store_name}}\nمشتری گرامی {{customer_name}}؛ مبلغ {{paid_amount}} بابت قسط شماره {{installment_number}} پرونده {{loan_code}} ثبت گردید.\nمانده قابل پرداخت: {{remaining_loan}}",
                    'placeholders' => json_encode([
                        'store_name',
                        'customer_name',
                        'paid_amount',
                        'installment_number',
                        'loan_code',
                        'remaining_loan',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['template_key'],
            ['title', 'category', 'body', 'placeholders', 'is_system', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')
            ->where('template_key', 'default_admin_installment_payment_registered')
            ->delete();
    }
};
