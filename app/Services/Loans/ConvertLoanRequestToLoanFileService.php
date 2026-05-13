<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanRequest;
use App\Models\LoanType;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تبدیل یک «درخواست وام» به «پروندهٔ وام واقعی» (CustomerLoanFile) به‌همراه تولید جدول اقساط.
 *
 * این سرویس دو نقطه فراهم می‌کند:
 *  - preview(): جزئیات مالی برای دیالوگ تأیید (بدون نوشتن در DB)
 *  - convert(): انجام عملی تبدیل در تراکنش
 *
 * منطق مالی دقیقاً مطابق مدال درخواست وام و CustomerController::storeLoan است تا تجربهٔ ادمین یکدست بماند.
 */
final class ConvertLoanRequestToLoanFileService
{
    public function __construct(
        private readonly LoanFileFinanceCalculator $calculator,
        private readonly LoanWizardParameterValidator $wizardValidator,
        private readonly LoanInstallmentScheduleService $scheduler,
    ) {}

    /**
     * @return array{
     *   request_id:int, customer:array<string,mixed>, loan_type:array<string,mixed>,
     *   amount_toman:int, installments_count:int, installment_interval_count:int,
     *   installment_interval_unit:string, installment_interval_unit_fa:string,
     *   profit_toman:int, total_repayable_toman:int, installment_amount_toman:int,
     *   loan_start_jdate:string|null, loan_start_jdate_fa:string|null,
     *   disbursement_due_jdate:string|null, disbursement_due_jdate_fa:string|null,
     *   first_due_jdate_fa:string|null, last_due_jdate_fa:string|null,
     *   already_converted:bool, converted_loan_file_id:int|null
     * }
     */
    public function preview(CustomerLoanRequest $request, ?string $startJdate, ?string $dueJdate): array
    {
        $request->loadMissing(['customer', 'loanType']);
        $customer = $request->customer;
        $plan = $request->loanType;

        if (! $customer instanceof Customer || ! $plan instanceof LoanType) {
            throw ValidationException::withMessages([
                '*' => 'اطلاعات مشتری یا نوع وام درخواست در دسترس نیست.',
            ]);
        }

        $amount = (int) $request->amount_toman;
        $count = max(1, (int) $request->installments_count);
        $gap = max(1, (int) $request->installment_interval_count);
        $unit = (string) $request->installment_interval_unit;
        $profitMethod = (string) ($request->profit_calculation_method ?: LoanType::PROFIT_MONTHLY);
        $rate = (float) $request->interest_rate;

        $profit = $this->calculator->calculateLoanProfitToman(
            $amount, $rate, $profitMethod, $count, $gap, $unit
        );
        $totalRepayable = max(0, $amount + $profit);
        $perInstallment = (int) max(1, (int) round($totalRepayable / $count));

        $startDate = $startJdate !== null ? JalaliInputParser::toCarbonDate($startJdate) : null;
        $dueDate = $dueJdate !== null && $dueJdate !== '' ? JalaliInputParser::toCarbonDate($dueJdate) : null;

        $firstDueFa = null;
        $lastDueFa = null;
        if ($startDate instanceof Carbon && $count > 0 && $gap > 0) {
            $firstDue = $startDate->copy();
            $lastDue = $startDate->copy();
            if ($unit === LoanType::GAP_WEEKLY) {
                $firstDue->addWeeks($gap);
                $lastDue->addWeeks($count * $gap);
            } else {
                $firstDue->addMonths($gap);
                $lastDue->addMonths($count * $gap);
            }
            $firstDueFa = Jalali::enToFaNumbers(Jalali::instance($firstDue)->format('Y/m/d'));
            $lastDueFa = Jalali::enToFaNumbers(Jalali::instance($lastDue)->format('Y/m/d'));
        }

        return [
            'request_id' => (int) $request->id,
            'customer' => [
                'id' => (int) $customer->id,
                'full_name' => $customer->fullName(),
                'username' => (string) $customer->username,
                'national_id_fa' => Jalali::enToFaNumbers((string) $customer->national_id),
                'mobile_fa' => Jalali::enToFaNumbers((string) $customer->mobile),
            ],
            'loan_type' => [
                'id' => (int) $plan->id,
                'title' => trim((string) ($plan->plan_title ?? '')) !== '' ? (string) $plan->plan_title : (string) $plan->title,
                'profit_method_label' => $plan->profitCalculationLabel(),
                'interest_rate_fa' => Jalali::enToFaNumbers(rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.')),
            ],
            'amount_toman' => $amount,
            'installments_count' => $count,
            'installment_interval_count' => $gap,
            'installment_interval_unit' => $unit,
            'installment_interval_unit_fa' => $unit === LoanType::GAP_WEEKLY ? 'هفته' : 'ماه',
            'profit_toman' => $profit,
            'total_repayable_toman' => $totalRepayable,
            'installment_amount_toman' => $perInstallment,
            'loan_start_jdate' => $startDate instanceof Carbon ? Jalali::instance($startDate)->format('Y/m/d') : null,
            'loan_start_jdate_fa' => $startDate instanceof Carbon ? Jalali::enToFaNumbers(Jalali::instance($startDate)->format('Y/m/d')) : null,
            'disbursement_due_jdate' => $dueDate instanceof Carbon ? Jalali::instance($dueDate)->format('Y/m/d') : null,
            'disbursement_due_jdate_fa' => $dueDate instanceof Carbon ? Jalali::enToFaNumbers(Jalali::instance($dueDate)->format('Y/m/d')) : null,
            'first_due_jdate_fa' => $firstDueFa,
            'last_due_jdate_fa' => $lastDueFa,
            'already_converted' => $request->customer_loan_file_id !== null,
            'converted_loan_file_id' => $request->customer_loan_file_id !== null ? (int) $request->customer_loan_file_id : null,
        ];
    }

    /**
     * انجام تبدیل قطعی. ورودی‌ها قبلاً در کنترلر validate شده‌اند.
     *
     * @return array{loan_file:CustomerLoanFile, message:string, installment_amount_toman:int, total_repayable_toman:int, profit_toman:int}
     *
     * @throws ValidationException
     */
    public function convert(
        CustomerLoanRequest $request,
        string $startJdate,
        ?string $dueJdate,
        ?int $adminId,
    ): array {
        $request->loadMissing(['customer', 'loanType']);

        $customer = $request->customer;
        $plan = $request->loanType;

        if (! $customer instanceof Customer || ! $plan instanceof LoanType) {
            throw ValidationException::withMessages([
                '*' => 'اطلاعات مشتری یا نوع وام درخواست در دسترس نیست.',
            ]);
        }

        if ($request->customer_loan_file_id !== null) {
            throw ValidationException::withMessages([
                '*' => 'این درخواست قبلاً به وام تبدیل شده است.',
            ]);
        }

        $startDate = JalaliInputParser::toCarbonDate($startJdate);
        if (! $startDate instanceof Carbon) {
            throw ValidationException::withMessages([
                'loan_start_jdate' => 'تاریخ شروع وام معتبر نیست.',
            ]);
        }

        $disbursementDueDate = null;
        if ($dueJdate !== null && trim($dueJdate) !== '') {
            $disbursementDueDate = JalaliInputParser::toCarbonDate($dueJdate);
            if (! $disbursementDueDate instanceof Carbon) {
                throw ValidationException::withMessages([
                    'disbursement_due_jdate' => 'تاریخ سررسید واریز معتبر نیست.',
                ]);
            }
            if ($disbursementDueDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'disbursement_due_jdate' => 'سررسید واریز نمی‌تواند قبل از تاریخ شروع وام باشد.',
                ]);
            }
        }

