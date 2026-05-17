<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\SmsTemplate;
use App\Services\Admin\Reports\DepositsByDateReportService;
use App\Services\Admin\Reports\InstallmentDueDatesByDateReportService;
use App\Services\Admin\Reports\LoanGuaranteesReportService;
use App\Services\Admin\Reports\MemberLoansByDateReportService;
use App\Services\Admin\Reports\SettledMembersReportService;
use App\Services\Admin\Reports\WalletTransactionsByDateReportService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminReportsController extends Controller
{
    public function __construct(
        private readonly MemberLoansByDateReportService $memberLoansByDate,
        private readonly InstallmentDueDatesByDateReportService $installmentDueByDate,
        private readonly DepositsByDateReportService $depositsByDate,
        private readonly SettledMembersReportService $settledMembers,
        private readonly WalletTransactionsByDateReportService $walletTransactionsByDate,
        private readonly LoanGuaranteesReportService $loanGuarantees,
    ) {}

    public function index(): View
    {
        $today = Carbon::today();
        $jToday = Jalali::instance($today);

        return view('admin.reports.index', [
            'pageTitle' => 'گزارش‌ها',
            'defaultFromJdate' => Jalali::enToFaNumbers($jToday->clone()->startYear()->format('Y/m/d')),
            'defaultToJdate' => Jalali::enToFaNumbers($jToday->format('Y/m/d')),
            'quickSmsTemplates' => SmsTemplate::query()->orderBy('title')->get(['id', 'title']),
            'depositPaymentMethodOptions' => CustomerLoanInstallmentPayment::methodLabels(),
            'walletTransactionDirectionOptions' => WalletTransactionsByDateReportService::directionFilterOptions(),
            'walletTransactionSourceOptions' => WalletTransactionsByDateReportService::sourceFilterOptions(),
            'guaranteeTypeFilterOptions' => LoanGuaranteesReportService::guaranteeTypeFilterOptions(),
            'reportCards' => [
                [
                    'id' => 'member-loans-by-date',
                    'title' => 'وام‌های اعضا بر اساس تاریخ',
                    'description' => 'فهرست پرونده‌های وام بر اساس تاریخ شروع قرارداد، با جزئیات پرداخت، مانده و پیامک.',
                    'icon' => 'fa-hand-holding-dollar',
                    'accent' => '#2563eb',
                    'enabled' => true,
                ],
                [
                    'id' => 'installment-due-by-date',
                    'title' => 'سررسید اقساط بر اساس تاریخ',
                    'description' => 'فهرست اقساط بر اساس تاریخ سررسید، با جزئیات واریز، نحوه پرداخت و پیامک.',
                    'icon' => 'fa-calendar-days',
                    'accent' => '#7c3aed',
                    'enabled' => true,
                ],
                [
                    'id' => 'deposits-by-date',
                    'title' => 'واریزها بر اساس تاریخ',
                    'description' => 'فهرست واریزی‌های ثبت‌شده بر اساس تاریخ واریز، با جزئیات قسط و پرونده.',
                    'icon' => 'fa-money-bill-transfer',
                    'accent' => '#059669',
                    'enabled' => true,
                ],
                [
                    'id' => 'settled-members',
                    'title' => 'اعضای تسویه‌کننده وام',
                    'description' => 'فهرست اعضایی که حداقل یک پروندهٔ وام را تسویه کرده‌اند، با مجموع وام و تاریخ آخرین تسویه.',
                    'icon' => 'fa-user-check',
                    'accent' => '#d97706',
                    'enabled' => true,
                ],
                [
                    'id' => 'wallet-transactions-by-date',
                    'title' => 'واریز/برداشت کیف پول',
                    'description' => 'فهرست تراکنش‌های واریز و برداشت کیف پول اعضا بر اساس زمان ثبت، با درگاه و جزئیات پیگیری.',
                    'icon' => 'fa-wallet',
                    'accent' => '#0d9488',
                    'enabled' => true,
                ],
                [
                    'id' => 'loan-guarantees',
                    'title' => 'گزارش تضامین',
                    'description' => 'فهرست ضمانت‌های ثبت‌شده روی پرونده‌های وام، با جزئیات مشتری، مبلغ وام و اطلاعات ضامن.',
                    'icon' => 'fa-shield-halved',
                    'accent' => '#be123c',
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function memberLoansByDateData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'settled' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->memberLoansByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->memberLoansByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['settled'] ?? ''),
        );

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($rows),
            ],
        ]);
    }

    public function exportMemberLoansByDateExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'settled' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->memberLoansByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->memberLoansByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['settled'] ?? ''),
        );

        $filename = 'member-loans-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->memberLoansByDate->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->memberLoansByDate->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function installmentDueByDateData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'payment_status' => ['nullable', 'string', 'in:paid,unpaid,partial'],
            'overdue' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->installmentDueByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->installmentDueByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['payment_status'] ?? ''),
            (string) ($validated['overdue'] ?? ''),
        );

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($rows),
            ],
        ]);
    }

    public function exportInstallmentDueByDateExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'payment_status' => ['nullable', 'string', 'in:paid,unpaid,partial'],
            'overdue' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->installmentDueByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->installmentDueByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['payment_status'] ?? ''),
            (string) ($validated['overdue'] ?? ''),
        );

        $filename = 'installment-due-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->installmentDueByDate->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->installmentDueByDate->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function depositsByDateData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'payment_method' => ['nullable', 'string', Rule::in(CustomerLoanInstallmentPayment::methodKeys())],
        ]);

        $range = $this->depositsByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->depositsByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['payment_method'] ?? ''),
        );

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($rows),
            ],
        ]);
    }

    public function exportDepositsByDateExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'payment_method' => ['nullable', 'string', Rule::in(CustomerLoanInstallmentPayment::methodKeys())],
        ]);

        $range = $this->depositsByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->depositsByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['payment_method'] ?? ''),
        );

        $filename = 'deposits-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->depositsByDate->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->depositsByDate->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function settledMembersData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'min_settled_loans' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $range = $this->settledMembers->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->settledMembers->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (int) ($validated['min_settled_loans'] ?? 1),
        );

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($rows),
            ],
        ]);
    }

    public function exportSettledMembersExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'min_settled_loans' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $range = $this->settledMembers->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->settledMembers->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (int) ($validated['min_settled_loans'] ?? 1),
        );

        $filename = 'settled-members-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->settledMembers->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->settledMembers->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function walletTransactionsByDateData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'direction' => ['nullable', 'string', 'in:deposit,withdraw'],
            'source' => ['nullable', 'string', 'in:online,portal,admin,internal'],
        ]);

        $range = $this->walletTransactionsByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->walletTransactionsByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['direction'] ?? ''),
            (string) ($validated['source'] ?? ''),
        );

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($rows),
            ],
        ]);
    }

    public function exportWalletTransactionsByDateExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'direction' => ['nullable', 'string', 'in:deposit,withdraw'],
            'source' => ['nullable', 'string', 'in:online,portal,admin,internal'],
        ]);

        $range = $this->walletTransactionsByDate->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $rows = $this->walletTransactionsByDate->fetchRows(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['direction'] ?? ''),
            (string) ($validated['source'] ?? ''),
        );

        $filename = 'wallet-transactions-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->walletTransactionsByDate->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->walletTransactionsByDate->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function loanGuaranteesData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'guarantee_type' => ['nullable', 'string', Rule::in(array_keys(LoanGuaranteesReportService::guaranteeTypeFilterOptions()))],
            'settled' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->loanGuarantees->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $result = $this->loanGuarantees->fetchResult(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['guarantee_type'] ?? ''),
            (string) ($validated['settled'] ?? ''),
        );

        return response()->json([
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'meta' => [
                'from_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['from'])->format('Y/m/d')),
                'to_jdate' => Jalali::enToFaNumbers(Jalali::instance($range['to'])->format('Y/m/d')),
                'count' => count($result['rows']),
            ],
        ]);
    }

    public function exportLoanGuaranteesExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from_jdate' => ['nullable', 'string', 'max:20'],
            'to_jdate' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:200'],
            'guarantee_type' => ['nullable', 'string', Rule::in(array_keys(LoanGuaranteesReportService::guaranteeTypeFilterOptions()))],
            'settled' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $range = $this->loanGuarantees->resolveDateRange(
            isset($validated['from_jdate']) ? (string) $validated['from_jdate'] : null,
            isset($validated['to_jdate']) ? (string) $validated['to_jdate'] : null,
        );

        $result = $this->loanGuarantees->fetchResult(
            $range['from'],
            $range['to'],
            trim((string) ($validated['q'] ?? '')),
            (string) ($validated['guarantee_type'] ?? ''),
            (string) ($validated['settled'] ?? ''),
        );

        $filename = 'loan-guarantees-'
            .Jalali::instance($range['from'])->format('Ymd')
            .'-'
            .Jalali::instance($range['to'])->format('Ymd')
            .'-'
            .now()->format('His')
            .'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        $rows = $result['rows'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, $this->loanGuarantees->excelHeaderRow());

            foreach ($rows as $row) {
                $this->writeExcelUnicodeRow($out, $this->loanGuarantees->excelDataRow($row));
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
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
}
