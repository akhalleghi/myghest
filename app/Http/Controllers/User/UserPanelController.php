<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
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

    public function deposits(): View
    {
        return view('user.portal.deposits', [
            'pageTitle' => 'اعلام واریزی‌ها',
        ]);
    }

    public function loanRequest(): View
    {
        return view('user.portal.loan-request', [
            'pageTitle' => 'درخواست وام',
        ]);
    }
}
