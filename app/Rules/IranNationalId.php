<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IranNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('کد ملی معتبر نیست.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) !== 10) {
            $fail('کد ملی باید ۱۰ رقم باشد.');

            return;
        }

        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            $fail('کد ملی معتبر نیست.');

            return;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $check = (int) $digits[9];
        $valid = ($remainder < 2 && $check === $remainder)
            || ($remainder >= 2 && $check === (11 - $remainder));

        if (! $valid) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
