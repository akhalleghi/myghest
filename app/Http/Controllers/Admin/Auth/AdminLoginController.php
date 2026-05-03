<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminLoginController extends Controller
{
    public function create()
    {
        return view('admin.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'max:255'],
            'captcha' => ['required', 'string', 'size:5'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'نام کاربری را وارد کنید.',
            'username.regex' => 'نام کاربری تنها شامل حرف انگلیسی، عدد، نقطه، زیرخط و خط تیره است.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'captcha.required' => 'کد تأیید را وارد کنید.',
            'captcha.size' => 'کپچا باید ۵ کاراکتر باشد.',
        ]);

        $this->ensureNotLockedOutAdminLogin($request);

        if (! CaptchaService::validate($credentials['captcha'])) {
            $this->hitFailedAdminLoginThrottle($request);
            throw ValidationException::withMessages([
                'captcha' => 'کد تأیید نادرست است؛ برای کد جدید روی تصویر کلیک کنید.',
            ]);
        }

        $remember = (bool) $request->boolean('remember');

        $username = Str::lower($credentials['username']);

        if (
            ! Auth::guard('admin')->attempt([
                'username' => $username,
                'password' => $credentials['password'],
            ], $remember)
        ) {
            $this->hitFailedAdminLoginThrottle($request);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->hitFailedAdminLoginThrottle($request);

            throw ValidationException::withMessages([
                'username' => 'این حساب غیرفعال است.',
            ]);
        }

        RateLimiter::clear($this->throttleKeyAdminLogin($request));

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    protected function throttleKeyAdminLogin(Request $request): string
    {
        $username = Str::lower((string) $request->input('username'));

        return 'admin-login|'.sha1($username.'|'.$request->ip());
    }

    protected function ensureNotLockedOutAdminLogin(Request $request): void
    {
        $key = $this->throttleKeyAdminLogin($request);

        if (RateLimiter::tooManyAttempts($key, 15)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => 'تلاش زیاد؛ لطفاً '.$seconds.' ثانیه بعد دوباره تلاش کنید.',
            ]);
        }
    }

    protected function hitFailedAdminLoginThrottle(Request $request): void
    {
        RateLimiter::hit($this->throttleKeyAdminLogin($request), 60 * 5);
    }
}
