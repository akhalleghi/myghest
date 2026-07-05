<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforcePortalSessionLifetime;
use App\Jobs\SendAdminLoginNotifySmsJob;
use App\Models\Admin;
use App\Models\LoginAccessBlock;
use App\Services\Admin\CaptchaService;
use App\Services\Admin\AdminActivityLogService;
use App\Services\Auth\AdminLoginTwoFactorService;
use App\Services\Auth\LoginAccessBlockService;
use App\Support\AdminLoginSecuritySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

final class AdminLoginController extends Controller
{
    public function __construct(
        private readonly AdminLoginTwoFactorService $loginTwoFactor,
        private readonly LoginAccessBlockService $loginAccessBlocks,
        private readonly AdminActivityLogService $activityLog,
    ) {}

    public function create(): View
    {
        return view('admin.auth.login', [
            'adminLoginTwoFactorEnabled' => AdminLoginSecuritySettings::isTwoFactorEnabled(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
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

        $remember = (bool) $request->boolean('remember');
        $username = Str::lower($credentials['username']);

        $this->loginAccessBlocks->ensureLoginAllowed($request, LoginAccessBlock::GUARD_ADMIN, $username);

        if (! CaptchaService::validate($credentials['captcha'])) {
            $this->loginAccessBlocks->recordFailedAttempt($request, LoginAccessBlock::GUARD_ADMIN, $username);
            throw ValidationException::withMessages([
                'captcha' => 'کد تأیید نادرست است؛ برای کد جدید روی تصویر کلیک کنید.',
            ]);
        }

        /** @var Admin|null $admin */
        $admin = Admin::query()->where('username', $username)->first();

        if ($admin === null || ! Hash::check($credentials['password'], (string) $admin->password)) {
            $this->loginAccessBlocks->recordFailedAttempt($request, LoginAccessBlock::GUARD_ADMIN, $username);

            throw ValidationException::withMessages([
                'username' => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

        if (! $admin->is_active) {
            $this->loginAccessBlocks->recordFailedAttempt($request, LoginAccessBlock::GUARD_ADMIN, $username);

            throw ValidationException::withMessages([
                'username' => 'این حساب غیرفعال است.',
            ]);
        }

        if (AdminLoginSecuritySettings::isTwoFactorEnabled()) {
            try {
                $challenge = $this->loginTwoFactor->beginChallenge($admin, $request, $remember);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'no_mobile') {
                    throw ValidationException::withMessages([
                        'username' => 'ورود دو مرحله‌ای فعال است اما شماره موبایل معتبر در پروندهٔ این ادمین ثبت نشده است.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'username' => $e->getMessage() !== '' && $e->getMessage() !== 'sms_failed'
                        ? $e->getMessage()
                        : 'ارسال پیامک ورود ممکن نشد؛ لطفاً بعداً تلاش کنید.',
                ]);
            }

            return redirect()
                ->route('admin.login')
                ->withInput($request->only('username', 'remember'))
                ->with('login_2fa', $challenge);
        }

        return $this->completeAdminLogin($admin, $request, $remember, $username);
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            $this->activityLog->recordLogout($admin, $request);
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function completeAdminLogin(Admin $admin, Request $request, bool $remember, string $username): RedirectResponse
    {
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        $this->loginAccessBlocks->clearOnSuccessfulLogin($request, LoginAccessBlock::GUARD_ADMIN, $username);
        EnforcePortalSessionLifetime::touchSession($request, LoginAccessBlock::GUARD_ADMIN);

        $admin->forceFill([
            'login_count' => (int) $admin->login_count + 1,
            'last_login_at' => now(),
        ])->save();

        SendAdminLoginNotifySmsJob::dispatchAfterResponse((int) $admin->id);
        $this->activityLog->recordLogin($admin, $request);

        return redirect()->intended(route('admin.dashboard'));
    }
}
