<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Customer;
use App\Services\Support\SupportTicketUserService;
use Hekmatinasser\Jalali\Jalali;

/**
 * خلاصهٔ مالی داشبورد کاربر از روی همان دادهٔ پرونده‌های پنل (هم‌سو با کارت‌های وام).
 */
final class CustomerPortalSummaryBuilder
{
    public function __construct(
        private readonly SupportTicketUserService $tickets,
    ) {}

    /**
     * @param  array{loans?: list<array<string, mixed>>}  $portalLoans
     * @return array<string, mixed>
     */
    public function build(Customer $customer, array $portalLoans): array
    {
        $customer->loadMissing('wallet');

        $sumPrincipal = 0;
        $sumPaid = 0;
        $sumRemaining = 0;

        foreach ($portalLoans['loans'] ?? [] as $loan) {
            if (! empty($loan['is_revoked'])) {
                continue;
            }
            $sumPrincipal += (int) ($loan['amount_toman'] ?? 0);
            $sumPaid += (int) ($loan['paid_amount_toman'] ?? 0);
            $sumRemaining += (int) ($loan['remaining_amount_toman'] ?? 0);
        }

        $walletToman = (int) ($customer->wallet?->balance_toman ?? 0);
        $ticketsCount = $this->tickets->countActiveForCustomer($customer);

        return [
            'total_loans_principal_toman' => $sumPrincipal,
            'total_loans_principal_fa' => $this->moneyFa($sumPrincipal),
            'total_payments_toman' => $sumPaid,
            'total_payments_fa' => $this->moneyFa($sumPaid),
            'remaining_installments_toman' => $sumRemaining,
            'remaining_installments_fa' => $this->moneyFa($sumRemaining),
            'wallet_balance_toman' => $walletToman,
            'wallet_balance_fa' => $this->moneyFa($walletToman),
            'tickets_count' => $ticketsCount,
            'tickets_count_fa' => Jalali::enToFaNumbers((string) $ticketsCount),
        ];
    }

    private function moneyFa(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $toman), 0, '.', ',')).' تومان';
    }
}
