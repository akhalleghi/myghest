<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_panel_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64)->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->string('username', 120)->nullable();
            $table->text('password')->nullable();
            $table->string('domain_name', 120)->default('sepahansms');
            $table->string('last_connection_status', 24)->nullable();
            $table->text('last_connection_message')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_panel_settings');
    }
};
