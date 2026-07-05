<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Services\Auth\UserAgentSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AdminActivityLogService
{
    public function __construct(
        private readonly AdminActivityRouteLabelResolver $routeLabels,
    ) {}

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'captcha',
        '_token',
        'otp',
        'code',
        'login_session',
        'remember_token',
        'sms_text',
    ];

    public function recordLogin(Admin $admin, Request $request): void
    {
        $this->persist($admin, AdminActivityLog::ACTION_LOGIN, 'ورود موفق به سامانه', $request, [
            'http_status' => 302,
        ]);
    }

    public function recordLogout(Admin $admin, Request $request): void
    {
        $this->persist($admin, AdminActivityLog::ACTION_LOGOUT, 'خروج از سامانه', $request, [
            'http_status' => 302,
        ]);
    }

    public function recordSessionExpired(Admin $admin, Request $request): void
    {
        $this->persist($admin, AdminActivityLog::ACTION_SESSION_EXPIRED, 'پایان نشست به‌دلیل عدم فعالیت', $request, [
            'http_status' => 302,
        ]);
    }

    public function recordHttpRequest(Admin $admin, Request $request, Response $response): void
    {
        if ($this->shouldSkipHttpRequest($request)) {
            return;
        }

        $routeName = $request->route()?->getName();
        $description = $this->buildHttpDescription($request, is_string($routeName) ? $routeName : null);

        $this->persist($admin, AdminActivityLog::ACTION_HTTP, $description, $request, [
            'route_name' => is_string($routeName) ? $routeName : null,
            'http_method' => strtoupper($request->method()),
            'url_path' => '/'.ltrim($request->path(), '/'),
            'http_status' => $response->getStatusCode(),
            'metadata' => $this->buildRequestMetadata($request),
        ]);
    }

    private function shouldSkipHttpRequest(Request $request): bool
    {
        if ($request->isMethod('OPTIONS') || $request->isMethod('HEAD')) {
            return true;
        }

        $routeName = $request->route()?->getName();
        if (! is_string($routeName)) {
            return false;
        }

        return in_array($routeName, [
            'admin.captcha',
            'admin.captcha.refresh',
        ], true);
    }

    private function buildHttpDescription(Request $request, ?string $routeName): string
    {
        $method = strtoupper($request->method());
        $target = $this->routeLabels->resolve($routeName) ?? $request->path();

        $prefix = match ($method) {
            'GET' => 'مشاهده / دریافت',
            'POST' => 'ثبت / ارسال',
            'PUT', 'PATCH' => 'ویرایش',
            'DELETE' => 'حذف',
            default => $method,
        };

        if ($this->descriptionAlreadyPrefixed($target)) {
            return $target;
        }

        return $prefix.' '.$target;
    }

    private function descriptionAlreadyPrefixed(string $target): bool
    {
        $prefixes = [
            'مشاهده',
            'ثبت',
            'ویرایش',
            'حذف',
            'خروجی',
            'دانلود',
            'ارسال',
            'ذخیره',
            'ورود',
            'خروج',
            'پایان',
            'ایجاد',
            'افزودن',
            'بازگردانی',
            'بارگذاری',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($target, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function persist(Admin $admin, string $action, string $description, Request $request, array $extra = []): void
    {
        $ua = (string) ($request->userAgent() ?? '');
        $meta = UserAgentSummary::fromUserAgent($ua);

        AdminActivityLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'description' => $description,
            'route_name' => $extra['route_name'] ?? null,
            'http_method' => $extra['http_method'] ?? null,
            'url_path' => $extra['url_path'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $ua !== '' ? $ua : null,
            'browser' => $meta['browser'],
            'platform' => $meta['platform'],
            'device_type' => $meta['device_type'],
            'http_status' => isset($extra['http_status']) ? (int) $extra['http_status'] : null,
            'metadata' => $extra['metadata'] ?? null,
            'performed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRequestMetadata(Request $request): ?array
    {
        $payload = array_merge(
            $this->sanitizeInput($request->query->all()),
            $this->sanitizeInput($request->request->all()),
        );

        if ($payload === []) {
            return null;
        }

        return ['input' => $payload];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitizeInput(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->sanitizeInput($value);

                continue;
            }
            if (is_scalar($value) || $value === null) {
                $stringValue = is_string($value) ? $value : (string) $value;
                if (strlen($stringValue) > 500) {
                    $out[$key] = mb_substr($stringValue, 0, 500).'…';

                    continue;
                }
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function currentAdmin(): ?Admin
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
