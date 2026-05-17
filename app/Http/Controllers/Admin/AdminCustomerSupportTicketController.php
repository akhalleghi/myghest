<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketAccess;
use App\Services\Support\SupportTicketAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AdminCustomerSupportTicketController extends Controller
{
    private const TABS = ['received', 'sent'];

    public function __construct(
        private readonly SupportTicketAdminService $service,
        private readonly SupportTicketAccess $access,
    ) {}

    public function customerEmbedPanel(Request $request, Customer $customer): View
    {
        $tab = (string) $request->query('tab', 'received');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'received';
        }

        $search = $this->normalizeSearch($request->query('q'));

        $rows = $tab === 'sent'
            ? $this->service->paginateSentForCustomer((int) $customer->id, $search)
            : $this->service->paginateReceivedForCustomer((int) $customer->id, $search);

        $rowSnapshots = $this->buildSnapshots($rows->items(), $tab);

        $name = trim($customer->first_name.' '.$customer->last_name);

        return view('admin.customers.tickets_embed', [
            'pageTitle' => 'تیکت‌ها — '.($name !== '' ? $name : 'مشتری #'.$customer->id),
            'customer' => $customer,
            'customerLabel' => $name !== '' ? $name : 'مشتری #'.$customer->id,
            'activeTab' => $tab,
            'searchQ' => $search ?? '',
            'rows' => $rows,
            'rowSnapshots' => $rowSnapshots,
            'receivedCount' => $this->service->countReceivedForCustomer((int) $customer->id),
            'sentCount' => $this->service->countSentForCustomer((int) $customer->id),
            'smsPanelAvailable' => $this->service->isSmsPanelAvailable(),
            'smsComposeTemplate' => $this->service->composeSmsTemplate(),
            'appDisplayName' => $this->service->appDisplayName(),
        ]);
    }

    public function list(Request $request, Customer $customer): JsonResponse
    {
        $tab = (string) $request->query('tab', 'received');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'received';
        }

        $search = $this->normalizeSearch($request->query('q'));

        $rows = $tab === 'sent'
            ? $this->service->paginateSentForCustomer((int) $customer->id, $search)
            : $this->service->paginateReceivedForCustomer((int) $customer->id, $search);

        $rowSnapshots = $this->buildSnapshots($rows->items(), $tab);

        return response()->json([
            'data' => $rows->items(),
            'snapshots' => $rowSnapshots,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
            'pagination_html' => (string) $rows->withQueryString()->links(),
            'received_count' => $this->service->countReceivedForCustomer((int) $customer->id),
            'sent_count' => $this->service->countSentForCustomer((int) $customer->id),
            'active_tab' => $tab,
            'party_column_label' => $tab === 'sent' ? 'گیرنده' : 'فرستنده',
        ]);
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'subject' => 'عنوان تیکت',
            'body_html' => 'متن تیکت',
            'attachment' => 'فایل ضمیمه',
            'send_sms' => 'ارسال پیامک',
            'sms_text' => 'متن پیامک',
        ]);

        try {
            $result = $this->service->sendFromAdminToCustomer($admin, $customer, $validated, $request->file('attachment'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $message = 'تیکت برای این مشتری ارسال شد.';
        $smsResult = $result['sms_result'] ?? null;
        if (is_array($smsResult) && ($smsResult['sent'] ?? 0) > 0) {
            $message .= ' '.$smsResult['detail'];
        } elseif (is_array($smsResult) && filter_var($validated['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $message .= ' '.$smsResult['detail'];
        }

        return response()->json([
            'message' => trim($message),
            'ticket_id' => (int) $result['ticket']->id,
        ]);
    }

    public function reply(Request $request, Customer $customer, SupportTicket $ticket): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $this->access->assertAdminCanAccessTicketForCustomer((int) $customer->id, $ticket);

        $validated = $request->validate([
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'body_html' => 'متن پاسخ',
            'attachment' => 'فایل ضمیمه',
            'send_sms' => 'ارسال پیامک',
            'sms_text' => 'متن پیامک',
        ]);

        try {
            $result = $this->service->replyFromAdmin($admin, $ticket, $validated, $request->file('attachment'));
            $updated = $result['ticket'];
            $tab = $ticket->isCustomerOriginated() ? 'received' : 'sent';
            $payload = $this->service->detailPayload($updated, $tab);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $message = 'پاسخ ثبت شد.';
        $smsResult = $result['sms_result'] ?? null;
        if (is_array($smsResult) && ($smsResult['sent'] ?? 0) > 0) {
            $message .= ' '.$smsResult['detail'];
        } elseif (is_array($smsResult) && filter_var($validated['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $message .= ' '.$smsResult['detail'];
        }

        return response()->json([
            'message' => trim($message),
            'ticket' => $payload,
            'sms_result' => $smsResult,
        ]);
    }

    public function updateStatus(Request $request, Customer $customer, SupportTicket $ticket): JsonResponse
    {
        if (Auth::guard('admin')->user() === null) {
            abort(403);
        }

        $this->access->assertAdminCanAccessTicketForCustomer((int) $customer->id, $ticket);

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:24'],
        ], [], [
            'status' => 'وضعیت',
        ]);

        try {
            $updated = $this->service->updateStatus($ticket, (string) $validated['status']);
            $tab = $ticket->isCustomerOriginated() ? 'received' : 'sent';
            $payload = $this->service->detailPayload($updated, $tab);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'وضعیت تیکت به‌روزرسانی شد.',
            'ticket' => $payload,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildSnapshots(array $items, string $tab): array
    {
        $rowSnapshots = [];
        foreach ($items as $row) {
            $ticket = SupportTicket::query()->find((int) ($row['id'] ?? 0));
            if ($ticket !== null) {
                $rowSnapshots[(int) $ticket->id] = $this->service->detailPayload($ticket, $tab);
            }
        }

        return $rowSnapshots;
    }

    private function normalizeSearch(mixed $q): ?string
    {
        $search = is_string($q) ? trim($q) : null;
        if ($search === '') {
            return null;
        }

        return $search;
    }
}
