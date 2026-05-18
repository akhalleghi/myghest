<?php

declare(strict_types=1);

namespace App\Support;

/**
 * صفحات چندبخشی (hub) که زیرمجوزهای درخت دسترسی، تب و پنل UI را تعیین می‌کنند.
 */
final class AdminSectionHubRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function hubs(): array
    {
        /** @var array<string, array<string, mixed>> $hubs */
        $hubs = config('admin_permissions.section_hubs', []);

        return $hubs;
    }

    public function hubRoute(string $hubKey): ?string
    {
        $route = $this->hubs()[$hubKey]['route'] ?? null;

        return is_string($route) && $route !== '' ? $route : null;
    }

    public function permissionPrefix(string $hubKey): ?string
    {
        $prefix = $this->hubs()[$hubKey]['permission_prefix'] ?? null;

        return is_string($prefix) && $prefix !== '' ? $prefix : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function tabDefinitions(string $hubKey): array
    {
        $tabs = $this->hubs()[$hubKey]['tabs'] ?? [];

        return is_array($tabs) ? $tabs : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function featureDefinitions(string $hubKey): array
    {
        $features = $this->hubs()[$hubKey]['features'] ?? [];

        return is_array($features) ? $features : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function cardDefinitions(string $hubKey): array
    {
        $cards = $this->hubs()[$hubKey]['cards'] ?? [];

        return is_array($cards) ? $cards : [];
    }

    /**
     * @return list<string>
     */
    public function hubRouteNames(): array
    {
        $routes = [];
        foreach ($this->hubs() as $hub) {
            $route = $hub['route'] ?? null;
            if (is_string($route) && $route !== '') {
                $routes[] = $route;
            }
        }

        return array_values(array_unique($routes));
    }

    /**
     * مسیر hub را به همهٔ برگ‌های همان بخش (با پیشوند) اضافه می‌کند تا ورود به صفحهٔ مرکزی ممکن شود.
     *
     * @param  array<string, list<string>>  $routeMap
     * @return array<string, list<string>>
     */
    public function augmentRouteMapWithHubs(array $routeMap, AdminPermissionRegistry $registry): array
    {
        foreach ($this->hubs() as $hubKey => $hub) {
            $hubRoute = $hub['route'] ?? null;
            $prefix = $hub['permission_prefix'] ?? null;
            if (! is_string($hubRoute) || $hubRoute === '' || ! is_string($prefix) || $prefix === '') {
                continue;
            }

            foreach (array_keys($registry->allPermissionKeys()) as $permissionKey) {
                if (! str_starts_with($permissionKey, $prefix)) {
                    continue;
                }

                if (! isset($routeMap[$hubRoute])) {
                    $routeMap[$hubRoute] = [];
                }
                if (! in_array($permissionKey, $routeMap[$hubRoute], true)) {
                    $routeMap[$hubRoute][] = $permissionKey;
                }
            }
        }

        return $routeMap;
    }
}
