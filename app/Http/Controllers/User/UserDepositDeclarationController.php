<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CustomerDepositDeclaration;
use App\Services\Deposits\DepositDeclarationUserService;
use App\Services\Loans\CustomerLoanPortalPresenter;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDepositDeclarationController extends Controller
{
    public function __construct(
        private readonly DepositDeclarationUserService $deposits,
        private readonly CustomerLoanPortalPresenter $loans,
    ) {}

    public function page(): View
    {
        $customer = Auth::guard('customer')->user();
        $portalLoans = $customer !== null
            ? $this->loans->forDashboard($customer)
            : ['loan_count' => 0, 'loans' => []];

        return view('user.portal.deposits', [
            'pageTitle' => 'اعلام واریزی‌ها',
            'portalLoansJson' => $portalLoans['loans'] ?? [],
        ]);
    }

    /**
     * پس از باز شدن صفحهٔ اعلام واریزی (از طریق اسکریپت) اعلان‌های رسیدگی را خوانده‌شده می‌کند.
     */
    public function acknowledgeReviewNotifications(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }
        $this->deposits->acknowledgeAllReviewedForCustomer($customer);

        return response()->json(['message' => 'ok']);
    }

    public function list(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }
        $q = $request->query('q');
        $search = is_string($q) ? $q : null;
        $rows = $this->deposits->paginateForCustomer($customer, $search, 15);

        return response()->json([
            'data' => $rows->getCollection()->map(fn (CustomerDepositDeclaration $d): array => $this->mapRow($d)),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return response()->json(['message' => 'نیاز به ورود است.'], 401);
        }

        $validated = $request->validate([
            'customer_loan_file_id' => ['required', 'integer', 'min:1'],
            'customer_loan_installment_id' => ['required', 'integer', 'min:1'],
            'deposited_jdate' => ['required', 'string', 'max:20'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'user_payment_method' => ['required', 'string', Rule::in(CustomerDepositDeclaration::userPaymentMethodKeys())],
            'tracking_number' => ['nullable', 'string', 'max:190'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [], [
            'customer_loan_file_id' => 'وام',
            'customer_loan_installment_id' => 'قسط',
            'deposited_jdate' => 'تاریخ واریز',
            'amount_toman' => 'مبلغ واریزی',
            'user_payment_method' => 'نحوه پرداخت',
        ]);

        try {
            $row = $this->deposits->create($customer, $validated, $request->file('attachment'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'اعلام واریزی با موفقیت ثبت شد و در وضعیت «در حال بررسی» قرار گرفت.',
            'item' => $this->mapRow($row->load(['loanFile.loanType', 'installment'])),
        ], 201);
    }

    public function update(Request $request, CustomerDepositDeclaration $deposit_declaration): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null || (int) $deposit_declaration->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $validated = $request->validate([
            'customer_loan_file_id' => ['required', 'integer', 'min:1'],
            'customer_loan_installment_id' => ['required', 'integer', 'min:1'],
            'deposited_jdate' => ['required', 'string', 'max:20'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'user_payment_method' => ['required', 'string', Rule::in(CustomerDepositDeclaration::userPaymentMethodKeys())],
            'tracking_number' => ['nullable', 'string', 'max:190'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        try {
            $row = $this->deposits->update($customer, $deposit_declaration, $validated, $request->file('attachment'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'اعلام واریزی به‌روزرسانی شد.',
            'item' => $this->mapRow($row),
        ]);
    }

    public function destroy(CustomerDepositDeclaration $deposit_declaration): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null || (int) $deposit_declaration->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        try {
            $this->deposits->deleteIfPending($customer, $deposit_declaration);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'اعلام واریزی حذف شد.']);
    }

    public function downloadAttachment(Request $request, CustomerDepositDeclaration $deposit_declaration): StreamedResponse|JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if ($customer === null || (int) $deposit_declaration->customer_id !== (int) $customer->id) {
            abort(403);
        }
        $path = $deposit_declaration->attachment_path;
        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'فایل پیوست یافت نشد.'], 404);
        }

        if ($request->query('download') === '1') {
            return Storage::disk('public')->download($path);
        }

        $name = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path)) ?: 'attachment';
        $mime = (string) (Storage::disk('public')->mimeType($path) ?: 'application/octet-stream');

        return Storage::disk('public')->response($path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerDepositDeclaration $d): array
    {
        $d->loadMissing(['loanFile.loanType', 'installment']);
        $loanTitle = (string) ($d->loanFile?->loanType?->title ?? 'وام');
        $seqFa = Jalali::enToFaNumbers((string) (int) ($d->installment?->sequence ?? 0));
        $instLabel = 'قسط '.$seqFa;
        $due = $d->installment?->due_date;
        $dueFa = $due !== null
            ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($due))->format('Y/m/d'))
            : '—';
        $instDetail = $instLabel.' — سررسید '.$dueFa;
        $dep = Carbon::parse($d->deposited_at)->startOfDay();
        $depFa = Jalali::enToFaNumbers(Jalali::instance($dep)->format('Y/m/d'));
        $statusFa = CustomerDepositDeclaration::statusLabelsFa()[$d->status] ?? $d->status;
        $methodFa = CustomerDepositDeclaration::userPaymentMethodLabelsFa()[$d->user_payment_method] ?? $d->user_payment_method;
        $amountFa = Jalali::enToFaNumbers(number_format(max(0, (int) $d->amount_toman), 0, '.', ',')).' تومان';
        $path = $d->attachment_path;
        $hasFile = $path !== null && $path !== '' && Storage::disk('public')->exists($path);
        $kind = 'none';
        if ($hasFile) {
            $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
            $kind = $ext === 'pdf' ? 'pdf' : 'image';
        }
        $baseUrl = $hasFile ? route('user.deposits.attachment', ['deposit_declaration' => $d]) : null;
        $downloadUrl = $baseUrl !== null ? $baseUrl.'?download=1' : null;
        $adminNote = trim((string) ($d->admin_note ?? ''));

        return [
            'id' => (int) $d->id,
            'customer_loan_file_id' => (int) $d->customer_loan_file_id,
            'customer_loan_installment_id' => (int) $d->customer_loan_installment_id,
            'loan_title' => $loanTitle,
            'loan_code_fa' => Jalali::enToFaNumbers((string) ($d->loanFile?->loan_code ?? '')),
            'installment_label_fa' => $instDetail,
            'installment_seq_fa' => $seqFa,
            'deposited_jalali_fa' => $depFa,
            'deposited_jdate' => Jalali::instance($dep)->format('Y/m/d'),
            'amount_toman' => (int) $d->amount_toman,
            'amount_fa' => $amountFa,
            'user_payment_method' => (string) $d->user_payment_method,
            'user_payment_method_fa' => $methodFa,
            'tracking_number' => $d->tracking_number,
            'customer_note' => $d->customer_note,
            'status' => (string) $d->status,
            'status_fa' => $statusFa,
            'admin_note' => $adminNote !== '' ? $adminNote : null,
            'reviewed_at_fa' => $d->reviewed_at !== null
                ? Jalali::enToFaNumbers(Jalali::instance($d->reviewed_at)->format('Y/m/d H:i'))
                : null,
            'can_edit' => $d->isPending(),
            'attachment_url' => $baseUrl,
            'attachment' => [
                'has' => $hasFile,
                'kind' => $kind,
                'inline_url' => $baseUrl,
                'download_url' => $downloadUrl,
            ],
        ];
    }
}
