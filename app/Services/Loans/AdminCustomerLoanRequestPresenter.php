<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanRequest;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

/**
 * ردیف جدول «درخواست وام‌ها» در پنل ادمین.
 */
final class AdminCustomerLoanRequestPresenter
{
    public function __construct(
        private readonly LoanRequestStatusPresentation $statusPresentation,
    ) {}

    /**
     * @param  array<string, string>  $statusTitleByCode
     * @return array<string, mixed>
     */
    public function mapRow(CustomerLoanRequest $r, array $statusTitleByCode): array
    {
        $lt = $r->loanType;
        $loanTitle = $lt !== null && trim((string) ($lt->plan_title ?? '')) !== ''
            ? (string) $lt->plan_title
            : (string) ($lt?->title ?? '—');

        $cust = $r->customer;
        $customerId = $cust !== null ? (int) $cust->id : 0;
        $customerName = $cust !== null ? $cust->fullName() : '—';
        $nationalId = $cust !== null ? trim((string) ($cust->national_id ?? '')) : '';
        $nationalIdFa = $nationalId !== '' ? Jalali::enToFaNumbers($nationalId) : '—';

        $at = $r->submitted_at ?? $r->created_at;
        $submitted = $at instanceof Carbon ? $at : Carbon::parse((string) $at);

        $dateFa = Jalali::enToFaNumbers(Jalali::instance($submitted)->format('Y/m/d'));
        $timeFa = Jalali::enToFaNumbers($submitted->format('H:i'));
        $datetimeFa = $dateFa.' — '.$timeFa;

        $amountFa = Jalali::enToFaNumbers(number_format((int) $r->amount_toman, 0, '.', ','));
        $requestNoFa = Jalali::enToFaNumbers((string) $r->id);

        $status = (string) $r->status;
        $chip = $this->statusPresentation->adminBadge($status, $statusTitleByCode);

        $expert = trim((string) ($r->expert_note ?? ''));
        $expertHtml = $expert !== ''
            ? nl2br(e($expert))
            : '<span class="lrq-muted">ثبت نشده</span>';

        return [
            'id' => (int) $r->id,
            'request_no_fa' => $requestNoFa,
            'amount_toman' => (int) $r->amount_toman,
            'amount_fa' => $amountFa,
            'datetime_fa' => $datetimeFa,
            'loan_title' => $loanTitle,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'national_id_fa' => $nationalIdFa,
            'status' => $status,
            'status_label' => $chip['label'],
            'status_badge_class' => $chip['class'],
            'expert_note_html' => $expertHtml,
        ];
    }
}
