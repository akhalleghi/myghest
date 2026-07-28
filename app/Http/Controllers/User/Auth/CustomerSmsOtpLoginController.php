<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforcePortalSessionLifetime;
use App\Models\LoginAccessBlock;
use App\Services\Admin\CaptchaService;
use App\Services\Auth\CustomerLoginLogService;
use App\Services\Auth\CustomerSmsOtpLoginService;
use App\Services\Auth\LoginAccessBlockService;
use App\Services\Sms\PortalAdminSmsDispatcher;
use App\Support\CustomerLoginSecuritySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

final class CustomerSmsOtpLoginController extends Controller
{
    public function __construct(
        private readonly CustomerSmsOtpLoginService $otpLogin,
        private readonly CustomerLoginLogService $loginLogService,
        private readonly LoginAccessBlockService $loginAccessBlocks,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        if (! CustomerLoginSecuritySettings::isSmsOtpLoginEnabled()) {
            return response()->json(['message' => 'ورود با رمز یکبار مصرف غیرفعال است.'], 403);
        }

        $this->mergeNormalizedDigits($request, ['mobile', 'captcha']);

        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^(09|9)\d{9}$/'],
            'captcha' => ['required', 'string', 'regex:/^\d{5}$/'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'mobile.regex' => 'شماره موبایل معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹).',
            'captcha.regex' => 'کپچا باید ۵ رقم باشد.',
        ]);

        try {
            $this->otpLogin->assertSendAllowed($request);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        if (! CaptchaService::validate($validated['captcha'], CaptchaService::PURPOSE_USER_OTP_LOGIN)) {
            return response()->json([
                'message' => 'کد تأیید تصویر نادرست است؛ تصویر را تازه کنید و دوباره تلاش کنید.',
            ], 422);
        }

        $remember = (bool) $request->boolean('remember');

        try {
            $result = $this->otpLogin->requestOtp((string) $validated['mobile'], $request, $remember);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        return response()->json($result);
    }

    public function resend(Request $request): JsonResponse
    {
        if (! CustomerLoginSecuritySettings::isSmsOtpLoginEnabled()) {
            return response()->json(['message' => 'ورود با رمز یکبار مصرف غیرفعال است.'], 403);
        }

        $validated = $request->validate([
            'login_session' => ['required', 'string', 'uuid'],
        ]);

        try {
            $result = $this->otpLogin->resend((string) $validated['login_session'], $request);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => match ($e->getMessage()) {
                    'resend_limit' => 'حداکثر تعداد ارسال مجدد استفاده شد؛ از ابتدا درخواست کد دهید.',
                    default => 'نشست ورود منقضی شده؛ دوباره شماره موبایل را وارد کنید.',
                },
            ], 422);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        return response()->json($result);
    }

    public function verify(Request $request): JsonResponse
    {
        if (! CustomerLoginSecuritySettings::isSmsOtpLoginEnabled()) {
            return response()->json(['message' => 'ورود با رمز یکبار مصرف غیرفعال است.'], 403);
        }

        $this->mergeNormalizedDigits($request, ['code']);

        $validated = $request->validate([
            'login_session' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'code.regex' => 'کد ورود باید دقیقاً ۶ رقم باشد.',
        ]);

        try {
            $verified = $this->otpLogin->verify(
                (string) $validated['login_session'],
                (string) $validated['code'],
                $request,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => match ($e->getMessage()) {
                    'invalid_code' => 'کد وارد شده صحیح نیست.',
                    'attempts_exceeded' => 'تعداد تلاش بیش از حد مجاز است؛ دوباره از ابتدا اقدام کنید.',
                    default => 'نشست ورود منقضی شده؛ دوباره شماره موبایل را وارد کنید.',
                },
            ], 422);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        $customer = $verified['customer'];
        $remember = (bool) $verified['remember'];
        $username = (string) $verified['username'];

        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        $this->loginAccessBlocks->clearOnSuccessfulLogin(
            $request,
            LoginAccessBlock::GUARD_CUSTOMER,
            $username,
        );
        EnforcePortalSessionLifetime::touchSession($request, LoginAccessBlock::GUARD_CUSTOMER);

        try {
            $this->loginLogService->recordSuccessfulLogin($customer, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        PortalAdminSmsDispatcher::afterCustomerLogin((int) $customer->id);

        return response()->json([
            'message' => 'ورود با موفقیت انجام شد.',
            'redirect' => route('user.dashboard'),
        ]);
    }

    private function rateOrSmsError(RuntimeException $e): JsonResponse
    {
        $msg = $e->getMessage();
        if (str_starts_with($msg, 'rate_limited:')) {
            $seconds = (int) substr($msg, strlen('rate_limited:'));

            return response()->json([
                'message' => 'درخواست‌های مکرر؛ لطفاً '.$seconds.' ثانیه بعد تلاش کنید.',
            ], 429);
        }

        return response()->json([
            'message' => ($msg !== '' && $msg !== 'sms_failed')
                ? $msg
                : 'ارسال پیامک ممکن نشد؛ بعداً تلاش کنید.',
        ], 422);
    }

    /**
     * @param  list<string>  $keys
     */
    private function mergeNormalizedDigits(Request $request, array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $ascii = $this->toEnglishDigits(trim((string) $request->input($key)));
            $merge[$key] = $key === 'captcha'
                ? (preg_replace('/\D+/', '', $ascii) ?? '')
                : $ascii;
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }
}
