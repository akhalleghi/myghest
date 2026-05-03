<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('name');
        });

        foreach (DB::table('admins')->select('id', 'email')->cursor() as $row) {
            $local = Str::before((string) $row->email, '@');
            $base = Str::lower(preg_replace('/[^a-z0-9._-]/', '', $local));
            $username = $base !== '' ? Str::limit($base, 64, '') : 'admin'.(string) $row->id;

            while (DB::table('admins')->where('username', $username)->where('id', '!=', $row->id)->exists()) {
                $username .= '_'.$row->id;
            }

            DB::table('admins')->where('id', $row->id)->update(['username' => $username]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
