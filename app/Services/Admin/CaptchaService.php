<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\Session;

final class CaptchaService
{
    public const PURPOSE_ADMIN_LOGIN = 'admin_login';

    public const PURPOSE_USER_LOGIN = 'user_login';

    public const PURPOSE_USER_FORGOT = 'user_forgot';

    private const SESSION_KEYS = [
        self::PURPOSE_ADMIN_LOGIN => 'security.admin_login_captcha',
        self::PURPOSE_USER_LOGIN => 'security.user_login_captcha',
        self::PURPOSE_USER_FORGOT => 'security.user_forgot_captcha',
    ];

    public static function issueNewCaptchaForRendering(string $purpose = self::PURPOSE_ADMIN_LOGIN): string
    {
        $code = self::randomCode(5);

        Session::put(
            self::sessionKey($purpose),
            hash_hmac('sha256', self::normalize($code), config('app.key')),
        );

        return $code;
    }

    public static function validate(?string $userInput, string $purpose = self::PURPOSE_ADMIN_LOGIN): bool
    {
        $stored = Session::get(self::sessionKey($purpose));
        Session::forget(self::sessionKey($purpose));

        if (! is_string($stored) || ! is_string($userInput)) {
            return false;
        }

        $expected = hash_hmac('sha256', self::normalize($userInput), config('app.key'));

        return hash_equals($stored, $expected);
    }

    private static function sessionKey(string $purpose): string
    {
        return self::SESSION_KEYS[$purpose] ?? self::SESSION_KEYS[self::PURPOSE_ADMIN_LOGIN];
    }

    /**
     * نرمال‌سازی شامل تبدیل اعداد فارسی/عربی به لاتنی است تا ورود کلید فارسی هم پذیرفته شود.
     */
    private static function normalize(string $input): string
    {
        return strtolower(self::digitsToAscii(trim($input)));
    }

    private static function digitsToAscii(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $step = str_replace($persian, $latin, $value);

        return str_replace($arabic, $latin, $step);
    }

    private static function randomCode(int $length): string
    {
        $charset = 'abcdefghjkmpqrstuvwxyz23456789';

        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return $out;
    }

    public static function renderPngBinary(string $code): string
    {
        if (! extension_loaded('gd')) {
            return self::renderSvgRasterizable($code);
        }

        $font = self::iranSansFanumTtfPath();
        if ($font !== null && function_exists('imagettftext')) {
            $binary = self::renderGdWithTtf($code, $font);
            if ($binary !== '') {
                return $binary;
            }
        }

        return self::renderGdWithBuiltinFont($code);
    }

    private static function iranSansFanumTtfPath(): ?string
    {
        $path = public_path('fonts/iransans/iransans-fanum-400.ttf');

        return is_readable($path) ? $path : null;
    }

    private static function renderGdWithTtf(string $code, string $fontPath): string
    {
        $width = 160;
        $height = 48;

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return '';
        }

        $bg = imagecolorallocate($im, 240, 247, 255);
        $fg = imagecolorallocate($im, 22, 40, 70);
        $noise1 = imagecolorallocate($im, 173, 200, 230);
        $noise2 = imagecolorallocate($im, 207, 224, 244);

        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 8; $i++) {
            imageline(
                $im,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, 1) ? $noise1 : $noise2,
            );
        }

        $fontSize = (int) round(20);
        $x = 20;
        $baseY = 34;

        foreach (str_split($code) as $ch) {
            $angle = random_int(-9, 9);
            $ox = random_int(-1, 1);
            $oy = random_int(-1, 2);

            imagettftext(
                $im,
                $fontSize,
                $angle,
                $x + $ox,
                $baseY + $oy,
                $fg,
                $fontPath,
                $ch,
            );

            $x += random_int(20, 26);
        }

        ob_start();
        imagepng($im);
        $binary = (string) ob_get_clean();

        imagedestroy($im);

        return $binary;
    }

    private static function renderGdWithBuiltinFont(string $code): string
    {
        $width = 160;
        $height = 48;

        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return self::renderSvgRasterizable($code);
        }

        $bg = imagecolorallocate($im, 240, 247, 255);
        $fg = imagecolorallocate($im, 22, 40, 70);
        $noise1 = imagecolorallocate($im, 173, 200, 230);
        $noise2 = imagecolorallocate($im, 207, 224, 244);

        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 9; $i++) {
            imageline(
                $im,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, 1) ? $noise1 : $noise2,
            );
        }

        $fontSize = 5;
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        $textWidth = (strlen($code) + 2) * $charWidth;

        $sx = intdiv(max(8, $width - $textWidth), 2);
        $sy = intdiv(max(8, $height - $charHeight), 2);

        foreach (str_split($code) as $idx => $ch) {
            $ox = random_int(-2, 2);
            $oy = random_int(-2, 2);
            imagestring($im, $fontSize, $sx + $idx * ($charWidth + 3) + $ox, $sy + $oy, $ch, $fg);
        }

        ob_start();
        imagepng($im);
        $binary = (string) ob_get_clean();

        imagedestroy($im);

        return $binary !== '' ? $binary : self::renderSvgRasterizable($code);
    }

    private static function renderSvgRasterizable(string $code): string
    {
        $chars = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $w = 160;
        $h = 48;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <defs>
    <style type="text/css"><![CDATA[
      @font-face {
        font-family: 'IRANSans';
        font-weight: 400;
        src: url('/fonts/iransans/iransans-fanum-400.woff2') format('woff2'), url('/fonts/iransans/iransans-fanum-400.woff') format('woff'), url('/fonts/iransans/iransans-fanum-400.ttf') format('truetype');
        font-display: swap;
      }
    ]]></style>
  </defs>
  <rect width="100%" height="100%" fill="#f0f7ff"/>
  <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle"
    font-family="IRANSans, system-ui, sans-serif" font-size="20" fill="#16284a" letter-spacing="0.12em">{$chars}</text>
</svg>
SVG;
    }
}
