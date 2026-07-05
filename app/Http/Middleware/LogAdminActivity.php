<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\AdminActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class LogAdminActivity
{
    public function __construct(
        private readonly AdminActivityLogService $activityLog,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $admin = AdminActivityLogService::currentAdmin();
        if ($admin !== null) {
            $this->activityLog->recordHttpRequest($admin, $request, $response);
        }

        return $response;
    }
}
