<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_loan_file_id')->constrained('customer_loan_files')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('amount_toman');
            $table->date('due_date');
            $table->unsignedBigInteger('paid_amount_toman')->default(0);
            $table->date('paid_at')->nullable();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_loan_file_id', 'sequence']);
            $table->index(['customer_loan_file_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_installments');
    }
};
