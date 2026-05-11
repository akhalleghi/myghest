<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_deposit_declarations', function (Blueprint $table): void {
            $table->timestamp('review_acknowledged_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_deposit_declarations', function (Blueprint $table): void {
            $table->dropColumn('review_acknowledged_at');
        });
    }
};
