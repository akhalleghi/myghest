<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Admin;
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

final class SupportTicketAdminService
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly SupportTicketMessageWriter $writer,
        private readonly SupportTicketNotifier $notifier,
        private readonly SupportTicketSmsService $ticketSms,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateReceived(?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->whereNotNull('created_by_customer_id')
            ->with([
                'createdByCustomer',
                'firstMessage.attachments',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'received'));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateSent(?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->whereNotNull('created_by_admin_id')
            ->with([
                'createdByAdmin',
                'firstMessage.attachments',
                'latestMessage',
                'recipients.customer',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'sent'));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateReceivedForCustomer(int $customerId, ?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->where('created_by_customer_id', $customerId)
            ->with([
                'createdByCustomer',
                'firstMessage.attachments',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applyCustomerEmbedSearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'received'));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateSentForCustomer(int $customerId, ?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->whereNotNull('created_by_admin_id')
            ->whereHas('recipients', fn ($r) => $r->where('customer_id', $customerId))
            ->with([
                'createdByAdmin',
                'firstMessage.attachments',
                'latestMessage',
                'recipients.customer',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applyCustomerEmbedSearch($query, $search);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SupportTicket $t): array => $this->mapListRow($t, 'sent'));
    }

    public function countReceivedForCustomer(int $customerId): int
    {
        return SupportTicket::query()
            ->where('created_by_customer_id', $customerId)
            ->count();
    }

    public function countSentForCustomer(int $customerId): int
    {
        return SupportTicket::query()
            ->whereNotNull('created_by_admin_id')
            ->whereHas('recipients', fn ($r) => $r->where('customer_id', $customerId))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ticket: SupportTicket, tickets_created: int, sms_result: array<string, mixed>|null}
     */
    public function sendFromAdminToCustomer(Admin $admin, Customer $customer, array $data, ?UploadedFile $attachment): array
    {
        $payload = array_merge($data, [
            'recipient_mode' => SupportTicket::MODE_SINGLE,
            'customer_ids' => [(int) $customer->id],
        ]);

        return $this->sendFromAdmin($admin, $payload, $attachment);
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    public function searchCustomersForSelect(?string $term, int $limit = 40): array
    {
        $q = Customer::query()->orderBy('id');
        $s = $term !== null ? trim($term) : '';
        if ($s !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(function ($w) use ($like, $s): void {
                $w->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('national_id', 'like', $like);
                $digits = preg_replace('/\D+/', '', $s) ?? '';
                if ($digits !== '' && strlen($digits) >= 4) {
                    $w->orWhere('mobile', 'like', '%'.$digits.'%')
                        ->orWhere('national_id', 'like', '%'.$digits.'%');
                }
            });
        }

        return $q->limit(max(1, min(80, $limit)))->get()->map(function (Customer $c): array {
            $name = trim($c->first_name.' '.$c->last_name);
            $code = (string) ($c->customer_code ?? '');
            $mobile = (string) ($c->mobile ?? '');
            $text = $name !== '' ? $name : 'مشتری #'.$c->id;
            if ($code !== '') {
                $text .= ' — کد '.Jalali::enToFaNumbers($code);
            }
            if ($mobile !== '') {
                $text .= ' — '.Jalali::enToFaNumbers($mobile);
            }

            return ['id' => (int) $c->id, 'text' => $text];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ticket: SupportTicket, tickets_created: int, sms_result: array<string, mixed>|null}
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
        if (! in_array($mode, [SupportTicket::MODE_SINGLE, SupportTicket::MODE_MULTIPLE, SupportTicket::MODE_ALL], true)) {
            throw ValidationException::withMessages(['recipient_mode' => ['نوع گیرنده نامعتبر است.']]);
        }

        $sendSms = filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $smsText = trim((string) ($data['sms_text'] ?? ''));
        if ($sendSms && $smsText === '') {
            throw ValidationException::withMessages([
                'sms_text' => ['متن پیامک الزامی است.'],
            ]);
        }

        /** @var list<int> $customerIds */
        $customerIds = $this->resolveCustomerIds($mode, $data);

        /** @var list<SupportTicket> $createdTickets */
        $createdTickets = DB::transaction(function () use ($admin, $subject, $bodyHtml, $customerIds, $attachment): array {
            $now = Carbon::now();
            $tickets = [];
            $seedMessage = null;

            foreach ($customerIds as $index => $customerId) {
                $ticket = SupportTicket::query()->create([
                    'subject' => mb_substr($subject, 0, 255),
                    'status' => SupportTicketStatus::CREATED,
                    'recipient_mode' => SupportTicket::MODE_SINGLE,
                    'created_by_admin_id' => (int) $admin->id,
                    'created_by_customer_id' => null,
                    'last_message_at' => $now,
                ]);

                $upload = ($index === 0) ? $attachment : null;
                $message = $this->writer->createMessage(
                    $ticket,
                    $bodyHtml,
                    (int) $admin->id,
                    null,
                    $upload,
                );

                if ($index === 0) {
                    $seedMessage = $message;
                } elseif ($seedMessage !== null && $attachment !== null) {
                    $this->writer->replicateAttachments($seedMessage, $message);
                }

                SupportTicketRecipient::query()->create([
                    'support_ticket_id' => (int) $ticket->id,
                    'customer_id' => (int) $customerId,
                    'read_at' => null,
                ]);

                $customer = Customer::query()->find((int) $customerId);
                if ($customer !== null) {
                    $ticket = $ticket->fresh(['createdByAdmin', 'firstMessage.attachments', 'recipients.customer']);
                    $this->notifier->adminOpenedTicketForCustomer($ticket, $customer);
                }

                $tickets[] = $ticket;
            }

            return $tickets;
        });

        $ticket = $createdTickets[count($createdTickets) - 1] ?? $createdTickets[0];

        $smsResult = null;
        if ($sendSms) {
            $smsResults = [];
            foreach ($createdTickets as $created) {
                $smsResults[] = $this->ticketSms->sendNewTicketNotification($created, $smsText);
            }
            $smsResult = $this->mergeSmsResults($smsResults);
        }

        return [
            'ticket' => $ticket,
            'tickets_created' => count($createdTickets),
            'sms_result' => $smsResult,
        ];
    }

    /**
     * @param  list<array{sent: int, failed: int, skipped: int, detail: string}>  $results
     * @return array{sent: int, failed: int, skipped: int, detail: string}|null
     */
    private function mergeSmsResults(array $results): ?array
    {
        if ($results === []) {
            return null;
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($results as $row) {
            $sent += (int) ($row['sent'] ?? 0);
            $failed += (int) ($row['failed'] ?? 0);
            $skipped += (int) ($row['skipped'] ?? 0);
        }

        $detail = match (true) {
            $sent > 0 && $failed === 0 && $skipped === 0 => 'پیامک به '.$sent.' مشتری ارسال شد.',
            $sent > 0 => 'پیامک به '.$sent.' مشتری ارسال شد'
                .($failed > 0 ? ' ('.$failed.' ناموفق)' : '')
                .($skipped > 0 ? ' ('.$skipped.' بدون موبایل)' : '').'.',
            $failed > 0 => 'ارسال پیامک ناموفق بود.',
            $skipped > 0 => 'هیچ شماره موبایل معتبری برای مشتریان یافت نشد.',
            default => 'پیامک ارسال نشد.',
        };

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'detail' => $detail,
        ];
    }

    public function isSmsPanelAvailable(): bool
    {
        return $this->ticketSms->isPanelAvailable();
    }

    public function composeSmsTemplate(): string
    {
        return $this->ticketSms->buildComposeSmsTemplate();
    }

    public function appDisplayName(): string
    {
        return $this->ticketSms->appDisplayName();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @return array{ticket: SupportTicket, sms_result: array<string, mixed>|null}
     */
    public function replyFromAdmin(Admin $admin, SupportTicket $ticket, array $data, ?UploadedFile $attachment): array
    {
        if (! SupportTicketStatus::allowsAdminReply((string) $ticket->status)) {
            throw ValidationException::withMessages([
                'body_html' => ['امکان پاسخ به این تیکت در وضعیت فعلی وجود ندارد.'],
            ]);
        }

        $bodyRaw = (string) ($data['body_html'] ?? '');
        $sendSms = filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $smsText = trim((string) ($data['sms_text'] ?? ''));

        if ($sendSms && $smsText === '') {
            throw ValidationException::withMessages([
                'sms_text' => ['متن پیامک الزامی است.'],
            ]);
        }

        $ticket = DB::transaction(function () use ($admin, $ticket, $bodyRaw, $attachment): SupportTicket {
            $this->writer->createMessage(
                $ticket,
                $bodyRaw,
                (int) $admin->id,
                null,
                $attachment,
            );

            $ticket->update(['status' => SupportTicketStatus::WAITING_CUSTOMER]);

            SupportTicketRecipient::query()
                ->where('support_ticket_id', (int) $ticket->id)
                ->update(['read_at' => null]);

            $this->notifyCustomersOfAdminReply($ticket);

            return $ticket->fresh([
                'messages.attachments',
                'messages.senderAdmin',
                'messages.senderCustomer',
                'recipients.customer',
                'createdByCustomer',
            ]);
        });

        $smsResult = null;
        if ($sendSms) {
            $smsResult = $this->ticketSms->sendReplyNotification($ticket, $smsText);
        }

        return [
            'ticket' => $ticket,
            'sms_result' => $smsResult,
        ];
    }

    public function updateStatus(SupportTicket $ticket, string $status): SupportTicket
    {
        if (! SupportTicketStatus::isValid($status)) {
            throw ValidationException::withMessages(['status' => ['وضعیت نامعتبر است.']]);
        }

        $ticket->update(['status' => $status]);

        return $ticket->fresh([
            'messages.attachments',
            'messages.senderAdmin',
            'messages.senderCustomer',
            'recipients.customer',
            'createdByAdmin',
            'createdByCustomer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(SupportTicket $ticket, string $listType): array
    {
        $ticket->loadMissing([
            'createdByAdmin',
            'createdByCustomer',
            'messages.attachments',
            'messages.senderAdmin',
            'messages.senderCustomer',
            'recipients.customer',
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
                    'url' => route('admin.tickets.attachment', ['attachment' => $att->id]),
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

        $primaryCustomer = $this->ticketSms->primaryCustomerForTicket($ticket);
        $smsDefaultText = $primaryCustomer !== null
            ? $this->ticketSms->buildDefaultReplySmsText($primaryCustomer, $ticket)
            : '';

        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'party_label' => $partyLabel,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => SupportTicketStatus::label((string) $ticket->status),
            'can_reply' => SupportTicketStatus::allowsAdminReply((string) $ticket->status),
            'status_options' => SupportTicketStatus::adminSelectable(),
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'messages' => $messages,
            'has_attachment' => $first !== null && $first->attachments->isNotEmpty(),
            'sms_panel_available' => $this->ticketSms->isPanelAvailable(),
            'sms_default_text' => $smsDefaultText,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function resolveCustomerIds(string $mode, array $data): array
    {
        if ($mode === SupportTicket::MODE_ALL) {
            $customerIds = Customer::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($customerIds === []) {
                throw ValidationException::withMessages(['recipient_mode' => ['هیچ کاربری در سامانه ثبت نشده است.']]);
            }

            return $customerIds;
        }

        $rawIds = $data['customer_ids'] ?? [];
        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }
        $customerIds = [];
        foreach ($rawIds as $rid) {
            $id = (int) $rid;
            if ($id > 0) {
                $customerIds[$id] = $id;
            }
        }
        $customerIds = array_values($customerIds);
        if ($mode === SupportTicket::MODE_SINGLE && count($customerIds) !== 1) {
            throw ValidationException::withMessages(['customer_ids' => ['یک گیرنده انتخاب کنید.']]);
        }
        if ($mode === SupportTicket::MODE_MULTIPLE && count($customerIds) < 1) {
            throw ValidationException::withMessages(['customer_ids' => ['حداقل یک گیرنده انتخاب کنید.']]);
        }
        $validCount = Customer::query()->whereIn('id', $customerIds)->count();
        if ($validCount !== count($customerIds)) {
            throw ValidationException::withMessages(['customer_ids' => ['برخی گیرندگان انتخاب‌شده معتبر نیستند.']]);
        }

        return $customerIds;
    }

    private function notifyCustomersOfAdminReply(SupportTicket $ticket): void
    {
        if ($ticket->isCustomerOriginated()) {
            $creator = $ticket->createdByCustomer;
            if ($creator !== null) {
                $this->notifier->adminReplied($ticket, $creator);

                return;
            }
        }

        $ticket->loadMissing('recipients.customer');
        foreach ($ticket->recipients as $recipient) {
            $customer = $recipient->customer;
            if ($customer !== null) {
                $this->notifier->adminReplied($ticket, $customer);
            }
        }
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $this->applyCustomerEmbedSearch($query, $search);
    }

    private function applyCustomerEmbedSearch(Builder $query, ?string $search): void
    {
        $s = $search !== null ? trim($search) : '';
        if ($s === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
        $query->where(function ($w) use ($like): void {
            $w->where('subject', 'like', $like)
                ->orWhereHas('messages', fn ($m) => $m->where('body_excerpt', 'like', $like))
                ->orWhereHas('createdByCustomer', function ($c) use ($like): void {
                    $c->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('mobile', 'like', $like);
                })
                ->orWhereHas('recipients.customer', function ($c) use ($like): void {
                    $c->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('customer_code', 'like', $like);
                })
                ->orWhereHas('createdByAdmin', function ($a) use ($like): void {
                    $a->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
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
            'party_label' => $listType === 'sent'
                ? $this->formatRecipientsLabel($ticket)
                : $this->formatSenderLabel($ticket),
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'has_attachment' => $hasAttachment,
            'list_type' => $listType,
            'status' => (string) $ticket->status,
            'status_label' => SupportTicketStatus::label((string) $ticket->status),
        ];
    }

    private function formatSenderLabel(SupportTicket $ticket): string
    {
        $c = $ticket->createdByCustomer;
        if ($c === null) {
            return '—';
        }
        $name = trim($c->first_name.' '.$c->last_name);

        return $name !== '' ? $name : 'مشتری #'.$c->id;
    }

    private function formatRecipientsLabel(SupportTicket $ticket): string
    {
        if ($ticket->recipient_mode === SupportTicket::MODE_ALL) {
            $count = $ticket->relationLoaded('recipients')
                ? $ticket->recipients->count()
                : $ticket->recipients()->count();

            return 'همه کاربران ('.Jalali::enToFaNumbers((string) $count).' نفر)';
        }

        $recipients = $ticket->relationLoaded('recipients')
            ? $ticket->recipients
            : $ticket->recipients()->with('customer')->get();

        $names = [];
        foreach ($recipients as $r) {
            $c = $r->customer;
            if ($c === null) {
                continue;
            }
            $name = trim($c->first_name.' '.$c->last_name);
            $names[] = $name !== '' ? $name : 'مشتری #'.$c->id;
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

    private function messageSenderLabel(SupportTicketMessage $msg): string
    {
        if ($msg->sender_admin_id !== null) {
            $a = $msg->senderAdmin;

            return $a !== null && trim((string) $a->name) !== ''
                ? trim((string) $a->name)
                : 'مدیریت';
        }
        if ($msg->sender_customer_id !== null) {
            $c = $msg->senderCustomer;
            if ($c === null) {
                return 'مشتری';
            }
            $name = trim($c->first_name.' '.$c->last_name);

            return $name !== '' ? $name : 'مشتری #'.$c->id;
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
