@php
    /** @var list<array<string, mixed>> $permissionTree */
    /** @var list<string> $assignablePermissionKeys */
    $assignableSet = array_fill_keys($assignablePermissionKeys ?? [], true);
    $oldKeys = old('permission_keys');
    $oldKeys = is_array($oldKeys) ? $oldKeys : [];
@endphp
<div
    class="au-perm"
    id="au-perm-root"
    data-assignable='@json(array_values($assignablePermissionKeys ?? []))'
    @if (empty($canAssignPermissions)) data-readonly="1" @endif
>
    @if (empty($canAssignPermissions))
        <p class="au-perm-note au-perm-note--warn">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            شما مجوز «مدیریت دسترسی‌ها» را ندارید؛ فقط اطلاعات کاربری قابل ویرایش است.
        </p>
    @else
        <p class="au-perm-note">
            دسترسی‌های مورد نیاز را انتخاب کنید. انتخاب یک گروه، زیرمجموعه‌های آن را نیز فعال می‌کند.
        </p>
    @endif

    <div class="au-perm-toolbar">
        <input
            type="search"
            class="au-perm-search"
            id="au-perm-search"
            placeholder="جستجو در دسترسی‌ها…"
            autocomplete="off"
            @if (empty($canAssignPermissions)) disabled @endif
        >
        <div class="au-perm-toolbar-actions">
            <button type="button" class="au-perm-btn" id="au-perm-expand-all" @if (empty($canAssignPermissions)) disabled @endif>باز کردن همه</button>
            <button type="button" class="au-perm-btn" id="au-perm-collapse-all" @if (empty($canAssignPermissions)) disabled @endif>بستن همه</button>
            <button type="button" class="au-perm-btn" id="au-perm-select-all" @if (empty($canAssignPermissions)) disabled @endif>انتخاب همه</button>
            <button type="button" class="au-perm-btn" id="au-perm-clear-all" @if (empty($canAssignPermissions)) disabled @endif>پاک کردن</button>
        </div>
    </div>

    <div class="au-perm-tree-wrap" id="au-perm-tree">
        <ul class="au-perm-tree" role="tree">
            @foreach ($permissionTree as $node)
                @include('admin.users.partials.permission-tree-node', [
                    'node' => $node,
                    'assignableSet' => $assignableSet,
                    'oldKeys' => $oldKeys,
                    'depth' => 0,
                    'restrictAssignable' => $restrictAssignable ?? false,
                ])
            @endforeach
        </ul>
    </div>
</div>
