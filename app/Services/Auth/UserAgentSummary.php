<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * خلاصهٔ سبک از User-Agent بدون وابستگی خارجی.
 *
 * @return array{browser: string|null, platform: string|null, device_type: string}
 */
final class UserAgentSummary
{
    /**
     * @return array{browser: string|null, platform: string|null, device_type: string}
     */
    public static function fromUserAgent(string $ua): array
    {
        $ua = trim($ua);
        if ($ua === '') {
            return ['browser' => null, 'platform' => null, 'device_type' => 'unknown'];
        }

        $device = self::detectDevice($ua);
        $browser = self::detectBrowser($ua);
        $platform = self::detectPlatform($ua);

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $device,
        ];
    }

    private static function detectDevice(string $ua): string
    {
        if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit/i', $ua) === 1) {
            return 'bot';
        }
        if (preg_match('/tablet|ipad|playbook|silk|(android(?!.*mobile))/i', $ua) === 1) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|iemobile/i', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function detectBrowser(string $ua): ?string
    {
        $pairs = [
            'Edg/' => 'Microsoft Edge',
            'EdgiOS/' => 'Microsoft Edge',
            'OPR/' => 'Opera',
            'Opera' => 'Opera',
            'Chrome/' => 'Chrome',
            'CriOS/' => 'Chrome',
            'Firefox/' => 'Firefox',
            'FxiOS/' => 'Firefox',
            'Safari/' => 'Safari',
            'MSIE ' => 'Internet Explorer',
            'Trident/' => 'Internet Explorer',
        ];
        foreach ($pairs as $needle => $name) {
            if (stripos($ua, $needle) !== false) {
                if (preg_match('/'.preg_quote($needle, '/').'([\d.]+)/i', $ua, $m) === 1) {
                    return $name.' '.$m[1];
                }

                return $name;
            }
        }

        return null;
    }

    private static function detectPlatform(string $ua): ?string
    {
        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m) === 1) {
            return 'Windows '.$m[1];
        }
        if (stripos($ua, 'Android') !== false && preg_match('/Android ([\d._]+)/i', $ua, $m) === 1) {
            return 'Android '.$m[1];
        }
        if (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m) === 1) {
            return 'iOS '.str_replace('_', '.', $m[1]);
        }
        if (preg_match('/iPad.*?OS ([\d_]+)/i', $ua, $m) === 1) {
            return 'iPadOS '.str_replace('_', '.', $m[1]);
        }
        if (stripos($ua, 'Mac OS X') !== false && preg_match('/Mac OS X ([\d_]+)/i', $ua, $m) === 1) {
            return 'macOS '.str_replace('_', '.', $m[1]);
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'Linux';
        }

        return null;
    }
}
