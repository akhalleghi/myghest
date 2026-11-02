<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Only initialize the new rate fields. Keep money, schedules, receipts and custom rates intact.
            foreach (['daily_late_coefficient', 'daily_early_coefficient'] as $column) {
                DB::table('customer_loan_files')->whereNull($column)->update([
                    $column => DB::raw("COALESCE((SELECT {$column} FROM loan_types WHERE loan_types.id = customer_loan_files.loan_type_id), 0)"),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Restoring null would discard the captured contract rates.
    }
};
