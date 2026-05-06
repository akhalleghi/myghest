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
            $table->string('sender_number', 32)->default('50003300')->after('domain_name');
        });
    }

    public function down(): void
    {
        Schema::table('sms_panel_settings', function (Blueprint $table): void {
            $table->dropColumn('sender_number');
        });
    }
};
