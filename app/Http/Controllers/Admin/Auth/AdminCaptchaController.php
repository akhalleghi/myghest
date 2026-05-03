<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\Admin\CaptchaService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AdminCaptchaController extends Controller
{
    /**
     * تولید تصویر کپچا روی سرور (GD یا در نبود آن SVG).
     */
    public function show(): Response
    {
        $code = CaptchaService::issueNewCaptchaForRendering();
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

    /**
     * کپچای تازه به‌صورت Data URL؛ برای بارگذاری مجدد از طریق کلیک روی تصویر.
     */
    public function refresh(): JsonResponse
    {
        $code = CaptchaService::issueNewCaptchaForRendering();
        $payload = CaptchaService::renderPngBinary($code);

        $isSvg = str_starts_with(ltrim($payload), '<svg');
        $mime = $isSvg ? 'image/svg+xml' : 'image/png';

        return response()->json([
            'data_url' => sprintf('data:%s;base64,%s', $mime, base64_encode($payload)),
        ]);
    }
}
