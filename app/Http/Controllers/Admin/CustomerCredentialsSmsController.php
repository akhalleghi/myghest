<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\CustomerSmsCredentialVariables;
use App\Support\JalaliInputParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerCredentialsSmsController extends Controller
{
    private function fingerprint(Customer $customer): string
    {
        return hash('sha256', json_encode([(int) $customer->id, (string) $customer->mobile, (string) $customer->username, (string) $customer->password]));
    }

    private function draftData(Request $request): array
    {
        $request->merge(['mobile' => JalaliInputParser::toEnglishDigits(trim((string) $request->input('mobile', '')))]);

        return $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/D', Rule::unique('customers', 'mobile'), Rule::unique('customers', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);
    }

    private function draftFingerprint(array $data): string
    {
        return hash_hmac('sha256', json_encode($data), (string) config('app.key'));
    }

    public function draftPreview(Request $request): JsonResponse
    {
        $data = $this->draftData($request);
        $appName = AppSetting::query()->where('key', 'app_display_name')->value('value') ?: config('app.name');
        $text = 'سامانه '.$appName.chr(10).'نام کاربری: '.$data['mobile'].chr(10).'رمز عبور: '.$data['password'].chr(10).'ورود به سامانه: '.route('customer.login');
        $token = Str::random(48);
        Cache::put('customer_credentials_draft:'.$token, ['fingerprint' => $this->draftFingerprint($data), 'admin_id' => (int) auth('admin')->id()], now()->addMinutes(5));

        return response()->json(['body' => $text, 'mobile' => $data['mobile'], 'preview_token' => $token])->header('Cache-Control', 'no-store');
    }

    public function draftSend(Request $request, RawSmsDispatcher $sms): JsonResponse
    {
        $data = $this->draftData($request);
        $message = $request->validate(['sms_text' => ['required', 'string', 'max:1000'], 'preview_token' => ['required', 'string', 'size:48']]);
        $proof = Cache::pull('customer_credentials_draft:'.$message['preview_token']);
        if (! is_array($proof) || $proof['admin_id'] !== (int) auth('admin')->id() || $proof['fingerprint'] !== $this->draftFingerprint($data)) {
            throw ValidationException::withMessages(['sms_text' => 'اطلاعات فرم تغییر کرده یا پیش‌نمایش منقضی شده است؛ دوباره بازبینی کنید.']);
        }
        $result = $sms->send($data['mobile'], trim($message['sms_text']), 'customer-credentials');

        return response()->json(['message' => $result['ok'] ? 'پیامک ارسال شد؛ برای فعال شدن حساب، ثبت مشتری را تکمیل کنید.' : $result['message']], $result['ok'] ? 200 : 422);
    }

    public function preview(Customer $customer): JsonResponse
    {
        if (! $customer->receivesOutboundSms()) {
            throw ValidationException::withMessages(['mobile' => 'ارسال پیامک برای این مشتری غیرفعال است.']);
        }
        $vars = CustomerSmsCredentialVariables::for($customer);
        try {
            $passwordMatches = $vars['password'] !== '' && Hash::check($vars['password'], (string) $customer->password);
        } catch (\RuntimeException) {
            $passwordMatches = false;
        }
        if (! $passwordMatches) {
            throw ValidationException::withMessages(['password' => 'رمز قابل ارسال موجود نیست؛ ابتدا رمز جدیدی برای مشتری ذخیره کنید.']);
        }
        $appName = AppSetting::query()->where('key', 'app_display_name')->value('value') ?: config('app.name');
        $text = 'سامانه '.$appName.chr(10).'نام کاربری: '.$vars['username'].chr(10).'رمز عبور: '.$vars['password'].chr(10).'ورود به سامانه: '.route('customer.login');
        $token = Str::random(48);
        Cache::put('customer_credentials_preview:'.$token, ['fingerprint' => $this->fingerprint($customer), 'admin_id' => (int) auth('admin')->id()], now()->addMinutes(5));

        return response()->json(['body' => $text, 'mobile' => $customer->mobile, 'preview_token' => $token])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, Customer $customer, RawSmsDispatcher $sms): JsonResponse
    {
        $data = $request->validate(['sms_text' => ['required', 'string', 'max:1000'], 'preview_token' => ['required', 'string', 'size:48']]);
        $proof = Cache::pull('customer_credentials_preview:'.$data['preview_token']);
        if (! is_array($proof) || $proof['admin_id'] !== (int) auth('admin')->id() || $proof['fingerprint'] !== $this->fingerprint($customer)) {
            throw ValidationException::withMessages(['sms_text' => 'پیش‌نمایش منقضی شده یا اطلاعات مشتری تغییر کرده است؛ مجدداً بازبینی کنید.']);
        }
        if (! $customer->receivesOutboundSms()) {
            throw ValidationException::withMessages(['mobile' => 'ارسال پیامک برای این مشتری غیرفعال است.']);
        }
        $result = $sms->send((string) $customer->mobile, trim($data['sms_text']), 'customer-credentials');
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }
        $customer->credentials_sms_sent_at = now();
        $customer->save();

        return response()->json(['message' => 'پیامک اطلاعات ورود ارسال شد.']);
    }
}
