<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Admin $admin */
        $admin = $this->route('admin');

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('admins', 'username')->ignore($admin->id),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_keys' => ['nullable', 'array'],
            'permission_keys.*' => ['string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'نام کاربری الزامی است.',
            'username.regex' => 'نام کاربری تنها شامل حرف انگلیسی، عدد، نقطه، زیرخط و خط تیره است.',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            'first_name.required' => 'نام الزامی است.',
            'last_name.required' => 'نام خانوادگی الزامی است.',
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'password.min' => 'کلمه عبور حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار کلمه عبور با کلمه عبور یکسان نیست.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'mobile' => $this->normalizeMobile((string) $this->input('mobile')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizeMobile(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
}
