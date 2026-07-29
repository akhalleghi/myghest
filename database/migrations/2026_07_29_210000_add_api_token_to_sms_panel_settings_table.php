<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_panel_settings', function (Blueprint $table): void {
            $table->text('api_token')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('sms_panel_settings', function (Blueprint $table): void {
            $table->dropColumn('api_token');
        });
    }
};
