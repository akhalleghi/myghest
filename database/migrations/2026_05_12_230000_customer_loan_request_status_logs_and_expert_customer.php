<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_loan_requests', 'expert_note_customer')) {
            Schema::table('customer_loan_requests', function (Blueprint $table): void {
                $table->text('expert_note_customer')->nullable()->after('expert_note');
            });
        }

        if (! Schema::hasTable('customer_loan_request_status_logs')) {
            Schema::create('customer_loan_request_status_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_loan_request_id');
                $table->string('actor_type', 16)->index();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('from_status', 64)->nullable()->index();
                $table->string('to_status', 64)->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['customer_loan_request_id', 'created_at'], 'clr_status_logs_req_created_idx');

                $table->foreign('customer_loan_request_id', 'clr_status_logs_req_fk')
                    ->references('id')->on('customer_loan_requests')->cascadeOnDelete();
                $table->foreign('admin_id', 'clr_status_logs_admin_fk')
                    ->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loan_request_status_logs');

        if (Schema::hasColumn('customer_loan_requests', 'expert_note_customer')) {
            Schema::table('customer_loan_requests', function (Blueprint $table): void {
                $table->dropColumn('expert_note_customer');
            });
        }
    }
};
