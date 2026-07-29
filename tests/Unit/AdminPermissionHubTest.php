<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Services\Admin\AdminPermissionService;
use App\Support\AdminPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPermissionHubTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $id = uniqid();

        return Admin::query()->create([
            'name' => 'Test Admin',
            'username' => 'test_admin_'.$id,
            'email' => 'test_'.$id.'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    public function test_sms_settings_panel_grants_settings_tab_and_panel_feature(): void
    {
        $admin = $this->makeAdmin();
        AdminPermissionGrant::query()->create([
            'admin_id' => $admin->id,
            'permission_key' => 'sms.settings.panel',
        ]);

        $service = app(AdminPermissionService::class);
        $tabs = $service->allowedUiGroup($admin, 'sms', 'tabs');
        $features = $service->uiFeatureMap($admin, 'sms');

        $this->assertArrayHasKey('settings', $tabs);
        $this->assertArrayNotHasKey('credit', $tabs);
        $this->assertArrayNotHasKey('free_send', $tabs);
        $this->assertArrayNotHasKey('reports', $tabs);
        $this->assertTrue($features['settings.panel'] ?? false);
        $this->assertFalse($features['credit.view'] ?? true);
        $this->assertFalse($features['settings.scenarios'] ?? true);
    }

    public function test_sms_credit_and_free_send_are_separate_grantable_tabs(): void
    {
        $admin = $this->makeAdmin();
        AdminPermissionGrant::query()->create([
            'admin_id' => $admin->id,
            'permission_key' => 'sms.credit',
        ]);
        AdminPermissionGrant::query()->create([
            'admin_id' => $admin->id,
            'permission_key' => 'sms.free_send',
        ]);

        $service = app(AdminPermissionService::class);
        $tabs = $service->allowedUiGroup($admin, 'sms', 'tabs');
        $features = $service->uiFeatureMap($admin, 'sms');

        $this->assertArrayHasKey('credit', $tabs);
        $this->assertArrayHasKey('free_send', $tabs);
        $this->assertArrayNotHasKey('settings', $tabs);
        $this->assertTrue($features['credit.view'] ?? false);
        $this->assertTrue($features['free_send.send'] ?? false);
        $this->assertFalse($features['settings.panel'] ?? true);

        $registry = app(AdminPermissionRegistry::class);
        $this->assertContains('sms.credit', $registry->leafKeysUnder('sms'));
        $this->assertContains('sms.free_send', $registry->leafKeysUnder('sms'));
        $this->assertContains('sms.credit', $registry->permissionsForRoute('admin.sms.panel-credit'));
        $this->assertContains('sms.free_send', $registry->permissionsForRoute('admin.sms.free-send'));
    }

    public function test_sms_hub_page_resolves_settings_tab_when_session_has_reports(): void
    {
        $admin = $this->makeAdmin();
        AdminPermissionGrant::query()->create([
            'admin_id' => $admin->id,
            'permission_key' => 'sms.settings.panel',
        ]);

        $hub = app(AdminPermissionService::class)->resolveHubPage($admin, 'sms', 'reports', 'reports');

        $this->assertSame('settings', $hub['active_tab']);
        $this->assertArrayHasKey('settings', $hub['tabs']);
    }

    public function test_sms_index_route_accessible_with_settings_panel_only(): void
    {
        $admin = $this->makeAdmin();
        AdminPermissionGrant::query()->create([
            'admin_id' => $admin->id,
            'permission_key' => 'sms.settings.panel',
        ]);

        $service = app(AdminPermissionService::class);

        $this->assertTrue($service->canAccessRoute($admin, 'admin.sms.index'));
    }

    public function test_route_map_includes_hub_for_section_permissions(): void
    {
        $this->assertContains(
            'sms.settings.panel',
            app(AdminPermissionRegistry::class)->permissionsForRoute('admin.sms.index'),
        );
    }

    public function test_permission_tree_includes_backup_under_app_settings(): void
    {
        $registry = app(AdminPermissionRegistry::class);
        $appSettings = collect($registry->tree())->firstWhere('key', 'app_settings');
        $this->assertIsArray($appSettings);

        $backupGroup = collect($appSettings['children'] ?? [])->firstWhere('key', 'backup');
        $this->assertIsArray($backupGroup);
        $this->assertSame('پشتیبان‌گیری و بازیابی', $backupGroup['label'] ?? null);

        $this->assertContains('backup.view', $registry->leafKeysUnder('backup'));
        $this->assertContains('backup.restore', $registry->permissionsForRoute('admin.backups.restore'));
    }
}
