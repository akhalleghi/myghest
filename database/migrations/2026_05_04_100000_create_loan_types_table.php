<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('profit_calculation_method', 32);
            $table->decimal('interest_rate', 6, 2);
            $table->decimal('daily_late_coefficient', 12, 6);
            $table->decimal('daily_early_coefficient', 12, 6);
            $table->unsignedBigInteger('max_loan_amount')->nullable();
            $table->unsignedInteger('max_installment_gap')->nullable();
            $table->string('installment_gap_unit', 16);
            $table->json('repayment_periods');
            $table->boolean('sms_reminder_enabled')->default(true);
            $table->boolean('registration_suspended')->default(false);
            $table->text('registration_suspended_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_types');
    }
};
