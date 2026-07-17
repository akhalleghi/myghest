<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternalTicket;
use App\Models\InternalTicketAttachment;
use App\Services\InternalTickets\InternalTicketAccess;
use App\Services\InternalTickets\InternalTicketAdminService;
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

final class AdminInternalTicketController extends Controller
{
    private const TABS = ['received', 'sent'];

    public function index(Request $request, InternalTicketAdminService $service): View
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

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
            ? $service->paginateSent($admin, $search, $perPage)
            : $service->paginateReceived($admin, $search, $perPage);

        $rowSnapshots = [];
        foreach ($rows->items() as $row) {
            $ticket = InternalTicket::query()->find((int) ($row['id'] ?? 0));
            if ($ticket !== null && $service->resolveListTypeForAdmin($admin, $ticket) === $tab) {
                $rowSnapshots[(int) $ticket->id] = $service->detailPayload($ticket, $tab, $admin);
            }
        }

        return view('admin.internal-tickets.index', [
            'pageTitle' => 'تیکت داخلی',
            'activeTab' => $tab,
            'searchQ' => $search ?? '',
            'rows' => $rows,
            'rowSnapshots' => $rowSnapshots,
            'receivedCount' => $service->countReceived($admin),
            'sentCount' => $service->countSent($admin),
            'totalAdminCount' => $service->countActiveAdminsExcluding((int) $admin->id),
            'appDisplayName' => $service->appDisplayName(),
        ]);
    }

    public function list(Request $request, InternalTicketAdminService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

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
            ? $service->paginateSent($admin, $search, $perPage)
            : $service->paginateReceived($admin, $search, $perPage);

        $rowSnapshots = [];
        foreach ($rows->items() as $row) {
            $ticket = InternalTicket::query()->find((int) ($row['id'] ?? 0));
            if ($ticket !== null) {
                $rowSnapshots[(int) $ticket->id] = $service->detailPayload($ticket, $tab, $admin);
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
            'received_count' => $service->countReceived($admin),
            'sent_count' => $service->countSent($admin),
            'active_tab' => $tab,
            'party_column_label' => $tab === 'sent' ? 'گیرنده' : 'فرستنده',
        ]);
    }

    public function show(InternalTicket $ticket, InternalTicketAdminService $service, InternalTicketAccess $access): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        try {
            $access->assertAdminCanAccess($admin, $ticket);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $service->markReadForRecipient($admin, $ticket);
        $tab = $service->resolveListTypeForAdmin($admin, $ticket);
        $payload = $service->detailPayload($ticket->fresh(), $tab, $admin);

        return response()->json(['ticket' => $payload]);
    }

    public function adminsSearch(Request $request, InternalTicketAdminService $service): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $term = isset($validated['q']) && is_string($validated['q']) ? $validated['q'] : null;

        return response()->json([
            'results' => $service->searchAdminsForSelect($admin, $term),
        ]);
    }

    public function store(Request $request, InternalTicketAdminService $service): RedirectResponse|JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'recipient_mode' => ['required', 'string', Rule::in([
                InternalTicket::MODE_SINGLE,
                InternalTicket::MODE_MULTIPLE,
                InternalTicket::MODE_ALL,
            ])],
            'admin_ids' => ['nullable', 'array'],
            'admin_ids.*' => ['integer', 'min:1'],
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ], [], [
            'subject' => 'عنوان تیکت',
            'recipient_mode' => 'گیرنده',
            'admin_ids' => 'گیرندگان',
            'body_html' => 'متن تیکت',
            'attachment' => 'فایل ضمیمه',
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
            ? 'برای '.$ticketsCreated.' همکار تیکت جداگانه ایجاد شد.'
            : 'تیکت داخلی با موفقیت ارسال شد.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => trim($message),
                'ticket_id' => (int) $ticket->id,
                'tickets_created' => $ticketsCreated,
                'redirect' => route('admin.internal-tickets.index', ['tab' => 'sent']),
            ]);
        }

        return redirect()
            ->route('admin.internal-tickets.index', ['tab' => 'sent'])
            ->with('internal_ticket_flash_success', $message);
    }

    public function reply(
        Request $request,
        InternalTicket $ticket,
        InternalTicketAdminService $service,
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $validated = $request->validate([
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ], [], [
            'body_html' => 'متن پاسخ',
            'attachment' => 'فایل ضمیمه',
        ]);

        try {
            $result = $service->replyFromAdmin($admin, $ticket, $validated, $request->file('attachment'));
            $updated = $result['ticket'];
            $tab = $service->resolveListTypeForAdmin($admin, $updated);
            $payload = $service->detailPayload($updated, $tab, $admin);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'پاسخ ثبت شد.',
            'ticket' => $payload,
        ]);
    }

    public function updateStatus(
        Request $request,
        InternalTicket $ticket,
        InternalTicketAdminService $service,
    ): JsonResponse {
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
            $updated = $service->updateStatus($admin, $ticket, (string) $validated['status']);
            $tab = $service->resolveListTypeForAdmin($admin, $updated);
            $payload = $service->detailPayload($updated, $tab, $admin);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'وضعیت تیکت به‌روزرسانی شد.',
            'ticket' => $payload,
        ]);
    }

    public function attachment(
        InternalTicketAttachment $attachment,
        InternalTicketAccess $access,
    ): StreamedResponse {
        $admin = Auth::guard('admin')->user();
        if ($admin === null) {
            abort(403);
        }

        $access->assertAdminCanAccessAttachment($admin, $attachment);

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
