<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\CustomerLoanRequestStatusLog;
use App\Models\LoanRequestStatusDefinition;
use App\Models\SmsLog;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Loans\AdminCustomerLoanRequestPresenter;
use App\Services\Loans\AdminCustomerLoanRequestUpdateService;
use App\Services\Loans\AdminLoanRequestEditModalPresenter;
use App\Services\Loans\ConvertLoanRequestToLoanFileService;
use App\Services\Loans\LoanRequestDocumentAdminWriter;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * کنترلر ادمین برای مدیریت «درخواست‌های وام».
 *
 * فیلترهای صفحهٔ فهرست:
 *  - `q` (string)
 *  - `from_jdate` / `to_jdate` (Y/m/d Jalali)
 *  - `status[]` (آرایه‌ای از کدهای `loan_request_status_definitions.code`)
 *
 * این کنترلر `index`، `export` و `printView` همگی از یک منبع فیلتر مشترک
 * استفاده می‌کنند تا خروجی اکسل و چاپ، دقیقاً همان رکوردهای جدول را شامل شوند.
 */
final class AdminCustomerLoanRequestController extends Controller
{
    public function index(Request $request, AdminCustomerLoanRequestPresenter $presenter): View
    {
        $filters = $this->resolveListFilters($request);

        $query = $this->buildFilteredQuery($filters, $this->optionalCustomerFilterFromRequest($request));

        $paginator = $query->paginate(25)->withQueryString();

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $rows = $paginator->getCollection()->map(
            static fn (CustomerLoanRequest $r): array => $presenter->mapRow($r, $statusTitles)
        );

        $paginator->setCollection($rows);

        $statusOptions = LoanRequestStatusDefinition::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['code', 'title'])
            ->map(static fn (LoanRequestStatusDefinition $d): array => [
                'code' => (string) $d->code,
                'title' => (string) $d->title,
            ])
            ->all();

