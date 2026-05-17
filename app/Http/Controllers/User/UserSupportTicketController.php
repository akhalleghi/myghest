<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Support\SupportTicketAccess;
use App\Services\Support\SupportTicketUserService;
use App\Support\ListPerPage;
use App\Support\PaginationBar;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserSupportTicketController extends Controller
{
    private const TABS = ['received', 'sent'];

    public function __construct(
        private readonly SupportTicketUserService $tickets,
        private readonly SupportTicketAccess $access,
    ) {}

    public function page(): View
    {
        return view('user.portal.tickets', [
            'pageTitle' => 'تیکت‌ها',
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
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
            ? $this->tickets->paginateSent($customer, $search, $perPage)
            : $this->tickets->paginateReceived($customer, $search, $perPage);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'pagination_html' => PaginationBar::html($rows, true, true),
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }

        $tab = (string) $request->query('tab', 'received');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'received';
        }

        try {
            $payload = $this->tickets->detailPayload($customer, $ticket, $tab);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 403);
        }

        return response()->json(['ticket' => $payload]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ], [], [
            'subject' => 'عنوان تیکت',
            'body_html' => 'متن تیکت',
            'attachment' => 'فایل ضمیمه',
        ]);

        try {
            $ticket = $this->tickets->createFromCustomer($customer, $validated, $request->file('attachment'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'تیکت با موفقیت ثبت شد.',
            'ticket_id' => (int) $ticket->id,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }

        $validated = $request->validate([
            'body_html' => ['required', 'string', 'max:200000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ], [], [
            'body_html' => 'متن پاسخ',
            'attachment' => 'فایل ضمیمه',
        ]);

        try {
            $updated = $this->tickets->replyAsCustomer($customer, $ticket, $validated, $request->file('attachment'));
            $tab = $ticket->isCustomerOriginated() ? 'sent' : 'received';
            $payload = $this->tickets->detailPayload($customer, $updated, $tab);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'پاسخ شما ثبت شد.',
            'ticket' => $payload,
        ]);
    }

    public function attachment(SupportTicketAttachment $attachment): StreamedResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            abort(403);
        }

        $this->access->assertCustomerCanAccessAttachment($customer, $attachment);

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
