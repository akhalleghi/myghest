<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallmentPayment;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\LoanFullSettlementOnlinePrincipalAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AdminCustomerSettleAllLoansService
{
    public function __construct(
        private readonly CustomerLoanPortalPresenter $portalPresenter,
        private readonly LoanFullSettlementOnlinePrincipalAllocator $fullSettlementAllocator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Customer $customer): array
    {
        $quote = $this->portalPresenter->fullSettlementQuoteForAllOpenFiles($customer);
        if ($quote === null) {
            return [
                'settleable' => false,
                'files' => [],
                'files_count' => 0,
                'principal_toman' => 0,
                'late_fee_toman' => 0,
                'amount_toman' => 0,
                'principal_fa' => '',
                'late_fee_fa' => '',
                'amount_fa' => '',
            ];
        }

        return array_merge(['settleable' => true], $quote);
    }

    /**
     * @return array{ok: bool, message: string, settled_count?: int, total_principal_toman?: int}
     */
    public function settle(
        Customer $customer,
        string $paymentMethod,
        Carbon $depositedAt,
        ?string $note,
        int $adminId,
    ): array {
        if (! in_array($paymentMethod, CustomerLoanInstallmentPayment::creatablePaymentMethodKeys(), true)) {
            return ['ok' => false, 'message' => 'نحوهٔ پرداخت انتخاب‌شده مجاز نیست.'];
        }

        $noteTrim = $note !== null ? trim($note) : '';
        $depositedDate = $depositedAt->startOfDay()->format('Y-m-d');

        try {
            return DB::transaction(function () use ($customer, $paymentMethod, $depositedDate, $noteTrim, $adminId): array {
                $quote = $this->portalPresenter->fullSettlementQuoteForAllOpenFiles($customer);
                if ($quote === null) {
                    return ['ok' => false, 'message' => 'پروندهٔ قابل تسویه‌ای یافت نشد.'];
                }

                $settledCount = 0;
                $totalPrincipal = 0;

                foreach ($quote['files'] as $item) {
                    $fileId = (int) $item['loan_file_id'];
                    $expectedPrincipal = (int) $item['principal_toman'];
                    $expectedLateFee = (int) $item['late_fee_toman'];
                    $expectedAmount = (int) $item['amount_toman'];

                    $file = CustomerLoanFile::query()
                        ->where('customer_id', (int) $customer->id)
                        ->whereKey($fileId)
                        ->lockForUpdate()
                        ->first();

                    if ($file === null) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $quote2 = $this->portalPresenter->fullSettlementOnlinePaymentQuote($file);
                    if ($quote2 === null
                        || (int) $quote2['amount_toman'] !== $expectedAmount
                        || (int) $quote2['principal_toman'] !== $expectedPrincipal
                        || (int) $quote2['late_fee_toman'] !== $expectedLateFee) {
                        throw new \RuntimeException('QUOTE_MISMATCH');
                    }

                    $this->fullSettlementAllocator->allocatePrincipalAcrossInstallments(
                        $file,
                        $expectedPrincipal,
                        $noteTrim,
                        $paymentMethod,
                        $adminId,
                        $depositedDate,
                    );

                    $file->refresh();
                    $file->is_settled = true;
                    $file->settled_at = Carbon::parse($depositedDate)->startOfDay();
                    $file->save();

                    $settledCount++;
                    $totalPrincipal += $expectedPrincipal;
                }

                return [
                    'ok' => true,
                    'message' => 'تسویهٔ کلی '.$settledCount.' پرونده با موفقیت ثبت شد.',
                    'settled_count' => $settledCount,
                    'total_principal_toman' => $totalPrincipal,
                ];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'QUOTE_MISMATCH') {
                return ['ok' => false, 'message' => 'وضعیت پرونده‌ها هنگام تسویه تغییر کرد؛ لطفاً پیش‌نمایش را دوباره بگیرید.'];
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'خطای غیرمنتظره هنگام تسویه؛ لطفاً دوباره تلاش کنید.'];
        }
    }
}
