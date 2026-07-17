<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforcePortalSessionLifetime;
use App\Models\Customer;
use App\Models\LoginAccessBlock;
use App\Services\Admin\CaptchaService;
use App\Services\Auth\CustomerLoginLogService;
use App\Services\Auth\CustomerLoginTwoFactorService;
use App\Services\Auth\LoginAccessBlockService;
use App\Services\Sms\PortalAdminSmsDispatcher;
use App\Support\CustomerLoginSecuritySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

final class CustomerLoginController extends Controller
{
    public function __construct(
        private readonly CustomerLoginTwoFactorService $loginTwoFactor,
        private readonly LoginAccessBlockService $loginAccessBlocks,
    ) {}

    public function create(): View
    {
        return view('user.auth.login', [
            'customerLoginTwoFactorEnabled' => CustomerLoginSecuritySettings::isTwoFactorEnabled(),
            'customerLoginSmsOtpEnabled' => CustomerLoginSecuritySettings::isSmsOtpLoginEnabled(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, CustomerLoginLogService $loginLogService): RedirectResponse|JsonResponse
    {
        if ($request->has('captcha')) {
            $request->merge([
                'captcha' => $this->normalizeCaptchaDigits((string) $request->input('captcha')),
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'captcha' => ['required', 'string', 'regex:/^\d{5}$/'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'نام کاربری را وارد کنید.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'captcha.required' => 'کد تأیید را وارد کنید.',
            'captcha.regex' => 'کپچا باید ۵ رقم باشد.',
        ]);

        $remember = (bool) $request->boolean('remember');
        $username = $this->normalizeLoginUsername(trim($credentials['username']));

        $this->loginAccessBlocks->ensureLoginAllowed($request, LoginAccessBlock::GUARD_CUSTOMER, $username);

        if (! CaptchaService::validate($credentials['captcha'], CaptchaService::PURPOSE_USER_LOGIN)) {
            $this->loginAccessBlocks->recordFailedAttempt($request, LoginAccessBlock::GUARD_CUSTOMER, $username);
            throw ValidationException::withMessages([
                'captcha' => 'کد تأیید نادرست است؛ برای کد جدید روی تصویر کلیک کنید.',
            ]);
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()->where('username', $username)->first();

        if ($customer === null || ! Hash::check($credentials['password'], (string) $customer->password)) {
            $this->loginAccessBlocks->recordFailedAttempt($request, LoginAccessBlock::GUARD_CUSTOMER, $username);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

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

        return $this->completeCustomerLogin($customer, $request, $remember, $username, $loginLogService);
    }

    private function completeCustomerLogin(
        Customer $customer,
        Request $request,
        bool $remember,
        string $username,
        CustomerLoginLogService $loginLogService,
    ): RedirectResponse|JsonResponse {
        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        $this->loginAccessBlocks->clearOnSuccessfulLogin($request, LoginAccessBlock::GUARD_CUSTOMER, $username);
        EnforcePortalSessionLifetime::touchSession($request, LoginAccessBlock::GUARD_CUSTOMER);

        try {
            $loginLogService->recordSuccessfulLogin($customer, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        PortalAdminSmsDispatcher::afterCustomerLogin((int) $customer->id);

        if ($this->expectsLoginJson($request)) {
            return response()->json([
                'requires_otp' => false,
                'redirect' => route('user.dashboard'),
            ]);
        }

        return redirect()->intended(route('user.dashboard'));
    }

    protected function normalizeLoginUsername(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $step = str_replace($ar, $en, str_replace($fa, $en, $value));

        return trim($step);
    }

    private function normalizeCaptchaDigits(string $value): string
    {
        $ascii = $this->normalizeLoginUsername($value);

        return preg_replace('/\D+/', '', $ascii) ?? '';
    }

    private function expectsLoginJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->header('X-Login-Mode') === 'ajax'
            || $request->boolean('ajax');
    }
}
