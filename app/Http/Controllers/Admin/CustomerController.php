<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerBankAccount;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanGuarantee;
use App\Models\CustomerReferrer;
use App\Models\CustomerWallet;
use App\Models\LoanType;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Rules\IranNationalId;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly SmsPanelManager $panelManager,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->with(['loanFiles.loanType:id,title,interest_rate'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($w) use ($q): void {
                    $w->where('customer_code', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('mobile', 'like', '%'.$q.'%')
                        ->orWhere('national_id', 'like', '%'.$q.'%');
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $q,
            'appDisplayName' => $this->appDisplayName(),
            'loanTypes' => LoanType::query()
                ->latest('id')
                ->get(['id', 'title', 'interest_rate', 'profit_calculation_method', 'max_loan_amount', 'max_installment_gap', 'installment_gap_unit', 'repayment_periods'])
                ->values(),
            'loanManageMap' => $customers->getCollection()->mapWithKeys(function (Customer $customer): array {
                $loanFiles = $customer->loanFiles->map(fn (CustomerLoanFile $file): array => $this->mapLoanFile($file))->values();
                $loanTotalWithProfit = $loanFiles->sum(static fn (array $row): int => (int) ($row['total_repayable_toman'] ?? 0));
                $remainingInstallments = $loanFiles->sum(static fn (array $row): int => (int) ($row['remaining_amount_toman'] ?? 0));

                return [
                    (string) $customer->id => [
                        'loan_files' => $loanFiles->all(),
                        'loan_count' => $loanFiles->count(),
                        'loan_total_with_profit' => $loanTotalWithProfit,
                        'loan_remaining_installments' => $remainingInstallments,
                    ],
                ];
            }),
            'smsTemplates' => SmsTemplate::query()
                ->latest('id')
                ->get(['id', 'title', 'category', 'body'])
                ->map(static fn (SmsTemplate $tpl): array => [
                    'id' => $tpl->id,
                    'title' => $tpl->title,
                    'category' => $tpl->category,
                    'body' => $tpl->body,
                ])
                ->values(),
        ]);
    }

    public function storeLoan(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'loan_start_jdate' => ['required', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1200'],
            'installment_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_interval_unit' => ['required', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'installment_amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'down_payment_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'sub_file_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_settled' => ['nullable', 'boolean'],
            'settled_jdate' => ['nullable', 'string', 'max:20'],
            'has_custom_interest_rate' => ['nullable', 'boolean'],
            'custom_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ]);

        $startDate = $this->parseJalaliDate((string) $validated['loan_start_jdate']);
        if ($startDate === null) {
            return response()->json(['message' => 'تاریخ شروع وام معتبر نیست.'], 422);
        }

        $disbursementDueDate = null;
        if (($validated['disbursement_due_jdate'] ?? '') !== '') {
            $disbursementDueDate = $this->parseJalaliDate((string) $validated['disbursement_due_jdate']);
            if ($disbursementDueDate === null) {
                return response()->json(['message' => 'سررسید واریز معتبر نیست.'], 422);
            }
        }

        $isSettled = false;
        $settledAt = null;

        $amount = (int) $validated['amount_toman'];
        $installmentsCount = (int) $validated['installments_count'];
        $installmentAmount = (int) $validated['installment_amount_toman'];
        $downPayment = (int) ($validated['down_payment_toman'] ?? 0);
        if ($downPayment > $amount) {
            return response()->json(['message' => 'مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ وام باشد.'], 422);
        }

        $loanType = LoanType::query()->findOrFail((int) $validated['loan_type_id']);
        $intervalCount = (int) $validated['installment_interval_count'];
        $intervalUnit = (string) $validated['installment_interval_unit'];
        if ($intervalUnit !== (string) $loanType->installment_gap_unit) {
            return response()->json(['message' => 'محدوده زمانی اقساط باید مطابق تنظیمات نوع وام باشد.'], 422);
        }
        if ($loanType->max_loan_amount !== null && $amount > (int) $loanType->max_loan_amount) {
            return response()->json(['message' => 'مبلغ وام از سقف مجاز نوع وام بیشتر است.'], 422);
        }
        if ($loanType->max_installment_gap !== null && $intervalCount > (int) $loanType->max_installment_gap) {
            return response()->json(['message' => 'فاصله اقساط از مقدار مجاز نوع وام بیشتر است.'], 422);
        }
        if (! $this->isRepaymentPeriodAllowed($loanType, $installmentsCount, $intervalCount, $intervalUnit, $amount)) {
            return response()->json(['message' => 'دوره بازپرداخت واردشده با محدودیت‌های نوع وام سازگار نیست.'], 422);
        }

        $baseInterestRate = (float) $loanType->interest_rate;
        $profitMethod = (string) $loanType->profit_calculation_method;
        $hasCustomInterestRate = (bool) ($validated['has_custom_interest_rate'] ?? false);
        $customInterestRate = null;
        if ($hasCustomInterestRate) {
            if (($validated['custom_interest_rate'] ?? null) === null || $validated['custom_interest_rate'] === '') {
                return response()->json(['message' => 'درصد بهره جدید را وارد کنید.'], 422);
            }
            $customInterestRate = round((float) $validated['custom_interest_rate'], 2);
        }
        $effectiveInterestRate = $hasCustomInterestRate ? (float) $customInterestRate : $baseInterestRate;
        $calculatedProfit = $this->calculateLoanProfitToman(
            $amount,
            $effectiveInterestRate,
            $profitMethod,
            $installmentsCount,
            $intervalCount,
            $intervalUnit
        );
        $payableAfterDownPayment = max(0, ($amount + $calculatedProfit) - $downPayment);
        $sumInstallments = $installmentAmount * $installmentsCount;
        if ($sumInstallments > $payableAfterDownPayment) {
            return response()->json(['message' => 'مجموع مبلغ اقساط از مبلغ قابل بازپرداخت (با احتساب بهره نوع وام) بیشتر است.'], 422);
        }

        $loanFile = DB::transaction(function () use (
            $customer,
            $loanType,
            $startDate,
            $disbursementDueDate,
            $amount,
            $validated,
            $installmentsCount,
            $installmentAmount,
            $downPayment,
            $isSettled,
            $settledAt,
            $baseInterestRate,
            $profitMethod,
            $hasCustomInterestRate,
            $customInterestRate,
            $effectiveInterestRate
        ): CustomerLoanFile {
            $file = CustomerLoanFile::query()->create([
                'customer_id' => $customer->id,
                'loan_type_id' => $loanType->id,
                'loan_code' => 'TMP',
                'loan_start_date' => $startDate,
                'disbursement_due_date' => $disbursementDueDate,
                'amount_toman' => $amount,
                'installments_count' => $installmentsCount,
                'installment_interval_count' => (int) $validated['installment_interval_count'],
                'installment_interval_unit' => (string) $validated['installment_interval_unit'],
                'installment_amount_toman' => $installmentAmount,
                'down_payment_toman' => $downPayment,
                'profit_calculation_method' => $profitMethod,
                'sub_file_number' => trim((string) ($validated['sub_file_number'] ?? '')) ?: null,
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'is_settled' => $isSettled,
                'settled_at' => $settledAt,
                'base_interest_rate' => $baseInterestRate,
                'has_custom_interest_rate' => $hasCustomInterestRate,
                'custom_interest_rate' => $customInterestRate,
                'effective_interest_rate' => $effectiveInterestRate,
                'created_by_admin_id' => auth('admin')->id(),
            ]);
            $file->loan_code = 'LF-'.str_pad((string) $file->id, 7, '0', STR_PAD_LEFT);
            $file->save();

            return $file->fresh(['loanType']) ?? $file;
        });

        $smsFeedback = '';
        if ((bool) ($validated['send_sms'] ?? false)) {
            $smsText = trim((string) ($validated['sms_text'] ?? ''));
            if ($smsText === '' && isset($validated['sms_template_id'])) {
                $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
                if ($template !== null) {
                    $smsText = $this->renderTemplate($template->body, $this->loanSmsTemplateVars($customer, $loanFile));
                }
            }
            if ($smsText === '') {
                $smsText = $this->defaultLoanCreatedSmsText($customer, $loanFile);
            }
            $smsResult = $this->sendRawSms($customer->mobile, $smsText, 'loan-file-created');
            $smsFeedback = ' '.$smsResult['message'];
        }

        return response()->json([
            'message' => 'پرونده وام با موفقیت ثبت شد.'.$smsFeedback,
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function updateLoan(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'loan_start_jdate' => ['required', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1200'],
            'installment_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_interval_unit' => ['required', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'installment_amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'down_payment_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'sub_file_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_settled' => ['nullable', 'boolean'],
            'settled_jdate' => ['nullable', 'string', 'max:20'],
            'has_custom_interest_rate' => ['nullable', 'boolean'],
            'custom_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $startDate = $this->parseJalaliDate((string) $validated['loan_start_jdate']);
        if ($startDate === null) {
            return response()->json(['message' => 'تاریخ شروع وام معتبر نیست.'], 422);
        }
        $disbursementDueDate = null;
        if (($validated['disbursement_due_jdate'] ?? '') !== '') {
            $disbursementDueDate = $this->parseJalaliDate((string) $validated['disbursement_due_jdate']);
            if ($disbursementDueDate === null) {
                return response()->json(['message' => 'سررسید واریز معتبر نیست.'], 422);
            }
        }

        $isSettled = (bool) ($validated['is_settled'] ?? false);
        $settledAt = null;
        if ($isSettled) {
            if (($validated['settled_jdate'] ?? '') === '') {
                return response()->json(['message' => 'تاریخ تسویه الزامی است.'], 422);
            }
            $settledAt = $this->parseJalaliDate((string) $validated['settled_jdate']);
            if ($settledAt === null) {
                return response()->json(['message' => 'تاریخ تسویه معتبر نیست.'], 422);
            }
            if ($settledAt->lt($startDate)) {
                return response()->json(['message' => 'تاریخ تسویه نمی‌تواند قبل از تاریخ شروع وام باشد.'], 422);
            }
        }

        $amount = (int) $validated['amount_toman'];
        $installmentsCount = (int) $validated['installments_count'];
        $installmentAmount = (int) $validated['installment_amount_toman'];
        $downPayment = (int) ($validated['down_payment_toman'] ?? 0);
        if ($downPayment > $amount) {
            return response()->json(['message' => 'مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ وام باشد.'], 422);
        }

        $loanType = LoanType::query()->findOrFail((int) $validated['loan_type_id']);
        $intervalCount = (int) $validated['installment_interval_count'];
        $intervalUnit = (string) $validated['installment_interval_unit'];
        if ($intervalUnit !== (string) $loanType->installment_gap_unit) {
            return response()->json(['message' => 'محدوده زمانی اقساط باید مطابق تنظیمات نوع وام باشد.'], 422);
        }
        if ($loanType->max_loan_amount !== null && $amount > (int) $loanType->max_loan_amount) {
            return response()->json(['message' => 'مبلغ وام از سقف مجاز نوع وام بیشتر است.'], 422);
        }
        if ($loanType->max_installment_gap !== null && $intervalCount > (int) $loanType->max_installment_gap) {
            return response()->json(['message' => 'فاصله اقساط از مقدار مجاز نوع وام بیشتر است.'], 422);
        }
        if (! $this->isRepaymentPeriodAllowed($loanType, $installmentsCount, $intervalCount, $intervalUnit, $amount)) {
            return response()->json(['message' => 'دوره بازپرداخت واردشده با محدودیت‌های نوع وام سازگار نیست.'], 422);
        }

        $baseInterestRate = (float) $loanType->interest_rate;
        $profitMethod = (string) $loanType->profit_calculation_method;
        $hasCustomInterestRate = (bool) ($validated['has_custom_interest_rate'] ?? false);
        $customInterestRate = null;
        if ($hasCustomInterestRate) {
            if (($validated['custom_interest_rate'] ?? null) === null || $validated['custom_interest_rate'] === '') {
                return response()->json(['message' => 'درصد بهره جدید را وارد کنید.'], 422);
            }
            $customInterestRate = round((float) $validated['custom_interest_rate'], 2);
        }
        $effectiveInterestRate = $hasCustomInterestRate ? (float) $customInterestRate : $baseInterestRate;
        $calculatedProfit = $this->calculateLoanProfitToman(
            $amount,
            $effectiveInterestRate,
            $profitMethod,
            $installmentsCount,
            $intervalCount,
            $intervalUnit
        );
        $payableAfterDownPayment = max(0, ($amount + $calculatedProfit) - $downPayment);
        $sumInstallments = $installmentAmount * $installmentsCount;
        if ($sumInstallments > $payableAfterDownPayment) {
            return response()->json(['message' => 'مجموع مبلغ اقساط از مبلغ قابل بازپرداخت (با احتساب بهره نوع وام) بیشتر است.'], 422);
        }

        $loanFile->update([
            'loan_type_id' => $loanType->id,
            'loan_start_date' => $startDate,
            'disbursement_due_date' => $disbursementDueDate,
            'amount_toman' => $amount,
            'installments_count' => $installmentsCount,
            'installment_interval_count' => $intervalCount,
            'installment_interval_unit' => $intervalUnit,
            'installment_amount_toman' => $installmentAmount,
            'down_payment_toman' => $downPayment,
            'profit_calculation_method' => $profitMethod,
            'sub_file_number' => trim((string) ($validated['sub_file_number'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'is_settled' => $isSettled,
            'settled_at' => $settledAt,
            'base_interest_rate' => $baseInterestRate,
            'has_custom_interest_rate' => $hasCustomInterestRate,
            'custom_interest_rate' => $customInterestRate,
            'effective_interest_rate' => $effectiveInterestRate,
        ]);
        $loanFile->refresh();
        $loanFile->load('loanType');

        return response()->json([
            'message' => 'پرونده وام با موفقیت ویرایش شد.',
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function destroyLoan(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $loanCode = (string) $loanFile->loan_code;
        $loanFile->delete();

        return response()->json([
            'message' => 'پرونده وام '.$loanCode.' حذف شد.',
        ]);
    }

    public function sendLoanFileSms(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ]);

        $smsText = trim((string) ($validated['sms_text'] ?? ''));
        if ($smsText === '' && isset($validated['sms_template_id'])) {
            $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
            if ($template !== null) {
                $smsText = $this->renderTemplate($template->body, $this->loanSmsTemplateVars($customer, $loanFile));
            }
        }
        if ($smsText === '') {
            $smsText = $this->defaultLoanCreatedSmsText($customer, $loanFile);
        }

        $smsResult = $this->sendRawSms($customer->mobile, $smsText, 'loan-file-created');

        return response()->json([
            'ok' => $smsResult['ok'],
            'message' => $smsResult['message'],
        ], $smsResult['ok'] ? 200 : 422);
    }

    public function loanGuarantees(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $rows = CustomerLoanGuarantee::query()
            ->where('loan_file_id', $loanFile->id)
            ->latest('id')
            ->get()
            ->map(fn (CustomerLoanGuarantee $g): array => $this->mapLoanGuarantee($g))
            ->values();

        return response()->json([
            'guarantees' => $rows,
        ]);
    }

    public function storeLoanGuarantee(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                CustomerLoanGuarantee::TYPE_ORG_SELF,
                CustomerLoanGuarantee::TYPE_ORG_OTHER,
                CustomerLoanGuarantee::TYPE_CHEQUE,
                CustomerLoanGuarantee::TYPE_GOLD,
                CustomerLoanGuarantee::TYPE_OTHER,
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'org_name' => ['nullable', 'string', 'max:255'],
            'employee_no' => ['nullable', 'string', 'max:120'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_national_id' => ['nullable', 'string', 'max:20'],
            'guarantor_phone' => ['nullable', 'string', 'max:20'],
            'cheque_bank' => ['nullable', 'string', 'max:255'],
            'cheque_serial' => ['nullable', 'string', 'max:120'],
            'cheque_sayadi' => ['nullable', 'string', 'max:120'],
            'cheque_due_jdate' => ['nullable', 'string', 'max:20'],
            'gold_item_type' => ['nullable', 'string', 'max:255'],
            'gold_weight_gram' => ['nullable', 'numeric', 'min:0'],
            'gold_karat' => ['nullable', 'numeric', 'min:0'],
            'amount_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
        ]);

        $type = (string) $validated['type'];
        $description = trim((string) ($validated['description'] ?? ''));
        $amountToman = (int) ($validated['amount_toman'] ?? 0);

        $meta = [];
        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            if (trim((string) ($validated['org_name'] ?? '')) === '') {
                return response()->json(['message' => 'نام سازمان الزامی است.'], 422);
            }
            $meta = [
                'org_name' => trim((string) ($validated['org_name'] ?? '')),
                'employee_no' => trim((string) ($validated['employee_no'] ?? '')),
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            if (trim((string) ($validated['guarantor_name'] ?? '')) === '') {
                return response()->json(['message' => 'نام ضامن الزامی است.'], 422);
            }
            $meta = [
                'guarantor_name' => trim((string) ($validated['guarantor_name'] ?? '')),
                'guarantor_national_id' => $this->toEnglishDigits(trim((string) ($validated['guarantor_national_id'] ?? ''))),
                'guarantor_phone' => $this->toEnglishDigits(trim((string) ($validated['guarantor_phone'] ?? ''))),
                'org_name' => trim((string) ($validated['org_name'] ?? '')),
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            if (trim((string) ($validated['cheque_serial'] ?? '')) === '') {
                return response()->json(['message' => 'شماره چک الزامی است.'], 422);
            }
            $chequeDueDate = null;
            if (($validated['cheque_due_jdate'] ?? '') !== '') {
                $chequeDueDate = $this->parseJalaliDate((string) $validated['cheque_due_jdate']);
                if ($chequeDueDate === null) {
                    return response()->json(['message' => 'تاریخ سررسید چک معتبر نیست.'], 422);
                }
            }
            $meta = [
                'cheque_bank' => trim((string) ($validated['cheque_bank'] ?? '')),
                'cheque_serial' => trim((string) ($validated['cheque_serial'] ?? '')),
                'cheque_sayadi' => trim((string) ($validated['cheque_sayadi'] ?? '')),
                'cheque_due_jdate' => $chequeDueDate ? Jalali::instance($chequeDueDate)->format('Y/m/d') : '',
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_GOLD) {
            $meta = [
                'gold_item_type' => trim((string) ($validated['gold_item_type'] ?? '')),
                'gold_weight_gram' => isset($validated['gold_weight_gram']) ? (float) $validated['gold_weight_gram'] : null,
                'gold_karat' => isset($validated['gold_karat']) ? (float) $validated['gold_karat'] : null,
                'amount_toman' => $amountToman,
            ];
        } else {
            // سایر
            if ($description === '') {
                return response()->json(['message' => 'برای ضمانت نوع سایر، توضیحات الزامی است.'], 422);
            }
            $meta = [
                'amount_toman' => $amountToman,
            ];
        }

        $attachmentPath = null;
        $attachment = $request->file('attachment');
        if ($attachment instanceof UploadedFile && $attachment->isValid()) {
            $attachmentPath = $this->storeGuaranteeAttachment($attachment);
        }

        $guarantee = CustomerLoanGuarantee::query()->create([
            'customer_id' => $customer->id,
            'loan_file_id' => $loanFile->id,
            'type' => $type,
            'description' => $description !== '' ? $description : null,
            'meta' => $meta,
            'attachment_path' => $attachmentPath,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return response()->json([
            'message' => 'ضمانت با موفقیت ثبت شد.',
            'guarantee' => $this->mapLoanGuarantee($guarantee),
        ]);
    }

    public function updateLoanGuarantee(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                CustomerLoanGuarantee::TYPE_ORG_SELF,
                CustomerLoanGuarantee::TYPE_ORG_OTHER,
                CustomerLoanGuarantee::TYPE_CHEQUE,
                CustomerLoanGuarantee::TYPE_GOLD,
                CustomerLoanGuarantee::TYPE_OTHER,
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'org_name' => ['nullable', 'string', 'max:255'],
            'employee_no' => ['nullable', 'string', 'max:120'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_national_id' => ['nullable', 'string', 'max:20'],
            'guarantor_phone' => ['nullable', 'string', 'max:20'],
            'cheque_bank' => ['nullable', 'string', 'max:255'],
            'cheque_serial' => ['nullable', 'string', 'max:120'],
            'cheque_sayadi' => ['nullable', 'string', 'max:120'],
            'cheque_due_jdate' => ['nullable', 'string', 'max:20'],
            'gold_item_type' => ['nullable', 'string', 'max:255'],
            'gold_weight_gram' => ['nullable', 'numeric', 'min:0'],
            'gold_karat' => ['nullable', 'numeric', 'min:0'],
            'amount_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);

        $type = (string) $validated['type'];
        $description = trim((string) ($validated['description'] ?? ''));
        $amountToman = (int) ($validated['amount_toman'] ?? 0);
        $meta = [];

        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            if (trim((string) ($validated['org_name'] ?? '')) === '') {
                return response()->json(['message' => 'نام سازمان الزامی است.'], 422);
            }
            $meta = [
                'org_name' => trim((string) ($validated['org_name'] ?? '')),
                'employee_no' => trim((string) ($validated['employee_no'] ?? '')),
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            if (trim((string) ($validated['guarantor_name'] ?? '')) === '') {
                return response()->json(['message' => 'نام ضامن الزامی است.'], 422);
            }
            $meta = [
                'guarantor_name' => trim((string) ($validated['guarantor_name'] ?? '')),
                'guarantor_national_id' => $this->toEnglishDigits(trim((string) ($validated['guarantor_national_id'] ?? ''))),
                'guarantor_phone' => $this->toEnglishDigits(trim((string) ($validated['guarantor_phone'] ?? ''))),
                'org_name' => trim((string) ($validated['org_name'] ?? '')),
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            if (trim((string) ($validated['cheque_serial'] ?? '')) === '') {
                return response()->json(['message' => 'شماره چک الزامی است.'], 422);
            }
            $chequeDueDate = null;
            if (($validated['cheque_due_jdate'] ?? '') !== '') {
                $chequeDueDate = $this->parseJalaliDate((string) $validated['cheque_due_jdate']);
                if ($chequeDueDate === null) {
                    return response()->json(['message' => 'تاریخ سررسید چک معتبر نیست.'], 422);
                }
            }
            $meta = [
                'cheque_bank' => trim((string) ($validated['cheque_bank'] ?? '')),
                'cheque_serial' => trim((string) ($validated['cheque_serial'] ?? '')),
                'cheque_sayadi' => trim((string) ($validated['cheque_sayadi'] ?? '')),
                'cheque_due_jdate' => $chequeDueDate ? Jalali::instance($chequeDueDate)->format('Y/m/d') : '',
                'amount_toman' => $amountToman,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_GOLD) {
            $meta = [
                'gold_item_type' => trim((string) ($validated['gold_item_type'] ?? '')),
                'gold_weight_gram' => isset($validated['gold_weight_gram']) ? (float) $validated['gold_weight_gram'] : null,
                'gold_karat' => isset($validated['gold_karat']) ? (float) $validated['gold_karat'] : null,
                'amount_toman' => $amountToman,
            ];
        } else {
            if ($description === '') {
                return response()->json(['message' => 'برای ضمانت نوع سایر، توضیحات الزامی است.'], 422);
            }
            $meta = [
                'amount_toman' => $amountToman,
            ];
        }

        $removeAttachment = (bool) ($validated['remove_attachment'] ?? false);
        $newAttachment = $request->file('attachment');
        $attachmentPath = $guarantee->attachment_path;
        if ($removeAttachment && is_string($attachmentPath) && $attachmentPath !== '') {
            Storage::disk('public')->delete($attachmentPath);
            $attachmentPath = null;
        }
        if ($newAttachment instanceof UploadedFile && $newAttachment->isValid()) {
            if (is_string($attachmentPath) && $attachmentPath !== '') {
                Storage::disk('public')->delete($attachmentPath);
            }
            $attachmentPath = $this->storeGuaranteeAttachment($newAttachment);
        }

        $guarantee->update([
            'type' => $type,
            'description' => $description !== '' ? $description : null,
            'meta' => $meta,
            'attachment_path' => $attachmentPath,
        ]);
        $guarantee->refresh();

        return response()->json([
            'message' => 'ضمانت با موفقیت ویرایش شد.',
            'guarantee' => $this->mapLoanGuarantee($guarantee),
        ]);
    }

    public function destroyLoanGuarantee(Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }

        if (is_string($guarantee->attachment_path) && $guarantee->attachment_path !== '') {
            Storage::disk('public')->delete($guarantee->attachment_path);
        }
        $guarantee->delete();

        return response()->json([
            'message' => 'ضمانت حذف شد.',
        ]);
    }

    public function loanGuaranteeAttachment(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee)
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        $attachmentPath = is_string($guarantee->attachment_path) ? trim($guarantee->attachment_path) : '';
        if ($attachmentPath === '' || ! Storage::disk('public')->exists($attachmentPath)) {
            abort(404);
        }

        $download = $request->boolean('download');
        $fileName = basename($attachmentPath);

        if ($download) {
            return Storage::disk('public')->download($attachmentPath, $fileName);
        }

        return Storage::disk('public')->response($attachmentPath, $fileName, [], 'inline');
    }

    public function sendQuickSms(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'sms_type' => ['required', 'in:wallet_link,welcome'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ]);

        $smsType = (string) $validated['sms_type'];
        $messageText = trim((string) ($validated['sms_text'] ?? ''));
        $templateId = $validated['sms_template_id'] ?? null;
        if ($messageText === '' && $templateId !== null) {
            $tpl = SmsTemplate::query()->find((int) $templateId);
            if ($tpl !== null) {
                $messageText = $this->renderTemplate($tpl->body, [
                    'store_name' => $this->appDisplayName(),
                    'customer_name' => $customer->fullName(),
                    'payment_link' => '—',
                    'payment_link_variable' => '—',
                ]);
            }
        }
        if ($messageText === '') {
            $messageText = $smsType === 'wallet_link'
                ? 'سلام '.$customer->fullName().'، لینک شارژ کیف پول شما: —'
                : 'سلام '.$customer->fullName().'، به سامانه '.$this->appDisplayName().' خوش آمدید.';
        }

        $result = $this->sendRawSms($customer->mobile, $messageText, $smsType === 'wallet_link' ? 'wallet-charge-link' : 'welcome-message');

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function store(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $request->merge([
            'national_id' => $this->toEnglishDigits(trim((string) $request->input('national_id', ''))),
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'postal_code' => $this->toEnglishDigits(trim((string) $request->input('postal_code', ''))),
        ]);

        $validated = $request->validate([
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['required', 'string', 'max:120'],
            'national_id' => ['required', 'digits:10', new IranNationalId, Rule::unique('customers', 'national_id')],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')],
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:2000'],
            'postal_code' => ['required', 'string', 'max:16', 'regex:/^[0-9]{10}$/'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.',
            ]);
        }

        $accounts = $this->validatedBankAccounts($request->input('accounts', []));
        $referrers = $this->validatedReferrers($request->input('referrers', []));

        $sendCredentials = $request->boolean('send_credentials');

        $customerCode = trim((string) ($validated['customer_code'] ?? ''));
        if ($customerCode === '') {
            $customerCode = $this->generateUniqueCustomerCode();
        }

        $plainPassword = (string) $validated['password'];

        $customer = DB::transaction(function () use (
            $validated,
            $customerCode,
            $username,
            $plainPassword,
            $membershipAt,
            $birthDate,
            $accounts,
            $referrers
        ): Customer {
            /** @var Customer $c */
            $c = Customer::query()->create([
                'customer_code' => $customerCode,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'],
                'national_id' => $validated['national_id'],
                'mobile' => $validated['mobile'],
                'phone_landline' => $validated['phone_landline'] !== null && $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'password' => $plainPassword,
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
            ]);

            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $c->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $c->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }

            CustomerWallet::query()->create([
                'customer_id' => $c->id,
                'balance_toman' => 0,
                'is_locked' => false,
            ]);

            return $c;
        });

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10)
                .'رمز عبور: '.$plainPassword;
            $smsResult = $this->sendRawSms($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'مشتری با موفقیت ثبت شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', $flash);
    }

    public function editData(Customer $customer): JsonResponse
    {
        $customer->load(['bankAccounts', 'referrers']);

        $membershipJ = '';
        if ($customer->membership_at !== null) {
            $membershipJ = Jalali::instance(Carbon::parse($customer->membership_at))->format('Y/m/d');
        }
        $birthJ = '';
        if ($customer->birth_date !== null) {
            $birthJ = Jalali::instance(Carbon::parse($customer->birth_date))->format('Y/m/d');
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'father_name' => $customer->father_name,
                'national_id' => $customer->national_id,
                'mobile' => $customer->mobile,
                'phone_landline' => (string) ($customer->phone_landline ?? ''),
                'membership_jdate' => $membershipJ,
                'birth_jdate' => $birthJ,
                'email' => (string) ($customer->email ?? ''),
                'city' => $customer->city,
                'address' => $customer->address,
                'postal_code' => $customer->postal_code,
            ],
            'bank_accounts' => $customer->bankAccounts->map(static function (CustomerBankAccount $b): array {
                return [
                    'account_identifier' => $b->account_identifier,
                    'bank_name' => (string) ($b->bank_name ?? ''),
                    'branch_name' => (string) ($b->branch_name ?? ''),
                ];
            })->values(),
            'referrers' => $customer->referrers->map(static function (CustomerReferrer $r): array {
                return [
                    'first_name' => $r->first_name,
                    'last_name' => $r->last_name,
                    'phone' => $r->phone,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $request->merge([
            'national_id' => $this->toEnglishDigits(trim((string) $request->input('national_id', ''))),
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'postal_code' => $this->toEnglishDigits(trim((string) $request->input('postal_code', ''))),
        ]);

        $validator = Validator::make($request->all(), [
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')->ignore($customer->id)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['required', 'string', 'max:120'],
            'national_id' => ['required', 'digits:10', new IranNationalId, Rule::unique('customers', 'national_id')->ignore($customer->id)],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')->ignore($customer->id)],
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:2000'],
            'postal_code' => ['required', 'string', 'max:16', 'regex:/^[0-9]{10}$/'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($validator)
                ->with('open_edit_customer_id', $customer->id);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->where('id', '!=', $customer->id)->exists()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors(['mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.'])
                ->with('open_edit_customer_id', $customer->id);
        }

        try {
            $accounts = $this->validatedBankAccounts($request->input('accounts', []));
            $referrers = $this->validatedReferrers($request->input('referrers', []));
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($e->errors())
                ->with('open_edit_customer_id', $customer->id);
        }

        $sendCredentials = $request->boolean('send_credentials');
        $plainPasswordInput = trim((string) ($validated['password'] ?? ''));

        DB::transaction(function () use ($validated, $customer, $username, $membershipAt, $birthDate, $accounts, $referrers, $plainPasswordInput): void {
            $data = [
                'customer_code' => trim((string) ($validated['customer_code'] ?? '')) !== ''
                    ? (string) $validated['customer_code']
                    : $customer->customer_code,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'],
                'national_id' => $validated['national_id'],
                'mobile' => $validated['mobile'],
                'phone_landline' => $validated['phone_landline'] !== null && (string) $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
            ];

            if ($plainPasswordInput !== '') {
                $data['password'] = $plainPasswordInput;
            }

            $customer->update($data);

            $customer->bankAccounts()->delete();
            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $customer->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            $customer->referrers()->delete();
            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $customer->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }
        });

        $customer->refresh();

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10);
            if ($plainPasswordInput !== '') {
                $msg .= 'رمز عبور: '.$plainPasswordInput;
            } else {
                $msg .= 'رمز عبور تغییر نکرده است.';
            }
            $smsResult = $this->sendRawSms($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'اطلاعات مشتری با موفقیت به‌روزرسانی شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index', $request->only('q'))
            ->with('flash_success', $flash);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', 'مشتری با موفقیت حذف شد.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{account_identifier: string, bank_name: string|null, branch_name: string|null}>
     */
    private function validatedBankAccounts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $acc = $this->toEnglishDigits(trim((string) ($row['account_identifier'] ?? '')));
            $bank = trim((string) ($row['bank_name'] ?? ''));
            $branch = trim((string) ($row['branch_name'] ?? ''));
            if ($acc === '' && $bank === '' && $branch === '') {
                continue;
            }
            if ($acc === '') {
                throw ValidationException::withMessages([
                    'accounts' => 'برای هر ردیف شماره حساب، شماره کارت یا شبا را کامل کنید.',
                ]);
            }
            $out[] = [
                'account_identifier' => $acc,
                'bank_name' => $bank !== '' ? $bank : null,
                'branch_name' => $branch !== '' ? $branch : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{first_name: string, last_name: string, phone: string}>
     */
    private function validatedReferrers(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fn = trim((string) ($row['first_name'] ?? ''));
            $ln = trim((string) ($row['last_name'] ?? ''));
            $ph = $this->toEnglishDigits(trim((string) ($row['phone'] ?? '')));
            if ($fn === '' && $ln === '' && $ph === '') {
                continue;
            }
            if ($fn === '' || $ln === '' || $ph === '') {
                throw ValidationException::withMessages([
                    'referrers' => 'برای هر معرف، نام، نام خانوادگی و شماره تماس الزامی است.',
                ]);
            }
            if (! preg_match('/^09\d{9}$/', $ph)) {
                throw ValidationException::withMessages([
                    'referrers' => 'شماره تماس معرف باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
                ]);
            }
            $out[] = ['first_name' => $fn, 'last_name' => $ln, 'phone' => $ph];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function sendRawSms(string $recipient, string $messageText, string $type = 'customer-credentials'): array
    {
        $active = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($active === null) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک فعال نیست).'];
        }

        $providerOptions = $this->panelManager->providerOptions();
        $providerKey = $active->provider;
        if (! isset($providerOptions[$providerKey])) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک پیکربندی نشده).'];
        }

        $password = $this->decryptPasswordOrEmpty((string) $active->password);
        if ($password === '') {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (رمز پنل ذخیره نشده).'];
        }

        $gateway = $this->panelManager->gateway($providerKey);
        $result = $gateway->sendTestMessage(
            (string) $active->username,
            $password,
            $recipient,
            $messageText,
            [
                'domain_name' => (string) ($active->domain_name ?: 'sepahansms'),
                'sender_number' => (string) ($active->sender_number ?: '50003300'),
            ]
        );

        SmsLog::query()->create([
            'sms_panel' => (string) ($providerOptions[$providerKey] ?? $providerKey),
            'status' => $result->ok ? SmsLog::STATUS_DELIVERED : SmsLog::STATUS_UNDELIVERED,
            'sent_at' => now(),
            'message_text' => $messageText,
            'recipient' => $recipient,
            'type' => $type,
            'cost' => 0,
            'meta' => [
                'provider' => $providerKey,
                'response_code' => $result->code,
                'result_message' => $result->message,
            ],
        ]);

        // Do not mutate panel connection health here.
        // Operational SMS failures must not flip panel status in settings.

        return [
            'ok' => $result->ok,
            'message' => $result->ok
                ? 'پیام برای مشتری ارسال شد.'
                : 'عملیات انجام شد اما ارسال پیامک ناموفق بود: '.$result->message,
        ];
    }

    private function renderTemplate(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
        }

        return trim($out);
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $v : (string) config('app.name');
    }

    private function decryptPasswordOrEmpty(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    private function usernameFromMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    private function generateUniqueCustomerCode(): string
    {
        do {
            $code = 'CUS-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (Customer::query()->where('customer_code', $code)->exists());

        return $code;
    }

    private function parseJalaliDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = $this->toEnglishDigits($value);

        try {
            $j = Jalali::parseFormat('Y/m/d', $value);

            return Carbon::createFromTimestamp($j->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanFile(CustomerLoanFile $file): array
    {
        $profit = $this->calculateLoanProfitToman(
            (int) $file->amount_toman,
            (float) $file->effective_interest_rate,
            (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $file->installments_count,
            (int) $file->installment_interval_count,
            (string) $file->installment_interval_unit
        );
        $totalRepayable = ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman;
        $totalRepayable = max(0, $totalRepayable);
        $remainingAmount = $file->is_settled ? 0 : $totalRepayable;

        return [
            'id' => $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_type_id' => (int) $file->loan_type_id,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'loan_start_jdate' => $file->loan_start_date ? Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d') : '',
            'disbursement_due_jdate' => $file->disbursement_due_date ? Jalali::instance(Carbon::parse($file->disbursement_due_date))->format('Y/m/d') : '',
            'amount_toman' => (int) $file->amount_toman,
            'installments_count' => (int) $file->installments_count,
            'installment_interval_count' => (int) $file->installment_interval_count,
            'installment_interval_unit' => (string) $file->installment_interval_unit,
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'down_payment_toman' => (int) $file->down_payment_toman,
            'profit_calculation_method' => (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            'sub_file_number' => (string) ($file->sub_file_number ?? ''),
            'description' => (string) ($file->description ?? ''),
            'is_settled' => (bool) $file->is_settled,
            'settled_jdate' => $file->settled_at ? Jalali::instance(Carbon::parse($file->settled_at))->format('Y/m/d') : '',
            'base_interest_rate' => (float) $file->base_interest_rate,
            'has_custom_interest_rate' => (bool) $file->has_custom_interest_rate,
            'custom_interest_rate' => $file->custom_interest_rate !== null ? (float) $file->custom_interest_rate : null,
            'effective_interest_rate' => (float) $file->effective_interest_rate,
            'calculated_profit_toman' => $profit,
            'total_repayable_toman' => $totalRepayable,
            'remaining_amount_toman' => $remainingAmount,
        ];
    }

    private function calculateLoanProfitToman(
        int $amountToman,
        float $interestRatePercent,
        string $profitMethod,
        int $installmentsCount,
        int $intervalCount,
        string $intervalUnit
    ): int {
        if ($amountToman <= 0 || $interestRatePercent <= 0 || $installmentsCount <= 0 || $intervalCount <= 0) {
            return 0;
        }
        $months = $this->repaymentDurationInMonths($installmentsCount, $intervalCount, $intervalUnit);
        if ($months <= 0) {
            return 0;
        }
        $rate = $interestRatePercent / 100;
        $profit = $profitMethod === LoanType::PROFIT_BANK
            ? ($amountToman * $rate * ($months / 12))
            : ($amountToman * $rate * $months);

        return max(0, (int) round($profit));
    }

    private function repaymentDurationInMonths(int $installmentsCount, int $intervalCount, string $intervalUnit): float
    {
        $multiplier = $intervalUnit === LoanType::GAP_WEEKLY ? (12 / 52) : 1.0;

        return max(0, $installmentsCount * $intervalCount * $multiplier);
    }

    private function isRepaymentPeriodAllowed(LoanType $loanType, int $installmentsCount, int $intervalCount, string $intervalUnit, int $amount): bool
    {
        $periods = is_array($loanType->repayment_periods) ? $loanType->repayment_periods : [];
        $type = (string) ($periods['type'] ?? LoanType::REPAY_UNLIMITED);
        if ($type === LoanType::REPAY_UNLIMITED) {
            return true;
        }
        $months = (int) ceil($this->repaymentDurationInMonths($installmentsCount, $intervalCount, $intervalUnit));
        if ($type === LoanType::REPAY_MAX_UNTIL) {
            $maxMonths = (int) ($periods['max_months'] ?? 0);

            return $maxMonths < 1 || $months <= $maxMonths;
        }
        if ($type === LoanType::REPAY_ALLOWED_MONTHS) {
            $rows = is_array($periods['allowed_rows'] ?? null) ? $periods['allowed_rows'] : [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $m = (int) ($row['months'] ?? 0);
                $cap = (int) round((float) ($row['cap'] ?? 0));
                if ($m === $months && ($cap < 1 || $amount <= $cap)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function loanSmsTemplateVars(Customer $customer, CustomerLoanFile $loanFile): array
    {
        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'loan_amount' => number_format((int) $loanFile->amount_toman, 0, '.', ',').' تومان',
            'installment_amount' => number_format((int) $loanFile->installment_amount_toman, 0, '.', ',').' تومان',
        ];
    }

    private function defaultLoanCreatedSmsText(Customer $customer, CustomerLoanFile $loanFile): string
    {
        return 'سامانه '.$this->appDisplayName()."\n"
            .'مشتری گرامی '.$customer->fullName()."\n"
            .'ثبت پرونده وام جدید انجام شد.'."\n"
            .'پرونده وام: '.$loanFile->loan_code."\n"
            .'مبلغ وام: '.number_format((int) $loanFile->amount_toman, 0, '.', ',').' تومان'."\n"
            .'مبلغ هر قسط: '.number_format((int) $loanFile->installment_amount_toman, 0, '.', ',').' تومان';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanGuarantee(CustomerLoanGuarantee $g): array
    {
        $attachmentUrl = null;
        $attachmentDownloadUrl = null;
        $attachmentPreviewUrl = null;
        if (is_string($g->attachment_path) && $g->attachment_path !== '') {
            $routeParams = [
                'customer' => (int) $g->customer_id,
                'loanFile' => (int) $g->loan_file_id,
                'guarantee' => (int) $g->id,
            ];
            $attachmentPreviewUrl = route('admin.customers.loan-files.guarantees.attachment', $routeParams);
            $attachmentDownloadUrl = route('admin.customers.loan-files.guarantees.attachment', $routeParams + ['download' => 1]);
            $attachmentUrl = $attachmentDownloadUrl;
        }
        $typeLabels = [
            CustomerLoanGuarantee::TYPE_ORG_SELF => 'سازمانی - خودم',
            CustomerLoanGuarantee::TYPE_ORG_OTHER => 'سازمانی - شخص دیگر',
            CustomerLoanGuarantee::TYPE_CHEQUE => 'چک',
            CustomerLoanGuarantee::TYPE_GOLD => 'طلا',
            CustomerLoanGuarantee::TYPE_OTHER => 'سایر',
        ];

        return [
            'id' => (int) $g->id,
            'type' => (string) $g->type,
            'type_label' => (string) ($typeLabels[$g->type] ?? $g->type),
            'description' => (string) ($g->description ?? ''),
            'meta' => is_array($g->meta) ? $g->meta : [],
            'attachment_url' => $attachmentUrl,
            'attachment_preview_url' => $attachmentPreviewUrl,
            'attachment_download_url' => $attachmentDownloadUrl,
            'attachment_name' => is_string($g->attachment_path) && $g->attachment_path !== '' ? basename($g->attachment_path) : '',
            'created_at' => $g->created_at ? Jalali::instance($g->created_at)->format('Y/m/d H:i') : '',
        ];
    }

    private function storeGuaranteeAttachment(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = 'guarantee-'.Str::lower(Str::random(22)).'.'.$extension;

        return $file->storeAs('loan-guarantees', $safeName, 'public');
    }
}
