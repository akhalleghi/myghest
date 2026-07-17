<?php

declare(strict_types=1);

use App\Support\AdminDashboardWidgetRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * اعطای دسترسی کارت جدید «کیف پول مشتریان» به ادمین‌هایی که از قبل کارت‌های داشبورد دارند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_permission_grants')) {
            return;
        }

        $widgetRegistry = app(AdminDashboardWidgetRegistry::class);
        $newKey = $widgetRegistry->permissionKeyForWidget('summary-customer-wallets');

        $adminIds = DB::table('admin_permission_grants')
            ->where('permission_key', 'like', 'dashboard.card.%')
            ->pluck('admin_id')
            ->unique()
            ->all();

        if ($adminIds === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($adminIds as $adminId) {
            $exists = DB::table('admin_permission_grants')
                ->where('admin_id', $adminId)
                ->where('permission_key', $newKey)
                ->exists();

            if ($exists) {
                continue;
            }

            $rows[] = [
                'admin_id' => $adminId,
                'permission_key' => $newKey,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('admin_permission_grants')->insert($rows);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_permission_grants')) {
            return;
        }

        $widgetRegistry = app(AdminDashboardWidgetRegistry::class);
        $newKey = $widgetRegistry->permissionKeyForWidget('summary-customer-wallets');

        DB::table('admin_permission_grants')
            ->where('permission_key', $newKey)
            ->delete();
    }
};
