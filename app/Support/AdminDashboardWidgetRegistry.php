<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نگاشت یکتا بین شناسهٔ ویجت داشبورد و کلید دسترسی.
 */
final class AdminDashboardWidgetRegistry
{
    /** @var array<string, string> widget_id => label */
    public const WIDGETS = [
        'system-stats' => 'آمار سیستم',
        'summary-overdue' => 'اقساط سررسید شده و معوق',
        'summary-deposit-notifications' => 'اعلام واریزی‌های جدید',
        'summary-loan-requests' => 'درخواست وام‌ها',
        'summary-sms-email' => 'وضعیت پیامک',
        'summary-counterparty-matured' => 'سررسید شده‌های طرف حساب',
        'tbl-online-installments' => 'جدول واریز قسط‌های آنلاین',
        'tbl-bank-transactions' => 'جدول تراکنش‌های بانک',
        'tbl-fund-transactions' => 'جدول تراکنش‌های صندوق',
        'tbl-special-box' => 'جعبه‌شکن / تراکنش ویژه',
        'chart-installments-12m' => 'نمودار اقساط (۱۲ ماه اخیر)',
        'chart-new-loans-12m' => 'نمودار وام‌های جدید (۱۲ ماه اخیر)',
    ];

    public function permissionKeyForWidget(string $widgetId): string
    {
        return 'dashboard.card.'.str_replace('-', '_', $widgetId);
    }

    public function isKnownWidget(string $widgetId): bool
    {
        return isset(self::WIDGETS[$widgetId]);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function permissionTreeChildren(): array
    {
        $children = [];
        foreach (self::WIDGETS as $widgetId => $label) {
            $children[] = [
                'key' => $this->permissionKeyForWidget($widgetId),
                'label' => $label,
            ];
        }

        return $children;
    }
}
