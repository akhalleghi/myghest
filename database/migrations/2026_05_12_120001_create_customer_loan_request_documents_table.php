<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loan_request_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_loan_request_id')
                ->constrained('customer_loan_requests')
                ->cascadeOnDelete();
            $table->string('preset_key', 160);
            $table->string('document_title', 255);
            $table->unsignedSmallInteger('row_index');
            $table->unsignedInteger('client_row_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('stored_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['customer_loan_request_id', 'preset_key'], 'clr_req_docs_req_preset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_request_documents');
    }
};
