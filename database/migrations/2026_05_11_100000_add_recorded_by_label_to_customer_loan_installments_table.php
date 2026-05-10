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
            $table->string('recorded_by_label', 120)->nullable()->after('recorded_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_installments', function (Blueprint $table): void {
            $table->dropColumn('recorded_by_label');
        });
    }
};
