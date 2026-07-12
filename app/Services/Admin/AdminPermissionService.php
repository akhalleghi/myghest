<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Support\AdminDashboardWidgetRegistry;
use App\Support\AdminPermissionRegistry;
use App\Support\AdminSectionHubRegistry;
use Illuminate\Support\Facades\DB;

final class AdminPermissionService
{
    public function __construct(
        private readonly AdminPermissionRegistry $registry,
        private readonly AdminDashboardWidgetRegistry $dashboardWidgets,
        private readonly AdminSectionHubRegistry $sectionHubs,
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

        foreach ($this->registry->permissionsForRoute($routeName) as $permissionKey) {
            if ($this->hasPermission($admin, $permissionKey)) {
                return true;
            }
        }

        foreach ($this->sectionHubs->hubs() as $hub) {
            $hubRoute = $hub['route'] ?? null;
            $prefix = $hub['permission_prefix'] ?? null;
            if ($hubRoute !== $routeName || ! is_string($prefix) || $prefix === '') {
                continue;
            }
            if ($this->hasPermissionWithPrefix($admin, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionForRule(Admin $admin, array $rule): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        return $this->matchesUiAccessRule($admin, $rule);
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
            $route = (string) ($item['route'] ?? '');
            if ($route !== '' && $this->canAccessRoute($admin, $route)) {
                $items[] = $item;

                continue;
            }

            $perm = (string) ($item['permission'] ?? '');
            if ($perm !== '' && $this->hasPermissionInNavSection($admin, $perm)) {
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

    public function hasPermissionWithPrefix(Admin $admin, string $prefix): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        $normalized = rtrim($prefix, '.').'.';

        foreach ($this->permissionKeysFor($admin) as $key) {
            if ($key === rtrim($prefix, '.') || str_starts_with($key, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * اگر کاربر هر زیرمجموعه‌ای از بخش منو را داشته باشد، آیتم منو نمایش داده می‌شود.
     */
    public function hasPermissionInNavSection(Admin $admin, string $navPermission): bool
    {
        if ($this->hasPermission($admin, $navPermission)) {
            return true;
        }

        if (! str_contains($navPermission, '.')) {
            return false;
        }

        $prefix = explode('.', $navPermission, 2)[0].'.';

        return $this->hasPermissionWithPrefix($admin, $prefix);
    }

    /**
     * @return array<string, string> id => label
     */
    public function allowedUiGroup(Admin $admin, string $section, string $group): array
    {
        if ($admin->isSuperAdmin()) {
            return $this->uiGroupLabels($section, $group);
        }

        $definitions = $this->hubGroupDefinitions($section, $group);
        $allowed = [];

        foreach ($definitions as $itemId => $rule) {
            if (! is_array($rule) || ! $this->matchesUiAccessRule($admin, $rule)) {
                continue;
            }

            $allowed[(string) $itemId] = (string) ($rule['label'] ?? $itemId);
        }

        return $allowed;
    }

    /**
     * @return array{tabs: array<string, string>, features: array<string, bool>, active_tab: string}
     */
    public function resolveHubPage(Admin $admin, string $hubKey, ?string $requestedTab, ?string $sessionTab = null): array
    {
        $tabs = $this->allowedUiGroup($admin, $hubKey, 'tabs');
        if ($tabs === []) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        $preferred = $requestedTab ?? $sessionTab;
        if ($preferred !== null && $preferred !== '' && ! isset($tabs[$preferred])) {
            $preferred = null;
        }

        $activeTab = $this->resolveUiTab($admin, $hubKey, $preferred, $tabs);
        $features = $this->uiFeatureMap($admin, $hubKey);

        return [
            'tabs' => $tabs,
            'features' => $features,
            'active_tab' => $activeTab,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function allowedAppSettingsPanels(Admin $admin): array
    {
        return $this->allowedUiGroup($admin, 'app_settings', 'panels');
    }

    /**
     * @param  array<string, string>|null  $allowedItems
     */
    public function resolveUiTab(Admin $admin, string $section, ?string $requested, ?array $allowedItems = null): string
    {
        $allowed = $allowedItems ?? $this->allowedUiGroup($admin, $section, 'tabs');

        if ($allowed === []) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        if ($requested !== null && $requested !== '' && isset($allowed[$requested])) {
            return $requested;
        }

        return (string) array_key_first($allowed);
    }

    /**
     * @return array<string, bool>
     */
    public function uiFeatureMap(Admin $admin, string $section): array
    {
        $definitions = $this->hubGroupDefinitions($section, 'features');
        $map = [];

        foreach (array_keys($definitions) as $featureId) {
            $map[(string) $featureId] = $this->canAccessUiFeature($admin, $section, (string) $featureId);
        }

        return $map;
    }

    public function canAccessUiFeature(Admin $admin, string $section, string $featureId): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        $definitions = $this->hubGroupDefinitions($section, 'features');
        $rule = $definitions[$featureId] ?? null;

        return is_array($rule) && $this->matchesUiAccessRule($admin, $rule);
    }

    public function canAccessUiCard(Admin $admin, string $section, string $cardId): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        $definitions = $this->hubGroupDefinitions($section, 'cards');
        $rule = $definitions[$cardId] ?? null;

        return is_array($rule) && $this->matchesUiAccessRule($admin, $rule);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matchesUiAccessRule(Admin $admin, array $rule): bool
    {
        $permissions = $rule['permissions'] ?? [];
        if (is_array($permissions)) {
            foreach ($permissions as $permissionKey) {
                if (is_string($permissionKey) && $permissionKey !== '' && $this->hasPermission($admin, $permissionKey)) {
                    return true;
                }
            }
        }

        $prefix = $rule['any_prefix'] ?? null;
        if (is_string($prefix) && $prefix !== '' && $this->hasPermissionWithPrefix($admin, $prefix)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function hubGroupDefinitions(string $hubKey, string $group): array
    {
        $hub = $this->sectionHubs->hubs()[$hubKey] ?? [];

        $definitions = match ($group) {
            'tabs' => $this->sectionHubs->tabDefinitions($hubKey),
            'features' => $this->sectionHubs->featureDefinitions($hubKey),
            'cards' => $this->sectionHubs->cardDefinitions($hubKey),
            'panels' => is_array($hub['panels'] ?? null) ? $hub['panels'] : [],
            default => [],
        };

        return $definitions;
    }

    /**
     * @return array<string, string>
     */
    private function uiGroupLabels(string $section, string $group): array
    {
        $definitions = $this->hubGroupDefinitions($section, $group);
        $labels = [];

        foreach ($definitions as $itemId => $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $labels[(string) $itemId] = (string) ($rule['label'] ?? $itemId);
        }

        return $labels;
    }

    public function canOpenAppSettings(Admin $admin): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        foreach (['app_settings.base', 'app_settings.ui', 'app_settings.financial', 'app_settings.security', 'app_settings.notifications', 'app_settings.loans', 'app_settings.reports', 'app_settings.print'] as $key) {
            if ($this->hasPermission($admin, $key)) {
                return true;
            }
        }

        return false;
    }

    public function canOpenDatabaseBackups(Admin $admin): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermissionWithPrefix($admin, 'backup.');
    }

    public function canCreateDatabaseBackup(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $this->hasPermission($admin, 'backup.create');
    }

    public function canDownloadDatabaseBackup(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $this->hasPermission($admin, 'backup.download');
    }

    public function canDeleteDatabaseBackup(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $this->hasPermission($admin, 'backup.delete');
    }

    public function canRestoreDatabaseBackup(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $this->hasPermission($admin, 'backup.restore');
    }
}
