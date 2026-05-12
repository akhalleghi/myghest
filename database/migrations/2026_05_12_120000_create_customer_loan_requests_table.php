<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('loan_type_id')->constrained('loan_types')->restrictOnDelete();
            $table->string('status', 40)->default('pending_review')->index();
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedInteger('installments_count');
            $table->unsignedInteger('installment_interval_count');
            $table->string('installment_interval_unit', 20);
            $table->string('profit_calculation_method', 32);
            $table->decimal('interest_rate', 8, 2);
            $table->decimal('daily_late_coefficient', 12, 6)->default(0);
            $table->decimal('daily_early_coefficient', 12, 6)->default(0);
            $table->text('description')->nullable();
            $table->text('expert_note')->nullable();
            $table->boolean('documents_physical_received')->default(false);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_requests');
    }
};
