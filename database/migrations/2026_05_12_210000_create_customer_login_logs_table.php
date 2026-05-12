<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('logged_in_at')->useCurrent();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('browser', 191)->nullable();
            $table->string('platform', 191)->nullable();
            $table->string('device_type', 32)->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_login_logs');
    }
};
