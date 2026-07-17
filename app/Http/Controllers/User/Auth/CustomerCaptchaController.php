<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Services\Admin\CaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CustomerCaptchaController extends Controller
{
    private function purposeFromRouteSegment(string $purpose): string
    {
        return match ($purpose) {
            'login' => CaptchaService::PURPOSE_USER_LOGIN,
            'forgot' => CaptchaService::PURPOSE_USER_FORGOT,
            'otp-login' => CaptchaService::PURPOSE_USER_OTP_LOGIN,
            default => CaptchaService::PURPOSE_USER_LOGIN,
        };
    }

    public function show(string $purpose): Response
    {
        $p = $this->purposeFromRouteSegment($purpose);
        $code = CaptchaService::issueNewCaptchaForRendering($p);
        $payload = CaptchaService::renderPngBinary($code);

        $isSvg = str_starts_with(ltrim($payload), '<svg');

        return response($payload, 200, [
            'Content-Type' => $isSvg ? 'image/svg+xml' : 'image/png',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'CDN-Cache-Control' => 'no-store',
            'Surrogate-Control' => 'no-store',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'in:login,forgot,otp-login'],
        ]);

        $p = $this->purposeFromRouteSegment($validated['purpose']);
        $code = CaptchaService::issueNewCaptchaForRendering($p);
        $payload = CaptchaService::renderPngBinary($code);

        $isSvg = str_starts_with(ltrim($payload), '<svg');
        $mime = $isSvg ? 'image/svg+xml' : 'image/png';

        return response()->json([
            'data_url' => sprintf('data:%s;base64,%s', $mime, base64_encode($payload)),
        ]);
    }
}
