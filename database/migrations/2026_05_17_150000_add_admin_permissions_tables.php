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
        Schema::table('admins', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('is_active');
        });

        if (Schema::hasTable('admins') && DB::table('admins')->where('id', 1)->exists()) {
            DB::table('admins')->where('id', 1)->update(['is_super_admin' => true]);
        }

        Schema::create('admin_permission_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('permission_key', 120);
            $table->timestamps();

            $table->unique(['admin_id', 'permission_key'], 'admin_perm_grant_unique');
            $table->index('permission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permission_grants');
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }
};
