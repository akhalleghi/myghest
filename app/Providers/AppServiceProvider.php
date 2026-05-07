<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\Sms\Gateways\SepahanGostarGateway;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
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

        View::composer(['layouts.admin.app', 'layouts.admin.auth'], function ($view): void {
            $displayName = AppSetting::query()
                ->where('key', 'app_display_name')
                ->value('value');

            $fontSize = AppSetting::query()
                ->where('key', 'app_font_size')
                ->value('value');

            $uiFont = AppSetting::query()
                ->where('key', 'app_ui_font')
                ->value('value');

            $view->with('appDisplayName', is_string($displayName) && $displayName !== '' ? $displayName : config('app.name'));
            $view->with('appFontSize', is_string($fontSize) && in_array($fontSize, ['small', 'normal', 'large'], true) ? $fontSize : 'normal');
            $view->with('appUiFont', is_string($uiFont) && in_array($uiFont, ['iransans', 'iranyekan', 'anjoman'], true) ? $uiFont : 'iransans');
        });
    }
}
