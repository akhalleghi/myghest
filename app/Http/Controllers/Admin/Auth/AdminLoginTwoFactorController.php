<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforcePortalSessionLifetime;
use App\Jobs\SendAdminLoginNotifySmsJob;
use App\Models\LoginAccessBlock;
use App\Models\Admin;
use App\Services\Admin\AdminActivityLogService;
use App\Services\Auth\AdminLoginTwoFactorService;
use App\Services\Auth\LoginAccessBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class AdminLoginTwoFactorController extends Controller
{
    public function __construct(
        private readonly AdminLoginTwoFactorService $twoFactor,
        private readonly LoginAccessBlockService $loginAccessBlocks,
        private readonly AdminActivityLogService $activityLog,
    ) {}

    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_session' => ['required', 'string', 'uuid'],
        ]);

        try {
            $result = $this->twoFactor->resend((string) $validated['login_session'], $request);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(match ($e->getMessage()) {
                'resend_limit' => 'حداکثر تعداد ارسال مجدد استفاده شد؛ از ابتدا وارد شوید.',
                default => 'نشست ورود منقضی شده؛ دوباره نام کاربری و رمز را وارد کنید.',
            }, 422);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        return response()->json($result);
    }

    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'login_session' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        try {
            $verified = $this->twoFactor->verify(
                (string) $validated['login_session'],
                (string) $validated['code'],
                $request,
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return $this->errorResponse(match ($e->getMessage()) {
                    'invalid_code' => 'کد وارد شده صحیح نیست.',
                    'attempts_exceeded' => 'تعداد تلاش بیش از حد مجاز است؛ دوباره وارد شوید.',
                    default => 'نشست ورود منقضی شده؛ دوباره نام کاربری و رمز را وارد کنید.',
                }, 422);
            }

            throw ValidationException::withMessages([
                'code' => match ($e->getMessage()) {
                    'invalid_code' => 'کد وارد شده صحیح نیست.',
                    'attempts_exceeded' => 'تعداد تلاش بیش از حد مجاز است؛ دوباره وارد شوید.',
                    default => 'نشست ورود منقضی شده؛ دوباره تلاش کنید.',
                },
            ]);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return $this->rateOrSmsError($e);
            }

            throw ValidationException::withMessages([
                'code' => 'ارسال یا تأیید پیامک ممکن نشد؛ لطفاً بعداً تلاش کنید.',
            ]);
        }

        $admin = $verified['admin'];
        $remember = (bool) $verified['remember'];

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        $this->loginAccessBlocks->clearOnSuccessfulLogin(
            $request,
            LoginAccessBlock::GUARD_ADMIN,
            Str::lower((string) $admin->username),
        );
        EnforcePortalSessionLifetime::touchSession($request, LoginAccessBlock::GUARD_ADMIN);

        $admin->forceFill([
            'login_count' => (int) $admin->login_count + 1,
            'last_login_at' => now(),
        ])->save();

        SendAdminLoginNotifySmsJob::dispatchAfterResponse((int) $admin->id);
        if ($admin instanceof Admin) {
            $this->activityLog->recordLogin($admin, $request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'ورود با موفقیت انجام شد.',
                'redirect' => route('admin.dashboard'),
            ]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    private function rateOrSmsError(RuntimeException $e): JsonResponse
    {
        $msg = $e->getMessage();
        if (str_starts_with($msg, 'rate_limited:')) {
            $seconds = (int) substr($msg, strlen('rate_limited:'));

            return $this->errorResponse('تلاش زیاد؛ لطفاً '.$seconds.' ثانیه بعد دوباره تلاش کنید.', 429);
        }

        return $this->errorResponse('ارسال پیامک ممکن نشد؛ لطفاً بعداً تلاش کنید.', 503);
    }
}
