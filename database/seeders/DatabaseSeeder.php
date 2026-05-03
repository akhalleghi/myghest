<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
        نمونه کاربر عمومی پیش‌فرض Laravel؛ برای سپرده‌ها در گام بعدی در صورت نیاز فعال کنید.

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        */

        $this->call(AdminSeeder::class);
    }
}
