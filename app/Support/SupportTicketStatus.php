<?php

declare(strict_types=1);

namespace App\Support;

/**
 * وضعیت‌های استاندارد تیکت پشتیبانی.
 */
final class SupportTicketStatus
{
    public const CREATED = 'created';

    public const PENDING_REVIEW = 'pending_review';

    public const ANSWERED = 'answered';

    public const CLOSED = 'closed';

    public const ON_HOLD = 'on_hold';

    public const WAITING_CUSTOMER = 'waiting_customer';

    public const WAITING_ADMIN = 'waiting_admin';

    /** @deprecated use PENDING_REVIEW */
    public const OPEN = 'open';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CREATED,
            self::PENDING_REVIEW,
            self::ANSWERED,
            self::CLOSED,
            self::ON_HOLD,
            self::WAITING_CUSTOMER,
            self::WAITING_ADMIN,
            self::OPEN,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CREATED => 'ایجاد شده',
            self::PENDING_REVIEW => 'در انتظار بررسی',
            self::ANSWERED => 'پاسخ داده شده',
            self::CLOSED => 'پایان یافته',
            self::ON_HOLD => 'نگهداشته شده',
            self::WAITING_CUSTOMER => 'در انتظار پاسخ مشتری',
            self::WAITING_ADMIN => 'در انتظار پاسخ پشتیبانی',
            self::OPEN => 'در انتظار بررسی',
        ];
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    /**
     * وضعیت‌های قابل انتخاب توسط ادمین در UI.
     *
     * @return array<string, string>
     */
    public static function adminSelectable(): array
    {
        $keys = [
            self::CREATED,
            self::PENDING_REVIEW,
            self::ANSWERED,
            self::CLOSED,
            self::ON_HOLD,
            self::WAITING_CUSTOMER,
            self::WAITING_ADMIN,
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::label($key);
        }

        return $out;
    }

    public static function allowsCustomerReply(string $status): bool
    {
        return ! in_array($status, [self::CLOSED, self::ON_HOLD], true);
    }

    public static function allowsAdminReply(string $status): bool
    {
        return ! in_array($status, [self::CLOSED], true);
    }
}
