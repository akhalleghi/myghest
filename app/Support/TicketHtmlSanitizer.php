<?php

declare(strict_types=1);

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/** پاک‌سازی HTML تیکت (متن CKEditor) قبل از ذخیره و نمایش. */
final class TicketHtmlSanitizer
{
    public static function clean(?string $html): string
    {
        $html ??= '';
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('#<figure\b[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#</figure>#i', '', $html) ?? $html;
        $html = preg_replace('#<figcaption\b[^>]*>.*?</figcaption>#is', '', $html) ?? $html;

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[style]',
            'br',
            'strong',
            'b',
            'em',
            'i',
            'u',
            'sub',
            'sup',
            'a[href|title|target|rel]',
            'ul[style]',
            'ol[style]',
            'li[style]',
            'h2[style]',
            'h3[style]',
            'h4[style]',
            'blockquote[style]',
            'span[style]',
            'hr',
        ]));
        $config->set('CSS.AllowedProperties', implode(',', [
            'color',
            'background-color',
            'font-size',
            'font-weight',
            'font-style',
            'text-align',
            'text-decoration',
            'direction',
            'margin',
            'padding',
            'line-height',
        ]));
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($html);
    }

    public static function excerptFromHtml(string $html, int $maxLen = 140): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($plain === '') {
            return '—';
        }
        if (mb_strlen($plain) <= $maxLen) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $maxLen - 1)).'…';
    }
}
