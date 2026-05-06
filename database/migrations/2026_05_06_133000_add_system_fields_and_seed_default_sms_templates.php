<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->string('template_key', 80)->nullable()->unique()->after('id');
            $table->boolean('is_system')->default(false)->index()->after('template_key');
        });

        $now = now();
        $defaults = [
            [
                'template_key' => 'default_installment_due_reminder',
                'is_system' => true,
                'title' => 'پیامک یادآور سررسید',
                'category' => 'installment',
                'body' => "مشتری گرامی {{customer_name}}؛ سررسید قسط به مبلغ {{installment_amount}} فرا رسیده است، لطفا نسبت به پرداخت به موقع اقدام فرمایید.\n{{store_name}}",
                'placeholders' => json_encode(['customer_name', 'installment_amount', 'store_name'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'default_installment_overdue_reminder',
                'is_system' => true,
                'title' => 'پیامک یادآور قسط معوق',
                'category' => 'installment',
                'body' => "مشتری گرامی {{customer_name}}؛ سررسید قسط به مبلغ {{installment_amount}} معوق شده است، لطفا در اسرع وقت نسبت به پرداخت اقدام فرمایید.\n{{store_name}}",
                'placeholders' => json_encode(['customer_name', 'installment_amount', 'store_name'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'default_installment_payment_thanks',
                'is_system' => true,
                'title' => 'تشکر از پرداخت قسط',
                'category' => 'installment',
                'body' => "قسط به مبلغ {{paid_amount}} دریافت شد. مانده وام {{remaining_loan}}.\nبا تشکر {{store_name}}",
                'placeholders' => json_encode(['paid_amount', 'remaining_loan', 'store_name'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'default_installment_pre_due_reminder',
                'is_system' => true,
                'title' => 'یادآوری پیش از موعد',
                'category' => 'installment',
                'body' => "درود {{customer_name}} گرامی؛ سررسید قسط شماره {{installment_number}} به مبلغ {{installment_amount}} طی {{days_until_due}} روز آینده فرا خواهد رسید، لطفا نسبت به پرداخت به موقع اقدام فرمایید.",
                'placeholders' => json_encode(['customer_name', 'installment_number', 'installment_amount', 'days_until_due'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('sms_templates')->upsert(
            $defaults,
            ['template_key'],
            ['title', 'category', 'body', 'placeholders', 'is_system', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')
            ->whereIn('template_key', [
                'default_installment_due_reminder',
                'default_installment_overdue_reminder',
                'default_installment_payment_thanks',
                'default_installment_pre_due_reminder',
            ])
            ->delete();

        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->dropColumn('is_system');
            $table->dropUnique(['template_key']);
            $table->dropColumn('template_key');
        });
    }
};