        $amount = (int) $request->amount_toman;
        $count = max(1, (int) $request->installments_count);
        $gap = max(1, (int) $request->installment_interval_count);
        $unit = (string) $request->installment_interval_unit;
        $profitMethod = (string) ($request->profit_calculation_method ?: LoanType::PROFIT_MONTHLY);
        $rate = (float) $request->interest_rate;

        // اعتبارسنجی مجدد پارامترها در برابر طرح (همانند ویزارد) — جلوگیری از تبدیل ناسازگار.
        $this->wizardValidator->assertPlanAcceptsParameters(
            $plan,
            $amount,
            $count,
            $gap,
            $unit,
            (string) ($request->description ?? '—'),
        );

        if ($unit !== (string) $plan->installment_gap_unit) {
            throw ValidationException::withMessages([
                'installment_interval_unit' => 'واحد فاصلهٔ اقساط با نوع وام انتخاب‌شده هم‌خوانی ندارد.',
            ]);
        }

        $profit = $this->calculator->calculateLoanProfitToman($amount, $rate, $profitMethod, $count, $gap, $unit);
        $totalRepayable = max(0, $amount + $profit);
        $perInstallment = (int) max(1, (int) round($totalRepayable / $count));

        // امنیت محاسباتی: مجموع اقساط نباید بیشتر از کل قابل بازپرداخت باشد (مطابق storeLoan).
        if ($perInstallment * $count > $totalRepayable) {
            throw ValidationException::withMessages([
                '*' => 'مجموع اقساط محاسبه‌شده از مبلغ قابل بازپرداخت بیشتر است؛ پارامترهای درخواست را بازنگری کنید.',
            ]);
        }

        $loanFile = DB::transaction(function () use (
            $request, $customer, $plan, $startDate, $disbursementDueDate,
            $amount, $count, $gap, $unit, $profitMethod, $rate, $perInstallment, $adminId
        ): CustomerLoanFile {
            $file = CustomerLoanFile::query()->create([
                'customer_id' => $customer->id,
                'loan_type_id' => $plan->id,
                'loan_code' => 'TMP',
                'loan_start_date' => $startDate,
                'disbursement_due_date' => $disbursementDueDate,
                'amount_toman' => $amount,
                'installments_count' => $count,
                'installment_interval_count' => $gap,
                'installment_interval_unit' => $unit,
                'installment_amount_toman' => $perInstallment,
                'down_payment_toman' => 0,
                'profit_calculation_method' => $profitMethod,
                'sub_file_number' => null,
                'description' => trim((string) ($request->description ?? '')) !== ''
                    ? trim((string) $request->description)
                    : null,
                'is_settled' => false,
                'settled_at' => null,
                'base_interest_rate' => $rate,
                'has_custom_interest_rate' => false,
                'custom_interest_rate' => null,
                'effective_interest_rate' => $rate,
                'created_by_admin_id' => $adminId,
            ]);
            $file->loan_code = 'LF-'.str_pad((string) $file->id, 7, '0', STR_PAD_LEFT);
            $file->save();

            $this->scheduler->ensureSchedule($file);

            // پیوند درخواست به وام ایجاد‌شده (جلوگیری از تبدیل دوباره و قابلیت ردیابی).
            $request->forceFill([
                'customer_loan_file_id' => $file->id,
                'converted_to_loan_at' => Carbon::now(),
                'converted_by_admin_id' => $adminId,
            ])->save();

            return $file->fresh(['loanType', 'installments']) ?? $file;
        });

        return [
            'loan_file' => $loanFile,
            'profit_toman' => $profit,
            'total_repayable_toman' => $totalRepayable,
            'installment_amount_toman' => $perInstallment,
            'message' => 'وام با موفقیت برای مشتری ایجاد شد. کد وام: '.(string) $loanFile->loan_code,
        ];
    }
}
