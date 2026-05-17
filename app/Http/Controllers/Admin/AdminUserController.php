<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\Admin;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function index(): View
    {
        $admins = Admin::query()->orderByDesc('id')->get();
        $currentAdminId = (int) Auth::guard('admin')->id();

        $adminEditMap = $admins->mapWithKeys(
            fn (Admin $admin): array => [$admin->id => $this->adminEditPayload($admin)]
        )->all();

        return view('admin.users.index', [
            'admins' => $admins,
            'adminEditMap' => $adminEditMap,
            'currentAdminId' => $currentAdminId,
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $username = (string) $validated['username'];

        Admin::query()->create([
            'name' => $this->composeDisplayName($validated['first_name'], $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $username,
            'email' => $username.'@admin.local',
            'mobile' => $validated['mobile'],
            'password' => $validated['password'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('flash_success', 'کاربر ادمین با موفقیت ثبت شد.');
    }

    public function update(Admin $admin, UpdateAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currentId = (int) Auth::guard('admin')->id();

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

        return redirect()
            ->route('admin.users.index')
            ->with('flash_success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        if ($admin->id === (int) Auth::guard('admin')->id()) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['delete' => 'حذف حساب کاربری خودتان مجاز نیست.']);
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
        ];
    }

    private function composeDisplayName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }
}
