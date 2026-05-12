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
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
        }

        return trim($out);
    }
}
