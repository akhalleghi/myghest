<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_access_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('guard', 20);
            $table->string('username', 64);
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('blocked_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['guard', 'is_active', 'blocked_at']);
            $table->index(['guard', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_access_blocks');
    }
};
