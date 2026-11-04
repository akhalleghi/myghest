<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerCodeGenerator
{
    private const SETTING_KEY = 'customer_code_sequence';

    /** Reserve inside the customer's transaction so failed registrations roll back the counter. */
    public function allocate(?string $requestedCode = null): string
    {
        $requestedCode = trim((string) $requestedCode);
        if ($requestedCode !== '' && ! $this->numeric($requestedCode)) {
            return $requestedCode;
        }

        return DB::transaction(function () use ($requestedCode): string {
            DB::table('app_settings')->insertOrIgnore([
                'key' => self::SETTING_KEY, 'value' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('app_settings')->where('key', self::SETTING_KEY)->lockForUpdate()->first();
            $last = $row->value === null ? $this->initialValue($requestedCode) : (string) $row->value;
            if (! $this->numeric($last)) {
                throw ValidationException::withMessages(['customer_code' => 'شمارنده کد مشتری معتبر نیست؛ تنظیمات سامانه بررسی شود.']);
            }
            $last = $this->normalize($last);
            if ($requestedCode !== '') {
                $next = $this->maximum($last, $this->normalize($requestedCode));
                $code = $requestedCode;
            } else {
                $next = $this->increment($last);
                while (Customer::query()->where('customer_code', $next)->exists()) {
                    $next = $this->increment($next);
                }
                $code = $next;
            }
            DB::table('app_settings')->where('key', self::SETTING_KEY)->update(['value' => $next, 'updated_at' => now()]);

            return $code;
        });
    }

    private function initialValue(string $requested): string
    {
        $maximum = $requested !== '' ? $this->normalize($requested) : null;
        // Only the first allocation scans existing codes. Legacy alphanumeric codes are never rewritten.
        foreach (Customer::query()->select('customer_code')->cursor() as $customer) {
            $code = (string) $customer->customer_code;
            if ($this->numeric($code)) {
                $number = $this->normalize($code);
                $maximum = $maximum === null ? $number : $this->maximum($maximum, $number);
            }
        }

        return $maximum ?? (string) (Customer::query()->max('id') ?? 0);
    }

    private function numeric(string $value): bool
    {
        return preg_match('/\A[0-9]+\z/', $value) === 1;
    }

    private function normalize(string $value): string
    {
        return ltrim($value, '0') ?: '0';
    }

    private function maximum(string $left, string $right): string
    {
        return strlen($left) > strlen($right) || (strlen($left) === strlen($right) && strcmp($left, $right) > 0) ? $left : $right;
    }

    private function increment(string $value): string
    {
        // Decimal strings avoid overflow for previously imported numeric codes longer than a PHP integer.
        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            if ($value[$index] !== '9') {
                $value[$index] = (string) ((int) $value[$index] + 1);

                return $value;
            }
            $value[$index] = '0';
        }
        if (strlen($value) >= 40) {
            throw ValidationException::withMessages(['customer_code' => 'ظرفیت شماره‌گذاری کد مشتری تکمیل شده است.']);
        }

        return '1'.$value;
    }
}
