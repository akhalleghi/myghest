<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * مدیریت اعلان‌های ادمین: «دنبال‌کردن» (مارک‌خوانده + ریدایرکت) و «خواندن همه».
 *
 * امنیتی: هر اعلان تنها در صورتی قابل دسترسی است که متعلق به همان ادمین احرازشده باشد.
 */
final class AdminNotificationController extends Controller
{
    /**
     * مارک‌کردن یک اعلان به‌عنوان خوانده‌شده و هدایت به URL ذخیره‌شده در دادهٔ اعلان.
     */
    public function follow(string $notification): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $note = DatabaseNotification::query()
            ->where('id', $notification)
            ->where('notifiable_type', $admin::class)
            ->where('notifiable_id', $admin->getKey())
            ->first();

        if (! $note instanceof DatabaseNotification) {
            return redirect()->route('admin.dashboard');
        }

        if ($note->read_at === null) {
            $note->forceFill(['read_at' => Carbon::now()])->save();
        }

        $target = $this->resolveTargetUrl($note->data);

        return redirect()->to($target);
    }

    /**
     * مارک‌کردن همهٔ اعلان‌های نخواندهٔ ادمین فعلی.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        DatabaseNotification::query()
            ->where('notifiable_type', $admin::class)
            ->where('notifiable_id', $admin->getKey())
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
            return route('admin.dashboard');
        }
        try {
            return route($name, $query);
        } catch (\Throwable) {
            return route('admin.dashboard');
        }
    }
}
