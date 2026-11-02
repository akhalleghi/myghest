<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_free_send_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('source', 40);
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_free_send_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('sms_free_send_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('recipient', 20);
            $table->text('message_text');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('sms_log_id')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status', 'id'], 'sms_campaign_recipient_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_free_send_campaign_recipients');
        Schema::dropIfExists('sms_free_send_campaigns');
    }
};
