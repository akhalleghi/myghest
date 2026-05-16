<?php

declare(strict_types=1);

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRecipient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * تیکت‌های قدیمی ادمین با چند گیرنده مشترک را به تیکت جدا برای هر مشتری تبدیل می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sharedTicketIds = DB::table('support_ticket_recipients')
            ->select('support_ticket_id')
            ->groupBy('support_ticket_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('support_ticket_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($sharedTicketIds === []) {
            return;
        }

        $tickets = SupportTicket::query()
            ->whereIn('id', $sharedTicketIds)
            ->whereNotNull('created_by_admin_id')
            ->with(['messages.attachments', 'recipients'])
            ->orderBy('id')
            ->get();

        foreach ($tickets as $ticket) {
            $this->splitTicketPerCustomer($ticket);
        }
    }

    public function down(): void
    {
        // غیرقابل برگشت امن — داده‌ها ادغام نمی‌شوند.
    }

    private function splitTicketPerCustomer(SupportTicket $ticket): void
    {
        $recipients = $ticket->recipients->sortBy('id')->values();
        if ($recipients->count() <= 1) {
            return;
        }

        $primaryRecipient = $recipients->first();
        $primaryCustomerId = (int) $primaryRecipient->customer_id;
        $others = $recipients->slice(1);

        foreach ($others as $recipient) {
            $customerId = (int) $recipient->customer_id;
            $newTicket = $this->cloneTicketShell($ticket);

            SupportTicketRecipient::query()->create([
                'support_ticket_id' => (int) $newTicket->id,
                'customer_id' => $customerId,
                'read_at' => $recipient->read_at,
            ]);

            foreach ($ticket->messages as $message) {
                if ($this->messageBelongsToCustomer($message, $customerId)) {
                    $this->cloneMessageToTicket($message, $newTicket);
                }
            }

            $recipient->delete();
        }

        foreach ($ticket->messages()->get() as $message) {
            if (! $this->messageBelongsToCustomer($message, $primaryCustomerId)) {
                foreach ($message->attachments as $attachment) {
                    $this->deleteAttachmentFile($attachment);
                    $attachment->delete();
                }
                $message->delete();
            }
        }

        $ticket->update([
            'recipient_mode' => SupportTicket::MODE_SINGLE,
        ]);
    }

    private function cloneTicketShell(SupportTicket $source): SupportTicket
    {
        return SupportTicket::query()->create([
            'subject' => (string) $source->subject,
            'status' => (string) $source->status,
            'recipient_mode' => SupportTicket::MODE_SINGLE,
            'created_by_admin_id' => $source->created_by_admin_id,
            'created_by_customer_id' => null,
            'last_message_at' => $source->last_message_at,
            'created_at' => $source->created_at,
            'updated_at' => $source->updated_at,
        ]);
    }

    private function messageBelongsToCustomer(SupportTicketMessage $message, int $customerId): bool
    {
        if ($message->sender_admin_id !== null) {
            return true;
        }

        return (int) $message->sender_customer_id === $customerId;
    }

    private function cloneMessageToTicket(SupportTicketMessage $source, SupportTicket $targetTicket): void
    {
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => (int) $targetTicket->id,
            'body_html' => (string) $source->body_html,
            'body_excerpt' => (string) $source->body_excerpt,
            'sender_admin_id' => $source->sender_admin_id,
            'sender_customer_id' => $source->sender_customer_id,
            'created_at' => $source->created_at,
            'updated_at' => $source->updated_at,
        ]);

        foreach ($source->attachments as $attachment) {
            $this->cloneAttachment($attachment, $message);
        }
    }

    private function cloneAttachment(SupportTicketAttachment $source, SupportTicketMessage $targetMessage): void
    {
        $fromPath = (string) $source->storage_path;
        if ($fromPath === '') {
            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($fromPath)) {
            return;
        }

        $ext = pathinfo($fromPath, PATHINFO_EXTENSION) ?: 'bin';
        $toPath = 'support-tickets/'.(int) $targetMessage->support_ticket_id.'/'.Str::lower(Str::random(20)).'.'.$ext;
        $disk->copy($fromPath, $toPath);

        SupportTicketAttachment::query()->create([
            'support_ticket_message_id' => (int) $targetMessage->id,
            'storage_path' => $toPath,
            'original_filename' => (string) $source->original_filename,
            'mime_type' => (string) $source->mime_type,
            'file_size' => (int) $source->file_size,
        ]);
    }

    private function deleteAttachmentFile(SupportTicketAttachment $attachment): void
    {
        $path = (string) $attachment->storage_path;
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
};
