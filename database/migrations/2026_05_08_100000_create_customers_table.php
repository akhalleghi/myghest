<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 40)->unique();
            $table->string('username', 32)->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('father_name', 120);
            $table->string('national_id', 10)->unique();
            $table->string('mobile', 16)->unique();
            $table->string('phone_landline', 32)->nullable();
            $table->date('membership_at')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email', 191)->nullable()->unique();
            $table->string('password');
            $table->string('city', 120);
            $table->text('address');
            $table->string('postal_code', 16);
            $table->timestamp('credentials_sms_sent_at')->nullable();
            $table->timestamps();

            $table->index('mobile');
            $table->index('national_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
