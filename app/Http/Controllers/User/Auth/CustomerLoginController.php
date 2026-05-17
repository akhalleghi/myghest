<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Admin\CaptchaService;
use App\Services\Auth\CustomerLoginLogService;
use App\Services\Auth\CustomerLoginTwoFactorService;
use App\Support\CustomerLoginSecuritySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

final class CustomerLoginController extends Controller
{
    public function __construct(
        private readonly CustomerLoginTwoFactorService $loginTwoFactor,
    ) {}

    public function create(): View
    {
        return view('user.auth.login', [
            'customerLoginTwoFactorEnabled' => CustomerLoginSecuritySettings::isTwoFactorEnabled(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, CustomerLoginLogService $loginLogService): RedirectResponse|JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'captcha' => ['required', 'string', 'size:5'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'نام کاربری را وارد کنید.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'captcha.required' => 'کد تأیید را وارد کنید.',
            'captcha.size' => 'کپچا باید ۵ کاراکتر باشد.',
        ]);

        $this->ensureNotLockedOut($request);

        if (! CaptchaService::validate($credentials['captcha'], CaptchaService::PURPOSE_USER_LOGIN)) {
            $this->hitFailedLoginThrottle($request);
            throw ValidationException::withMessages([
                'captcha' => 'کد تأیید نادرست است؛ برای کد جدید روی تصویر کلیک کنید.',
            ]);
        }

        $remember = (bool) $request->boolean('remember');
        $username = $this->normalizeLoginUsername(trim($credentials['username']));

        /** @var Customer|null $customer */
        $customer = Customer::query()->where('username', $username)->first();

        if ($customer === null || ! Hash::check($credentials['password'], (string) $customer->password)) {
            $this->hitFailedLoginThrottle($request);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        if (CustomerLoginSecuritySettings::isTwoFactorEnabled()) {
            try {
                $challenge = $this->loginTwoFactor->beginChallenge($customer, $request, $remember);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'no_mobile') {
                    throw ValidationException::withMessages([
                        'username' => 'ورود دو مرحله‌ای فعال است اما شماره موبایل معتبر در پرونده ثبت نشده؛ با پشتیبانی تماس بگیرید.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'username' => $e->getMessage() !== '' && $e->getMessage() !== 'sms_failed'
                        ? $e->getMessage()
                        : 'ارسال پیامک ورود ممکن نشد؛ لطفاً بعداً تلاش کنید.',
                ]);
            }

            if ($this->expectsLoginJson($request)) {
                return response()->json([
                    'requires_otp' => true,
                    'login_session' => $challenge['login_session'],
                    'masked_mobile' => $challenge['masked_mobile'],
                    'message' => $challenge['message'],
                    'resend_available_in' => $challenge['resend_available_in'],
                ]);
            }

            return redirect()
                ->route('customer.login')
                ->withInput($request->only('username', 'remember'))
                ->with('login_2fa', $challenge);
        }

        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        try {
            $loginLogService->recordSuccessfulLogin($customer, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($this->expectsLoginJson($request)) {
            return response()->json([
                'requires_otp' => false,
                'redirect' => route('user.dashboard'),
            ]);
        }

        return redirect()->intended(route('user.dashboard'));
    }

    protected function throttleKey(Request $request): string
    {
        $username = Str::lower($this->normalizeLoginUsername(trim((string) $request->input('username'))));

        return 'customer-login|'.sha1($username.'|'.$request->ip());
    }

    protected function ensureNotLockedOut(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 12)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => 'تلاش زیاد؛ لطفاً '.$seconds.' ثانیه بعد دوباره تلاش کنید.',
            ]);
        }
    }

    protected function hitFailedLoginThrottle(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), 60 * 5);
    }

    protected function normalizeLoginUsername(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $step = str_replace($ar, $en, str_replace($fa, $en, $value));

        return trim($step);
    }

    private function expectsLoginJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->header('X-Login-Mode') === 'ajax'
            || $request->boolean('ajax');
    }
}
