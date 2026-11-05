<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Also tolerate a partially applied schema restored from a backup.
        foreach (['daily_late_coefficient', 'daily_early_coefficient'] as $column) {
            if (! Schema::hasColumn('customer_loan_files', $column)) {
                Schema::table('customer_loan_files', function (Blueprint $table) use ($column): void {
                    $table->decimal($column, 12, 6)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('customer_loan_files', function (Blueprint $table): void {
            $table->dropColumn(['daily_late_coefficient', 'daily_early_coefficient']);
        });
    }
};
