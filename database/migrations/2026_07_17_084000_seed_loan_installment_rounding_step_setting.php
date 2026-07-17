<?php

declare(strict_types=1);

use App\Support\LoanInstallmentRoundingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => LoanInstallmentRoundingSettings::SETTING_KEY_ROUNDING_STEP],
            [
                'value' => (string) LoanInstallmentRoundingSettings::ROUNDING_STEP_TOMAN,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', LoanInstallmentRoundingSettings::SETTING_KEY_ROUNDING_STEP)->delete();
    }
};
