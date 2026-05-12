<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use App\Models\CustomerLoanRequestStatusLog;
use App\Models\LoanRequestStatusDefinition;
use App\Models\SmsLog;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Loans\AdminCustomerLoanRequestPresenter;
use App\Services\Loans\AdminCustomerLoanRequestUpdateService;
use App\Services\Loans\AdminLoanRequestEditModalPresenter;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCustomerLoanRequestController extends Controller
{
    public function index(Request $request, AdminCustomerLoanRequestPresenter $presenter): View
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

        $query = CustomerLoanRequest::query()
            ->with(['customer', 'loanType'])
            ->whereBetween('submitted_at', [$from, $to])
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

        $paginator = $query->paginate(25)->withQueryString();

        $statusTitles = LoanRequestStatusDefinition::titlesByCode();
        $rows = $paginator->getCollection()->map(
            static fn (CustomerLoanRequest $r): array => $presenter->mapRow($r, $statusTitles)
        );

        $paginator->setCollection($rows);

        return view('admin.loan_requests.index', [
            'pageTitle' => 'درخواست وام‌ها',
            'loanRequests' => $paginator,
            'fromJDate' => $fromJDate,
            'toJDate' => $toJDate,
            'search' => $search,
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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
}
