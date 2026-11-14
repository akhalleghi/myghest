<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')->insertOrIgnore([
            'template_key' => 'cheque_customer_summary', 'is_system' => true, 'title' => 'خلاصه بدهی مشتری چکی', 'category' => 'cheque_sales',
            'body' => '{{customer_name}} گرامی، مجموع مانده قابل تسویه خریدهای چکی شما تا امروز با دیرکرد و زودکرد: {{cheque_debt}}. موجودی کیف پول چکی: {{wallet_balance}}.',
            'placeholders' => json_encode(['customer_name', 'cheque_debt', 'wallet_balance']), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Retain administrator template edits.
    }
};
