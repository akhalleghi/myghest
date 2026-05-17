<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_loan_full_settlement_online_payment_intents')) {
            return;
        }

        Schema::create('customer_loan_full_settlement_online_payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('customer_loan_file_id');
            $table->unsignedBigInteger('expected_amount_toman');
            $table->unsignedBigInteger('expected_amount_rial');
            $table->unsignedBigInteger('principal_component_toman');
            $table->unsignedBigInteger('late_fee_component_toman');
            $table->unsignedBigInteger('track_id')->nullable();
            $table->string('status', 32);
            $table->string('gateway_key', 24)->default('zibal');
            $table->string('zibal_ref_number', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique('track_id', 'clfso_track_uidx');

            $table->foreign('customer_id', 'clfso_pi_cust_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('customer_loan_file_id', 'clfso_pi_file_fk')
                ->references('id')->on('customer_loan_files')->cascadeOnDelete();
            $table->index(['customer_id', 'status'], 'clfso_pi_cust_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_full_settlement_online_payment_intents');
    }
};
