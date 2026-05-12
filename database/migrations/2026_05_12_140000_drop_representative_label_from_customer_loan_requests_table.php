<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_loan_requests', 'representative_label')) {
            return;
        }
        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            $table->dropColumn('representative_label');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            $table->string('representative_label', 255)->nullable()->after('expert_note');
        });
    }
};
