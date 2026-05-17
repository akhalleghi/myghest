<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use App\Models\LoginAccessBlock;

final class PortalLoginSecuritySettings
{
    public const CUSTOMER_SESSION_LIFETIME_KEY = 'customer_login_session_lifetime_minutes';

    public const CUSTOMER_MAX_FAILED_ATTEMPTS_KEY = 'customer_login_max_failed_attempts';

    public const ADMIN_SESSION_LIFETIME_KEY = 'admin_login_session_lifetime_minutes';

    public const ADMIN_MAX_FAILED_ATTEMPTS_KEY = 'admin_login_max_failed_attempts';

    private const DEFAULT_SESSION_MINUTES = 120;

    private const DEFAULT_CUSTOMER_MAX_ATTEMPTS = 12;

    private const DEFAULT_ADMIN_MAX_ATTEMPTS = 15;

    public static function sessionLifetimeMinutes(string $guard): int
    {
        $key = $guard === LoginAccessBlock::GUARD_ADMIN
            ? self::ADMIN_SESSION_LIFETIME_KEY
            : self::CUSTOMER_SESSION_LIFETIME_KEY;

        $default = self::DEFAULT_SESSION_MINUTES;

        return self::boundedInt($key, $default, 5, 1440);
    }

    public static function maxFailedAttempts(string $guard): int
    {
        $key = $guard === LoginAccessBlock::GUARD_ADMIN
            ? self::ADMIN_MAX_FAILED_ATTEMPTS_KEY
            : self::CUSTOMER_MAX_FAILED_ATTEMPTS_KEY;

        $default = $guard === LoginAccessBlock::GUARD_ADMIN
            ? self::DEFAULT_ADMIN_MAX_ATTEMPTS
            : self::DEFAULT_CUSTOMER_MAX_ATTEMPTS;

        return self::boundedInt($key, $default, 3, 50);
    }

    public static function lockoutDecaySeconds(): int
    {
        return 60 * 60;
    }

    private static function boundedInt(string $key, int $default, int $min, int $max): int
    {
        $value = AppSetting::query()->where('key', $key)->value('value');
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        $parsed = (int) $value;

        return max($min, min($max, $parsed));
    }
}
