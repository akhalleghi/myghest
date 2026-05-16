<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        DB::table('support_tickets')
            ->where('status', 'open')
            ->update(['status' => 'pending_review']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        DB::table('support_tickets')
            ->where('status', 'pending_review')
            ->whereNull('created_by_customer_id')
            ->update(['status' => 'open']);
    }
};
