<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRecipient;
use App\Support\SupportTicketStatus;
use App\Support\TicketHtmlSanitizer;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupportTicketUserService
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly SupportTicketAccess $access,
        private readonly SupportTicketMessageWriter $writer,
        private readonly SupportTicketNotifier $notifier,
    ) {}

    /**
     * تیکت‌های دریافتی: ارسال‌شده از پشتیبانی برای این مشتری.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateReceived(Customer $customer, ?string $search): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->whereNotNull('created_by_admin_id')
            ->whereHas('recipients', fn (Builder $r) => $r->where('customer_id', (int) $customer->id))
            ->with(['firstMessage.attachments', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'received'));
    }

    /**
     * تیکت‌های ارسالی: ایجادشده توسط خود مشتری.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateSent(Customer $customer, ?string $search): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->where('created_by_customer_id', (int) $customer->id)
            ->with(['firstMessage.attachments', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'sent'));
    }

    public function createFromCustomer(Customer $customer, array $data, ?UploadedFile $attachment): SupportTicket
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            throw ValidationException::withMessages(['subject' => ['عنوان تیکت الزامی است.']]);
        }

        $bodyRaw = (string) ($data['body_html'] ?? '');
        $bodyHtml = TicketHtmlSanitizer::clean($bodyRaw);
        if (trim(strip_tags($bodyHtml)) === '') {
            throw ValidationException::withMessages(['body_html' => ['متن تیکت الزامی است.']]);
        }

        return DB::transaction(function () use ($customer, $subject, $bodyHtml, $attachment): SupportTicket {
            $now = Carbon::now();

            $ticket = SupportTicket::query()->create([
                'subject' => mb_substr($subject, 0, 255),
                'status' => SupportTicketStatus::PENDING_REVIEW,
                'recipient_mode' => SupportTicket::MODE_SINGLE,
                'created_by_admin_id' => null,
                'created_by_customer_id' => (int) $customer->id,
                'last_message_at' => $now,
            ]);

            $this->writer->createMessage(
                $ticket,
                $bodyHtml,
                null,
                (int) $customer->id,
                $attachment,
            );

            $this->notifier->customerSubmittedTicket($ticket, $customer);

            return $ticket->fresh(['firstMessage.attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function replyAsCustomer(Customer $customer, SupportTicket $ticket, array $data, ?UploadedFile $attachment): SupportTicket
    {
        $this->access->assertCustomerCanAccess($customer, $ticket);

        if (! SupportTicketStatus::allowsCustomerReply((string) $ticket->status)) {
            throw ValidationException::withMessages([
                'body_html' => ['امکان پاسخ به این تیکت در وضعیت فعلی وجود ندارد.'],
            ]);
        }

        $bodyRaw = (string) ($data['body_html'] ?? '');

        return DB::transaction(function () use ($customer, $ticket, $bodyRaw, $attachment): SupportTicket {
            $this->writer->createMessage(
                $ticket,
                $bodyRaw,
                null,
                (int) $customer->id,
                $attachment,
            );

            $ticket->update(['status' => SupportTicketStatus::WAITING_ADMIN]);

            SupportTicketRecipient::query()
                ->where('support_ticket_id', (int) $ticket->id)
                ->where('customer_id', (int) $customer->id)
                ->update(['read_at' => Carbon::now()]);

            $this->notifier->customerReplied($ticket->fresh() ?? $ticket, $customer);

            return $ticket->fresh([
                'messages.attachments',
                'messages.senderAdmin',
                'messages.senderCustomer',
            ]);
        });
    }

    public function markReadForCustomer(Customer $customer, SupportTicket $ticket): void
    {
        $this->access->assertCustomerCanAccess($customer, $ticket);

        SupportTicketRecipient::query()
            ->where('support_ticket_id', (int) $ticket->id)
            ->where('customer_id', (int) $customer->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(Customer $customer, SupportTicket $ticket, string $listType): array
    {
        $this->access->assertCustomerCanAccess($customer, $ticket);

        if ($listType === 'received') {
            $this->markReadForCustomer($customer, $ticket);
        }

        return $this->buildDetailPayload($ticket, $listType, 'user');
    }

    public function countActiveForCustomer(Customer $customer): int
    {
        return SupportTicket::query()
            ->where(function (Builder $q) use ($customer): void {
                $q->where('created_by_customer_id', (int) $customer->id)
                    ->orWhereHas('recipients', fn (Builder $r) => $r->where('customer_id', (int) $customer->id));
            })
            ->whereNotIn('status', [SupportTicketStatus::CLOSED])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetailPayload(SupportTicket $ticket, string $listType, string $routePrefix): array
    {
        $ticket->loadMissing([
            'createdByAdmin',
            'createdByCustomer',
            'messages.attachments',
            'messages.senderAdmin',
            'messages.senderCustomer',
        ]);

        $first = $ticket->messages->first();
        $displayAt = $first !== null ? Carbon::parse($first->created_at) : Carbon::parse($ticket->created_at);

        $partyLabel = $listType === 'sent' ? 'پشتیبانی' : $this->formatCustomerPartyForReceived($ticket);

        $attachmentRoute = $routePrefix === 'user'
            ? 'user.tickets.attachment'
            : 'admin.tickets.attachment';

        $messages = [];
        foreach ($ticket->messages as $msg) {
            $attachments = [];
            foreach ($msg->attachments as $att) {
                $attachments[] = [
                    'id' => (int) $att->id,
                    'name' => (string) $att->original_filename,
                    'url' => route($attachmentRoute, ['attachment' => $att->id]),
                    'size_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $att->file_size), 0, '.', ',')).' بایت',
                ];
            }
            $messages[] = [
                'id' => (int) $msg->id,
                'body_html' => (string) $msg->body_html,
                'sender_label' => $this->messageSenderLabel($msg),
                'datetime_fa' => $this->formatDateTimeFa(Carbon::parse($msg->created_at)),
                'is_admin_sender' => $msg->sender_admin_id !== null,
                'attachments' => $attachments,
            ];
        }

        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'party_label' => $partyLabel,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => SupportTicketStatus::label((string) $ticket->status),
            'can_reply' => SupportTicketStatus::allowsCustomerReply((string) $ticket->status),
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'messages' => $messages,
            'has_attachment' => $first !== null && $first->attachments->isNotEmpty(),
        ];
    }

    private function formatCustomerPartyForReceived(SupportTicket $ticket): string
    {
        return 'پشتیبانی';
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $s = $search !== null ? trim($search) : '';
        if ($s === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
        $query->where(function ($w) use ($like): void {
            $w->where('subject', 'like', $like)
                ->orWhereHas('messages', fn ($m) => $m->where('body_excerpt', 'like', $like));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapListRow(SupportTicket $ticket, string $listType): array
    {
        $latest = $ticket->latestMessage ?? $ticket->firstMessage;
        $displayAt = $latest !== null
            ? Carbon::parse($latest->created_at)
            : Carbon::parse($ticket->created_at);

        $excerpt = $latest !== null
            ? (string) $latest->body_excerpt
            : ($ticket->firstMessage !== null ? (string) $ticket->firstMessage->body_excerpt : '—');

        $hasAttachment = $latest !== null && $latest->relationLoaded('attachments')
            ? $latest->attachments->isNotEmpty()
            : ($latest !== null && $latest->attachments()->exists());

        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'excerpt' => $excerpt,
            'party_label' => $listType === 'sent' ? 'پشتیبانی' : 'پشتیبانی',
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'has_attachment' => $hasAttachment,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => SupportTicketStatus::label((string) $ticket->status),
        ];
    }

    private function messageSenderLabel(SupportTicketMessage $msg): string
    {
        if ($msg->sender_admin_id !== null) {
            $a = $msg->senderAdmin;

            return $a !== null && trim((string) $a->name) !== ''
                ? trim((string) $a->name)
                : 'پشتیبانی';
        }
        if ($msg->sender_customer_id !== null) {
            $c = $msg->senderCustomer;
            if ($c === null) {
                return 'شما';
            }
            $name = trim($c->first_name.' '.$c->last_name);

            return $name !== '' ? $name : 'شما';
        }

        return '—';
    }

    private function formatDateTimeFa(Carbon $c): string
    {
        $jDate = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d'));
        $time = Jalali::enToFaNumbers($c->format('H:i'));

        return $jDate.'، '.$time;
    }
}
