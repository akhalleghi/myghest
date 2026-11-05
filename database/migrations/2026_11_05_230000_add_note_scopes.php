<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_admin_notes', function (Blueprint $table): void {
            $table->foreignId('loan_file_id')->nullable()->constrained('customer_loan_files')->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('customer_loan_installments')->nullOnDelete();
            $table->string('scope_label')->nullable();
        });
    }

    public function down(): void {}
};
