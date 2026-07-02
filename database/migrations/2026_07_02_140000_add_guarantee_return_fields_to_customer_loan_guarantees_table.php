<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_guarantees', function (Blueprint $table): void {
            $table->string('return_document_path')->nullable()->after('attachment_path');
            $table->timestamp('returned_at')->nullable()->after('return_document_path');
            $table->foreignId('returned_by_admin_id')->nullable()->after('returned_at')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_guarantees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('returned_by_admin_id');
            $table->dropColumn(['return_document_path', 'returned_at']);
        });
    }
};
