<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\LoanType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InstallmentLateFeeWaiverTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private CustomerLoanFile $file;
    private CustomerLoanInstallment $installment;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
        $this->actingAs(Admin::create(['email' => 'owner@example.test', 'username' => 'owner', 'name' => 'Owner', 'password' => 'password', 'is_active' => true, 'is_super_admin' => true]), 'admin');
        $this->customer = Customer::create(['first_name' => 'Test', 'last_name' => 'Customer', 'customer_code' => '1', 'username' => '09123456789', 'mobile' => '09123456789', 'password' => 'password']);
        $type = LoanType::create(['title' => 'Test', 'profit_calculation_method' => 'monthly', 'interest_rate' => 0,
            'daily_late_coefficient' => .005, 'daily_early_coefficient' => 0, 'installment_gap_unit' => 'monthly', 'repayment_periods' => [1]]);
        $this->file = CustomerLoanFile::create(['customer_id' => $this->customer->id, 'loan_type_id' => $type->id,
            'loan_code' => 'DS 100', 'loan_start_date' => now()->subMonth(), 'amount_toman' => 900000,
            'installments_count' => 1, 'installment_amount_toman' => 900000, 'installment_interval_count' => 1,
            'installment_interval_unit' => 'monthly', 'profit_calculation_method' => 'monthly', 'base_interest_rate' => 0, 'effective_interest_rate' => 0]);
        $this->installment = CustomerLoanInstallment::create(['customer_loan_file_id' => $this->file->id,
            'sequence' => 1, 'amount_toman' => 900000, 'due_date' => now()->subDays(10), 'paid_amount_toman' => 910000, 'paid_at' => now()]);
        $this->installment->payments()->create(['payment_method' => 'cash', 'amount_toman' => 910000, 'deposited_at' => now()]);
        $this->url = route('admin.customers.loan-files.installments.late-fee-waiver', [$this->customer, $this->file, $this->installment]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_partial_and_full_waivers_change_only_unpaid_fees_and_are_audited(): void
    {
        $before = $this->getJson($this->url)->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('remaining_late_fee_toman', 35000)->json();
        $receipt = $this->installment->payments()->firstOrFail()->getRawOriginal();
        $this->putJson($this->url, ['remaining_toman' => 1234, 'version' => $before['version'], 'reason' => 'تخفیف دیرکرد'])->assertOk()->assertJsonPath('installment_remaining_toman', 1234)->assertJsonPath('loan_remaining_toman', 1234);
        $this->putJson($this->url, ['remaining_toman' => 0, 'version' => $before['version'], 'reason' => 'درخواست تکراری'])->assertUnprocessable();
        self::assertSame(1, DB::table('installment_late_fee_waivers')->count());
        Carbon::setTestNow(now()->addDay());
        $this->withSession(['portal_session_activity:admin' => now()->timestamp]);
        $current = $this->getJson($this->url)->assertOk()->json();
        self::assertCount(1, $current['history']);
        $this->putJson($this->url, ['remaining_toman' => 0, 'version' => $current['version'], 'reason' => 'بخشودگی کامل'])->assertOk()->assertJsonPath('remaining_late_fee_toman', 0)->assertJsonPath('loan_remaining_toman', 0);
        self::assertSame($receipt, $this->installment->payments()->firstOrFail()->getRawOriginal());
        self::assertSame(35000, $this->installment->fresh()->late_fee_waived_toman);
        $balance = $this->installment->fresh()->debtBalance();
        self::assertSame(10000, $balance['late_fee_assessed_toman']);
        self::assertSame(10000, $balance['late_fee_paid_toman']);
        self::assertSame(910000, $balance['final_amount_toman']);
        self::assertSame(0, $balance['credit_toman']);
        $this->getJson(route('admin.customers.loan-board-summary', $this->customer))->assertOk()->assertJsonPath('loan_remaining_installments', 0)->assertJsonPath('overdue_total_toman', 0)->assertJsonPath('nearest_due_date_fa', null);
        self::assertSame(1234, $this->installment->fresh()->debtBalance(null, Carbon::parse('2026-11-09'))['remaining_toman']);
        self::assertSame(41000, $this->installment->fresh()->debtBalance(null, Carbon::parse('2026-11-08'))['late_fee_assessed_toman']);
        Carbon::setTestNow(now()->addDays(10));
        self::assertSame(0, $this->installment->fresh()->remainingDebtToman());
    }

    public function test_principal_must_be_fully_paid_and_fee_cannot_be_increased(): void
    {
        $before = $this->getJson($this->url)->json();
        foreach ([-1, 35000, 35001] as $amount) {
            $this->putJson($this->url, ['remaining_toman' => $amount, 'version' => $before['version'], 'reason' => 'بررسی مبلغ'])->assertUnprocessable();
        }
        $this->putJson($this->url, ['remaining_toman' => 0, 'version' => $before['version'], 'reason' => ''])->assertUnprocessable();
        $this->installment->payments()->update(['amount_toman' => 899999]);
        $this->installment->update(['paid_amount_toman' => 899999]);
        $before = $this->getJson($this->url)->assertJsonPath('allowed', false)->json();
        $this->putJson($this->url, ['remaining_toman' => 0, 'version' => $before['version'], 'reason' => 'اصل قسط مانده دارد'])->assertUnprocessable();
        self::assertSame(0, DB::table('installment_late_fee_waivers')->count());
    }

    public function test_dedicated_permission_and_customer_scoping_are_enforced(): void
    {
        $limited = Admin::create(['email' => 'limited@example.test', 'username' => 'limited', 'name' => 'Limited', 'password' => 'password', 'is_active' => true, 'is_super_admin' => false]);
        $this->actingAs($limited, 'admin')->getJson($this->url)->assertForbidden();
        $this->putJson($this->url, [])->assertForbidden();
        AdminPermissionGrant::create(['admin_id' => $limited->id, 'permission_key' => 'customers.loans.installments.waive_late_fee']);
        $this->actingAs($limited->fresh(), 'admin');
        $before = $this->getJson($this->url)->assertOk()->json();
        $this->putJson($this->url, ['remaining_toman' => 0, 'version' => $before['version'], 'reason' => 'کاربر مجاز'])->assertOk();
        self::assertSame($limited->id, DB::table('installment_late_fee_waivers')->value('admin_id'));
        $other = Customer::create(['first_name' => 'Other', 'last_name' => 'Customer', 'customer_code' => '2', 'username' => '09123456780', 'mobile' => '09123456780', 'password' => 'password']);
        $wrongUrl = route('admin.customers.loan-files.installments.late-fee-waiver', [$other, $this->file, $this->installment]);
        $this->getJson($wrongUrl)->assertNotFound();
        $this->putJson($wrongUrl, [])->assertNotFound();
    }
}
