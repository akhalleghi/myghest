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
            .'عودت ضمانت پرونده وام شما در سامانه ({{app_name}}) ثبت می‌شود.'.chr(10)
            .'کد تایید: {{code}}'."\n"
            .'لطفا این کد را در اختیار شخص دیگر قرار ندهید.';

        DB::table('sms_templates')->upsert(
            [
                [
                    'template_key' => 'default_guarantee_return_otp',
                    'is_system' => true,
                    'title' => 'تایید پیامکی عودت ضمانت',
                    'category' => 'guarantee_return_otp',
                    'body' => $body,
                    'placeholders' => json_encode(['customer_name', 'app_name', 'code', 'guarantee_type_label'], JSON_UNESCAPED_UNICODE),
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
        DB::table('sms_templates')->where('template_key', 'default_guarantee_return_otp')->delete();
    }
};
