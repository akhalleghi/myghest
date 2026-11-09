<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')->insertOrIgnore([
            'template_key' => 'default_installment_wallet_reward',
            'is_system' => true,
            'title' => 'اطلاع‌رسانی شارژ کیف پول بابت پاداش خوش‌حسابی',
            'category' => 'installment',
            'body' => "{{store_name}}\nمشتری گرامی {{customer_name}}؛ بابت پرداخت به‌موقع قسط، مبلغ {{reward_amount}} پاداش خوش‌حسابی به کیف پول شما افزوده شد.\nموجودی کیف پول شما: {{wallet_balance}}\nورود به سامانه: {{app_url}}",
            'placeholders' => json_encode(['store_name', 'customer_name', 'reward_amount', 'wallet_balance', 'app_url'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve any administrator changes to the template.
    }
};
