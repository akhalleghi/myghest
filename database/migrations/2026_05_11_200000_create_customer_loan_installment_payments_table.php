<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_installment_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_loan_installment_id');
            $table->string('payment_method', 40);
            $table->unsignedBigInteger('amount_toman');
            $table->date('reference_due_date')->nullable();
            $table->date('deposited_at');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('customer_loan_installment_id', 'cli_pay_inst_fk')
                ->references('id')->on('customer_loan_installments')->cascadeOnDelete();
            $table->foreign('recorded_by_admin_id', 'cli_pay_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();

            $table->index(['customer_loan_installment_id', 'deposited_at'], 'cli_pay_inst_dep_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_installment_payments');
    }
};
