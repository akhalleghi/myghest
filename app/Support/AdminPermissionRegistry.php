<?php

declare(strict_types=1);

namespace App\Support;

final class AdminPermissionRegistry
{
    /** @var array<string, list<string>>|null */
    private static ?array $routeToPermissions = null;

    /** @var array<string, true>|null */
    private static ?array $allKeys = null;

    /** @var array<string, list<string>>|null */
    private static ?array $descendantLeaves = null;

    /**
     * @return list<array{key: string, label: string, routes?: list<string>, children?: list<mixed>}>
     */
    public function tree(): array
    {
        /** @var list<array{key: string, label: string, routes?: list<string>, children?: list<mixed>}> $tree */
        $tree = config('admin_permissions.tree', []);

        return array_map(function (array $node): array {
            if (($node['key'] ?? '') !== 'dashboard') {
                return $node;
            }

            $widgetRegistry = app(AdminDashboardWidgetRegistry::class);

            return [
                'key' => 'dashboard',
                'label' => (string) ($node['label'] ?? 'داشبورد'),
                'children' => [
                    [
                        'key' => 'dashboard.view',
                        'label' => 'دسترسی به صفحه داشبورد',
                        'routes' => ['admin.dashboard'],
                    ],
                    [
                        'key' => 'dashboard.cards',
                        'label' => 'کارت‌های اطلاعات داشبورد',
                        'children' => $widgetRegistry->permissionTreeChildren(),
                    ],
                ],
            ];
        }, $tree);
    }

    /**
     * @return list<array{label: string, href: string, icon: string, route: string, permission: string}>
     */
    public function navigationItems(): array
    {
        $raw = config('admin_permissions.nav', []);
        $items = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $routeName = (string) ($row['route'] ?? '');
            if ($routeName === '') {
                continue;
            }
            $items[] = [
                'label' => (string) ($row['label'] ?? ''),
                'href' => route($routeName),
                'icon' => (string) ($row['icon'] ?? 'fa-circle'),
                'route' => $routeName,
                'permission' => (string) ($row['permission'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function exemptRouteNames(): array
    {
        /** @var list<string> $list */
        $list = config('admin_permissions.exempt_route_names', []);

        return $list;
    }

    /**
     * @return array<string, list<string>>
     */
    public function routeToPermissionMap(): array
    {
        if (self::$routeToPermissions !== null) {
            return self::$routeToPermissions;
        }

        $map = [];
        $this->walkTree($this->tree(), static function (array $node) use (&$map): void {
            $key = (string) ($node['key'] ?? '');
            if ($key === '') {
                return;
            }
            $routes = $node['routes'] ?? [];
            if (! is_array($routes)) {
                return;
            }
            foreach ($routes as $routeName) {
                if (! is_string($routeName) || $routeName === '') {
                    continue;
                }
                if (! isset($map[$routeName])) {
                    $map[$routeName] = [];
                }
                if (! in_array($key, $map[$routeName], true)) {
                    $map[$routeName][] = $key;
                }
            }
        });

        $map = app(AdminSectionHubRegistry::class)->augmentRouteMapWithHubs($map, $this);

        self::$routeToPermissions = $map;

        return $map;
    }

    /**
     * @return list<string>
     */
    public function permissionsForRoute(?string $routeName): array
    {
        if ($routeName === null || $routeName === '') {
            return [];
        }

        return $this->routeToPermissionMap()[$routeName] ?? [];
    }

    public function permissionForRoute(?string $routeName): ?string
    {
        $keys = $this->permissionsForRoute($routeName);

        return $keys[0] ?? null;
    }

    /**
     * @return array<string, true>
     */
    public function allPermissionKeys(): array
    {
        if (self::$allKeys !== null) {
            return self::$allKeys;
        }

        $keys = [];
        $this->walkTree($this->tree(), static function (array $node) use (&$keys): void {
            $key = (string) ($node['key'] ?? '');
            if ($key !== '') {
                $keys[$key] = true;
            }
        });

        self::$allKeys = $keys;

        return $keys;
    }

    public function isValidPermissionKey(string $key): bool
    {
        return isset($this->allPermissionKeys()[$key]);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function sanitizePermissionKeys(array $keys): array
    {
        $valid = $this->allPermissionKeys();
        $out = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset($valid[$key])) {
                $out[$key] = $key;
            }
        }

        return array_values($out);
    }

    /**
     * تمام کلیدهای برگ زیر یک گره (برای انتخاب والد در UI).
     *
     * @return list<string>
     */
    public function leafKeysUnder(string $nodeKey): array
    {
        $cache = $this->descendantLeavesCache();

        return $cache[$nodeKey] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function descendantLeavesCache(): array
    {
        if (self::$descendantLeaves !== null) {
            return self::$descendantLeaves;
        }

        $cache = [];
        $this->walkTree($this->tree(), function (array $node) use (&$cache): void {
            $key = (string) ($node['key'] ?? '');
            if ($key === '') {
                return;
            }
            $cache[$key] = $this->collectLeaves($node);
        });

        self::$descendantLeaves = $cache;

        return $cache;
    }

    /**
     * @param  array{key?: string, label?: string, routes?: list<string>, children?: list<mixed>}  $node
     * @return list<string>
     */
    private function collectLeaves(array $node): array
    {
        $children = $node['children'] ?? [];
        if (is_array($children) && $children !== []) {
            $leaves = [];
            foreach ($children as $child) {
                if (is_array($child)) {
                    $leaves = array_merge($leaves, $this->collectLeaves($child));
                }
            }

            return array_values(array_unique($leaves));
        }

        $key = (string) ($node['key'] ?? '');
        $routes = $node['routes'] ?? [];
        $hasRoutes = is_array($routes) && $routes !== [];

        $isMetaLeaf = $key === 'users.permissions'
            || $key === 'app_settings.notifications'
            || str_starts_with($key, 'dashboard.card.');

        return $key !== '' && ($hasRoutes || $isMetaLeaf)
            ? [$key]
            : ($key !== '' ? [$key] : []);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  callable(array<string, mixed>): void  $visitor
     */
    private function walkTree(array $nodes, callable $visitor): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $visitor($node);
            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->walkTree($children, $visitor);
            }
        }
    }
}
