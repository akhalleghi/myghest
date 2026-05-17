<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforcePortalSessionLifetime;
use App\Models\LoginAccessBlock;
use App\Services\Auth\CustomerLoginLogService;
use App\Services\Auth\CustomerLoginTwoFactorService;
use App\Services\Auth\LoginAccessBlockService;
use App\Services\Sms\PortalAdminSmsDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

final class CustomerLoginTwoFactorController extends Controller
{
    public function __construct(
        private readonly CustomerLoginTwoFactorService $twoFactor,
        private readonly CustomerLoginLogService $loginLogService,
        private readonly LoginAccessBlockService $loginAccessBlocks,
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

    public function verify(Request $request): JsonResponse
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
            return $this->errorResponse(match ($e->getMessage()) {
                'invalid_code' => 'کد وارد شده صحیح نیست.',
                'attempts_exceeded' => 'تعداد تلاش بیش از حد مجاز است؛ دوباره وارد شوید.',
                default => 'نشست ورود منقضی شده؛ دوباره نام کاربری و رمز را وارد کنید.',
            }, 422);
        } catch (RuntimeException $e) {
            return $this->rateOrSmsError($e);
        }

        $customer = $verified['customer'];
        $remember = (bool) $verified['remember'];

        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        $this->loginAccessBlocks->clearOnSuccessfulLogin(
            $request,
            LoginAccessBlock::GUARD_CUSTOMER,
            (string) $customer->username,
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

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
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

        if ($msg === 'sms_failed' || $msg !== '') {
            return response()->json([
                'message' => $msg !== 'sms_failed' ? $msg : 'ارسال پیامک ممکن نشد؛ بعداً تلاش کنید.',
            ], 422);
        }

        return response()->json(['message' => 'خطای غیرمنتظره؛ دوباره تلاش کنید.'], 500);
    }
}
