<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerLoginLog;
use Illuminate\Http\Request;

/**
 * ثبت ورود موفق مشتری (IP، مرورگر، دستگاه).
 */
final class CustomerLoginLogService
{
    public function recordSuccessfulLogin(Customer $customer, Request $request): void
    {
        $ua = (string) ($request->userAgent() ?? '');
        $meta = UserAgentSummary::fromUserAgent($ua);

        CustomerLoginLog::query()->create([
            'customer_id' => $customer->id,
            'logged_in_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $ua !== '' ? $ua : null,
            'browser' => $meta['browser'],
            'platform' => $meta['platform'],
            'device_type' => $meta['device_type'],
        ]);
    }
}
