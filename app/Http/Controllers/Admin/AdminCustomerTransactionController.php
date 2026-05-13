<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Services\Admin\AdminCustomerTransactionListService;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCustomerTransactionController extends Controller
{
    public function index(Request $request, AdminCustomerTransactionListService $list): View
    {
        $parsed = $this->parseListingFilters($request);
        $filters = $parsed['filters'];

        /** @var LengthAwarePaginator<int, CustomerTransaction> $transactions */
        $transactions = $list->paginate($filters);

        $rowSnapshots = [];
        foreach ($transactions as $tx) {
            $rowSnapshots[$tx->id] = $this->buildRowSnapshot($tx);
        }

        return view('admin.customer-transactions.index', [
            'pageTitle' => 'تراکنش‌های مشتریان',
            'transactions' => $transactions,
            'filters' => $filters,
            'filterInputs' => $parsed['filterInputs'],
            'rowSnapshots' => $rowSnapshots,
            'kindLabels' => $this->kindLabelsFa(),
            'statusLabels' => $this->statusLabelsFa(),
            'ctxListRouteName' => 'admin.customer-transactions.index',
            'ctxListRouteParams' => [],
            'ctxForcedCustomerId' => null,
            'ctxEmbedCustomer' => null,
        ]);
    }

    /**
     * نمایش تراکنش‌های یک مشتری داخل iframe (مدال مدیریت وام‌ها) — مسیر و Blade مستقل از صفحهٔ فهرست عمومی.
     */
    public function customerEmbedPanel(Request $request, Customer $customer, AdminCustomerTransactionListService $list): View
    {
        $parsed = $this->parseListingFilters($request);
        $filters = $parsed['filters'];
        $filters['customer_id'] = (int) $customer->id;

        $filterInputs = $parsed['filterInputs'];
        $filterInputs['customer_id'] = (string) $customer->id;

        /** @var LengthAwarePaginator<int, CustomerTransaction> $transactions */
        $transactions = $list->paginate($filters);

        $rowSnapshots = [];
        foreach ($transactions as $tx) {
            $rowSnapshots[$tx->id] = $this->buildRowSnapshot($tx);
        }

        $name = trim($customer->first_name.' '.$customer->last_name);

        return view('admin.customer-transactions.customer_embed', [
            'pageTitle' => 'تراکنش‌ها — '.($name !== '' ? $name : 'مشتری #'.$customer->id),
            'transactions' => $transactions,
            'filters' => $filters,
            'filterInputs' => $filterInputs,
            'rowSnapshots' => $rowSnapshots,
            'kindLabels' => $this->kindLabelsFa(),
            'statusLabels' => $this->statusLabelsFa(),
            'ctxListRouteName' => 'admin.customers.customer-transactions.embed',
            'ctxListRouteParams' => ['customer' => $customer],
            'ctxForcedCustomerId' => (int) $customer->id,
            'ctxEmbedCustomer' => $customer,
        ]);
    }

    public function export(Request $request, AdminCustomerTransactionListService $list): StreamedResponse
    {
        $parsed = $this->parseListingFilters($request);
        $filters = $parsed['filters'];

        $kindLabels = $this->kindLabelsFa();
        $statusLabels = $this->statusLabelsFa();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle('تراکنشها');

        $headers = [
            'شناسه تراکنش',
            'تاریخ ثبت (شمسی)',
            'شناسه مشتری',
            'نام مشتری',
            'موبایل',
            'کد مشتری',
            'نوع تراکنش',
            'وضعیت',
            'عنوان',
            'شرح',
            'مبلغ (تومان)',
            'مبلغ (ریال)',
            'درگاه',
            'شماره پیگیری',
            'مرجع بانک',
            'منبع سیستمی',
            'خطا / توضیح',
            'meta (JSON)',
            'نوع (سیستم)',
            'وضعیت (سیستم)',
            'تاریخ به‌روزرسانی (شمسی)',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $row = 2;
        foreach ($list->makeFilteredQuery($filters)->cursor() as $tx) {
            $sheet->fromArray([$this->buildExportRow($tx, $kindLabels, $statusLabels)], null, 'A'.$row);
            $row++;
        }

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastCol.'1');

        $filename = 'customer-transactions-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{filters: array<string, mixed>, filterInputs: array<string, string>}
     */
    private function parseListingFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'string', 'max:48'],
            'status' => ['nullable', 'string', 'max:32'],
            'gateway' => ['nullable', 'string', 'max:24'],
            'customer_id' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]*$/'],
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
        ], [], [
            'q' => 'جستجو',
            'kind' => 'نوع تراکنش',
            'status' => 'وضعیت',
            'gateway' => 'درگاه',
            'customer_id' => 'مشتری',
            'date_from' => 'از تاریخ',
            'date_to' => 'تا تاریخ',
        ]);

        $fromCarbon = JalaliInputParser::toCarbonDate(
            isset($validated['date_from']) && is_string($validated['date_from']) ? $validated['date_from'] : null
        );
        $toCarbon = JalaliInputParser::toCarbonDate(
            isset($validated['date_to']) && is_string($validated['date_to']) ? $validated['date_to'] : null
        );
        if ($toCarbon !== null) {
            $toCarbon = $toCarbon->copy()->endOfDay();
        }

        $filters = [
            'q' => isset($validated['q']) && is_string($validated['q']) ? trim($validated['q']) : null,
            'kind' => isset($validated['kind']) && is_string($validated['kind']) ? trim($validated['kind']) : null,
            'status' => isset($validated['status']) && is_string($validated['status']) ? trim($validated['status']) : null,
            'gateway' => isset($validated['gateway']) && is_string($validated['gateway']) ? trim($validated['gateway']) : null,
            'customer_id' => isset($validated['customer_id']) && is_string($validated['customer_id']) && trim($validated['customer_id']) !== ''
                ? (int) trim($validated['customer_id'])
                : null,
            'date_from' => $fromCarbon,
            'date_to' => $toCarbon,
        ];

        return [
            'filters' => $filters,
            'filterInputs' => [
                'q' => $filters['q'] ?? '',
                'kind' => $filters['kind'] ?? '',
                'status' => $filters['status'] ?? '',
                'gateway' => $filters['gateway'] ?? '',
                'customer_id' => $filters['customer_id'] !== null ? (string) $filters['customer_id'] : '',
                'date_from' => isset($validated['date_from']) && is_string($validated['date_from']) ? $validated['date_from'] : '',
                'date_to' => isset($validated['date_to']) && is_string($validated['date_to']) ? $validated['date_to'] : '',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $kindLabels
     * @param  array<string, string>  $statusLabels
     * @return list<int|string>
     */
    private function buildExportRow(CustomerTransaction $tx, array $kindLabels, array $statusLabels): array
    {
        $tx->loadMissing(['customer']);

        $c = $tx->customer;
        $created = Carbon::parse($tx->created_at);
        $updated = Carbon::parse($tx->updated_at);

        $createdFa = Jalali::enToFaNumbers(Jalali::instance($created)->format('Y/m/d H:i'));
        $updatedFa = Jalali::enToFaNumbers(Jalali::instance($updated)->format('Y/m/d H:i'));

        $sourceType = $tx->source_type;
        $sourceShort = is_string($sourceType) && $sourceType !== ''
            ? (class_basename($sourceType)).' #'.Jalali::enToFaNumbers((string) ($tx->source_id ?? ''))
            : '';

        $meta = $tx->meta;
        $metaStr = $meta !== null && $meta !== []
            ? (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        return [
            (int) $tx->id,
            $createdFa,
            (int) $tx->customer_id,
            $c !== null ? trim($c->fullName()) : '',
            $c !== null ? (string) ($c->mobile ?? '') : '',
            $c !== null ? (string) ($c->customer_code ?? '') : '',
            $kindLabels[$tx->kind] ?? (string) $tx->kind,
            $statusLabels[$tx->status] ?? (string) $tx->status,
            self::excelOneLine((string) $tx->title),
            self::excelOneLine($tx->detail !== null ? (string) $tx->detail : ''),
            (int) $tx->amount_toman,
            (int) $tx->amount_rial,
            $this->gatewayLabelFa((string) ($tx->gateway_key ?? '')),
            $tx->track_id !== null ? (string) $tx->track_id : '',
            $tx->bank_reference !== null ? trim((string) $tx->bank_reference) : '',
            $sourceShort,
            $tx->failure_reason !== null ? self::excelOneLine((string) $tx->failure_reason) : '',
            $metaStr,
            (string) $tx->kind,
            (string) $tx->status,
            $updatedFa,
        ];
    }

    private static function excelOneLine(string $v): string
    {
        return trim(str_replace(["\r\n", "\r", "\n"], ' ', $v));
    }

    /**
     * @return array<string, string>
     */
    private function kindLabelsFa(): array
    {
        return [
            CustomerTransaction::KIND_INSTALLMENT_ONLINE_PAYMENT => 'پرداخت قسط (درگاه)',
            CustomerTransaction::KIND_WALLET_TOPUP => 'شارژ کیف پول',
            CustomerTransaction::KIND_FULL_SETTLEMENT_ONLINE_PAYMENT => 'تسویهٔ کلی بدهی (درگاه)',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabelsFa(): array
    {
        return [
            CustomerTransaction::STATUS_CREATED => 'ثبت درخواست',
            CustomerTransaction::STATUS_REDIRECTED => 'هدایت به درگاه',
            CustomerTransaction::STATUS_COMPLETED => 'پرداخت موفق',
            CustomerTransaction::STATUS_FAILED => 'ناموفق / لغو',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRowSnapshot(CustomerTransaction $tx): array
    {
        $tx->loadMissing(['customer']);

        $c = $tx->customer;
        $customerLine = $c !== null
            ? trim($c->fullName()).' — '.Jalali::enToFaNumbers((string) ($c->mobile ?? '')).' — کد: '.Jalali::enToFaNumbers((string) ($c->customer_code ?? ''))
            : '—';

        $meta = $tx->meta;
        $metaJson = $meta !== null && $meta !== []
            ? (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : null;

        $created = Carbon::parse($tx->created_at);
        $updated = Carbon::parse($tx->updated_at);

        $sourceType = $tx->source_type;
        $sourceShort = is_string($sourceType) && $sourceType !== ''
            ? (class_basename($sourceType)).' #'.Jalali::enToFaNumbers((string) ($tx->source_id ?? ''))
            : '—';

        $customerCode = $c !== null ? trim((string) ($c->customer_code ?? '')) : '';

        return [
            'id' => (int) $tx->id,
            'customer_id' => (int) $tx->customer_id,
            'customer_code_raw' => $customerCode,
            'customer_profile_url' => $customerCode !== ''
                ? route('admin.customers.index', ['q' => $customerCode])
                : route('admin.customers.index', ['q' => (string) $tx->customer_id]),
            'customer_line' => $customerLine,
            'kind' => (string) $tx->kind,
            'kind_label_fa' => ($this->kindLabelsFa())[$tx->kind] ?? $tx->kind,
            'status' => (string) $tx->status,
            'status_label_fa' => ($this->statusLabelsFa())[$tx->status] ?? $tx->status,
            'title' => (string) $tx->title,
            'detail' => $tx->detail !== null && trim((string) $tx->detail) !== '' ? (string) $tx->detail : null,
            'amount_toman' => (int) $tx->amount_toman,
            'amount_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $tx->amount_toman), 0, '.', ',')).' تومان',
            'amount_rial_fa' => Jalali::enToFaNumbers(number_format(max(0, (int) $tx->amount_rial), 0, '.', ',')).' ریال',
            'gateway_key' => $tx->gateway_key,
            'gateway_label_fa' => $this->gatewayLabelFa((string) ($tx->gateway_key ?? '')),
            'track_id_fa' => $tx->track_id !== null ? Jalali::enToFaNumbers((string) $tx->track_id) : '—',
            'bank_reference_fa' => $tx->bank_reference !== null && trim((string) $tx->bank_reference) !== ''
                ? Jalali::enToFaNumbers(trim((string) $tx->bank_reference))
                : '—',
            'failure_reason' => $tx->failure_reason !== null && trim((string) $tx->failure_reason) !== ''
                ? (string) $tx->failure_reason
                : null,
            'source_short' => $sourceShort,
            'source_type' => $tx->source_type,
            'source_id' => $tx->source_id,
            'meta_json' => $metaJson,
            'created_at_fa' => Jalali::enToFaNumbers(Jalali::instance($created)->format('Y/m/d H:i:s')),
            'updated_at_fa' => Jalali::enToFaNumbers(Jalali::instance($updated)->format('Y/m/d H:i:s')),
        ];
    }

    private function gatewayLabelFa(string $key): string
    {
        return match (mb_strtolower($key)) {
            'zibal' => 'زیبال',
            '' => '—',
            default => $key,
        };
    }
}
