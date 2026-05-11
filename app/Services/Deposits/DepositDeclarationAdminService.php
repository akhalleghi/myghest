<?php

declare(strict_types=1);

namespace App\Services\Deposits;

use App\Models\Admin;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Loans\LoanRemainingPayableOnFileToman;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DepositDeclarationAdminService
{
    public function __construct(
        private readonly LoanRemainingPayableOnFileToman $remainingPayable,
        private readonly LoanInstallmentPaidAmountSyncer $paidSyncer,
    ) {}

    public function paginate(?string $status, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        $q = CustomerDepositDeclaration::query()
            ->with(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin'])
            ->latest('id');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        $s = $search !== null ? trim($search) : '';
        if ($s !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(function ($w) use ($like): void {
                $w->where('tracking_number', 'like', $like)
                    ->orWhereHas('customer', function ($c) use ($like): void {
                        $c->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('customer_code', 'like', $like);
                    })
                    ->orWhereHas('loanFile', function ($lf) use ($like): void {
                        $lf->where('loan_code', 'like', $like);
                    });
            });
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function review(
        CustomerDepositDeclaration $declaration,
        Admin $admin,
        string $action,
        ?string $adminNote
    ): CustomerDepositDeclaration {
        if (! $declaration->isPending()) {
            throw ValidationException::withMessages(['status' => ['این درخواست قبلاً رسیدگی شده است.']]);
        }

        $note = $adminNote !== null ? trim($adminNote) : '';
        $noteStored = $note !== '' ? $note : null;
        $now = Carbon::now();
        $adminId = (int) $admin->id;

        return match ($action) {
            'approve' => $this->applyApprove($declaration, $adminId, $now, $noteStored),
            'reject' => $this->applyReject($declaration, $adminId, $now, $noteStored),
            'approve_apply' => $this->applyApproveApply($declaration, $adminId, $now, $noteStored),
            default => throw ValidationException::withMessages(['action' => ['عمل نامعتبر است.']]),
        };
    }

    private function applyApprove(
        CustomerDepositDeclaration $declaration,
        int $adminId,
        Carbon $now,
        ?string $adminNote
    ): CustomerDepositDeclaration {
        $declaration->update([
            'status' => CustomerDepositDeclaration::STATUS_APPROVED,
            'admin_note' => $adminNote,
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => $now,
        ]);

        return $declaration->fresh(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin']);
    }

    private function applyReject(
        CustomerDepositDeclaration $declaration,
        int $adminId,
        Carbon $now,
        ?string $adminNote
    ): CustomerDepositDeclaration {
        $declaration->update([
            'status' => CustomerDepositDeclaration::STATUS_REJECTED,
            'admin_note' => $adminNote,
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => $now,
        ]);

        return $declaration->fresh(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin']);
    }

    private function applyApproveApply(
        CustomerDepositDeclaration $declaration,
        int $adminId,
        Carbon $now,
        ?string $adminNote
    ): CustomerDepositDeclaration {
        $loanFile = CustomerLoanFile::query()
            ->whereKey($declaration->customer_loan_file_id)
            ->with(['loanType', 'installments'])
            ->firstOrFail();

        if ($loanFile->revoked_at !== null) {
            throw ValidationException::withMessages(['loan' => ['قرارداد فسخ شده است؛ ثبت پرداخت ممکن نیست.']]);
        }
        if ($loanFile->is_settled) {
            throw ValidationException::withMessages(['loan' => ['پرونده تسویه‌شده است؛ ثبت پرداخت مجاز نیست.']]);
        }

        $installment = CustomerLoanInstallment::query()
            ->where('customer_loan_file_id', $loanFile->id)
            ->whereKey($declaration->customer_loan_installment_id)
            ->firstOrFail();

        $paymentMethod = $this->mapUserMethodToPaymentMethod($declaration->user_payment_method);
        $amount = (int) $declaration->amount_toman;

        DB::transaction(function () use (
            $declaration,
            $loanFile,
            $installment,
            $paymentMethod,
            $amount,
            $adminId,
            $now,
            $adminNote
        ): CustomerLoanInstallmentPayment {
            $loanFile->refresh();
            $installment->refresh();

            $ceiling = $this->remainingPayable->value($loanFile);
            if ($amount > $ceiling) {
                throw ValidationException::withMessages([
                    'amount' => [
                        $ceiling <= 0
                            ? 'طبق ماندهٔ وام، مبلغ دیگری قابل ثبت نیست.'
                            : ('جمع پرداخت‌ها نمی‌تواند از ماندهٔ وام بیشتر شود؛ حداکثر قابل ثبت: '
                                .number_format($ceiling, 0, '.', ',').' تومان.'),
                    ],
                ]);
            }

            $noteParts = array_filter([
                'اعلام کاربر #'.$declaration->id,
                $declaration->tracking_number !== null && $declaration->tracking_number !== ''
                    ? ('شماره پیگیری/فیش: '.$declaration->tracking_number)
                    : null,
                $declaration->customer_note,
                $adminNote !== null && $adminNote !== '' ? ('یادداشت مدیر: '.$adminNote) : null,
            ]);
            $combinedNote = implode(' — ', $noteParts);

            $row = CustomerLoanInstallmentPayment::query()->create([
                'customer_loan_installment_id' => (int) $installment->id,
                'payment_method' => $paymentMethod,
                'amount_toman' => $amount,
                'reference_due_date' => null,
                'deposited_at' => $declaration->deposited_at->format('Y-m-d'),
                'note' => $combinedNote !== '' ? $combinedNote : null,
                'recorded_by_admin_id' => $adminId,
            ]);

            $installment->refresh();
            $this->paidSyncer->syncFromPaymentRows($installment);

            $declaration->update([
                'status' => CustomerDepositDeclaration::STATUS_APPROVED_APPLIED,
                'admin_note' => $adminNote,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $now,
                'applied_payment_id' => (int) $row->id,
            ]);

            return $row;
        });

        return $declaration->fresh(['customer', 'loanFile.loanType', 'installment', 'reviewedByAdmin', 'appliedPayment']);
    }

    private function mapUserMethodToPaymentMethod(string $userMethod): string
    {
        return match ($userMethod) {
            CustomerDepositDeclaration::USER_METHOD_CASH => CustomerLoanInstallmentPayment::METHOD_CASH,
            CustomerDepositDeclaration::USER_METHOD_BANK => CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER,
            CustomerDepositDeclaration::USER_METHOD_ONLINE => CustomerLoanInstallmentPayment::METHOD_ONLINE,
            default => CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER,
        };
    }
}
