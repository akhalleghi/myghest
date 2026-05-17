<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_sms_reminder_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_loan_installment_id');
            $table->string('kind', 32);
            $table->date('business_date');
            $table->unsignedBigInteger('sms_log_id')->nullable();
            $table->timestamps();

            $table->unique(['customer_loan_installment_id', 'kind', 'business_date'], 'inst_sms_reminder_unique');
            $table->index(['business_date', 'kind']);

            $table->foreign('customer_loan_installment_id', 'inst_sms_rem_inst_fk')
                ->references('id')->on('customer_loan_installments')->cascadeOnDelete();
            $table->foreign('sms_log_id', 'inst_sms_rem_log_fk')
                ->references('id')->on('sms_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_sms_reminder_dispatches');
    }
};
