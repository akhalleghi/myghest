<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Services\Admin\AdminDashboardStatisticsService;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardStatisticsService $dashboardStats,
    ) {}

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

        $dashboard = $this->dashboardStats->build();
        $summaryCards = $dashboard['summaryCards'];

        foreach ($summaryCards as $index => $card) {
            if (($card['widget_id'] ?? '') !== 'summary-sms-email') {
                continue;
            }
            $summaryCards[$index]['lines'] = [
                [
                    'k' => 'ارسال موفق پیامک امروز',
                    'v' => \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $smsDeliveredToday).' مورد',
                ],
                ['k' => 'وضعیت پنل پیامکی', 'v' => $smsPanelStatusLabel],
            ];
            break;
        }

        return view('admin.dashboard', [
            'systemStatRows' => $dashboard['systemStatRows'],
            'summaryCards' => $summaryCards,
            'tables' => $dashboard['tables'],
            'installmentChart' => $dashboard['installmentChart'],
            'newLoansChart' => $dashboard['newLoansChart'],
        ]);
    }
}
