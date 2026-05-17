<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Support\AdminDashboardWidgetRegistry;
use App\Support\AdminPermissionRegistry;
use Illuminate\Support\Facades\DB;

final class AdminPermissionService
{
    public function __construct(
        private readonly AdminPermissionRegistry $registry,
        private readonly AdminDashboardWidgetRegistry $dashboardWidgets,
    ) {}

    public function canAccessRoute(Admin $admin, ?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if ($admin->isSuperAdmin()) {
            return true;
        }

        if (in_array($routeName, $this->registry->exemptRouteNames(), true)) {
            return true;
        }

        $required = $this->registry->permissionForRoute($routeName);
        if ($required === null) {
            return false;
        }

        return $this->hasPermission($admin, $required);
    }

    public function hasPermission(Admin $admin, string $permissionKey): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        return in_array($permissionKey, $this->permissionKeysFor($admin), true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeysFor(Admin $admin): array
    {
        if ($admin->relationLoaded('permissionGrants')) {
            return $admin->permissionGrants
                ->pluck('permission_key')
                ->map(static fn ($k): string => (string) $k)
                ->values()
                ->all();
        }

        return AdminPermissionGrant::query()
            ->where('admin_id', $admin->id)
            ->pluck('permission_key')
            ->map(static fn ($k): string => (string) $k)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function assignablePermissionKeysFor(Admin $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return array_keys($this->registry->allPermissionKeys());
        }

        return $this->permissionKeysFor($actor);
    }

    public function canAssignPermissions(Admin $actor): bool
    {
        return $actor->isSuperAdmin() || $this->hasPermission($actor, 'users.permissions');
    }

    /**
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public function filterAssignableKeys(Admin $actor, array $requestedKeys): array
    {
        $sanitized = $this->registry->sanitizePermissionKeys($requestedKeys);
        if ($actor->isSuperAdmin()) {
            return $sanitized;
        }

        if (! $this->canAssignPermissions($actor)) {
            return [];
        }

        $allowed = array_fill_keys($this->assignablePermissionKeysFor($actor), true);
        $out = [];
        foreach ($sanitized as $key) {
            if (isset($allowed[$key])) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $keys
     */
    public function syncPermissions(Admin $target, array $keys, Admin $actor): void
    {
        if ($target->id === $actor->id && ! $actor->isSuperAdmin()) {
            return;
        }

        if (! $actor->isSuperAdmin() && ! $this->canAssignPermissions($actor)) {
            return;
        }

        $keys = $this->filterAssignableKeys($actor, $keys);

        if (! $actor->isSuperAdmin() && ! $this->hasPermission($actor, 'users.permissions')) {
            $keys = array_values(array_filter(
                $keys,
                static fn (string $k): bool => $k !== 'users.permissions'
            ));
        }

        DB::transaction(function () use ($target, $keys): void {
            AdminPermissionGrant::query()->where('admin_id', $target->id)->delete();
            foreach ($keys as $key) {
                AdminPermissionGrant::query()->create([
                    'admin_id' => $target->id,
                    'permission_key' => $key,
                ]);
            }
        });

        $target->unsetRelation('permissionGrants');
    }

    /**
     * @return list<array{label: string, href: string, icon: string, route: string, permission: string}>
     */
    public function visibleNavigationItems(Admin $admin): array
    {
        $items = [];
        foreach ($this->registry->navigationItems() as $item) {
            $perm = (string) ($item['permission'] ?? '');
            if ($perm === '' || $this->hasPermission($admin, $perm)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function canViewDashboardWidget(Admin $admin, string $widgetId): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        if (! $this->hasPermission($admin, 'dashboard.view')) {
            return false;
        }

        if (! $this->dashboardWidgets->isKnownWidget($widgetId)) {
            return false;
        }

        return $this->hasPermission($admin, $this->dashboardWidgets->permissionKeyForWidget($widgetId));
    }

    /**
     * @return list<string>
     */
    public function allowedDashboardWidgetIds(Admin $admin): array
    {
        if ($admin->isSuperAdmin()) {
            return array_keys(AdminDashboardWidgetRegistry::WIDGETS);
        }

        if (! $this->hasPermission($admin, 'dashboard.view')) {
            return [];
        }

        $allowed = [];
        foreach (AdminDashboardWidgetRegistry::WIDGETS as $widgetId => $_label) {
            if ($this->hasPermission($admin, $this->dashboardWidgets->permissionKeyForWidget($widgetId))) {
                $allowed[] = $widgetId;
            }
        }

        return $allowed;
    }

    public function canOpenAppSettings(Admin $admin): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        foreach (['app_settings.base', 'app_settings.ui', 'app_settings.financial', 'app_settings.security', 'app_settings.notifications'] as $key) {
            if ($this->hasPermission($admin, $key)) {
                return true;
            }
        }

        return false;
    }
}
