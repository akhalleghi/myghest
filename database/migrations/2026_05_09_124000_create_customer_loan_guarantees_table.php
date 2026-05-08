<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_guarantees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('loan_file_id')->constrained('customer_loan_files')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['loan_file_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_guarantees');
    }
};
