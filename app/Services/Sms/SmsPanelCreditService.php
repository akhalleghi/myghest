<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsPanelSetting;
use App\Services\Sms\Gateways\SepahanGostarGateway;
use Illuminate\Support\Facades\Crypt;

final class SmsPanelCreditService
{
    public function __construct(
        private readonly SmsPanelManager $panelManager,
    ) {}

    public function fetchActivePanelCredit(): SmsPanelCreditResult
    {
        $setting = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($setting === null || trim((string) ($setting->username ?? '')) === '') {
            return new SmsPanelCreditResult(
                false,
                null,
                'پنل فعالی تنظیم نشده است. ابتدا از تب تنظیمات، اتصال پنل را ذخیره کنید.'
            );
        }

        if ($setting->provider !== 'sepahan-gostar') {
            return new SmsPanelCreditResult(
                false,
                null,
                'استعلام اعتبار برای پنل انتخاب‌شده پشتیبانی نمی‌شود.'
            );
        }

        $gateway = $this->panelManager->gateway($setting->provider);
        if (! $gateway instanceof SepahanGostarGateway) {
            return new SmsPanelCreditResult(false, null, 'درگاه پنل پیامک برای استعلام اعتبار آماده نیست.');
        }

        $token = $this->decryptOrEmpty((string) ($setting->api_token ?? ''));
        if ($token === '') {
            return new SmsPanelCreditResult(
                false,
                null,
                'توکن WebAPI تنظیم نشده است. توکن را از پنل سپاهان‌گستر کپی کرده و در همین صفحه ذخیره کنید.'
            );
        }

        return $gateway->fetchRemainingCredit($token);
    }

    /**
     * @return array{configured:bool,hint:string}
     */
    public function activePanelTokenStatus(): array
    {
        $setting = SmsPanelSetting::query()->where('is_active', true)->first();
        $token = $setting !== null ? $this->decryptOrEmpty((string) ($setting->api_token ?? '')) : '';

        if ($token === '') {
            return ['configured' => false, 'hint' => ''];
        }

        $suffix = strlen($token) >= 4 ? substr($token, -4) : $token;

        return [
            'configured' => true,
            'hint' => '••••••••••••'.$suffix,
        ];
    }

    public function saveActivePanelApiToken(string $plainToken): SmsPanelCreditResult
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || ! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $plainToken)) {
            return new SmsPanelCreditResult(false, null, 'فرمت توکن WebAPI نامعتبر است.');
        }

        $setting = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($setting === null) {
            return new SmsPanelCreditResult(
                false,
                null,
                'پنل فعالی تنظیم نشده است. ابتدا از تب تنظیمات، اتصال پنل را ذخیره کنید.'
            );
        }

        if ($setting->provider !== 'sepahan-gostar') {
            return new SmsPanelCreditResult(
                false,
                null,
                'ذخیره توکن برای پنل انتخاب‌شده پشتیبانی نمی‌شود.'
            );
        }

        $gateway = $this->panelManager->gateway($setting->provider);
        if (! $gateway instanceof SepahanGostarGateway) {
            return new SmsPanelCreditResult(false, null, 'درگاه پنل پیامک برای اعتبارسنجی توکن آماده نیست.');
        }

        $probe = $gateway->fetchRemainingCredit($plainToken);
        if (! $probe->ok) {
            return new SmsPanelCreditResult(
                false,
                null,
                $probe->message !== '' ? $probe->message : 'توکن WebAPI نامعتبر است و ذخیره نشد.'
            );
        }

        $setting->api_token = Crypt::encryptString($plainToken);
        $setting->save();

        return new SmsPanelCreditResult(
            true,
            $probe->credit,
            'توکن WebAPI ذخیره شد و اعتبار با موفقیت دریافت گردید.'
        );
    }

    private function decryptOrEmpty(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
