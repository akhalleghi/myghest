<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_types', function (Blueprint $table) {
            $table->boolean('plan_list_enabled')->default(false)->after('registration_suspended_message');
            $table->string('plan_image_path', 512)->nullable()->after('plan_list_enabled');
            $table->string('plan_title', 255)->nullable()->after('plan_image_path');
            $table->text('plan_summary')->nullable()->after('plan_title');
            $table->longText('plan_body')->nullable()->after('plan_summary');
        });
    }

    public function down(): void
    {
        Schema::table('loan_types', function (Blueprint $table) {
            $table->dropColumn([
                'plan_list_enabled',
                'plan_image_path',
                'plan_title',
                'plan_summary',
                'plan_body',
            ]);
        });
    }
};
