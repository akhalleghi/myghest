<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_installment_online_payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('customer_loan_installment_id');
            $table->unsignedBigInteger('expected_amount_toman');
            $table->unsignedBigInteger('expected_amount_rial');
            $table->unsignedBigInteger('track_id')->nullable()->unique();
            $table->string('status', 32);
            $table->string('gateway_key', 24)->default('zibal');
            $table->string('zibal_ref_number', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'clio_pi_cust_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('customer_loan_installment_id', 'clio_pi_inst_fk')
                ->references('id')->on('customer_loan_installments')->cascadeOnDelete();
            $table->index(['customer_id', 'status'], 'clio_pi_cust_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_installment_online_payment_intents');
    }
};
