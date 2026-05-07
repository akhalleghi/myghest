<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    /**
     * داشبورد مدیریت با لایوت کامل (سایدبار، هدر، کارت‌ها و جداول نمونه).
     */
    public function __invoke(): View
    {
        $smsDeliveredToday = SmsLog::query()
            ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('status', SmsLog::STATUS_DELIVERED)
            ->count();

        $activePanel = SmsPanelSetting::query()
            ->where('is_active', true)
            ->first();

        $smsPanelStatusLabel = 'تنظیم نشده';
        if ($activePanel !== null && $activePanel->last_connection_status === 'connected') {
            $smsPanelStatusLabel = 'متصل';
        } elseif ($activePanel !== null) {
            $smsPanelStatusLabel = 'نامتصل';
        }

        return view('admin.dashboard', [
            'smsDeliveredToday' => $smsDeliveredToday,
            'smsPanelStatusLabel' => $smsPanelStatusLabel,
        ]);
    }
}
