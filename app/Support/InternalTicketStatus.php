<?php

declare(strict_types=1);

namespace App\Support;

/**
 * وضعیت‌های تیکت داخلی بین ادمین‌ها.
 */
final class InternalTicketStatus
{
    public const CREATED = 'created';

    public const PENDING_REVIEW = 'pending_review';

    public const ANSWERED = 'answered';

    public const CLOSED = 'closed';

    public const ON_HOLD = 'on_hold';

    public const WAITING_RECIPIENT = 'waiting_recipient';

    public const WAITING_AUTHOR = 'waiting_author';

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
            self::WAITING_RECIPIENT,
            self::WAITING_AUTHOR,
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
            self::WAITING_RECIPIENT => 'در انتظار پاسخ گیرنده',
            self::WAITING_AUTHOR => 'در انتظار پاسخ فرستنده',
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
     * @return array<string, string>
     */
    public static function adminSelectable(): array
    {
        $out = [];
        foreach (self::all() as $key) {
            $out[$key] = self::label($key);
        }

        return $out;
    }

    public static function allowsReply(string $status): bool
    {
        return ! in_array($status, [self::CLOSED], true);
    }
}
