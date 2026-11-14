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
        Schema::create('cheque_customers', function (Blueprint $t): void {
            $t->id();
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->string('father_name', 120)->nullable();
            $t->string('national_id', 10)->nullable()->unique();
            $t->string('mobile', 11)->unique();
            $t->string('phone_landline', 32)->nullable();
            $t->string('city', 120)->nullable();
            $t->text('address')->nullable();
            $t->string('postal_code', 10)->nullable();
            $t->string('email')->nullable();
            $t->date('membership_at');
            $t->timestamp('mobile_verified_at')->nullable();
            $t->boolean('sms_enabled')->default(true);
            $t->boolean('auto_reminders_enabled')->default(false);
            $t->unsignedBigInteger('wallet_balance_toman')->default(0);
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('cheque_sales', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cheque_customer_id')->constrained('cheque_customers')->restrictOnDelete();
            $t->string('code', 50)->unique();
            $t->date('purchased_at')->index();
            $t->date('disbursement_due_date')->nullable();
            $t->unsignedBigInteger('purchase_amount_toman');
            $t->unsignedInteger('months');
            $t->decimal('interest_rate', 8, 4)->default(0);
            $t->string('profit_method', 16)->default('monthly');
            $t->unsignedBigInteger('profit_toman');
            $t->unsignedBigInteger('down_payment_toman')->default(0);
            $t->unsignedBigInteger('discount_toman')->default(0);
            $t->unsignedBigInteger('total_toman');
            $t->decimal('daily_late_coefficient', 12, 8)->default(0);
            $t->decimal('daily_early_coefficient', 12, 8)->default(0);
            $t->json('owner_details');
            $t->boolean('guarantee_by_self')->default(true);
            $t->timestamp('owner_verified_at')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('cheque_instruments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cheque_sale_id')->constrained('cheque_sales')->restrictOnDelete();
            $t->string('sayad_number', 16)->unique();
            $t->string('serial', 64);
            $t->string('bank_name', 120);
            $t->string('branch', 120)->nullable();
            $t->unsignedBigInteger('amount_toman');
            $t->date('due_date')->index();
            $t->string('folder', 24)->default('active')->index();
            $t->string('custody', 16)->default('store');
            $t->json('destination')->nullable();
            $t->date('bounced_at')->nullable();
            $t->date('cleared_at')->nullable();
            $t->text('note')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('cheque_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cheque_instrument_id')->constrained('cheque_instruments')->restrictOnDelete();
            $t->uuid('request_uuid')->unique();
            $t->unsignedBigInteger('amount_toman');
            $t->unsignedBigInteger('principal_toman');
            $t->unsignedBigInteger('late_fee_toman');
            $t->unsignedBigInteger('early_benefit_toman');
            $t->date('paid_at');
            $t->string('method', 24);
            $t->text('note')->nullable();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('cheque_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cheque_customer_id')->constrained('cheque_customers')->restrictOnDelete();
            $t->foreignId('cheque_instrument_id')->nullable()->constrained('cheque_instruments')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('payload');
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
        });
        foreach ([
            'cheque_received' => ['ثبت چک خرید', '{{customer_name}} گرامی، چک {{cheque_number}} به مبلغ {{cheque_amount}} با سررسید {{cheque_due_date}} در پرونده {{cheque_sale_code}} ثبت شد.'],
            'cheque_cleared' => ['تشکر وصول چک', '{{customer_name}} گرامی، پرداخت چک {{cheque_number}} ثبت و بدهی آن تسویه شد. از خوش‌حسابی شما سپاسگزاریم.'],
            'cheque_bounced' => ['اخطار برگشت چک', '{{customer_name}} گرامی، چک {{cheque_number}} برگشت خورده است. مانده بدهی با دیرکرد تا امروز: {{cheque_debt}}. لطفاً جهت تسویه اقدام فرمایید.'],
            'cheque_due' => ['یادآوری سررسید چک', '{{customer_name}} گرامی، سررسید چک {{cheque_number}} به مبلغ {{cheque_amount}} در تاریخ {{cheque_due_date}} است.'],
            'cheque_auto_reminder' => ['یادآوری تجمیعی فروش چکی', '{{customer_name}} گرامی، مجموع مانده چک‌های سررسید امروز و چک‌های برگشتی شما با دیرکرد تا امروز: {{cheque_debt}}. لطفاً جهت تأمین موجودی یا تسویه اقدام فرمایید.'],
            'cheque_identity_otp' => ['احراز هویت مشتری چکی', '{{customer_name}} گرامی، کد تأیید شماره موبایل: {{code}}'],
            'cheque_owner_otp' => ['تأیید دارنده چک خرید', '{{customer_name}} گرامی، کد تأیید چک‌های خرید با مبلغ کل {{cheque_amount}}: {{code}}'],
            'cheque_return_otp' => ['تأیید عودت چک خرید', '{{customer_name}} گرامی، کد تأیید عودت چک {{cheque_number}}: {{code}}'],
        ] as $key => [$title, $body]) {
            DB::table('sms_templates')->insertOrIgnore([
                'template_key' => $key, 'title' => $title, 'body' => $body, 'is_system' => true,
                'category' => 'cheque_sales', 'placeholders' => json_encode(['customer_name', 'code', 'cheque_number', 'cheque_amount', 'cheque_due_date', 'cheque_debt', 'cheque_sale_code']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cheque_customers') && DB::table('cheque_customers')->exists()) {
            throw new RuntimeException('Refusing to drop cheque financial records. Back up and archive them explicitly before rollback.');
        }
        // SMS templates are deliberately retained, including administrator edits.
        foreach (['cheque_events', 'cheque_receipts', 'cheque_instruments', 'cheque_sales', 'cheque_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
