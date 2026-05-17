<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Support\SupportTicketAdminService;
use App\Support\ListPerPage;
use App\Support\PaginationBar;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminSupportTicketController extends Controller
{
    private const TABS = ['received', 'sent'];

    public function index(Request $request, SupportTicketAdminService $service): View
    {
        $tab = (string) $request->query('tab', 'received');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'received';
        }

        $q = $request->query('q');
        $search = is_string($q) ? trim($q) : null;
        if ($search === '') {
            $search = null;
        }

        $perPage = ListPerPage::resolve($request);
        $rows = $tab === 'sent'
            ? $service->paginateSent($search, $perPage)
            : $service->paginateReceived($search, $perPage);

        $rowSnapshots = [];
        foreach ($rows->items() as $row) {
            $ticket = SupportTicket::query()->find((int) ($row['id'] ?? 0));
            if ($ticket !== null) {
                $rowSnapshots[(int) $ticket->id] = $service->detailPayload($ticket, $tab);
            }
        }

        return view('admin.tickets.index', [
            'pageTitle' => 'تیکت‌ها',
            'activeTab' => $tab,
            'searchQ' => $search ?? '',
            'rows' => $rows,
            'rowSnapshots' => $rowSnapshots,
            'receivedCount' => SupportTicket::query()->whereNotNull('created_by_customer_id')->count(),
            'sentCount' => SupportTicket::query()->whereNotNull('created_by_admin_id')->count(),
            'smsPanelAvailable' => $service->isSmsPanelAvailable(),
            'smsComposeTemplate' => $service->composeSmsTemplate(),
            'totalCustomerCount' => Customer::query()->count(),
            'appDisplayName' => $service->appDisplayName(),
        ]);
    }

    public function list(Request $request, SupportTicketAdminService $service): JsonResponse
    {
        $tab = (string) $request->query('tab', 'received');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'received';
        }

        $q = $request->query('q');
        $search = is_string($q) ? trim($q) : null;
        if ($search === '') {
            $search = null;
        }

        $perPage = ListPerPage::resolve($request);
        $rows = $tab === 'sent'
            ? $service->paginateSent($search, $perPage)
            : $service->paginateReceived($search, $perPage);

        $rowSnapshots = [];
        foreach ($rows->items() as $row) {
            $ticket = SupportTicket::query()->find((int) ($row['id'] ?? 0));
            if ($ticket !== null) {
                $rowSnapshots[(int) $ticket->id] = $service->detailPayload($ticket, $tab);
            }
        }

        return response()->json([
            'data' => $rows->items(),
            'snapshots' => $rowSnapshots,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'pagination_html' => PaginationBar::html($rows, true, true),
            'received_count' => SupportTicket::query()->whereNotNull('created_by_customer_id')->count(),
            'sent_count' => SupportTicket::query()->whereNotNull('created_by_admin_id')->count(),
            'active_tab' => $tab,
            'party_column_label' => $tab === 'sent' ? 'گیرنده' : 'فرستنده',
        ]);
    }

    public function customersSearch(Request $request, SupportTicketAdminService $service): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $term = isset($validated['q']) && is_string($validated['q']) ? $validated['q'] : null;

        return response()->json([
            'results' => $service->searchCustomersForSelect($term),
        ]);
    }

    public function store(Request $request, SupportTicketAdminService $service): RedirectResponse|JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'recipient_mode' => ['required', 'string', Rule::in([
                SupportTicket::MODE_SINGLE,
                SupportTicket::MODE_MULTIPLE,
                SupportTicket::MODE_ALL,
            ])],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer', 'min:1'],
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'subject' => 'عنوان تیکت',
            'recipient_mode' => 'گیرنده',
            'customer_ids' => 'گیرندگان',
            'body_html' => 'متن تیکت',
            'attachment' => 'فایل ضمیمه',
            'send_sms' => 'ارسال پیامک',
            'sms_text' => 'متن پیامک',
        ]);

        try {
            $result = $service->sendFromAdmin($admin, $validated, $request->file('attachment'));
            $ticket = $result['ticket'];
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        $ticketsCreated = (int) ($result['tickets_created'] ?? 1);
        $message = $ticketsCreated > 1
            ? 'برای '.$ticketsCreated.' مشتری تیکت جداگانه ایجاد شد.'
            : 'تیکت با موفقیت ارسال شد.';
        $smsResult = $result['sms_result'] ?? null;
        if (is_array($smsResult) && ($smsResult['sent'] ?? 0) > 0) {
            $message .= ' '.$smsResult['detail'];
        } elseif (is_array($smsResult) && filter_var($validated['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $message .= ' '.$smsResult['detail'];
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => trim($message),
                'ticket_id' => (int) $ticket->id,
                'tickets_created' => $ticketsCreated,
                'redirect' => route('admin.tickets.index', ['tab' => 'sent']),
                'sms_result' => $smsResult,
            ]);
        }

        return redirect()
            ->route('admin.tickets.index', ['tab' => 'sent'])
            ->with('ticket_flash_success', $message);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportTicketAdminService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

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
            $result = $service->replyFromAdmin($admin, $ticket, $validated, $request->file('attachment'));
            $updated = $result['ticket'];
            $tab = $ticket->isCustomerOriginated() ? 'received' : 'sent';
            $payload = $service->detailPayload($updated, $tab);
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

    public function updateStatus(Request $request, SupportTicket $ticket, SupportTicketAdminService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:24'],
        ], [], [
            'status' => 'وضعیت',
        ]);

        try {
            $updated = $service->updateStatus($ticket, (string) $validated['status']);
            $tab = $ticket->isCustomerOriginated() ? 'received' : 'sent';
            $payload = $service->detailPayload($updated, $tab);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'وضعیت تیکت به‌روزرسانی شد.',
            'ticket' => $payload,
        ]);
    }

    public function attachment(SupportTicketAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message.ticket');
        $path = $attachment->storage_path;
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $name = $attachment->original_filename !== '' ? $attachment->original_filename : 'attachment';
        $mime = $attachment->mime_type !== '' ? $attachment->mime_type : 'application/octet-stream';

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => $mime,
        ]);
    }
}
