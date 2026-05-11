<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Admin\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerLoginController extends Controller
{
    public function create()
    {
        return view('user.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request)
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

        if (
            ! Auth::guard('customer')->attempt([
                'username' => $username,
                'password' => $credentials['password'],
            ], $remember)
        ) {
            $this->hitFailedLoginThrottle($request);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

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
}
