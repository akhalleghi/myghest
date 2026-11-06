<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\LoanType;
use App\Services\Loans\InstallmentDebtCalculator;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class LoanDebtBalanceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function installment(int $amount, array $receipts): CustomerLoanInstallment
    {
        $i = new CustomerLoanInstallment(['amount_toman' => $amount, 'due_date' => '2026-08-01', 'paid_amount_toman' => array_sum(array_column($receipts, 1))]);
        $i->id = 1;
        $payments = [];
        foreach ($receipts as $id => [$date, $value]) {
            $p = new CustomerLoanInstallmentPayment(['deposited_at' => $date, 'amount_toman' => $value]);
            $p->id = $id + 1;
            $payments[] = $p;
        }
        $i->setRelation('payments', new Collection($payments));

        return $i;
    }

    public function test_overdue_rounding_boundaries_and_partial_receipts(): void
    {
        foreach ([1 => 1000, 999 => 1000, 1000 => 1000, 1001 => 2000, 2026670 => 2027000] as $raw => $expected) {
            $balance = (new InstallmentDebtCalculator)->balance($this->installment($raw, []), 0, Carbon::parse('2026-08-02'));
            self::assertSame($expected, $balance['final_amount_toman']);
            self::assertSame($expected, $balance['remaining_toman']);
        }
        $partial = (new InstallmentDebtCalculator)->balance($this->installment(2026670, [['2026-08-02', 500500]]), 0, Carbon::parse('2026-08-02'));
        self::assertSame(1526500, $partial['remaining_toman']);
        self::assertSame(2027000, $partial['final_amount_toman']);
    }

    public function test_principal_completion_keeps_unpaid_late_fee_as_debt(): void
    {
        $i = $this->installment(900000, [['2026-08-11', 930000]]);
        $balance = (new InstallmentDebtCalculator)->balance($i, 1 / 180, Carbon::parse('2026-08-21'));
        self::assertSame(0, $balance['principal_remaining_toman']);
        self::assertSame(50000, $balance['late_fee_assessed_toman']);
        self::assertSame(30000, $balance['late_fee_paid_toman']);
        self::assertSame(20000, $balance['remaining_toman']);
    }

    public function test_separate_payment_of_remaining_fee_closes_installment_without_new_penalty(): void
    {
        $i = $this->installment(900000, [['2026-08-11', 930000], ['2026-08-21', 20000]]);
        $balance = (new InstallmentDebtCalculator)->balance($i, 1 / 180, Carbon::parse('2026-09-01'));
        self::assertSame(50000, $balance['late_fee_assessed_toman']);
        self::assertSame(0, $balance['remaining_toman']);
    }

    public function test_partial_receipts_accrue_simple_late_charges_on_remaining_principal(): void
    {
        $i = $this->installment(250000, [['2026-08-11', 100000]]);
        $balance = (new InstallmentDebtCalculator)->balance($i, 0.001, Carbon::parse('2026-08-16'));
        self::assertSame(150000, $balance['principal_remaining_toman']);
        self::assertSame(4000, $balance['late_fee_remaining_toman']);
        self::assertSame(3250, $balance['late_fee_unrounded_toman']);
        self::assertSame(154000, $balance['remaining_toman']);
    }

    public function test_editing_excludes_the_original_receipt(): void
    {
        $i = $this->installment(900000, [['2026-08-11', 930000]]);
        $balance = (new InstallmentDebtCalculator)->balance($i, 1 / 180, Carbon::parse('2026-08-11'), 1);
        self::assertSame(950000, $balance['remaining_toman']);
    }

    public function test_early_payment_has_no_late_charge(): void
    {
        $i = $this->installment(250000, [['2026-07-30', 250000]]);
        $balance = (new InstallmentDebtCalculator)->balance($i, 0.001, Carbon::parse('2026-09-01'));
        self::assertSame(0, $balance['late_fee_assessed_toman']);
        self::assertSame(0, $balance['remaining_toman']);
    }

    public function test_legacy_receipt_total_keeps_unpaid_fee(): void
    {
        $i = $this->installment(900000, []);
        $i->paid_amount_toman = 930000;
        $i->paid_at = '2026-08-11';
        $balance = (new InstallmentDebtCalculator)->balance($i, 1 / 180, Carbon::parse('2026-08-21'));
        self::assertSame(20000, $balance['remaining_toman']);
    }

    public function test_two_nominally_paid_installments_with_fee_shortfalls_do_not_close_loan(): void
    {
        Carbon::setTestNow('2026-08-21');
        $first = $this->installment(900000, [['2026-08-11', 930000]]);
        $second = $this->installment(900000, [['2026-08-11', 900000]]);
        $second->id = 2;
        $type = new LoanType(['daily_late_coefficient' => 0.005, 'daily_early_coefficient' => 0]);
        $file = new CustomerLoanFile(['is_settled' => false, 'discount_amount_toman' => 0]);
        $file->setRelation('loanType', $type);
        $file->setRelation('installments', new Collection([$first, $second]));
        $snap = app(LoanFileFinanceCalculator::class)->loanInstallmentFinancialSnapshot($file, 1800000);
        self::assertSame(0, $snap['schedule_remaining_toman']);
        self::assertSame(60000, $snap['remaining_amount_toman']);
        self::assertSame(0, $snap['paid_installments_count']);
        self::assertSame(2, $snap['unpaid_installments_count']);
    }

    public function test_file_specific_daily_coefficients_override_loan_type_defaults(): void
    {
        Carbon::setTestNow('2026-08-21');
        $installment = $this->installment(900000, []);
        $type = new LoanType(['daily_late_coefficient' => 0.005, 'daily_early_coefficient' => 0.006]);
        $file = new CustomerLoanFile([
            'amount_toman' => 900000,
            'is_settled' => false,
            'discount_amount_toman' => 0,
            'daily_late_coefficient' => 0.001,
            'daily_early_coefficient' => 0.002,
        ]);
        $file->setRelation('loanType', $type);
        $file->setRelation('installments', new Collection([$installment]));

        $snapshot = app(LoanFileFinanceCalculator::class)->loanInstallmentFinancialSnapshot($file, 900000);

        self::assertSame(18000, $snapshot['late_fee_payable_toman']);
        self::assertSame(0.001, $file->dailyLateCoefficient());
        self::assertSame(0.002, $file->dailyEarlyCoefficient());
    }

    public function test_loan_snapshot_does_not_report_settled_while_fees_remain(): void
    {
        Carbon::setTestNow('2026-08-21');
        $i = $this->installment(900000, [['2026-08-11', 930000]]);
        $type = new LoanType(['daily_late_coefficient' => 1 / 180, 'daily_early_coefficient' => 0]);
        // Keep exact coefficient rather than the production decimal cast in this arithmetic fixture.
        $type->mergeCasts(['daily_late_coefficient' => 'float']);
        $file = new CustomerLoanFile(['amount_toman' => 900000, 'is_settled' => false, 'discount_amount_toman' => 0]);
        $file->setRelation('loanType', $type);
        $file->setRelation('installments', new Collection([$i]));
        $snap = app(LoanFileFinanceCalculator::class)->loanInstallmentFinancialSnapshot($file, 900000);
        self::assertSame(20000, $snap['remaining_amount_toman']);
        self::assertSame(0, $snap['principal_payable_toman']);
        self::assertSame(20000, $snap['late_fee_payable_toman']);
        self::assertSame(0, $snap['paid_installments_count']);
        self::assertSame(1, $snap['unpaid_installments_count']);
        $file->discount_amount_toman = 20000;
        $discounted = app(LoanFileFinanceCalculator::class)->loanInstallmentFinancialSnapshot($file, 900000);
        self::assertSame(0, $discounted['remaining_amount_toman']);
        self::assertSame(1, $discounted['paid_installments_count']);
    }
}
