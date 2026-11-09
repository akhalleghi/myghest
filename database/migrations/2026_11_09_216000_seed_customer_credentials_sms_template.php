<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')->insertOrIgnore([
            'template_key' => 'default_customer_credentials',
            'is_system' => true,
            'title' => 'ارسال اطلاعات ورود مشتری (نام کاربری و رمز عبور)',
            'category' => 'panel_login',
            'body' => "سامانه {{store_name}}\nنام کاربری: {{username}}\nرمز عبور: {{password}}\nورود به سامانه: {{app_url}}",
            'placeholders' => json_encode(['store_name', 'username', 'password', 'app_url'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the template and administrator edits on rollback.
    }
};
