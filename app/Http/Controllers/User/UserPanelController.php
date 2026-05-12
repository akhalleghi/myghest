<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\LoanType;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Portal\CustomerPortalSummaryBuilder;
use App\Support\BankingHtmlSanitizer;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class UserPanelController extends Controller
{
    public function dashboard(): View
    {
        $customer = Auth::guard('customer')->user();
        $portalLoans = $customer !== null
            ? app(CustomerLoanPortalPresenter::class)->forDashboard($customer)
            : ['loan_count' => 0, 'loans' => []];
        $portalLoans['loan_count_fa'] = Jalali::enToFaNumbers((string) (int) ($portalLoans['loan_count'] ?? 0));

        $portalSummary = $customer !== null
            ? app(CustomerPortalSummaryBuilder::class)->build($customer, $portalLoans)
            : null;

        $showInPanel = AppSetting::query()
            ->where('key', 'banking_info_show_in_user_panel')
            ->value('value');
        $enabled = is_string($showInPanel) && $showInPanel === '1';

        $rawHtml = AppSetting::query()
            ->where('key', 'banking_info_html')
            ->value('value');
        $bankingHtmlSafe = BankingHtmlSanitizer::clean(is_string($rawHtml) ? $rawHtml : null);

        $showUserBankingCard = $enabled && trim($bankingHtmlSafe) !== '';

        return view('user.portal.dashboard', [
            'pageTitle' => 'داشبورد',
            'showUserBankingCard' => $showUserBankingCard,
            'bankingInfoHtmlSafe' => $bankingHtmlSafe,
            'portalLoans' => $portalLoans,
            'portalSummary' => $portalSummary,
        ]);
    }

    public function loans(): View
    {
        $customer = Auth::guard('customer')->user();
        $portalLoans = $customer !== null
            ? app(CustomerLoanPortalPresenter::class)->forDashboard($customer)
            : ['loan_count' => 0, 'loans' => []];
        $portalLoans['loan_count_fa'] = Jalali::enToFaNumbers((string) (int) ($portalLoans['loan_count'] ?? 0));

        return view('user.portal.loans', [
            'pageTitle' => 'لیست وام‌ها',
            'portalLoans' => $portalLoans,
        ]);
    }

    public function loanRequest(): View
    {
        // در حال حاضر مدل/جدول درخواست وام پیاده‌سازی نشده است؛ یک کالکشن خالی پاس می‌شود
        // تا ساختار صفحه (جدول، کارت موبایل، خالی‌بودن…) آماده برای توسعهٔ آتی باشد.
        // وقتی مدل ساخته شد، می‌توان این مقدار را با Paginator واقعی جایگزین کرد.
        $loanRequests = collect();

        // طرح‌های وام قابل انتخاب توسط مشتری: «در لیست طرح‌ها قرار بگیرد» فعال و «ثبت درخواست تعلیق نشده»
        $loanPlans = LoanType::query()
            ->where('plan_list_enabled', true)
            ->where('registration_suspended', false)
            ->orderBy('title')
            ->get()
            ->map(fn (LoanType $lt) => $this->mapLoanPlanForWizard($lt))
            ->values()
            ->all();

        return view('user.portal.loan-request', [
            'pageTitle' => 'درخواست وام',
            'loanRequests' => $loanRequests,
            'loanRequestsCountFa' => Jalali::enToFaNumbers((string) $loanRequests->count()),
            'loanPlans' => $loanPlans,
        ]);
    }

    /**
     * تبدیل LoanType به ساختار سبک و امن برای wizard (سمت کلاینت).
     * فقط فیلدهای موردنیاز برای انتخاب طرح و محاسبهٔ اقساط را افشا می‌کند؛
     * هیچ ID یا اطلاعات حساس داخلی اضافه پاس داده نمی‌شود.
     *
     * @return array<string, mixed>
     */
    private function mapLoanPlanForWizard(LoanType $lt): array
    {
        $rd = is_array($lt->required_documents) ? $lt->required_documents : [];

        $filterByTiming = static fn (string $timing): array => array_values(array_filter(
            $rd,
            static fn ($d) => is_array($d) && ($d['timing'] ?? null) === $timing
        ));

        $initialDocs = array_map(static fn (array $d) => [
            'title' => (string) ($d['title'] ?? ''),
            'description' => isset($d['description']) ? (string) $d['description'] : null,
        ], $filterByTiming(LoanType::DOC_TIMING_INITIAL));

        $afterDocs = array_map(static fn (array $d) => [
            'title' => (string) ($d['title'] ?? ''),
        ], $filterByTiming(LoanType::DOC_TIMING_AFTER_EVALUATION));

        $rep = is_array($lt->repayment_periods) ? $lt->repayment_periods : ['type' => 'unlimited'];
        $repType = (string) ($rep['type'] ?? 'unlimited');
        $repMaxMonths = isset($rep['max_months']) ? (int) $rep['max_months'] : null;
        $allowedRows = [];
        if (isset($rep['allowed_rows']) && is_array($rep['allowed_rows'])) {
            foreach ($rep['allowed_rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $months = isset($row['months']) ? (int) $row['months'] : 0;
                if ($months <= 0) {
                    continue;
                }
                $allowedRows[] = [
                    'months' => $months,
                    'cap' => isset($row['cap']) ? (int) $row['cap'] : null,
                ];
            }
        }

        $gapUnit = (string) ($lt->installment_gap_unit ?? LoanType::GAP_MONTHLY);
        $maxAmount = $lt->max_loan_amount !== null ? (int) $lt->max_loan_amount : null;
        $maxGap = $lt->max_installment_gap !== null ? (int) $lt->max_installment_gap : null;
        $interestRate = (float) $lt->interest_rate;
        $lateCoef = (float) $lt->daily_late_coefficient;
        $earlyCoef = (float) $lt->daily_early_coefficient;
        $profitMethod = (string) ($lt->profit_calculation_method ?? LoanType::PROFIT_MONTHLY);

        $displayTitle = trim((string) ($lt->plan_title ?? '')) !== ''
            ? (string) $lt->plan_title
            : (string) $lt->title;

        return [
            'id' => (int) $lt->id,
            'title' => $displayTitle,
            'title_with_code' => sprintf('%s (کد طرح: %s)', $displayTitle, Jalali::enToFaNumbers((string) $lt->id)),
            'profit_method' => $profitMethod,
            'profit_method_label' => $lt->profitCalculationLabel(),
            'interest_rate' => $interestRate,
            'interest_rate_fa' => Jalali::enToFaNumbers($this->trimDecimal($interestRate)),
            'daily_late_coefficient' => $lateCoef,
            'daily_early_coefficient' => $earlyCoef,
            'max_loan_amount' => $maxAmount,
            'max_loan_amount_fa' => $maxAmount !== null ? Jalali::enToFaNumbers(number_format($maxAmount, 0, '.', ',')) : null,
            'max_installment_gap' => $maxGap,
            'installment_gap_unit' => $gapUnit,
            'installment_gap_unit_label' => $lt->installmentGapLabel(),
            'repayment' => [
                'type' => $repType,
                'max_months' => $repMaxMonths,
                'allowed_rows' => $allowedRows,
            ],
            'initial_documents' => $initialDocs,
            'after_evaluation_documents' => $afterDocs,
        ];
    }

    /**
     * نمایش تمیز عدد اعشاری: 4.00 → "4" ، 4.50 → "4.5"
     */
    private function trimDecimal(float $n): string
    {
        $s = number_format($n, 2, '.', '');

        return rtrim(rtrim($s, '0'), '.');
    }
}
