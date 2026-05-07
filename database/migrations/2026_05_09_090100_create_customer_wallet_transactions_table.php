<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('direction', ['deposit', 'withdraw']);
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('balance_after_toman');
            $table->string('description', 500)->nullable();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
    }
};
