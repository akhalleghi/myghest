<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $body = 'مشتری گرامی ({{customer_name}})'."\n"
            .'برای ثبت پرونده وام در سامانه ({{app_name}}) کد تایید زیر را وارد کنید.'."\n"
            .'کد تایید: {{code}}'."\n"
            .'لطفا این کد را در اختیار شخص دیگر قرار ندهید.';

        DB::table('sms_templates')->upsert(
            [
                [
                    'template_key' => 'default_loan_creation_otp',
                    'is_system' => true,
                    'title' => 'تایید پیامکی ایجاد پرونده وام',
                    'category' => 'loan_creation_otp',
                    'body' => $body,
                    'placeholders' => json_encode(['customer_name', 'app_name', 'code'], JSON_UNESCAPED_UNICODE),
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
        DB::table('sms_templates')->where('template_key', 'default_loan_creation_otp')->delete();
    }
};
