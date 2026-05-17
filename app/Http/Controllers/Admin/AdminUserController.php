<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\Admin;
use App\Services\Admin\AdminPermissionService;
use App\Support\AdminPermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminPermissionService $permissions,
        private readonly AdminPermissionRegistry $permissionRegistry,
    ) {}

    public function index(): View
    {
        /** @var Admin $actor */
        $actor = Auth::guard('admin')->user();

        $admins = Admin::query()->with('permissionGrants')->orderByDesc('id')->get();
        $currentAdminId = (int) $actor->id;

        $adminEditMap = $admins->mapWithKeys(
            fn (Admin $admin): array => [$admin->id => $this->adminEditPayload($admin)]
        )->all();

        return view('admin.users.index', [
            'admins' => $admins,
            'adminEditMap' => $adminEditMap,
            'currentAdminId' => $currentAdminId,
            'permissionTree' => $this->permissionRegistry->tree(),
            'canAssignPermissions' => $this->permissions->canAssignPermissions($actor),
            'assignablePermissionKeys' => $this->permissions->assignablePermissionKeysFor($actor),
            'restrictAssignable' => ! $actor->isSuperAdmin(),
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        /** @var Admin $actor */
        $actor = Auth::guard('admin')->user();
        $validated = $request->validated();
        $username = (string) $validated['username'];

        $newAdmin = Admin::query()->create([
            'name' => $this->composeDisplayName($validated['first_name'], $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $username,
            'email' => $username.'@admin.local',
            'mobile' => $validated['mobile'],
            'password' => $validated['password'],
            'is_active' => $validated['is_active'] ?? false,
            'is_super_admin' => false,
        ]);

        $permissionKeys = is_array($validated['permission_keys'] ?? null) ? $validated['permission_keys'] : [];
        $this->permissions->syncPermissions($newAdmin, $permissionKeys, $actor);

        return redirect()
            ->route('admin.users.index')
            ->with('flash_success', 'کاربر ادمین با موفقیت ثبت شد.');
    }

    public function update(Admin $admin, UpdateAdminUserRequest $request): RedirectResponse
    {
        /** @var Admin $actor */
        $actor = Auth::guard('admin')->user();
        $validated = $request->validated();
        $currentId = (int) $actor->id;

        if ($admin->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['update' => 'ویرایش مدیر ارشد سیستم مجاز نیست.'])
                ->withInput();
        }

        if ($admin->id === $currentId && ! ($validated['is_active'] ?? false)) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['is_active' => 'نمی‌توانید حساب خود را غیرفعال کنید.'])
                ->withInput()
                ->with('au_open_modal', true);
        }

        $admin->name = $this->composeDisplayName($validated['first_name'], $validated['last_name']);
        $admin->first_name = $validated['first_name'];
        $admin->last_name = $validated['last_name'];
        $admin->username = (string) $validated['username'];
        $admin->mobile = $validated['mobile'];
        $admin->is_active = $validated['is_active'] ?? false;

        if (! empty($validated['password'])) {
            $admin->password = $validated['password'];
        }

        $admin->save();

        $permissionKeys = is_array($validated['permission_keys'] ?? null) ? $validated['permission_keys'] : [];
        $this->permissions->syncPermissions($admin, $permissionKeys, $actor);

        return redirect()
            ->route('admin.users.index')
            ->with('flash_success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        /** @var Admin $actor */
        $actor = Auth::guard('admin')->user();

        if ($admin->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['delete' => 'حذف مدیر ارشد سیستم مجاز نیست.']);
        }

        if ($admin->id === (int) Auth::guard('admin')->id()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['delete' => 'حذف حساب کاربری خودتان مجاز نیست.']);
        }

        if ($admin->isSuperAdmin()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['delete' => 'حذف مدیر ارشد سیستم مجاز نیست.']);
        }

        if (Admin::query()->count() <= 1) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['delete' => 'حداقل یک کاربر ادمین باید در سامانه باقی بماند.']);
        }

        $admin->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('flash_success', 'کاربر حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function adminEditPayload(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'first_name' => $admin->first_name ?? '',
            'last_name' => $admin->last_name ?? '',
            'mobile' => $admin->mobile ?? '',
            'is_active' => (bool) $admin->is_active,
            'is_super_admin' => (bool) $admin->is_super_admin,
            'permission_keys' => $this->permissions->permissionKeysFor($admin),
        ];
    }

    private function composeDisplayName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }
}
