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
            $table->string('first_name', 80)->nullable()->after('name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('mobile', 11)->nullable()->after('last_name');
            $table->unsignedInteger('login_count')->default(0)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('login_count');
        });

        foreach (DB::table('admins')->select('id', 'name')->cursor() as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $parts = preg_split('/\s+/u', $name, 2) ?: [];
            $first = $parts[0] ?? $name;
            $last = $parts[1] ?? '';
            DB::table('admins')->where('id', $row->id)->update([
                'first_name' => $first,
                'last_name' => $last,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'mobile', 'login_count', 'last_login_at']);
        });
    }
};
