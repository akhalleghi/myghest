<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            // پروندهٔ وامی که این درخواست به آن «تبدیل» شده است.
            // nullOnDelete: اگر پروندهٔ وام بعداً حذف شد، درخواست همچنان به‌عنوان «هنوز تبدیل‌نشده» باقی بماند.
            $table->foreignId('customer_loan_file_id')
                ->nullable()
                ->after('expert_note_customer')
                ->constrained('customer_loan_files')
                ->nullOnDelete();

            $table->timestamp('converted_to_loan_at')
                ->nullable()
                ->after('customer_loan_file_id');

            $table->foreignId('converted_by_admin_id')
                ->nullable()
                ->after('converted_to_loan_at')
                ->constrained('admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loan_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_by_admin_id');
            $table->dropColumn('converted_to_loan_at');
            $table->dropConstrainedForeignId('customer_loan_file_id');
        });
    }
};
