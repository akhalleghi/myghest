<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * مدیریت اعلان‌های مشتری: «دنبال‌کردن» (مارک‌خوانده + ریدایرکت) و «خواندن همه».
 */
final class UserNotificationController extends Controller
{
    public function follow(string $notification): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        $note = DatabaseNotification::query()
            ->where('id', $notification)
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->first();

        if (! $note instanceof DatabaseNotification) {
            return redirect()->route('user.dashboard');
        }

        if ($note->read_at === null) {
            $note->forceFill(['read_at' => Carbon::now()])->save();
        }

        $target = $this->resolveTargetUrl($note->data);

        return redirect()->to($target);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        DatabaseNotification::query()
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        return back();
    }

    /**
     * @param  array<string, mixed>|mixed  $data
     */
    private function resolveTargetUrl(mixed $data): string
    {
        $data = is_array($data) ? $data : [];
        $name = isset($data['url_name']) ? (string) $data['url_name'] : '';
        $query = isset($data['url_query']) && is_array($data['url_query']) ? $data['url_query'] : [];

        if ($name === '') {
            return route('user.dashboard');
        }
        try {
            return route($name, $query);
        } catch (\Throwable) {
            return route('user.dashboard');
        }
    }
}
