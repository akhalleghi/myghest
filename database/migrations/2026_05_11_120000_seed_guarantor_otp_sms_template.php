<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $body = 'آقای/خانم ({{guarantor_name}})'."\n"
            .'شما در حال ضمانت آقای/خانم ({{borrower_name}}) در سامانه ({{app_name}}) هستید.'."\n"
            .'کد تایید: {{code}}'."\n"
            .'لطفا این کد را در اختیار شخص دیگر قرار ندهید.';

        DB::table('sms_templates')->upsert(
            [
                [
                    'template_key' => 'default_guarantor_otp',
                    'is_system' => true,
                    'title' => 'احراز هویت موبایل ضامن (سازمانی)',
                    'category' => 'guarantor_otp',
                    'body' => $body,
                    'placeholders' => json_encode(['guarantor_name', 'borrower_name', 'app_name', 'code'], JSON_UNESCAPED_UNICODE),
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
        DB::table('sms_templates')->where('template_key', 'default_guarantor_otp')->delete();
    }
};
