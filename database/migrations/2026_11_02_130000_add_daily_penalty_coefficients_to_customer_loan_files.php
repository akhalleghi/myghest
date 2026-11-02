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
            // A following migration captures legacy rates in these new contract fields.
            $table->decimal('daily_late_coefficient', 12, 6)->nullable();
            $table->decimal('daily_early_coefficient', 12, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            $table->dropColumn(['daily_late_coefficient', 'daily_early_coefficient']);
        });
    }
};
