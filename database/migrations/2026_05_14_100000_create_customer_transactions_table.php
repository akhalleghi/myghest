<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('kind', 48);
            $table->string('status', 32);
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('amount_rial');
            $table->string('gateway_key', 24)->nullable();
            $table->unsignedBigInteger('track_id')->nullable();
            $table->string('bank_reference', 64)->nullable();
            $table->string('title', 190);
            $table->text('detail')->nullable();
            $table->json('meta')->nullable();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'cust_tx_cust_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['customer_id', 'created_at'], 'cust_tx_cust_created_idx');
            $table->index('track_id', 'cust_tx_track_idx');
            $table->unique(['source_type', 'source_id'], 'cust_tx_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
    }
};
