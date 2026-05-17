<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_permission_grants')) {
            return;
        }

        DB::table('admin_permission_grants')
            ->where('permission_key', 'dashboard')
            ->update(['permission_key' => 'dashboard.view']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_permission_grants')) {
            return;
        }

        DB::table('admin_permission_grants')
            ->where('permission_key', 'dashboard.view')
            ->update(['permission_key' => 'dashboard']);
    }
};
