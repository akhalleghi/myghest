<?php

declare(strict_types=1);

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

final class BankingHtmlSanitizer
{
    public static function clean(?string $html): string
    {
        $html ??= '';
        if (trim($html) === '') {
            return '';
        }

        // CKEditor may wrap tables in <figure>; HTMLPurifier core schema does not support <figure>/<figcaption>.
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
            'table[style]',
            'thead',
            'tbody',
            'tr',
            'th[style]',
            'td[style]',
            'colgroup',
            'col[style]',
            'blockquote[style]',
            'span[style]',
            'hr',
        ]));
        $config->set('CSS.AllowedProperties', implode(',', [
            'color',
            'background-color',
            'background',
            'font-size',
            'font-family',
            'font-weight',
            'font-style',
            'text-align',
            'text-decoration',
            'direction',
            'margin',
            'margin-left',
            'margin-right',
            'margin-top',
            'margin-bottom',
            'padding',
            'padding-left',
            'padding-right',
            'padding-top',
            'padding-bottom',
            'width',
            'max-width',
            'min-width',
            'border',
            'border-collapse',
            'border-color',
            'border-width',
            'border-style',
            'vertical-align',
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

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
