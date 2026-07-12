<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('father_name', 120)->nullable()->change();
            $table->string('national_id', 10)->nullable()->change();
            $table->string('city', 120)->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('postal_code', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('father_name', 120)->nullable(false)->change();
            $table->string('national_id', 10)->nullable(false)->change();
            $table->string('city', 120)->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('postal_code', 16)->nullable(false)->change();
        });
    }
};
