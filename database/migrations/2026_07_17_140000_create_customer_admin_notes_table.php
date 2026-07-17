<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_admin_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('admin_id');
            $table->text('body');
            $table->timestamps();

            $table->foreign('customer_id', 'can_customer_fk')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('admin_id', 'can_admin_fk')
                ->references('id')->on('admins')->cascadeOnDelete();

            $table->index(['customer_id', 'created_at'], 'can_customer_created_idx');
            $table->index(['admin_id', 'created_at'], 'can_admin_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_admin_notes');
    }
};
