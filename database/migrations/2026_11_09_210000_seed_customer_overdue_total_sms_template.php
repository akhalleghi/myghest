<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')->insertOrIgnore([
            'template_key' => 'default_customer_overdue_total',
            'is_system' => true,
            'title' => 'مجموع اقساط معوق مشتری با دیرکرد (گرد ۲٬۰۰۰ تومانی)',
            'category' => 'installment',
            'body' => "{{store_name}}\nمشتری گرامی {{customer_name}}؛ مجموع بدهی معوق شما با احتساب جریمه دیرکرد، {{installment_amount}} است. لطفاً نسبت به پرداخت اقدام فرمایید.",
            'placeholders' => json_encode(['store_name', 'customer_name', 'overdue_installment_count', 'installment_amount'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the template and any administrator edits on rollback.
    }
};
