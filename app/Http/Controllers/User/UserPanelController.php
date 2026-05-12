<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerLoanRequest;
use App\Models\LoanRequestStatusDefinition;
use App\Models\LoanType;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\CustomerLoanRequestUserPresenter;
use App\Services\Loans\LoanPlanForWizardMapper;
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
        $customer = Auth::guard('customer')->user();
        $presenter = app(CustomerLoanRequestUserPresenter::class);
        $loanRequests = collect();
        if ($customer !== null) {
            $statusTitles = LoanRequestStatusDefinition::titlesByCode();
            $loanRequests = CustomerLoanRequest::query()
                ->where('customer_id', $customer->id)
                ->with(['loanType', 'documents'])
                ->latest('id')
                ->get()
                ->map(static fn (CustomerLoanRequest $r): array => $presenter->mapRequest($r, $statusTitles));
        }

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
     * @return array<string, mixed>
     */
    private function mapLoanPlanForWizard(LoanType $lt): array
    {
        return app(LoanPlanForWizardMapper::class)->map($lt);
    }
}
