<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_loan_files', 'profit_calculation_method')) {
                $table->string('profit_calculation_method', 32)->default('monthly')->after('down_payment_toman');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_loan_files', 'profit_calculation_method')) {
                $table->dropColumn('profit_calculation_method');
            }
        });
    }
};
