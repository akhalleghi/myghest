<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoanTypeRequest;
use App\Http\Requests\Admin\UpdateLoanTypeRequest;
use App\Models\LoanType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanTypeController extends Controller
{
    public function index(): View
    {
        $loanTypes = LoanType::query()->latest('id')->get();

        $loanEditMap = $loanTypes->mapWithKeys(
            fn (LoanType $lt): array => [$lt->id => $this->loanTypeEditPayload($lt)]
        )->all();

        return view('admin.loan_types.index', [
            'loanTypes' => $loanTypes,
            'loanEditMap' => $loanEditMap,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loanTypeEditPayload(LoanType $lt): array
    {
        return [
            'id' => $lt->id,
            'title' => $lt->title,
            'profit_calculation_method' => $lt->profit_calculation_method,
            'interest_rate' => (string) $lt->interest_rate,
            'daily_late_coefficient' => (string) $lt->daily_late_coefficient,
            'daily_early_coefficient' => (string) $lt->daily_early_coefficient,
            'max_loan_amount' => $lt->max_loan_amount,
            'max_installment_gap' => $lt->max_installment_gap,
            'installment_gap_unit' => $lt->installment_gap_unit,
            'repayment_periods' => $lt->repayment_periods ?? [],
            'sms_reminder_enabled' => (bool) $lt->sms_reminder_enabled,
            'registration_suspended' => (bool) $lt->registration_suspended,
            'registration_suspended_message' => $lt->registration_suspended_message,
            'plan_list_enabled' => (bool) $lt->plan_list_enabled,
            'plan_title' => $lt->plan_title,
            'plan_summary' => $lt->plan_summary,
            'plan_body' => $lt->plan_body,
            'plan_image_url' => $lt->plan_image_path
                ? route('admin.loan-types.plan-image', ['loanType' => $lt->id])
                : null,
            'required_documents' => $lt->required_documents ?? [],
        ];
    }

    public function store(StoreLoanTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $loanType = LoanType::create([
            'title' => $validated['title'],
            'profit_calculation_method' => $validated['profit_calculation_method'],
            'interest_rate' => $validated['interest_rate'],
            'daily_late_coefficient' => $validated['daily_late_coefficient'],
            'daily_early_coefficient' => $validated['daily_early_coefficient'],
            'max_loan_amount' => isset($validated['max_loan_amount']) && $validated['max_loan_amount'] !== ''
                ? (int) round((float) $validated['max_loan_amount'])
                : null,
            'max_installment_gap' => isset($validated['max_installment_gap']) && $validated['max_installment_gap'] !== ''
                ? (int) $validated['max_installment_gap']
                : null,
            'installment_gap_unit' => $validated['installment_gap_unit'],
            'repayment_periods' => $this->repaymentPayloadFromValidated($validated),
            'sms_reminder_enabled' => $validated['sms_reminder_enabled'] ?? false,
            'registration_suspended' => $validated['registration_suspended'] ?? false,
            'registration_suspended_message' => $validated['registration_suspended_message'] ?? null,
            ...$this->planFieldsFromValidated($validated),
            'required_documents' => LoanType::normalizeRequiredDocumentsPayload($validated['required_documents'] ?? []),
        ]);

        $this->storePlanImageIfPresent($loanType, $request);

        return redirect()
            ->route('admin.loan-types.index')
            ->with('flash_success', 'نوع وام با موفقیت ثبت شد.');
    }

    public function update(LoanType $loanType, UpdateLoanTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $planPayload = $this->planFieldsFromValidated($validated);

        if (! ($planPayload['plan_list_enabled'] ?? false)) {
            if ($loanType->plan_image_path) {
                Storage::disk('public')->delete($loanType->plan_image_path);
            }
            $planPayload['plan_image_path'] = null;
        }

        $loanType->update([
            'title' => $validated['title'],
            'profit_calculation_method' => $validated['profit_calculation_method'],
            'interest_rate' => $validated['interest_rate'],
            'daily_late_coefficient' => $validated['daily_late_coefficient'],
            'daily_early_coefficient' => $validated['daily_early_coefficient'],
            'max_loan_amount' => isset($validated['max_loan_amount']) && $validated['max_loan_amount'] !== ''
                ? (int) round((float) $validated['max_loan_amount'])
                : null,
            'max_installment_gap' => isset($validated['max_installment_gap']) && $validated['max_installment_gap'] !== ''
                ? (int) $validated['max_installment_gap']
                : null,
            'installment_gap_unit' => $validated['installment_gap_unit'],
            'repayment_periods' => $this->repaymentPayloadFromValidated($validated),
            'sms_reminder_enabled' => $validated['sms_reminder_enabled'] ?? false,
            'registration_suspended' => $validated['registration_suspended'] ?? false,
            'registration_suspended_message' => $validated['registration_suspended_message'] ?? null,
            ...$planPayload,
            'required_documents' => LoanType::normalizeRequiredDocumentsPayload($validated['required_documents'] ?? []),
        ]);

        $loanType->refresh();

        $this->syncPlanImageOnUpdate($loanType, $request);

        return redirect()
            ->route('admin.loan-types.index')
            ->with('flash_success', 'تغییرات نوع وام ذخیره شد.');
    }

    public function destroy(LoanType $loanType): RedirectResponse
    {
        $loanType->delete();

        return redirect()
            ->route('admin.loan-types.index')
            ->with('flash_success', 'نوع وام حذف شد.');
    }

    public function planImage(LoanType $loanType): Response
    {
        if (! $loanType->plan_image_path || ! Storage::disk('public')->exists($loanType->plan_image_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($loanType->plan_image_path);
    }

    public function exportExcel(): StreamedResponse
    {
        $loanTypes = LoanType::query()->latest('id')->get();
        $filename = 'loan-types-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($loanTypes): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            // UTF-8 BOM for proper Persian display in Excel
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID',
                'عنوان وام',
                'نحوه محاسبه سود',
                'درصد بهره',
                'ضریب دیرکرد روزانه',
                'ضریب زودکرد روزانه',
                'سقف مبلغ وام',
                'حداکثر فاصله اقساط',
                'نوع فاصله اقساط',
                'نوع بازپرداخت',
                'جزئیات بازپرداخت',
                'پیامک یادآوری',
                'وضعیت ثبت درخواست',
                'متن غیرفعال بودن',
                'نمایش در لیست طرح‌ها',
                'عنوان طرح',
                'توضیحات کوتاه طرح',
                'تعداد مدارک لازم',
                'خلاصه مدارک لازم',
                'تاریخ ایجاد',
            ]);

            foreach ($loanTypes as $lt) {
                $rep = $lt->repayment_periods ?? [];
                $repType = (string) ($rep['type'] ?? LoanType::REPAY_UNLIMITED);
                $repDetails = match ($repType) {
                    LoanType::REPAY_MAX_UNTIL => 'حداکثر تا '.((string) ($rep['max_months'] ?? '')).' ماه',
                    LoanType::REPAY_ALLOWED_MONTHS => 'ردیف‌ها: '.count($rep['allowed_rows'] ?? []),
                    default => 'بدون محدودیت',
                };

                $docs = is_array($lt->required_documents) ? $lt->required_documents : [];
                $docSummaryParts = [];
                foreach ($docs as $d) {
                    if (! is_array($d)) {
                        continue;
                    }
                    $title = isset($d['title']) ? trim((string) $d['title']) : '';
                    if ($title === '') {
                        continue;
                    }
                    $timing = isset($d['timing']) && $d['timing'] === LoanType::DOC_TIMING_INITIAL
                        ? 'مدارک اولیه'
                        : 'پس از ارزیابی';
                    $docSummaryParts[] = $title.' ('.$timing.')';
                }

                fputcsv($out, [
                    (string) $lt->id,
                    $lt->title,
                    $lt->profitCalculationLabel(),
                    (string) $lt->interest_rate,
                    (string) $lt->daily_late_coefficient,
                    (string) $lt->daily_early_coefficient,
                    $lt->max_loan_amount !== null ? (string) $lt->max_loan_amount : '',
                    $lt->max_installment_gap !== null ? (string) $lt->max_installment_gap : '',
                    $lt->installmentGapLabel(),
                    $repType,
                    $repDetails,
                    $lt->sms_reminder_enabled ? 'فعال' : 'غیرفعال',
                    $lt->registration_suspended ? 'غیرفعال' : 'فعال',
                    $lt->registration_suspended_message ?? '',
                    $lt->plan_list_enabled ? 'بله' : 'خیر',
                    $lt->plan_title ?? '',
                    $lt->plan_summary ?? '',
                    (string) count($docs),
                    implode(' | ', $docSummaryParts),
                    (string) $lt->created_at,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function planFieldsFromValidated(array $validated): array
    {
        $enabled = (bool) ($validated['plan_list_enabled'] ?? false);

        if (! $enabled) {
            return [
                'plan_list_enabled' => false,
                'plan_image_path' => null,
                'plan_title' => null,
                'plan_summary' => null,
                'plan_body' => null,
            ];
        }

        return [
            'plan_list_enabled' => true,
            'plan_title' => $validated['plan_title'] ?? null,
            'plan_summary' => $validated['plan_summary'] ?? null,
            'plan_body' => $validated['plan_body'] ?? null,
        ];
    }

    private function storePlanImageIfPresent(LoanType $loanType, StoreLoanTypeRequest $request): void
    {
        if (! $loanType->plan_list_enabled || ! $request->hasFile('plan_image')) {
            return;
        }

        $file = $request->file('plan_image');
        if ($file === null || ! $file->isValid()) {
            return;
        }

        $path = $file->store('loan-type-plans', 'public');
        $loanType->update(['plan_image_path' => $path]);
    }

    private function syncPlanImageOnUpdate(LoanType $loanType, UpdateLoanTypeRequest $request): void
    {
        if (! $loanType->plan_list_enabled) {
            return;
        }

        if ($request->boolean('plan_remove_image') && $loanType->plan_image_path) {
            Storage::disk('public')->delete($loanType->plan_image_path);
            $loanType->update(['plan_image_path' => null]);
            $loanType->refresh();
        }

        if ($request->hasFile('plan_image')) {
            $file = $request->file('plan_image');
            if ($file !== null && $file->isValid()) {
                if ($loanType->plan_image_path) {
                    Storage::disk('public')->delete($loanType->plan_image_path);
                }
                $path = $file->store('loan-type-plans', 'public');
                $loanType->update(['plan_image_path' => $path]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{type: string, max_months: int|null, allowed_rows: list<array{months: int, cap: float}>}
     */
    private function repaymentPayloadFromValidated(array $validated): array
    {
        $ruleType = $validated['repayment_rule_type'];

        return match ($ruleType) {
            LoanType::REPAY_UNLIMITED => [
                'type' => LoanType::REPAY_UNLIMITED,
                'max_months' => null,
                'allowed_rows' => [],
            ],
            LoanType::REPAY_MAX_UNTIL => [
                'type' => LoanType::REPAY_MAX_UNTIL,
                'max_months' => (int) $validated['repayment_max_months'],
                'allowed_rows' => [],
            ],
            LoanType::REPAY_ALLOWED_MONTHS => [
                'type' => LoanType::REPAY_ALLOWED_MONTHS,
                'max_months' => null,
                'allowed_rows' => array_map(
                    static fn (array $r): array => [
                        'months' => (int) $r['months'],
                        'cap' => (float) $r['cap'],
                    ],
                    $validated['allowed_rows'] ?? [],
                ),
            ],
            default => [
                'type' => LoanType::REPAY_UNLIMITED,
                'max_months' => null,
                'allowed_rows' => [],
            ],
        };
    }
}
