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
        Schema::create('loan_request_status_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title', 191);
            $table->string('stage_slot', 64)->nullable()->index();
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->boolean('is_mutable')->default(true);
            $table->boolean('allow_duplicate_request')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['code' => 'initial', 'title' => 'ایجاد اولیه', 'stage_slot' => 'after_initial', 'sort_order' => 10],
            ['code' => 'documents_complete', 'title' => 'تکمیل مدارک', 'stage_slot' => 'after_documents', 'sort_order' => 20],
            ['code' => 'documents_incomplete', 'title' => 'مدارک ناقص تکمیل مدارک', 'stage_slot' => 'after_documents', 'sort_order' => 30],
            ['code' => 'pending_expert_review', 'title' => 'در انتظار بررسی توسط کارشناس', 'stage_slot' => 'after_expert', 'sort_order' => 40],
            ['code' => 'needs_followup', 'title' => 'نیاز به پیگیری', 'stage_slot' => 'after_expert', 'sort_order' => 50],
            ['code' => 'expert_re_review', 'title' => 'بررسی مجدد کارشناس', 'stage_slot' => 'after_expert', 'sort_order' => 60],
            ['code' => 'rejected', 'title' => 'رد شده', 'stage_slot' => 'terminal', 'sort_order' => 70],
            ['code' => 'prioritized', 'title' => 'اولویت بندی شده', 'stage_slot' => 'pre_disbursement', 'sort_order' => 80],
            ['code' => 'paid', 'title' => 'پرداخت شده', 'stage_slot' => 'terminal', 'sort_order' => 90],
        ];

        foreach ($rows as $r) {
            DB::table('loan_request_status_definitions')->insert([
                'code' => $r['code'],
                'title' => $r['title'],
                'stage_slot' => $r['stage_slot'],
                'sms_template_id' => null,
                'is_mutable' => true,
                'allow_duplicate_request' => false,
                'sort_order' => $r['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('customer_loan_requests')) {
            $map = [
                'pending_review' => 'pending_expert_review',
                'under_review' => 'expert_re_review',
                'approved' => 'prioritized',
                'rejected' => 'rejected',
                'withdrawn' => 'initial',
            ];
            foreach ($map as $from => $to) {
                DB::table('customer_loan_requests')->where('status', $from)->update(['status' => $to]);
            }

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `customer_loan_requests` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'initial'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_loan_requests')) {
            $map = [
                'pending_expert_review' => 'pending_review',
                'expert_re_review' => 'under_review',
                'prioritized' => 'approved',
                'rejected' => 'rejected',
                'initial' => 'withdrawn',
            ];
            foreach ($map as $from => $to) {
                DB::table('customer_loan_requests')->where('status', $from)->update(['status' => $to]);
            }

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `customer_loan_requests` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'pending_review'");
            }
        }

        Schema::dropIfExists('loan_request_status_definitions');
    }
};
