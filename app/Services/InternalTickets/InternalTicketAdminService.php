<?php

declare(strict_types=1);

namespace App\Services\InternalTickets;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\InternalTicket;
use App\Models\InternalTicketMessage;
use App\Models\InternalTicketRecipient;
use App\Support\InternalTicketStatus;
use App\Support\TicketHtmlSanitizer;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InternalTicketAdminService
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly InternalTicketMessageWriter $writer,
        private readonly InternalTicketNotifier $notifier,
        private readonly InternalTicketAccess $access,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateReceived(Admin $admin, ?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = InternalTicket::query()
            ->whereHas('recipients', fn ($r) => $r->where('admin_id', (int) $admin->id))
            ->with([
                'createdByAdmin',
                'firstMessage.attachments',
                'latestMessage',
                'recipients.admin',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (InternalTicket $t): array => $this->mapListRow($t, 'received'));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateSent(Admin $admin, ?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = InternalTicket::query()
            ->where('created_by_admin_id', (int) $admin->id)
            ->with([
                'createdByAdmin',
                'firstMessage.attachments',
                'latestMessage',
                'recipients.admin',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (InternalTicket $t): array => $this->mapListRow($t, 'sent'));
    }

    public function countReceived(Admin $admin): int
    {
        return InternalTicket::query()
            ->whereHas('recipients', fn ($r) => $r->where('admin_id', (int) $admin->id))
            ->count();
    }

    public function countSent(Admin $admin): int
    {
        return InternalTicket::query()
            ->where('created_by_admin_id', (int) $admin->id)
            ->count();
    }

    public function countActiveAdminsExcluding(int $excludeAdminId): int
    {
        return Admin::query()
            ->where('is_active', true)
            ->where('id', '!=', $excludeAdminId)
            ->count();
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    public function searchAdminsForSelect(Admin $viewer, ?string $term, int $limit = 40): array
    {
        $q = Admin::query()
            ->where('is_active', true)
            ->where('id', '!=', (int) $viewer->id)
            ->orderBy('id');

        $s = $term !== null ? trim($term) : '';
        if ($s !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(function ($w) use ($like, $s): void {
                $w->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
                $digits = preg_replace('/\D+/', '', $s) ?? '';
                if ($digits !== '' && strlen($digits) >= 4) {
                    $w->orWhere('mobile', 'like', '%'.$digits.'%');
                }
            });
        }

        return $q->limit(max(1, min(80, $limit)))->get()->map(function (Admin $a): array {
            $name = $a->fullName();
            $username = (string) ($a->username ?? '');
            $text = $name !== '' ? $name : 'ادمین #'.$a->id;
            if ($username !== '') {
                $text .= ' — @'.$username;
            }

            return ['id' => (int) $a->id, 'text' => $text];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ticket: InternalTicket, tickets_created: int}
     */
    public function sendFromAdmin(Admin $admin, array $data, ?UploadedFile $attachment): array
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

        $mode = (string) ($data['recipient_mode'] ?? '');
        if (! in_array($mode, [InternalTicket::MODE_SINGLE, InternalTicket::MODE_MULTIPLE, InternalTicket::MODE_ALL], true)) {
            throw ValidationException::withMessages(['recipient_mode' => ['نوع گیرنده نامعتبر است.']]);
        }

        /** @var list<int> $adminIds */
        $adminIds = $this->resolveAdminIds($admin, $mode, $data);

        /** @var list<InternalTicket> $createdTickets */
        $createdTickets = DB::transaction(function () use ($admin, $subject, $bodyHtml, $adminIds, $attachment): array {
            $now = Carbon::now();
            $tickets = [];
            $seedMessage = null;

            foreach ($adminIds as $index => $recipientAdminId) {
                $ticket = InternalTicket::query()->create([
                    'subject' => mb_substr($subject, 0, 255),
                    'status' => InternalTicketStatus::CREATED,
                    'recipient_mode' => InternalTicket::MODE_SINGLE,
                    'created_by_admin_id' => (int) $admin->id,
                    'last_message_at' => $now,
                ]);

                $upload = ($index === 0) ? $attachment : null;
                $message = $this->writer->createMessage(
                    $ticket,
                    $bodyHtml,
                    (int) $admin->id,
                    $upload,
                );

                if ($index === 0) {
                    $seedMessage = $message;
                } elseif ($seedMessage !== null && $attachment !== null) {
                    $this->writer->replicateAttachments($seedMessage, $message);
                }

                InternalTicketRecipient::query()->create([
                    'internal_ticket_id' => (int) $ticket->id,
                    'admin_id' => (int) $recipientAdminId,
                    'read_at' => null,
                ]);

                $recipient = Admin::query()->find((int) $recipientAdminId);
                if ($recipient !== null) {
                    $ticket = $ticket->fresh(['createdByAdmin', 'firstMessage.attachments', 'recipients.admin']);
                    $this->notifier->ticketOpenedForAdmin($ticket, $recipient, $admin);
                }

                $tickets[] = $ticket;
            }

            return $tickets;
        });

        $ticket = $createdTickets[count($createdTickets) - 1] ?? $createdTickets[0];

        return [
            'ticket' => $ticket,
            'tickets_created' => count($createdTickets),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ticket: InternalTicket}
     */
    public function replyFromAdmin(Admin $admin, InternalTicket $ticket, array $data, ?UploadedFile $attachment): array
    {
        $this->access->assertAdminCanAccess($admin, $ticket);

        if (! InternalTicketStatus::allowsReply((string) $ticket->status)) {
            throw ValidationException::withMessages([
                'body_html' => ['امکان پاسخ به این تیکت در وضعیت فعلی وجود ندارد.'],
            ]);
        }

        $bodyRaw = (string) ($data['body_html'] ?? '');

        $ticket = DB::transaction(function () use ($admin, $ticket, $bodyRaw, $attachment): InternalTicket {
            $this->writer->createMessage(
                $ticket,
                $bodyRaw,
                (int) $admin->id,
                $attachment,
            );

            $isCreator = (int) $ticket->created_by_admin_id === (int) $admin->id;
            $ticket->update([
                'status' => $isCreator
                    ? InternalTicketStatus::WAITING_RECIPIENT
                    : InternalTicketStatus::WAITING_AUTHOR,
            ]);

            if ($isCreator) {
                InternalTicketRecipient::query()
                    ->where('internal_ticket_id', (int) $ticket->id)
                    ->update(['read_at' => null]);
            }

            $this->notifyPeersOfReply($ticket, $admin, $isCreator);

            return $ticket->fresh([
                'messages.attachments',
                'messages.senderAdmin',
                'recipients.admin',
                'createdByAdmin',
            ]);
        });

        return ['ticket' => $ticket];
    }

    public function updateStatus(Admin $admin, InternalTicket $ticket, string $status): InternalTicket
    {
        $this->access->assertAdminCanAccess($admin, $ticket);

        if (! InternalTicketStatus::isValid($status)) {
            throw ValidationException::withMessages(['status' => ['وضعیت نامعتبر است.']]);
        }

        $ticket->update(['status' => $status]);

        return $ticket->fresh([
            'messages.attachments',
            'messages.senderAdmin',
            'recipients.admin',
            'createdByAdmin',
        ]);
    }

    public function markReadForRecipient(Admin $admin, InternalTicket $ticket): void
    {
        InternalTicketRecipient::query()
            ->where('internal_ticket_id', (int) $ticket->id)
            ->where('admin_id', (int) $admin->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    public function resolveListTypeForAdmin(Admin $admin, InternalTicket $ticket): string
    {
        if ((int) $ticket->created_by_admin_id === (int) $admin->id) {
            return 'sent';
        }

        return 'received';
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(InternalTicket $ticket, string $listType, Admin $viewer): array
    {
        $ticket->loadMissing([
            'createdByAdmin',
            'messages.attachments',
            'messages.senderAdmin',
            'recipients.admin',
        ]);

        $first = $ticket->messages->first();
        $displayAt = $first !== null ? Carbon::parse($first->created_at) : Carbon::parse($ticket->created_at);

        $partyLabel = $listType === 'sent'
            ? $this->formatRecipientsLabel($ticket)
            : $this->formatSenderLabel($ticket);

        $messages = [];
        foreach ($ticket->messages as $msg) {
            $attachments = [];
            foreach ($msg->attachments as $att) {
                $attachments[] = [
                    'id' => (int) $att->id,
                    'name' => (string) $att->original_filename,
                    'url' => route('admin.internal-tickets.attachment', ['attachment' => $att->id]),
                    'size_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $att->file_size), 0, '.', ',')).' بایت',
                ];
            }
            $messages[] = [
                'id' => (int) $msg->id,
                'body_html' => (string) $msg->body_html,
                'sender_label' => $this->messageSenderLabel($msg),
                'datetime_fa' => $this->formatDateTimeFa(Carbon::parse($msg->created_at)),
                'is_admin_sender' => (int) $msg->sender_admin_id === (int) $viewer->id,
                'attachments' => $attachments,
            ];
        }

        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'party_label' => $partyLabel,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => InternalTicketStatus::label((string) $ticket->status),
            'can_reply' => InternalTicketStatus::allowsReply((string) $ticket->status),
            'status_options' => InternalTicketStatus::adminSelectable(),
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'messages' => $messages,
            'has_attachment' => $first !== null && $first->attachments->isNotEmpty(),
        ];
    }

    public function appDisplayName(): string
    {
        $raw = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($raw) && $raw !== '' ? $raw : (string) config('app.name');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function resolveAdminIds(Admin $sender, string $mode, array $data): array
    {
        if ($mode === InternalTicket::MODE_ALL) {
            $adminIds = Admin::query()
                ->where('is_active', true)
                ->where('id', '!=', (int) $sender->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if ($adminIds === []) {
                throw ValidationException::withMessages(['recipient_mode' => ['هیچ ادمین فعال دیگری در سامانه ثبت نشده است.']]);
            }

            return $adminIds;
        }

        $rawIds = $data['admin_ids'] ?? [];
        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }
        $adminIds = [];
        foreach ($rawIds as $rid) {
            $id = (int) $rid;
            if ($id > 0 && $id !== (int) $sender->id) {
                $adminIds[$id] = $id;
            }
        }
        $adminIds = array_values($adminIds);
        if ($mode === InternalTicket::MODE_SINGLE && count($adminIds) !== 1) {
            throw ValidationException::withMessages(['admin_ids' => ['یک گیرنده انتخاب کنید.']]);
        }
        if ($mode === InternalTicket::MODE_MULTIPLE && count($adminIds) < 1) {
            throw ValidationException::withMessages(['admin_ids' => ['حداقل یک گیرنده انتخاب کنید.']]);
        }
        $validCount = Admin::query()
            ->where('is_active', true)
            ->whereIn('id', $adminIds)
            ->count();
        if ($validCount !== count($adminIds)) {
            throw ValidationException::withMessages(['admin_ids' => ['برخی گیرندگان انتخاب‌شده معتبر نیستند.']]);
        }

        return $adminIds;
    }

    private function notifyPeersOfReply(InternalTicket $ticket, Admin $sender, bool $senderIsCreator): void
    {
        $ticket->loadMissing(['createdByAdmin', 'recipients.admin']);

        if ($senderIsCreator) {
            foreach ($ticket->recipients as $recipient) {
                $peer = $recipient->admin;
                if ($peer !== null && (int) $peer->id !== (int) $sender->id) {
                    $this->notifier->repliedForAdmin($ticket, $peer, $sender, 'received');
                }
            }

            return;
        }

        $creator = $ticket->createdByAdmin;
        if ($creator !== null && (int) $creator->id !== (int) $sender->id) {
            $this->notifier->repliedForAdmin($ticket, $creator, $sender, 'sent');
        }
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
                ->orWhereHas('messages', fn ($m) => $m->where('body_excerpt', 'like', $like))
                ->orWhereHas('createdByAdmin', function ($a) use ($like): void {
                    $a->where('name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                })
                ->orWhereHas('recipients.admin', function ($a) use ($like): void {
                    $a->where('name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapListRow(InternalTicket $ticket, string $listType): array
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
            'party_label' => $listType === 'sent'
                ? $this->formatRecipientsLabel($ticket)
                : $this->formatSenderLabel($ticket),
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'has_attachment' => $hasAttachment,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => InternalTicketStatus::label((string) $ticket->status),
        ];
    }

    private function formatSenderLabel(InternalTicket $ticket): string
    {
        $a = $ticket->createdByAdmin;
        if ($a === null) {
            return '—';
        }
        $name = $a->fullName();

        return $name !== '' ? $name : 'ادمین #'.$a->id;
    }

    private function formatRecipientsLabel(InternalTicket $ticket): string
    {
        $recipients = $ticket->relationLoaded('recipients')
            ? $ticket->recipients
            : $ticket->recipients()->with('admin')->get();

        $names = [];
        foreach ($recipients as $r) {
            $a = $r->admin;
            if ($a === null) {
                continue;
            }
            $name = $a->fullName();
            $names[] = $name !== '' ? $name : 'ادمین #'.$a->id;
            if (count($names) >= 3) {
                break;
            }
        }

        if ($names === []) {
            return '—';
        }

        $extra = $recipients->count() - count($names);
        $label = implode('، ', $names);
        if ($extra > 0) {
            $label .= ' و '.Jalali::enToFaNumbers((string) $extra).' نفر دیگر';
        }

        return $label;
    }

    private function messageSenderLabel(InternalTicketMessage $msg): string
    {
        $a = $msg->senderAdmin;
        if ($a === null) {
            return 'ادمین';
        }
        $name = $a->fullName();

        return $name !== '' ? $name : 'ادمین #'.$a->id;
    }

    private function formatDateTimeFa(Carbon $c): string
    {
        $jDate = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d'));
        $time = Jalali::enToFaNumbers($c->format('H:i'));

        return $jDate.'، '.$time;
    }
}
