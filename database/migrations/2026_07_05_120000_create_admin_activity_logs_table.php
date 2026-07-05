<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('action', 64)->index();
            $table->text('description');
            $table->string('route_name', 191)->nullable()->index();
            $table->string('http_method', 10)->nullable();
            $table->string('url_path', 500)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('browser', 191)->nullable();
            $table->string('platform', 191)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['admin_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
