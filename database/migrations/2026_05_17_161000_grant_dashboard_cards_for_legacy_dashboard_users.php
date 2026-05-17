<?php

declare(strict_types=1);

use App\Support\AdminDashboardWidgetRegistry;
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

        $widgetRegistry = app(AdminDashboardWidgetRegistry::class);
        $cardKeys = array_map(
            static fn (string $widgetId): string => $widgetRegistry->permissionKeyForWidget($widgetId),
            array_keys(AdminDashboardWidgetRegistry::WIDGETS),
        );

        $adminIdsWithView = DB::table('admin_permission_grants')
            ->where('permission_key', 'dashboard.view')
            ->pluck('admin_id')
            ->unique()
            ->all();

        if ($adminIdsWithView === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($adminIdsWithView as $adminId) {
            $hasAnyCard = DB::table('admin_permission_grants')
                ->where('admin_id', $adminId)
                ->where('permission_key', 'like', 'dashboard.card.%')
                ->exists();

            if ($hasAnyCard) {
                continue;
            }

            foreach ($cardKeys as $key) {
                $rows[] = [
                    'admin_id' => $adminId,
                    'permission_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('admin_permission_grants')->insert($rows);
        }
    }

    public function down(): void
    {
        // غیرقابل برگشت بدون از دست دادن تنظیمات دستی
    }
};