        return view('admin.loan_requests.index', [
            'pageTitle' => 'درخواست وام‌ها',
            'loanRequests' => $paginator,
            'fromJDate' => $filters['from_jdate'],
            'toJDate' => $filters['to_jdate'],
            'search' => $filters['q'],
            'selectedStatuses' => $filters['statuses'],
            'statusOptions' => $statusOptions,
            'lrqListRouteName' => 'admin.loan-requests.index',
            'lrqListRouteParams' => [],
            'lrqForcedCustomerId' => null,
            'lrqEmbedCustomer' => null,
            'lrqHttpResourceBase' => $this->loanRequestHttpResourceBasePath(),
        ]);
    }

    /**
     * صفحهٔ مستقل (برای iframe داخل مدال «مدیریت وام‌ها») با همان منطق فهرست اصلی، محدود به یک مشتری.
     */
    public function customerEmbedPanel(
        Request $request,
        Customer $customer,
        AdminCustomerLoanRequestPresenter $presenter,
    ): View {
        $filters = $this->resolveListFilters($request);

        $query = $this->buildFilteredQuery($filters, (int) $customer->id);

        $paginator = $query->paginate(25)->withQueryString();

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $rows = $paginator->getCollection()->map(
            static fn (CustomerLoanRequest $r): array => $presenter->mapRow($r, $statusTitles)
        );

        $paginator->setCollection($rows);

        $statusOptions = LoanRequestStatusDefinition::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['code', 'title'])
            ->map(static fn (LoanRequestStatusDefinition $d): array => [
                'code' => (string) $d->code,
                'title' => (string) $d->title,
            ])
            ->all();

        $name = trim($customer->first_name.' '.$customer->last_name);

        return view('admin.loan_requests.customer_embed', [
            'pageTitle' => 'درخواست وام‌ها — '.($name !== '' ? $name : 'مشتری #'.$customer->id),
            'loanRequests' => $paginator,
            'fromJDate' => $filters['from_jdate'],
            'toJDate' => $filters['to_jdate'],
            'search' => $filters['q'],
            'selectedStatuses' => $filters['statuses'],
            'statusOptions' => $statusOptions,
            'lrqListRouteName' => 'admin.customers.loan-requests.embed',
            'lrqListRouteParams' => ['customer' => $customer],
            'lrqForcedCustomerId' => (int) $customer->id,
            'lrqEmbedCustomer' => $customer,
            'lrqHttpResourceBase' => $this->loanRequestHttpResourceBasePath(),
        ]);
    }

    /**
     * پیشوند URL منابع REST درخواست وام (ویرایش، تبدیل، مدارک، …) برای استفاده در اسکریپت‌های فرانت.
     * جدا از مسیر «فهرست» صفحهٔ embed یا index نگه داشته می‌شود تا در صورت تغییر نام/مسیر فقط همین نقطه به‌روز شود.
     */
    private function loanRequestHttpResourceBasePath(): string
    {
        return rtrim((string) route('admin.loan-requests.index'), '/');
    }

    /**
     * خروجی اکسل از فهرست فیلترشدهٔ درخواست‌های وام.
     *
     * فرمت: «.xls شبیه‌سازی‌شده» با ساختار «UTF-16LE + Tab» — هم‌خوان با اکسل ویندوز
     * و سازگار با همان الگوی موجود در `CustomerController::exportCustomersListExcel`.
     * این انتخاب از افزودن وابستگی جدید (PhpSpreadsheet) جلوگیری می‌کند.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->resolveListFilters($request);
        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $restrictedCustomerId = $this->optionalCustomerFilterFromRequest($request);

        $filename = 'loan-requests-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($filters, $statusTitles, $restrictedCustomerId): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");

            $this->writeExcelUnicodeRow($out, [
                'شماره درخواست',
                'نام مشتری',
                'کد مشتری',
                'کد ملی',
                'نام وام درخواستی',
                'مبلغ درخواستی (تومان)',
                'تاریخ ثبت درخواست',
                'وضعیت جاری',
                'نظر کارشناس',
                'شماره تماس',
                'شهر',
            ]);

            $this->buildFilteredQuery($filters, $restrictedCustomerId)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->chunkById(200, function ($chunk) use ($out, $statusTitles): void {
                    foreach ($chunk as $r) {
                        if (! $r instanceof CustomerLoanRequest) {
                            continue;
                        }
                        $this->writeExcelUnicodeRow($out, $this->buildExportCells($r, $statusTitles));
                    }
                });

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * صفحهٔ مخصوص چاپ (A4) با همان فیلترهای جدول؛ به‌صورت یک Blade مستقل رندر می‌شود
     * تا فقط جدول، سرلوحه و اطلاعات لازم چاپ شود (بدون منوها و کنترل‌های صفحه).
     */
    public function printView(Request $request, AdminCustomerLoanRequestPresenter $presenter): View
    {
        $filters = $this->resolveListFilters($request);
        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $restrictedCustomerId = $this->optionalCustomerFilterFromRequest($request);

        $rows = $this->buildFilteredQuery($filters, $restrictedCustomerId)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn (CustomerLoanRequest $r): array => $this->buildPrintRow($r, $presenter, $statusTitles))
            ->all();

        $statusLabels = array_values(array_map(
            static fn (string $code): string => $statusTitles[$code] ?? $code,
            $filters['statuses'],
        ));

        $uiFontRaw = AppSetting::query()->where('key', 'app_ui_font')->value('value');
        $appUiFont = is_string($uiFontRaw) && in_array($uiFontRaw, ['iransans', 'iranyekan', 'anjoman', 'estedad'], true)
            ? $uiFontRaw
            : 'iransans';

        $appDisplayNameRaw = AppSetting::query()->where('key', 'app_display_name')->value('value');
        $appDisplayName = is_string($appDisplayNameRaw) && $appDisplayNameRaw !== ''
            ? $appDisplayNameRaw
            : (string) config('app.name');

        return view('admin.loan_requests.print', [
            'rows' => $rows,
            'fromJDate' => $filters['from_jdate'],
            'toJDate' => $filters['to_jdate'],
            'search' => $filters['q'],
            'selectedStatusLabels' => $statusLabels,
            'generatedAtFa' => Jalali::enToFaNumbers(Jalali::now()->format('Y/m/d')).' '.Jalali::enToFaNumbers(now()->format('H:i')),
            'appUiFont' => $appUiFont,
            'appDisplayName' => $appDisplayName,
        ]);
    }

    /**
     * دادهٔ مدال «مشخصات درخواست وام» (ادمین).
     */
    public function editContext(
        CustomerLoanRequest $customerLoanRequest,
        AdminLoanRequestEditModalPresenter $presenter,
    ): JsonResponse {
        $statusTitles = LoanRequestStatusDefinition::titlesByCode();

        return response()->json($presenter->build($customerLoanRequest, $statusTitles));
    }

    public function update(
        Request $request,
        CustomerLoanRequest $customerLoanRequest,
        AdminCustomerLoanRequestUpdateService $updater,
        AdminLoanRequestEditModalPresenter $presenter,
    ): JsonResponse {
        $validated = $request->validate([
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:600'],
            'installment_interval_count' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', 'string', Rule::exists('loan_request_status_definitions', 'code')],
            'expert_note' => ['nullable', 'string', 'max:65535'],
            'expert_note_customer' => ['nullable', 'string', 'max:65535'],
            'documents_physical_received' => ['sometimes', 'boolean'],
            'send_status_sms' => ['sometimes', 'boolean'],
        ], [], [
            'loan_type_id' => 'نوع وام',
            'amount_toman' => 'مبلغ',
            'installments_count' => 'تعداد اقساط',
            'installment_interval_count' => 'فاصله اقساط',
            'status' => 'وضعیت',
            'expert_note' => 'نظر کارشناس (ادمین)',
            'expert_note_customer' => 'نظر کارشناس (مشتری)',
        ]);

        try {
            $out = $updater->update(
                $customerLoanRequest,
                $validated,
                $request,
                Auth::guard('admin')->id(),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'اطلاعات مالی با طرح انتخاب‌شده هم‌خوانی ندارد.',
                'errors' => $e->errors(),
            ], 422);
        }

        $fresh = $customerLoanRequest->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'درخواست یافت نشد.'], 404);
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();

        return response()->json([
            'message' => $out['message'],
            'sms_note' => $out['sms_note'],
            'edit_context' => $presenter->build($fresh, $statusTitles),
        ]);
    }

    public function statusLogs(Request $request, CustomerLoanRequest $customerLoanRequest): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $titles = LoanRequestStatusDefinition::titlesByCode();

        $query = CustomerLoanRequestStatusLog::query()
            ->where('customer_loan_request_id', $customerLoanRequest->id)
            ->with('admin')
            ->orderByDesc('created_at');

        $this->applyStatusLogSearch($query, $q);

        $rows = $query->limit(500)->get()->map(
            fn (CustomerLoanRequestStatusLog $log): array => $this->mapStatusLogRow($log, $titles)
        );

        return response()->json(['logs' => $rows->all()]);
    }

    public function exportStatusLogs(Request $request, CustomerLoanRequest $customerLoanRequest): StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $titles = LoanRequestStatusDefinition::titlesByCode();

        $query = CustomerLoanRequestStatusLog::query()
            ->where('customer_loan_request_id', $customerLoanRequest->id)
            ->with('admin')
            ->orderByDesc('created_at');

        $this->applyStatusLogSearch($query, $q);

        $filename = 'loan-request-'.$customerLoanRequest->id.'-status-logs.csv';

        return response()->streamDownload(function () use ($query, $titles): void {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['کاربر', 'تاریخ و ساعت', 'از وضعیت (مشتری)', 'به وضعیت (مشتری)']);
            foreach ($query->cursor() as $log) {
                if (! $log instanceof CustomerLoanRequestStatusLog) {
                    continue;
                }
                $row = $this->mapStatusLogRow($log, $titles);
                fputcsv($out, [
                    $row['user_label'],
                    $row['created_at_fa'],
                    $row['from_status_customer'],
                    $row['to_status_customer'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function statusSmsLogs(CustomerLoanRequest $customerLoanRequest): JsonResponse
    {
        $logs = SmsLog::query()
            ->where('type', SmsLog::TYPE_LOAN_REQUEST_STATUS)
            ->where('meta->customer_loan_request_id', $customerLoanRequest->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (SmsLog $log): array {
                $at = $log->sent_at;
                $c = $at instanceof Carbon ? $at : ($at !== null ? Carbon::parse((string) $at) : null);
                $sentFa = $c !== null
                    ? Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d')).' '.Jalali::enToFaNumbers($c->format('H:i:s'))
                    : '—';

                return [
                    'id' => (int) $log->id,
                    'sms_panel' => (string) $log->sms_panel,
                    'status' => (string) $log->status,
                    'status_label' => $log->statusLabel(),
                    'sent_at_fa' => $sentFa,
                    'message_text' => (string) $log->message_text,
                    'recipient' => (string) $log->recipient,
                    'type' => (string) $log->type,
                    'type_label' => 'تغییر وضعیت درخواست وام',
                ];
            });

        return response()->json(['logs' => $logs->all()]);
    }

    public function resendStatusSms(
        CustomerLoanRequest $customerLoanRequest,
        SmsLog $smsLog,
        RawSmsDispatcher $rawSms,
    ): JsonResponse {
        if ($smsLog->type !== SmsLog::TYPE_LOAN_REQUEST_STATUS) {
            abort(404);
        }
        $meta = is_array($smsLog->meta) ? $smsLog->meta : [];
        if ((int) ($meta['customer_loan_request_id'] ?? 0) !== (int) $customerLoanRequest->id) {
            abort(404);
        }

        $recipient = (string) $smsLog->recipient;
        $text = (string) $smsLog->message_text;
        $code = isset($meta['loan_request_status_code']) ? (string) $meta['loan_request_status_code'] : '';

        $result = $rawSms->send($recipient, $text, SmsLog::TYPE_LOAN_REQUEST_STATUS, [
            'customer_loan_request_id' => $customerLoanRequest->id,
            'loan_request_status_code' => $code,
            'resent_from_sms_log_id' => $smsLog->id,
        ]);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    public function documentFile(
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestDocument $customerLoanRequestDocument,
    ): BinaryFileResponse {
        $this->assertLoanRequestDocumentBelongs($customerLoanRequest, $customerLoanRequestDocument);
        $path = (string) $customerLoanRequestDocument->stored_path;
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $absolute = Storage::disk('local')->path($path);
        if (! is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute, [
            'Content-Type' => (string) ($customerLoanRequestDocument->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.basename((string) $customerLoanRequestDocument->original_filename).'"',
        ]);
    }

    public function documentUpdate(
        Request $request,
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestDocument $customerLoanRequestDocument,
        LoanRequestDocumentAdminWriter $writer,
    ): JsonResponse {
        $this->assertLoanRequestDocumentBelongs($customerLoanRequest, $customerLoanRequestDocument);

        $validated = $request->validate([
            'review_status' => ['required', 'string', Rule::in(CustomerLoanRequestDocument::reviewStatusCodes())],
            'expert_note' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'review_status' => 'وضعیت مدرک',
            'expert_note' => 'نظر کارشناس',
        ]);

        $writer->updateReview(
            $customerLoanRequestDocument,
            (string) $validated['review_status'],
            isset($validated['expert_note']) ? (string) $validated['expert_note'] : null,
        );
        $customerLoanRequestDocument->refresh();

        return response()->json([
            'message' => 'تغییرات مدرک ذخیره شد.',
            'document' => [
                'id' => (int) $customerLoanRequestDocument->id,
                'review_status' => (string) $customerLoanRequestDocument->review_status,
                'review_status_label' => CustomerLoanRequestDocument::reviewStatusLabels()[$customerLoanRequestDocument->review_status] ?? $customerLoanRequestDocument->review_status,
                'expert_note' => $customerLoanRequestDocument->expert_note !== null ? (string) $customerLoanRequestDocument->expert_note : null,
            ],
        ]);
    }

    /**
     * پیش‌نمایش مالی برای دیالوگ تأیید «تبدیل به وام»؛ بدون نوشتن در DB.
     * دو تاریخ ورودی اختیاری هستند تا با مقادیر فعلی فرم همخوان باشد.
     */
    public function convertPreview(
        Request $request,
        CustomerLoanRequest $customerLoanRequest,
        ConvertLoanRequestToLoanFileService $converter,
    ): JsonResponse {
        $validated = $request->validate([
            'loan_start_jdate' => ['nullable', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
        ], [], [
            'loan_start_jdate' => 'تاریخ شروع وام',
            'disbursement_due_jdate' => 'سررسید واریز',
        ]);

        try {
            $preview = $converter->preview(
                $customerLoanRequest,
                isset($validated['loan_start_jdate']) ? (string) $validated['loan_start_jdate'] : null,
                isset($validated['disbursement_due_jdate']) ? (string) $validated['disbursement_due_jdate'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'اطلاعات تبدیل قابل پیش‌نمایش نیست.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($preview);
    }

    /**
     * تبدیل درخواست وام به پروندهٔ وام واقعی + ایجاد جدول اقساط.
     * در صورت موفقیت، edit_context جدید برمی‌گرداند تا فرانت بتواند مدال را همگام کند.
     */
    public function convert(
        Request $request,
        CustomerLoanRequest $customerLoanRequest,
        ConvertLoanRequestToLoanFileService $converter,
        AdminLoanRequestEditModalPresenter $presenter,
    ): JsonResponse {
        $validated = $request->validate([
            'loan_start_jdate' => ['required', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
        ], [], [
            'loan_start_jdate' => 'تاریخ شروع وام',
            'disbursement_due_jdate' => 'سررسید واریز',
        ]);

        try {
            $result = $converter->convert(
                $customerLoanRequest,
                (string) $validated['loan_start_jdate'],
                isset($validated['disbursement_due_jdate']) ? (string) $validated['disbursement_due_jdate'] : null,
                Auth::guard('admin')->id(),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'ایجاد وام امکان‌پذیر نیست.',
                'errors' => $e->errors(),
            ], 422);
        }

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $fresh = $customerLoanRequest->fresh();

        return response()->json([
            'message' => $result['message'],
            'loan_file' => [
                'id' => (int) $result['loan_file']->id,
                'loan_code' => (string) $result['loan_file']->loan_code,
                'customer_id' => (int) $result['loan_file']->customer_id,
                'installments_count' => (int) $result['loan_file']->installments_count,
                'installment_amount_toman' => (int) $result['loan_file']->installment_amount_toman,
                'amount_toman' => (int) $result['loan_file']->amount_toman,
            ],
            'edit_context' => $fresh !== null ? $presenter->build($fresh, $statusTitles) : null,
        ]);
    }

    /**
     * حذف کامل درخواست وام؛ فایل‌های پیوست توسط CustomerLoanRequest::booted() پاک می‌شوند
     * و رکوردهای customer_loan_request_documents و customer_loan_request_status_logs
     * از طریق FK با cascadeOnDelete حذف می‌شوند.
     */
    public function destroy(CustomerLoanRequest $customerLoanRequest): JsonResponse
    {
        if ($customerLoanRequest->customer_loan_file_id !== null) {
            return response()->json([
                'message' => 'این درخواست به وام تبدیل شده است و قابل حذف نیست.',
            ], 422);
        }

        $customerLoanRequest->delete();

        return response()->json([
            'message' => 'درخواست وام و مدارک آن حذف شد.',
        ]);
    }

    public function documentDestroy(
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestDocument $customerLoanRequestDocument,
        LoanRequestDocumentAdminWriter $writer,
        AdminLoanRequestEditModalPresenter $presenter,
    ): JsonResponse {
        $this->assertLoanRequestDocumentBelongs($customerLoanRequest, $customerLoanRequestDocument);
        $writer->deleteDocument($customerLoanRequest, $customerLoanRequestDocument);

        $fresh = $customerLoanRequest->fresh(['documents', 'loanType', 'customer']);
        if ($fresh === null) {
            return response()->json(['message' => 'حذف شد.'], 200);
        }
        $statusTitles = LoanRequestStatusDefinition::titlesByCode();

        return response()->json([
            'message' => 'مدرک حذف شد.',
            'edit_context' => $presenter->build($fresh, $statusTitles),
        ]);
    }

    private function assertLoanRequestDocumentBelongs(
        CustomerLoanRequest $customerLoanRequest,
        CustomerLoanRequestDocument $customerLoanRequestDocument,
    ): void {
        if ((int) $customerLoanRequestDocument->customer_loan_request_id !== (int) $customerLoanRequest->id) {
            abort(404);
        }
    }

    /**
     * @param  Builder<CustomerLoanRequestStatusLog>  $query
     */
    private function applyStatusLogSearch(Builder $query, string $q): void
    {
        if ($q === '') {
            return;
        }
        $like = '%'.$q.'%';
        $query->where(function (Builder $sub) use ($like, $q): void {
            $sub->where('from_status', 'like', $like)
                ->orWhere('to_status', 'like', $like)
                ->orWhere('actor_type', 'like', $like);
            if (ctype_digit($q)) {
                $sub->orWhere('admin_id', (int) $q);
            }
            $sub->orWhereHas('admin', function (Builder $a) use ($like): void {
                $a->where('username', 'like', $like)->orWhere('name', 'like', $like);
            });
        });
    }

    /**
     * @param  array<string, string>  $titles
     * @return array<string, string|int>
     */
    private function mapStatusLogRow(CustomerLoanRequestStatusLog $log, array $titles): array
    {
        $at = $log->created_at;
        $c = $at instanceof Carbon ? $at : Carbon::parse((string) $at);
        $dtFa = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d')).' '.Jalali::enToFaNumbers($c->format('H:i:s'));

        $actorLabel = match ($log->actor_type) {
            CustomerLoanRequestStatusLog::ACTOR_ADMIN => $log->admin !== null
                ? (trim((string) ($log->admin->name ?? '')) !== ''
                    ? (string) $log->admin->name
                    : (string) $log->admin->username)
                : 'مدیر سیستم',
            CustomerLoanRequestStatusLog::ACTOR_CUSTOMER => 'مشتری',
            CustomerLoanRequestStatusLog::ACTOR_SYSTEM => 'سامانه',
            default => $log->actor_type,
        };

        $fromCode = $log->from_status;
        $toCode = $log->to_status;

        return [
            'id' => (int) $log->id,
            'user_label' => $actorLabel,
            'created_at_fa' => $dtFa,
            'from_status_customer' => $fromCode === null || $fromCode === ''
                ? '—'
                : ($titles[$fromCode] ?? $fromCode),
            'to_status_customer' => $titles[$toCode] ?? $toCode,
        ];
    }

    private function parseJalaliDateStart(string $value): ?Carbon
    {
        $value = $this->normalizeJalaliDigits($value);
        if ($value === '') {
            return null;
        }
        try {
            $j = Jalali::parseFormat('Y/m/d', $value);
            $j->startDay();

            return Carbon::createFromTimestamp($j->getTimestamp(), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * پایان همان «روز» در تقویم جلالی؛ استفاده از Carbon::endOfDay() اشتباه است چون مرز روز میلادی است.
     */
    private function parseJalaliDateEnd(string $value): ?Carbon
    {
        $value = $this->normalizeJalaliDigits($value);
        if ($value === '') {
            return null;
        }
        try {
            $j = Jalali::parseFormat('Y/m/d', $value);
            $j->startDay()->endDay();

            return Carbon::createFromTimestamp($j->getTimestamp(), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeJalaliDigits(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(array_merge($fa, $ar), array_merge($en, $en), $value);
    }

    /**
     * استخراج و اعتبارسنجی فیلترهای فهرست. در صورت نامعتبر بودن تاریخ‌ها، به محدودهٔ
     * پیش‌فرض (ابتدای ماه جاری تا امروز) برمی‌گردد — مشابه رفتار قبلی.
     *
     * @return array{q: string, from_jdate: string, to_jdate: string, from: Carbon, to: Carbon, statuses: list<string>}
     */
    private function resolveListFilters(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));

        $fromJDate = trim((string) $request->query('from_jdate', ''));
        $toJDate = trim((string) $request->query('to_jdate', ''));

        $nowJ = Jalali::now();
        $defaultFromJ = (clone $nowJ)->startMonth()->format('Y/m/d');
        $defaultToJ = $nowJ->format('Y/m/d');

        if ($fromJDate === '') {
            $fromJDate = $defaultFromJ;
        }
        if ($toJDate === '') {
            $toJDate = $defaultToJ;
        }

        $from = $this->parseJalaliDateStart($fromJDate);
        $to = $this->parseJalaliDateEnd($toJDate);

        if ($from === null || $to === null || $from->gt($to)) {
            $from = $this->parseJalaliDateStart($defaultFromJ);
            $to = $this->parseJalaliDateEnd($defaultToJ);
            $fromJDate = $defaultFromJ;
            $toJDate = $defaultToJ;
        }

        $rawStatuses = $request->query('status', []);
        if (is_string($rawStatuses)) {
            $rawStatuses = $rawStatuses === '' ? [] : [$rawStatuses];
        }
        if (! is_array($rawStatuses)) {
            $rawStatuses = [];
        }
        $rawStatuses = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => is_string($v) || is_int($v) ? trim((string) $v) : '',
            $rawStatuses,
        ), static fn (string $v): bool => $v !== '')));

        $allowedStatusCodes = LoanRequestStatusDefinition::query()
            ->pluck('code')
            ->map(static fn ($c): string => (string) $c)
            ->all();
        $statuses = array_values(array_intersect($rawStatuses, $allowedStatusCodes));

        return [
            'q' => $search,
            'from_jdate' => $fromJDate,
            'to_jdate' => $toJDate,
            'from' => $from,
            'to' => $to,
            'statuses' => $statuses,
        ];
    }

    /**
     * فیلتر اختیاری مشتری از query string (برای خروجی اکسل/چاپ و فهرست اصلی).
     */
    private function optionalCustomerFilterFromRequest(Request $request): ?int
    {
        $id = (int) $request->query('customer_id', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  array{q: string, from: Carbon, to: Carbon, statuses: list<string>}  $filters
     * @return Builder<CustomerLoanRequest>
     */
    private function buildFilteredQuery(array $filters, ?int $restrictedToCustomerId = null): Builder
    {
        $search = $filters['q'];
        $statuses = $filters['statuses'];

        return CustomerLoanRequest::query()
            ->with(['customer', 'loanType'])
            ->when($restrictedToCustomerId !== null && $restrictedToCustomerId > 0, static function (Builder $q) use ($restrictedToCustomerId): void {
                $q->where('customer_loan_requests.customer_id', $restrictedToCustomerId);
            })
            ->whereBetween('submitted_at', [$filters['from'], $filters['to']])
            ->when($statuses !== [], static function (Builder $q) use ($statuses): void {
                $q->whereIn('customer_loan_requests.status', $statuses);
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    if (ctype_digit($search)) {
                        $sub->where('customer_loan_requests.id', (int) $search);
                    }
                    $sub->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('expert_note', 'like', '%'.$search.'%')
                        ->orWhere('expert_note_customer', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', function (Builder $c) use ($search): void {
                            $c->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('national_id', 'like', '%'.$search.'%')
                                ->orWhere('mobile', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('loanType', function (Builder $lt) use ($search): void {
                            $lt->where('title', 'like', '%'.$search.'%')
                                ->orWhere('plan_title', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    /**
     * نوشتن یک ردیف در خروجی «اکسل»؛ شبیه‌سازی‌شده با UTF-16LE + tab.
     * هم‌راستا با `CustomerController::writeExcelUnicodeRow`.
     *
     * @param  resource  $out
     * @param  array<int, string>  $cells
     */
    private function writeExcelUnicodeRow($out, array $cells): void
    {
        $cleanCells = array_map(static function (string $value): string {
            return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);
        }, $cells);

        $line = implode("\t", $cleanCells)."\r\n";
        fwrite($out, mb_convert_encoding($line, 'UTF-16LE', 'UTF-8'));
    }

    /**
     * ستون‌های خروجی اکسل برای یک درخواست؛ مقادیر همگی رشته‌اند تا اکسل آن‌ها را
     * بدون تفسیر فرمت (مثلاً تبدیل تاریخ به عدد) نمایش دهد.
     *
     * @param  array<string, string>  $statusTitles
     * @return array<int, string>
     */
    private function buildExportCells(CustomerLoanRequest $r, array $statusTitles): array
    {
        $customer = $r->customer;
        $loanType = $r->loanType;

        $customerName = $customer !== null ? trim($customer->fullName()) : '';
        $customerCode = $customer !== null ? trim((string) ($customer->customer_code ?? '')) : '';
        $nationalId = $customer !== null ? trim((string) ($customer->national_id ?? '')) : '';
        $mobile = $customer !== null ? trim((string) ($customer->mobile ?? '')) : '';
        $city = $customer !== null ? trim((string) ($customer->city ?? '')) : '';

        $loanTitle = '';
        if ($loanType !== null) {
            $loanTitle = trim((string) ($loanType->plan_title ?? ''));
            if ($loanTitle === '') {
                $loanTitle = trim((string) ($loanType->title ?? ''));
            }
        }

        $submittedAt = $r->submitted_at ?? $r->created_at;
        $submittedFa = '';
        if ($submittedAt !== null) {
            $c = $submittedAt instanceof Carbon ? $submittedAt : Carbon::parse((string) $submittedAt);
            $submittedFa = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d')).' '.Jalali::enToFaNumbers($c->format('H:i'));
        }

        $statusCode = (string) $r->status;
        $statusLabel = $statusTitles[$statusCode] ?? $statusCode;

        $expertNote = trim((string) ($r->expert_note ?? ''));

        $amountStr = Jalali::enToFaNumbers(number_format(max(0, (int) $r->amount_toman), 0, '.', ','));

        return [
            Jalali::enToFaNumbers((string) $r->id),
            $customerName !== '' ? $customerName : '—',
            $customerCode !== '' ? Jalali::enToFaNumbers($customerCode) : '—',
            $nationalId !== '' ? Jalali::enToFaNumbers($nationalId) : '—',
            $loanTitle !== '' ? $loanTitle : '—',
            $amountStr,
            $submittedFa !== '' ? $submittedFa : '—',
            $statusLabel,
            $expertNote !== '' ? $expertNote : '—',
            $mobile !== '' ? Jalali::enToFaNumbers($mobile) : '—',
            $city !== '' ? $city : '—',
        ];
    }

    /**
     * ساخت ردیف برای صفحهٔ چاپ (ساختار آرایه‌ای استاندارد).
     *
     * @param  array<string, string>  $statusTitles
     * @return array<string, string>
     */
    private function buildPrintRow(
        CustomerLoanRequest $r,
        AdminCustomerLoanRequestPresenter $presenter,
        array $statusTitles,
    ): array {
        $base = $presenter->mapRow($r, $statusTitles);

        $customer = $r->customer;
        $customerCode = $customer !== null ? trim((string) ($customer->customer_code ?? '')) : '';
        $mobile = $customer !== null ? trim((string) ($customer->mobile ?? '')) : '';
        $city = $customer !== null ? trim((string) ($customer->city ?? '')) : '';

        return [
            'request_no_fa' => (string) $base['request_no_fa'],
            'customer_name' => (string) $base['customer_name'],
            'customer_code_fa' => $customerCode !== '' ? Jalali::enToFaNumbers($customerCode) : '—',
            'national_id_fa' => (string) $base['national_id_fa'],
            'loan_title' => (string) $base['loan_title'],
            'amount_fa' => (string) $base['amount_fa'],
            'datetime_fa' => (string) $base['datetime_fa'],
            'status_label' => (string) $base['status_label'],
            'expert_note' => trim((string) ($r->expert_note ?? '')),
            'mobile_fa' => $mobile !== '' ? Jalali::enToFaNumbers($mobile) : '—',
            'city' => $city !== '' ? $city : '—',
        ];
    }
}
