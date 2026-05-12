<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanRequest;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Str;

/**
 * دادهٔ امن برای جدول/کارت پنل کاربر (بدون مسیر فایل و جزئیات داخلی).
 */
final class CustomerLoanRequestUserPresenter
{
    public function __construct(
        private readonly LoanRequestStatusPresentation $statusPresentation,
    ) {}

    /**
     * @param  array<string, string>  $statusTitleByCode
     * @return array<string, mixed>
     */
    public function mapRequest(CustomerLoanRequest $r, array $statusTitleByCode): array
    {
        $r->loadMissing(['loanType', 'documents']);
        $lt = $r->loanType;
        $title = $lt !== null && trim((string) ($lt->plan_title ?? '')) !== ''
            ? (string) $lt->plan_title
            : (string) ($lt?->title ?? '—');

        $at = $r->submitted_at ?? $r->created_at;
        $submitted = $at instanceof Carbon ? $at : Carbon::parse((string) $at);
        $jalali = Jalali::instance($submitted)->format('Y/m/d');
        $jalaliFa = Jalali::enToFaNumbers($jalali);

        $idFa = Jalali::enToFaNumbers((string) $r->id);
        $amountFa = Jalali::enToFaNumbers(number_format((int) $r->amount_toman, 0, '.', ','));

        $status = (string) $r->status;
        $chip = $this->statusPresentation->userChip($status, $statusTitleByCode);

        $expertCustomer = trim((string) ($r->expert_note_customer ?? ''));
        $expertCustomerHtml = $expertCustomer !== ''
            ? nl2br(e($expertCustomer))
            : 'تاکنون پیامی از کارشناس برای شما ثبت نشده است.';

        $physical = (bool) $r->documents_physical_received;
        $physicalLine = $physical
            ? 'وضعیت تحویل شدن مدارک فیزیکی به شرکت: تحویل داده شده'
            : 'وضعیت تحویل شدن مدارک فیزیکی به شرکت: به شرکت نرسیده';

        $atCompanyBanner = $physical
            ? '<div class="lr-at-company-banner" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>مدارک ارسال‌شده به دست شرکت رسیده است.</span></div>'
            : '';

        $search = implode(' ', array_filter([
            (string) $r->id,
            $idFa,
            Str::lower($title),
            $amountFa,
            Str::lower($jalaliFa),
            Str::lower($chip['label']),
            Str::lower($expertCustomer),
        ]));

        $wizardEditable = CustomerLoanRequest::wizardAllowsCustomerDocumentEditing($r);

        $wizardOpenLabel = $wizardEditable ? 'ویرایش' : 'مشاهده';

        return [
            'id' => (int) $r->id,
            'id_fa' => $idFa,
            'loan_title' => $title,
            'amount_toman' => (int) $r->amount_toman,
            'amount_fa' => $amountFa,
            'submitted_at_jalali' => $jalali,
            'submitted_at_jalali_fa' => $jalaliFa,
            'status' => $status,
            'status_label' => $chip['label'],
            'status_chip_class' => $chip['class'],
            'expert_note_html' => $expertCustomerHtml,
            'documents_physical_received' => $physical,
            'documents_physical_line_fa' => $physicalLine,
            'documents_at_company_banner_html' => $atCompanyBanner,
            'wizard_editable' => $wizardEditable,
            'wizard_open_label' => $wizardOpenLabel,
            'search' => $search,
        ];
    }
}
