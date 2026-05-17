<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\LoginAccessBlock;
use App\Support\PortalLoginSecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginAccessBlockService
{
    public const BLOCKED_ACCOUNT_MESSAGE = 'حساب شما مسدود گردید. لطفاً با ادمین سامانه جهت رفع مسدودی ارتباط برقرار نمایید.';

    public function throttleKey(string $guard, string $username, string $ip): string
    {
        return $guard.'-login|'.sha1(Str::lower(trim($username)).'|'.$ip);
    }

    /**
     * @throws ValidationException
     */
    public function ensureLoginAllowed(Request $request, string $guard, string $username): void
    {
        $username = Str::lower(trim($username));
        $ip = (string) $request->ip();

        if ($this->hasActiveBlock($guard, $username, $ip)) {
            throw ValidationException::withMessages([
                'username' => self::BLOCKED_ACCOUNT_MESSAGE,
            ]);
        }

        $key = $this->throttleKey($guard, $username, $ip);
        $max = PortalLoginSecuritySettings::maxFailedAttempts($guard);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->syncActiveBlockFromLimiter($guard, $username, $ip, $max);

            throw ValidationException::withMessages([
                'username' => self::BLOCKED_ACCOUNT_MESSAGE,
            ]);
        }
    }

    public function recordFailedAttempt(Request $request, string $guard, string $username): void
    {
        $username = Str::lower(trim($username));
        $ip = (string) $request->ip();
        $key = $this->throttleKey($guard, $username, $ip);
        $max = PortalLoginSecuritySettings::maxFailedAttempts($guard);

        RateLimiter::hit($key, PortalLoginSecuritySettings::lockoutDecaySeconds());

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->createOrRefreshBlock($guard, $username, $ip, RateLimiter::attempts($key));
        }
    }

    public function clearOnSuccessfulLogin(Request $request, string $guard, string $username): void
    {
        $username = Str::lower(trim($username));
        $ip = (string) $request->ip();
        $key = $this->throttleKey($guard, $username, $ip);

        RateLimiter::clear($key);
        $this->deactivateBlocks($guard, $username, $ip);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listActiveBlocks(?string $guard = null): Collection
    {
        $query = LoginAccessBlock::query()
            ->where('is_active', true)
            ->orderByDesc('blocked_at');

        if ($guard !== null && $guard !== '') {
            $query->where('guard', $guard);
        }

        return $query->get()->map(static function (LoginAccessBlock $block): array {
            return [
                'id' => (int) $block->id,
                'guard' => (string) $block->guard,
                'guard_label' => $block->guardLabel(),
                'username' => (string) $block->username,
                'ip_address' => $block->ip_address,
                'failed_attempts' => (int) $block->failed_attempts,
                'blocked_at' => $block->blocked_at?->format('Y-m-d H:i:s'),
                'blocked_at_label' => $block->blocked_at?->format('Y/m/d H:i') ?? '—',
            ];
        });
    }

    public function unblock(int $blockId, int $adminId): bool
    {
        /** @var LoginAccessBlock|null $block */
        $block = LoginAccessBlock::query()->whereKey($blockId)->where('is_active', true)->first();
        if ($block === null) {
            return false;
        }

        $block->forceFill([
            'is_active' => false,
            'unblocked_at' => now(),
            'unblocked_by_admin_id' => $adminId,
        ])->save();

        $ip = (string) ($block->ip_address ?? '');
        if ($ip !== '') {
            RateLimiter::clear($this->throttleKey((string) $block->guard, (string) $block->username, $ip));
        }

        return true;
    }

    private function hasActiveBlock(string $guard, string $username, string $ip): bool
    {
        return LoginAccessBlock::query()
            ->where('guard', $guard)
            ->where('is_active', true)
            ->where('username', $username)
            ->where(function ($q) use ($ip): void {
                $q->whereNull('ip_address')->orWhere('ip_address', $ip);
            })
            ->exists();
    }

    private function syncActiveBlockFromLimiter(string $guard, string $username, string $ip, int $max): void
    {
        $attempts = RateLimiter::attempts($this->throttleKey($guard, $username, $ip));
        $this->createOrRefreshBlock($guard, $username, $ip, max($attempts, $max));
    }

    private function createOrRefreshBlock(string $guard, string $username, string $ip, int $failedAttempts): void
    {
        $existing = LoginAccessBlock::query()
            ->where('guard', $guard)
            ->where('username', $username)
            ->where('is_active', true)
            ->where(function ($q) use ($ip): void {
                $q->where('ip_address', $ip)->orWhereNull('ip_address');
            })
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'ip_address' => $ip !== '' ? $ip : $existing->ip_address,
                'failed_attempts' => max((int) $existing->failed_attempts, $failedAttempts),
                'blocked_at' => $existing->blocked_at ?? now(),
            ])->save();

            return;
        }

        LoginAccessBlock::query()->create([
            'guard' => $guard,
            'username' => $username,
            'ip_address' => $ip !== '' ? $ip : null,
            'failed_attempts' => $failedAttempts,
            'blocked_at' => now(),
            'is_active' => true,
        ]);
    }

    private function deactivateBlocks(string $guard, string $username, string $ip): void
    {
        LoginAccessBlock::query()
            ->where('guard', $guard)
            ->where('username', $username)
            ->where('is_active', true)
            ->where(function ($q) use ($ip): void {
                $q->whereNull('ip_address')->orWhere('ip_address', $ip);
            })
            ->update([
                'is_active' => false,
                'unblocked_at' => now(),
            ]);
    }
}
