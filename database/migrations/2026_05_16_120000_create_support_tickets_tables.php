<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject', 255);
            $table->string('status', 24)->default('open');
            $table->string('recipient_mode', 16)->default('single');
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('created_by_customer_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_admin_id', 'st_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();
            $table->foreign('created_by_customer_id', 'st_customer_fk')
                ->references('id')->on('customers')->nullOnDelete();

            $table->index(['created_by_admin_id', 'last_message_at'], 'st_admin_last_msg_idx');
            $table->index(['created_by_customer_id', 'last_message_at'], 'st_customer_last_msg_idx');
            $table->index('status');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->longText('body_html');
            $table->string('body_excerpt', 500);
            $table->unsignedBigInteger('sender_admin_id')->nullable();
            $table->unsignedBigInteger('sender_customer_id')->nullable();
            $table->timestamps();

            $table->foreign('support_ticket_id', 'stm_ticket_fk')
                ->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('sender_admin_id', 'stm_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();
            $table->foreign('sender_customer_id', 'stm_customer_fk')
                ->references('id')->on('customers')->nullOnDelete();

            $table->index(['support_ticket_id', 'created_at'], 'stm_ticket_created_idx');
        });

        Schema::create('support_ticket_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('customer_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('support_ticket_id', 'str_ticket_fk')
                ->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('customer_id', 'str_customer_fk')
                ->references('id')->on('customers')->cascadeOnDelete();

            $table->unique(['support_ticket_id', 'customer_id'], 'str_ticket_customer_uq');
            $table->index(['customer_id', 'read_at'], 'str_customer_read_idx');
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_message_id');
            $table->string('storage_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('support_ticket_message_id', 'sta_message_fk')
                ->references('id')->on('support_ticket_messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_recipients');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
