<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_deposit_declarations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('customer_loan_file_id');
            $table->unsignedBigInteger('customer_loan_installment_id');
            $table->date('deposited_at');
            $table->unsignedBigInteger('amount_toman');
            $table->string('user_payment_method', 32);
            $table->string('tracking_number', 190)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('applied_payment_id')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'cdd_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('customer_loan_file_id', 'cdd_loan_file_fk')->references('id')->on('customer_loan_files')->cascadeOnDelete();
            $table->foreign('customer_loan_installment_id', 'cdd_inst_fk')->references('id')->on('customer_loan_installments')->cascadeOnDelete();
            $table->foreign('reviewed_by_admin_id', 'cdd_admin_fk')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('applied_payment_id', 'cdd_pay_fk')->references('id')->on('customer_loan_installment_payments')->nullOnDelete();

            $table->index(['customer_id', 'status'], 'cdd_customer_status_idx');
            $table->index(['status', 'created_at'], 'cdd_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deposit_declarations');
    }
};
