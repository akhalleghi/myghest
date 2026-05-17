<?php

declare(strict_types=1);

namespace App\Services\Deposits;

use App\Models\Customer;
use App\Models\CustomerDepositDeclaration;
use App\Services\Sms\PortalAdminSmsDispatcher;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Services\Loans\LoanInstallmentScheduleService;
use App\Support\JalaliInputParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DepositDeclarationUserService
{
    public function __construct(
        private readonly LoanInstallmentScheduleService $schedule,
    ) {}

    /**
     * پس از بازدید صفحهٔ اعلام واریزی‌ها، اعلان‌های «رسیدگی شده» را برای کاربر خوانده‌شده علامت می‌زند.
     */
    public function acknowledgeAllReviewedForCustomer(Customer $customer): void
    {
        CustomerDepositDeclaration::query()
            ->where('customer_id', $customer->id)
            ->whereNeedsCustomerReviewNotification()
            ->update(['review_acknowledged_at' => now()]);
    }

    public function paginateForCustomer(Customer $customer, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        $q = CustomerDepositDeclaration::query()
            ->where('customer_id', $customer->id)
            ->with(['loanFile.loanType', 'installment'])
            ->latest('id');

        $s = $search !== null ? trim($search) : '';
        if ($s !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(function ($w) use ($like): void {
                $w->where('tracking_number', 'like', $like)
                    ->orWhereHas('loanFile', function ($lf) use ($like): void {
                        $lf->where('loan_code', 'like', $like)
                            ->orWhereHas('loanType', function ($lt) use ($like): void {
                                $lt->where('title', 'like', $like);
                            });
                    });
            });
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Customer $customer, array $data, ?UploadedFile $attachment): CustomerDepositDeclaration
    {
        $inst = $this->assertLoanAndInstallment($customer, (int) $data['customer_loan_file_id'], (int) $data['customer_loan_installment_id']);
        $this->assertDepositAmountWithinBounds($inst, (int) $data['amount_toman']);

        $depCarbon = JalaliInputParser::toCarbonDate((string) ($data['deposited_jdate'] ?? ''));
        if ($depCarbon === null) {
            throw ValidationException::withMessages(['deposited_jdate' => ['تاریخ واریز معتبر نیست. فرمت: ۱۴۰۳/۰۶/۱۵']]);
        }

        $path = $this->storeAttachment($customer, $attachment);

        $declaration = CustomerDepositDeclaration::query()->create([
            'customer_id' => $customer->id,
            'customer_loan_file_id' => (int) $data['customer_loan_file_id'],
            'customer_loan_installment_id' => (int) $data['customer_loan_installment_id'],
            'deposited_at' => $depCarbon->format('Y-m-d'),
            'amount_toman' => (int) $data['amount_toman'],
            'user_payment_method' => (string) $data['user_payment_method'],
            'tracking_number' => $this->nullableString($data['tracking_number'] ?? null),
            'customer_note' => $this->nullableString($data['customer_note'] ?? null),
            'attachment_path' => $path,
            'status' => CustomerDepositDeclaration::STATUS_PENDING,
        ]);

        PortalAdminSmsDispatcher::afterDepositDeclaration($declaration);

        return $declaration;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, CustomerDepositDeclaration $row, array $data, ?UploadedFile $attachment): CustomerDepositDeclaration
    {
        if ((int) $row->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if (! $row->isPending()) {
            throw ValidationException::withMessages(['status' => ['فقط درخواست‌های در حال بررسی قابل ویرایش هستند.']]);
        }

        $inst = $this->assertLoanAndInstallment(
            $customer,
            (int) $data['customer_loan_file_id'],
            (int) $data['customer_loan_installment_id']
        );
        $this->assertDepositAmountWithinBounds($inst, (int) $data['amount_toman']);

        $depCarbon = JalaliInputParser::toCarbonDate((string) ($data['deposited_jdate'] ?? ''));
        if ($depCarbon === null) {
            throw ValidationException::withMessages(['deposited_jdate' => ['تاریخ واریز معتبر نیست.']]);
        }

        $path = $row->attachment_path;
        if ($attachment !== null && $attachment->isValid()) {
            $this->deleteStoredFile($path);
            $path = $this->storeAttachment($customer, $attachment);
        }

        $row->update([
            'customer_loan_file_id' => (int) $data['customer_loan_file_id'],
            'customer_loan_installment_id' => (int) $data['customer_loan_installment_id'],
            'deposited_at' => $depCarbon->format('Y-m-d'),
            'amount_toman' => (int) $data['amount_toman'],
            'user_payment_method' => (string) $data['user_payment_method'],
            'tracking_number' => $this->nullableString($data['tracking_number'] ?? null),
            'customer_note' => $this->nullableString($data['customer_note'] ?? null),
            'attachment_path' => $path,
        ]);

        return $row->fresh(['loanFile.loanType', 'installment']);
    }

    public function deleteIfPending(Customer $customer, CustomerDepositDeclaration $row): void
    {
        if ((int) $row->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if (! $row->isPending()) {
            throw ValidationException::withMessages(['status' => ['فقط درخواست‌های در حال بررسی قابل حذف هستند.']]);
        }
        $this->deleteStoredFile($row->attachment_path);
        $row->delete();
    }

    private function assertLoanAndInstallment(Customer $customer, int $loanFileId, int $installmentId): CustomerLoanInstallment
    {
        $file = CustomerLoanFile::query()
            ->where('customer_id', $customer->id)
            ->whereKey($loanFileId)
            ->first();
        if ($file === null || $file->revoked_at !== null) {
            throw ValidationException::withMessages(['customer_loan_file_id' => ['پروندهٔ وام معتبر نیست یا فسخ شده است.']]);
        }

        $this->schedule->ensureSchedule($file);

        $inst = CustomerLoanInstallment::query()
            ->where('customer_loan_file_id', $file->id)
            ->whereKey($installmentId)
            ->first();
        if ($inst === null) {
            throw ValidationException::withMessages(['customer_loan_installment_id' => ['قسط انتخاب‌شده معتبر نیست.']]);
        }

        return $inst;
    }

    /**
     * جلوگیری از اعلام مبالغ غیرواقعی نسبت به ماندهٔ قسط (سقف سمت سرور مستقل از UI).
     */
    private function assertDepositAmountWithinBounds(CustomerLoanInstallment $inst, int $amountToman): void
    {
        $slotRemaining = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
        if ($slotRemaining < 1) {
            throw ValidationException::withMessages([
                'customer_loan_installment_id' => ['این قسط مانده‌ای برای اعلام واریزی ندارد.'],
            ]);
        }

        $headroom = max((int) floor($slotRemaining * 0.5), 5_000_000);
        $maxDeclare = min(500_000_000, $slotRemaining + $headroom);
        if ($amountToman < 1 || $amountToman > $maxDeclare) {
            throw ValidationException::withMessages([
                'amount_toman' => [
                    'مبلغ اعلام‌شده با ماندهٔ معقول این قسط هم‌خوان نیست. حداکثر مجاز در این مرحله: '
                    .number_format($maxDeclare, 0, '.', ',').' تومان',
                ],
            ]);
        }
    }

    private function storeAttachment(Customer $customer, ?UploadedFile $file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return null;
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            throw ValidationException::withMessages(['attachment' => ['فرمت فایل مجاز: jpg، png، pdf']]);
        }
        $mime = (string) ($file->getMimeType() ?: '');
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['attachment' => ['نوع فایل (MIME) با فرمت‌های مجاز هم‌خوان نیست.']]);
        }
        $name = Str::lower(Str::random(18)).'.'.$ext;
        $dir = 'deposit-declarations/'.$customer->id;

        return $file->storeAs($dir, $name, 'public');
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }
}
