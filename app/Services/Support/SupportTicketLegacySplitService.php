<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * جداسازی تیکت‌های قدیمی ادمین که چند گیرنده روی یک ردیف داشتند.
 */
final class SupportTicketLegacySplitService
{
    public function __construct(
        private readonly SupportTicketMessageWriter $writer,
    ) {}

    public function splitAllSharedAdminTickets(): int
    {
        $splitCount = 0;

        $ticketIds = SupportTicketRecipient::query()
            ->select('support_ticket_id')
            ->groupBy('support_ticket_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('support_ticket_id');

        foreach ($ticketIds as $ticketId) {
            $ticket = SupportTicket::query()
                ->with(['messages.attachments', 'recipients'])
                ->find((int) $ticketId);

            if ($ticket === null || $ticket->created_by_admin_id === null) {
                continue;
            }

            DB::transaction(function () use ($ticket, &$splitCount): void {
                $recipients = $ticket->recipients->sortBy('id')->values();
                if ($recipients->count() <= 1) {
                    return;
                }

                $primaryRecipient = $recipients->first();
                $primaryCustomerId = (int) $primaryRecipient->customer_id;

                foreach ($recipients->slice(1) as $recipient) {
                    $this->splitRecipientToOwnTicket($ticket, $recipient);
                    $splitCount++;
                }

                $this->retainOnlyPrimaryCustomerMessages($ticket, $primaryCustomerId);
            });
        }

        return $splitCount;
    }

    private function splitRecipientToOwnTicket(SupportTicket $source, SupportTicketRecipient $recipient): void
    {
        $customerId = (int) $recipient->customer_id;
        $now = $source->last_message_at ?? now();

        $newTicket = SupportTicket::query()->create([
            'subject' => (string) $source->subject,
            'status' => (string) $source->status,
            'recipient_mode' => SupportTicket::MODE_SINGLE,
            'created_by_admin_id' => (int) $source->created_by_admin_id,
            'created_by_customer_id' => null,
            'last_message_at' => $now,
            'created_at' => $source->created_at,
            'updated_at' => now(),
        ]);

        $source->loadMissing('messages.attachments');

        foreach ($source->messages as $message) {
            if (! $this->messageVisibleToCustomer($message, $customerId)) {
                continue;
            }

            $newMessage = SupportTicketMessage::query()->create([
                'support_ticket_id' => (int) $newTicket->id,
                'body_html' => (string) $message->body_html,
                'body_excerpt' => (string) $message->body_excerpt,
                'sender_admin_id' => $message->sender_admin_id,
                'sender_customer_id' => $message->sender_customer_id,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]);

            $this->copyMessageAttachments($message, $newMessage);
        }

        SupportTicketRecipient::query()->create([
            'support_ticket_id' => (int) $newTicket->id,
            'customer_id' => $customerId,
            'read_at' => $recipient->read_at,
        ]);

        $recipient->delete();
    }

    private function retainOnlyPrimaryCustomerMessages(SupportTicket $ticket, int $primaryCustomerId): void
    {
        $ticket->loadMissing('messages.attachments');

        foreach ($ticket->messages as $message) {
            if ($this->messageVisibleToCustomer($message, $primaryCustomerId)) {
                continue;
            }

            foreach ($message->attachments as $attachment) {
                $path = (string) $attachment->storage_path;
                if ($path !== '' && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
                $attachment->delete();
            }

            $message->delete();
        }

        SupportTicketRecipient::query()
            ->where('support_ticket_id', (int) $ticket->id)
            ->where('customer_id', '!=', $primaryCustomerId)
            ->delete();
    }

    private function messageVisibleToCustomer(SupportTicketMessage $message, int $customerId): bool
    {
        if ($message->sender_admin_id !== null) {
            return true;
        }

        return (int) $message->sender_customer_id === $customerId;
    }

    private function copyMessageAttachments(SupportTicketMessage $from, SupportTicketMessage $to): void
    {
        $from->loadMissing('attachments');
        if ($from->attachments->isEmpty()) {
            return;
        }

        $this->writer->replicateAttachments($from, $to);
    }
}
