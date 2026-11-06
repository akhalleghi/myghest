<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_installment_payments', function (Blueprint $table): void {
            $table->boolean('overdue_rounding_applied')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_installment_payments', function (Blueprint $table): void {
            $table->dropColumn('overdue_rounding_applied');
        });
    }
};
