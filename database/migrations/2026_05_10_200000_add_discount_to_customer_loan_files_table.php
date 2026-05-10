<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_amount_toman')->default(0)->after('revoked_by_admin_id');
            $table->timestamp('discount_updated_at')->nullable()->after('discount_amount_toman');
            $table->foreignId('discount_updated_by_admin_id')->nullable()->after('discount_updated_at')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_updated_by_admin_id');
            $table->dropColumn(['discount_updated_at', 'discount_amount_toman']);
        });
    }
};
