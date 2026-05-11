<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\Sms\Gateways\SepahanGostarGateway;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
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
        });
    }
}
