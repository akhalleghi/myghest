<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Admin\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminHasPermission
{
    public function __construct(
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($this->permissions->canAccessRoute($admin, $routeName)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'شما به این بخش دسترسی ندارید.',
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, 'شما به این بخش دسترسی ندارید.');
    }
}
