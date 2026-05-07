<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('loan_type_id')->constrained('loan_types')->restrictOnDelete();
            $table->string('loan_code', 40)->unique();
            $table->date('loan_start_date');
            $table->date('disbursement_due_date')->nullable();
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedInteger('installments_count');
            $table->unsignedInteger('installment_interval_count')->default(1);
            $table->string('installment_interval_unit', 20);
            $table->unsignedBigInteger('installment_amount_toman');
            $table->unsignedBigInteger('down_payment_toman')->default(0);
            $table->string('sub_file_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_settled')->default(false);
            $table->date('settled_at')->nullable();
            $table->decimal('base_interest_rate', 5, 2);
            $table->boolean('has_custom_interest_rate')->default(false);
            $table->decimal('custom_interest_rate', 5, 2)->nullable();
            $table->decimal('effective_interest_rate', 5, 2);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_files');
    }
};
