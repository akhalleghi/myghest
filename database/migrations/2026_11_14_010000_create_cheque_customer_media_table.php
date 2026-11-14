<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_customer_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cheque_customer_id')->constrained('cheque_customers')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->unique(['cheque_customer_id', 'kind']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cheque_customer_media') && DB::table('cheque_customer_media')->exists()) {
            throw new RuntimeException('Refusing to drop cheque customer documents; archive them explicitly first.');
        }
        Schema::dropIfExists('cheque_customer_media');
    }
};
