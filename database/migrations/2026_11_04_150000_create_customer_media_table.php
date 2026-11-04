<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->unique(['customer_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_media');
    }
};
