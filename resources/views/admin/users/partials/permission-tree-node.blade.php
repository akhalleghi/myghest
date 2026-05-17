@php
    $key = (string) ($node['key'] ?? '');
    $label = (string) ($node['label'] ?? $key);
    $children = $node['children'] ?? [];
    $hasChildren = is_array($children) && $children !== [];
    $routes = $node['routes'] ?? [];
    $hasRoutes = is_array($routes) && $routes !== [];
    $isMeta = in_array($key, ['users.permissions', 'app_settings.notifications'], true)
        || str_starts_with($key, 'dashboard.card.');
    $includeInForm = ! $hasChildren && ($hasRoutes || $isMeta);
    $checked = in_array($key, $oldKeys, true);
    $canAssign = empty($restrictAssignable) || isset($assignableSet[$key]);
@endphp
<li class="au-perm-node" role="treeitem" data-perm-key="{{ e($key) }}" data-perm-label="{{ e(mb_strtolower($label, 'UTF-8')) }}">
    <div class="au-perm-node-row" style="--au-depth: {{ (int) $depth }}">
        @if ($hasChildren)
            <button type="button" class="au-perm-toggle" aria-expanded="true" aria-label="باز و بسته کردن">
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
        @else
            <span class="au-perm-toggle au-perm-toggle--spacer" aria-hidden="true"></span>
        @endif
        <label class="au-perm-check @if (! $canAssign) is-disabled @endif">
            <input
                type="checkbox"
                @if ($includeInForm) name="permission_keys[]" @endif
                value="{{ e($key) }}"
                data-perm-checkbox
                data-perm-submit="{{ $includeInForm ? '1' : '0' }}"
                @checked($checked)
                @if (! $canAssign) disabled @endif
            >
            <span class="au-perm-label">{{ $label }}</span>
        </label>
    </div>
    @if ($hasChildren)
        <ul class="au-perm-children" role="group">
            @foreach ($children as $child)
                @if (is_array($child))
                    @include('admin.users.partials.permission-tree-node', [
                        'node' => $child,
                        'assignableSet' => $assignableSet,
                        'oldKeys' => $oldKeys,
                        'depth' => $depth + 1,
                        'restrictAssignable' => $restrictAssignable,
                    ])
                @endif
            @endforeach
        </ul>
    @endif
</li>
