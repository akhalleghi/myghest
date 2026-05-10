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
            $table->timestamp('revoked_at')->nullable()->after('settled_at');
            $table->foreignId('revoked_by_admin_id')->nullable()->after('revoked_at')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revoked_by_admin_id');
            $table->dropColumn('revoked_at');
        });
    }
};
