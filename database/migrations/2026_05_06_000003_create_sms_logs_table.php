<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('sms_panel', 120);
            $table->string('status', 32)->index();
            $table->dateTime('sent_at')->index();
            $table->text('message_text');
            $table->string('recipient', 32)->index();
            $table->string('type', 80)->index();
            $table->decimal('cost', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
