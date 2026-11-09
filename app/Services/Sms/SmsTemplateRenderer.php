<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsTemplateRenderer
{
    /**
     * @param  array<string, string|int|float>  $vars
     */
    public function render(string $body, array $vars): string
    {
        if (preg_match('/\{\{\s*app_url\s*\}\}/i', $body) === 1) {
            $vars['app_url'] = route('customer.login');
        }

        $out = $body;
        foreach ($vars as $k => $v) {
            $replacement = (string) $v;
            $out = preg_replace_callback(
                '/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i',
                static fn (array $matches): string => $replacement,
                $out,
            ) ?? $out;
        }

        return trim($out);
    }
}
