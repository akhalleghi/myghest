<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول استاندارد notifications لاراول.
 *
 * چندریختی (notifiable_type/id) امکان ارسال هم‌زمان به Admin و Customer را با
 * trait `Notifiable` فراهم می‌کند. ساختار رسمی فریم‌ورک: id UUID + type + data JSON + read_at.
 *
 * @see https://laravel.com/docs/notifications#database-notifications
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
