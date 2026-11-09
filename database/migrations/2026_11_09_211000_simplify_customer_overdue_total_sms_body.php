<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_templates')
            ->where('template_key', 'default_customer_overdue_total')
            ->where('body', "{{store_name}}\nمشتری گرامی {{customer_name}}؛ مجموع بدهی {{overdue_installment_count}} قسط معوق شما با احتساب دیرکرد و گردکردن رو به بالای ۲٬۰۰۰ تومانی، {{installment_amount}} است. لطفاً نسبت به پرداخت اقدام فرمایید.")
            ->update([
                'body' => "{{store_name}}\nمشتری گرامی {{customer_name}}؛ مجموع بدهی معوق شما با احتساب جریمه دیرکرد، {{installment_amount}} است. لطفاً نسبت به پرداخت اقدام فرمایید.",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Preserve administrator template edits.
    }
};
