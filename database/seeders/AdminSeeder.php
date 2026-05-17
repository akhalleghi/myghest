<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * ایجاد یا به‌روزرسانی حساب پیش‌فرض مدیر؛ ورود با `username` انجام می‌شود.
     */
    public function run(): void
    {
        $username = Str::lower((string) env('ADMIN_USERNAME', 'admin'));
        $password = (string) env('ADMIN_PASSWORD', 'changeme');
        $email = (string) env('ADMIN_EMAIL', 'admin@localhost');
        $name = (string) env('ADMIN_NAME', 'مدیر سیستم');

        $existing = Admin::query()
            ->where('username', $username)
            ->orWhere('email', $email)
            ->first();

        $payload = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'is_super_admin' => true,
        ];

        if ($existing instanceof Admin) {
            $existing->fill($payload)->save();

            return;
        }

        Admin::query()->create($payload);
    }
}
