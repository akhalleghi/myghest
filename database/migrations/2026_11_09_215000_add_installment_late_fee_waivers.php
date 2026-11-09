<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_installments', function (Blueprint $table): void {
            $table->unsignedBigInteger('late_fee_waived_toman')->default(0);
            $table->timestamp('late_fee_waived_at')->nullable();
        });
        Schema::create('installment_late_fee_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installment_id')->constrained('customer_loan_installments')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('old_waived_toman');
            $table->unsignedBigInteger('new_waived_toman');
            $table->unsignedBigInteger('old_remaining_toman');
            $table->unsignedBigInteger('new_remaining_toman');
            $table->string('reason', 1000);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_late_fee_waivers');
        Schema::table('customer_loan_installments', function (Blueprint $table): void {
            $table->dropColumn(['late_fee_waived_toman', 'late_fee_waived_at']);
        });
    }
};
