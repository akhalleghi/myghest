<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templateId = DB::table('sms_templates')->where('template_key', 'default_customer_overdue_total')->value('id');
        $setting = DB::table('app_settings')->where('key', 'sms_reminder_overdue_template_id')->first();
        if ($templateId !== null && $setting !== null && (int) $setting->value > 0) {
            DB::table('app_settings')->where('id', $setting->id)->update(['value' => (string) $templateId, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep the administrator's current reminder configuration.
    }
};
