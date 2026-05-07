<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_wallet_transactions', function (Blueprint $table): void {
            $table->uuid('request_uuid')->nullable()->unique()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('customer_wallet_transactions', function (Blueprint $table): void {
            $table->dropUnique(['request_uuid']);
            $table->dropColumn('request_uuid');
        });
    }
};
