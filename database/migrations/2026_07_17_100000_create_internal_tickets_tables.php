<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject', 255);
            $table->string('status', 24)->default('created');
            $table->string('recipient_mode', 16)->default('single');
            $table->unsignedBigInteger('created_by_admin_id');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_admin_id', 'it_creator_fk')
                ->references('id')->on('admins')->cascadeOnDelete();

            $table->index(['created_by_admin_id', 'last_message_at'], 'it_creator_last_msg_idx');
            $table->index('status');
        });

        Schema::create('internal_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('internal_ticket_id');
            $table->longText('body_html');
            $table->string('body_excerpt', 500);
            $table->unsignedBigInteger('sender_admin_id');
            $table->timestamps();

            $table->foreign('internal_ticket_id', 'itm_ticket_fk')
                ->references('id')->on('internal_tickets')->cascadeOnDelete();
            $table->foreign('sender_admin_id', 'itm_sender_fk')
                ->references('id')->on('admins')->cascadeOnDelete();

            $table->index(['internal_ticket_id', 'created_at'], 'itm_ticket_created_idx');
        });

        Schema::create('internal_ticket_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('internal_ticket_id');
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('internal_ticket_id', 'itr_ticket_fk')
                ->references('id')->on('internal_tickets')->cascadeOnDelete();
            $table->foreign('admin_id', 'itr_admin_fk')
                ->references('id')->on('admins')->cascadeOnDelete();

            $table->unique(['internal_ticket_id', 'admin_id'], 'itr_ticket_admin_uq');
            $table->index(['admin_id', 'read_at'], 'itr_admin_read_idx');
        });

        Schema::create('internal_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('internal_ticket_message_id');
            $table->string('storage_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('internal_ticket_message_id', 'ita_message_fk')
                ->references('id')->on('internal_ticket_messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_ticket_attachments');
        Schema::dropIfExists('internal_ticket_recipients');
        Schema::dropIfExists('internal_ticket_messages');
        Schema::dropIfExists('internal_tickets');
    }
};
