<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerDepositDeclaration;
use App\Services\Sms\Gateways\SepahanGostarGateway;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsPanelManager::class, function () {
            return new SmsPanelManager([
                new SepahanGostarGateway,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale((string) config('app.locale'));

        View::composer(['layouts.admin.app', 'layouts.admin.auth', 'layouts.user.app'], function ($view): void {
            $displayName = AppSetting::query()
                ->where('key', 'app_display_name')
                ->value('value');

            $fontSize = AppSetting::query()
                ->where('key', 'app_font_size')
                ->value('value');

            $uiFont = AppSetting::query()
                ->where('key', 'app_ui_font')
                ->value('value');
            $appIconPath = AppSetting::query()
                ->where('key', 'app_icon_path')
                ->value('value');
            $faviconPath = AppSetting::query()
                ->where('key', 'favicon_path')
                ->value('value');
            $appIconFa = AppSetting::query()
                ->where('key', 'app_icon_fa')
                ->value('value');
            $faviconFa = AppSetting::query()
                ->where('key', 'favicon_fa')
                ->value('value');

            $zibalMerchant = AppSetting::query()
                ->where('key', 'zibal_merchant')
                ->value('value');
            $paymentGateway = AppSetting::query()
                ->where('key', 'payment_gateway')
                ->value('value');

            $bankingInfoHtml = AppSetting::query()
                ->where('key', 'banking_info_html')
                ->value('value');

            $bankingShowInUserPanel = AppSetting::query()
                ->where('key', 'banking_info_show_in_user_panel')
                ->value('value');

            $zibalCallbackUrl = route('payment.zibal.callback', absolute: true);

            $view->with('appDisplayName', is_string($displayName) && $displayName !== '' ? $displayName : config('app.name'));
            $view->with('appFontSize', is_string($fontSize) && in_array($fontSize, ['small', 'normal', 'large', 'xlarge'], true) ? $fontSize : 'normal');
            $view->with('appUiFont', is_string($uiFont) && in_array($uiFont, ['iransans', 'iranyekan', 'anjoman', 'estedad'], true) ? $uiFont : 'iransans');
            $view->with('appIconUrl', is_string($appIconPath) && $appIconPath !== '' ? asset($appIconPath) : null);
            $view->with('faviconUrl', is_string($faviconPath) && $faviconPath !== '' ? asset($faviconPath) : null);
            $view->with('appIconFaClass', is_string($appIconFa) && preg_match('/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/', $appIconFa) ? $appIconFa : 'fa-solid fa-layer-group');
            $resolvedFaviconFaClass = is_string($faviconFa) && preg_match('/^fa-(solid|regular|brands)\s+fa-[a-z0-9-]+$/', $faviconFa)
                ? $faviconFa
                : 'fa-solid fa-globe';
            $view->with('faviconFaClass', $resolvedFaviconFaClass);
            $view->with('zibalMerchant', is_string($zibalMerchant) ? $zibalMerchant : '');
            $view->with('zibalCallbackUrl', $zibalCallbackUrl);
            $resolvedGateway = is_string($paymentGateway) && $paymentGateway !== '' ? $paymentGateway : 'zibal';
            $view->with('paymentGateway', in_array($resolvedGateway, ['zibal'], true) ? $resolvedGateway : 'zibal');
            $view->with('bankingInfoHtml', is_string($bankingInfoHtml) ? $bankingInfoHtml : '');
            $view->with(
                'bankingInfoShowInUserPanel',
                is_string($bankingShowInUserPanel) && $bankingShowInUserPanel === '1'
            );
        });

        View::composer('layouts.admin.app', function ($view): void {
            $pendingDepositCount = 0;
            $pendingDepositBadge = '';
            $loanNotifs = collect();
            $loanUnreadCount = 0;
            if (Auth::guard('admin')->check()) {
                $pendingDepositCount = (int) CustomerDepositDeclaration::query()
                    ->where('status', CustomerDepositDeclaration::STATUS_PENDING)
                    ->count();
                if ($pendingDepositCount > 0) {
                    $pendingDepositBadge = $pendingDepositCount > 99
                        ? Jalali::enToFaNumbers('99').'+'
                        : Jalali::enToFaNumbers((string) $pendingDepositCount);
                }

                $admin = Auth::guard('admin')->user();
                if ($admin instanceof Admin) {
                    $loanNotifs = $this->loadRecentNotifications($admin::class, (int) $admin->getKey());
                    $loanUnreadCount = (int) DatabaseNotification::query()
                        ->where('notifiable_type', $admin::class)
                        ->where('notifiable_id', $admin->getKey())
                        ->whereNull('read_at')
                        ->count();
                }
            }
            $totalBadgeCount = $pendingDepositCount + $loanUnreadCount;
            $unifiedBadge = $totalBadgeCount > 0
                ? ($totalBadgeCount > 99 ? Jalali::enToFaNumbers('99').'+' : Jalali::enToFaNumbers((string) $totalBadgeCount))
                : '';
            $view->with('adminPendingDepositDeclarationsCount', $pendingDepositCount);
            $view->with('adminPendingDepositDeclarationsBadge', $pendingDepositBadge);
            $view->with('adminLoanNotifications', $loanNotifs);
            $view->with('adminLoanNotificationsUnreadCount', $loanUnreadCount);
            $view->with('adminNotificationsBadgeUnified', $unifiedBadge);
        });

        View::composer('layouts.user.app', function ($view): void {
            $customer = Auth::guard('customer')->user();
            $balance = 0;
            if ($customer !== null) {
                $customer->loadMissing('wallet');
                $balance = (int) ($customer->wallet?->balance_toman ?? 0);
            }
            $view->with(
                'customerWalletBalanceFormatted',
                Jalali::enToFaNumbers(number_format(max(0, $balance), 0, '.', ',')),
            );
            $name = $customer !== null ? trim($customer->fullName()) : '';
            $view->with('portalCustomerDisplayName', $name !== '' ? $name : 'مشتری');

            $depositReviewNotifCount = 0;
            $depositReviewNotifBadge = '';
            $depositReviewNotifMessage = '';
            if ($customer !== null) {
                $base = CustomerDepositDeclaration::query()
                    ->where('customer_id', $customer->id)
                    ->whereNeedsCustomerReviewNotification();
                $depositReviewNotifCount = (int) (clone $base)->count();
                if ($depositReviewNotifCount > 0) {
                    $depositReviewNotifBadge = $depositReviewNotifCount > 99
                        ? Jalali::enToFaNumbers('99').'+'
                        : Jalali::enToFaNumbers((string) $depositReviewNotifCount);
                    $hasRejected = (clone $base)->where('status', CustomerDepositDeclaration::STATUS_REJECTED)->exists();
                    $hasApproved = (clone $base)->whereIn('status', [
                        CustomerDepositDeclaration::STATUS_APPROVED,
                        CustomerDepositDeclaration::STATUS_APPROVED_APPLIED,
                    ])->exists();
                    if ($hasRejected && ! $hasApproved) {
                        $depositReviewNotifMessage = 'اعلام واریزی شما توسط کارشناس رد شد. جهت مشاهدهٔ توضیحات به صفحهٔ اعلام واریزی‌ها بروید.';
                    } elseif ($hasApproved && ! $hasRejected) {
                        $depositReviewNotifMessage = 'با اعلام واریزی شما موافقت شد. جهت مشاهدهٔ توضیحات کلیک کنید و به صفحهٔ اعلام واریزی‌ها بروید.';
                    } else {
                        $depositReviewNotifMessage = 'رسیدگی به اعلام واریزی شما توسط کارشناس انجام شد. جهت مشاهدهٔ جزئیات و توضیحات به صفحهٔ اعلام واریزی‌ها بروید.';
                    }
                }
            }
            $loanNotifs = collect();
            $loanUnreadCount = 0;
            if ($customer instanceof Customer) {
                $loanNotifs = $this->loadRecentNotifications($customer::class, (int) $customer->getKey());
                $loanUnreadCount = (int) DatabaseNotification::query()
                    ->where('notifiable_type', $customer::class)
                    ->where('notifiable_id', $customer->getKey())
                    ->whereNull('read_at')
                    ->count();
            }
            $totalBadgeCount = $depositReviewNotifCount + $loanUnreadCount;
            $unifiedBadge = $totalBadgeCount > 0
                ? ($totalBadgeCount > 99 ? Jalali::enToFaNumbers('99').'+' : Jalali::enToFaNumbers((string) $totalBadgeCount))
                : '';
            $view->with('userPortalDepositReviewNotifCount', $depositReviewNotifCount);
            $view->with('userPortalDepositReviewNotifBadge', $depositReviewNotifBadge);
            $view->with('userPortalDepositReviewNotifMessage', $depositReviewNotifMessage);
            $view->with('userPortalLoanNotifications', $loanNotifs);
            $view->with('userPortalLoanNotificationsUnreadCount', $loanUnreadCount);
            $view->with('userPortalNotificationsBadgeUnified', $unifiedBadge);
        });
    }

    /**
     * بازخوانی آخرین ۱۵ اعلان دیتابیسی برای نمایش در flyout زنگ.
     *
     * هر آیتم شامل عنوان/متن/URL ازپیش‌مارک‌خوانده‌شدنی و زمان جلالی است.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function loadRecentNotifications(string $notifiableType, int $notifiableId): \Illuminate\Support\Collection
    {
        $rows = DatabaseNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'type', 'data', 'read_at', 'created_at']);

        return $rows->map(static function (DatabaseNotification $n): array {
            $data = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);
            $createdAt = $n->created_at instanceof Carbon ? $n->created_at : ($n->created_at !== null ? Carbon::parse((string) $n->created_at) : null);
            $createdAtFa = $createdAt !== null
                ? Jalali::enToFaNumbers(Jalali::instance($createdAt)->format('Y/m/d')).' '.Jalali::enToFaNumbers($createdAt->format('H:i'))
                : '';

            return [
                'id' => (string) $n->id,
                'title' => isset($data['title']) ? (string) $data['title'] : 'اعلان',
                'body' => isset($data['body']) ? (string) $data['body'] : '',
                'icon' => isset($data['icon']) ? (string) $data['icon'] : 'fa-regular fa-bell',
                'kind' => isset($data['kind']) ? (string) $data['kind'] : '',
                'is_unread' => $n->read_at === null,
                'created_at_fa' => $createdAtFa,
            ];
        });
    }
}
