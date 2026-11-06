<?php

declare(strict_types=1);

namespace App\Support;

final class ExcelUnicodeCell
{
    public static function clean(string $value): string
    {
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);
        $trimmed = ltrim($value);
        if (preg_match('/^[=+@-]/u', $trimmed) && ! preg_match('/^-?\d+(?:\.\d+)?$/D', $trimmed)) {
            return "'".$value;
        }
        if (preg_match('/^(?:0\d+|\d{16,})$/D', $trimmed)) {
            return "'".$value;
        }

        return $value;
    }
}
