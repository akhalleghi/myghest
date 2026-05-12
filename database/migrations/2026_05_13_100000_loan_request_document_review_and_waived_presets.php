<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_request_documents', function (Blueprint $table): void {
            $table->string('review_status', 32)->default('submitted_by_user')->after('size_bytes');
            $table->text('expert_note')->nullable()->after('review_status');
        });

        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            $table->json('waived_initial_preset_keys')->nullable()->after('documents_physical_received');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_request_documents', function (Blueprint $table): void {
            $table->dropColumn(['review_status', 'expert_note']);
        });

        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            $table->dropColumn('waived_initial_preset_keys');
        });
    }
};
