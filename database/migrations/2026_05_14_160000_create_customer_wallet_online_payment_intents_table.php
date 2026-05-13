<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallet_online_payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('expected_amount_toman');
            $table->unsignedBigInteger('expected_amount_rial');
            $table->unsignedBigInteger('track_id')->nullable();
            $table->string('status', 24)->default('created');
            $table->string('gateway_key', 24)->default('zibal');
            $table->string('zibal_ref_number', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique('track_id');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_online_payment_intents');
    }
};
