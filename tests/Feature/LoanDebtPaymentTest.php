<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerWallet;
use App\Models\LoanType;
use App\Services\Admin\AdminDashboardStatisticsService;
use App\Services\Admin\Reports\InstallmentDueDatesByDateReportService;
use App\Services\Admin\Reports\LoanInterestFeesReportService;
use App\Services\Admin\Reports\MemberLoansByDateReportService;
use App\Services\Customers\AdminCustomerSettleAllLoansService;
use App\Services\Customers\AdminCustomerSettleAllOverdueService;
use App\Services\Deposits\DepositDeclarationAdminService;
use App\Services\Deposits\DepositDeclarationUserService;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanFullSettlementOnlinePrincipalAllocator;
use App\Services\Payment\InstallmentOnlinePaymentCompletionService;
use App\Services\Payment\InstallmentOnlinePaymentResolver;
use App\Services\Payment\PortalLoanWalletPaymentService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LoanDebtPaymentTest extends TestCase
{
    private Customer $customer;

    private CustomerLoanFile $file;

    private CustomerLoanInstallment $installment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Bus::fake();
        Carbon::setTestNow('2026-08-21 12:00:00');
        Schema::create('admins', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('username')->nullable();
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t): void {
            $t->id();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('national_id')->nullable();
            $t->string('mobile')->nullable();
            $t->string('customer_code')->nullable();
            $t->timestamps();
        });
        Schema::create('loan_types', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->decimal('interest_rate', 5, 2)->default(0);
            $t->string('profit_calculation_method')->default('monthly');
            $t->string('installment_gap_unit')->default('monthly');
            $t->unsignedBigInteger('max_loan_amount')->nullable();
            $t->integer('max_installment_gap')->nullable();
            $t->json('repayment_periods')->nullable();
            $t->decimal('daily_late_coefficient', 12, 8);
            $t->decimal('daily_early_coefficient', 12, 8)->default(0);
            $t->timestamps();
        });
        Schema::create('customer_loan_files', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('loan_type_id');
            $t->string('loan_code');
            $t->date('loan_start_date');
            $t->date('disbursement_due_date')->nullable();
            $t->string('sub_file_number')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('amount_toman');
            $t->integer('installments_count');
            $t->integer('installment_interval_count');
            $t->string('installment_interval_unit');
            $t->unsignedBigInteger('installment_amount_toman');
            $t->decimal('effective_interest_rate', 6, 2)->default(0);
            $t->decimal('base_interest_rate', 6, 2)->default(0);
            $t->boolean('has_custom_interest_rate')->default(false);
            $t->decimal('custom_interest_rate', 6, 2)->nullable();
            $t->decimal('daily_late_coefficient', 12, 6)->nullable();
            $t->decimal('daily_early_coefficient', 12, 6)->nullable();
            $t->string('profit_calculation_method');
            $t->unsignedBigInteger('down_payment_toman')->default(0);
            $t->unsignedBigInteger('discount_amount_toman')->default(0);
            $t->timestamp('discount_updated_at')->nullable();
            $t->unsignedBigInteger('discount_updated_by_admin_id')->nullable();
            $t->boolean('is_settled')->default(false);
            $t->timestamp('revoked_at')->nullable();
            $t->date('settled_at')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_loan_installments', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_loan_file_id');
            $t->integer('sequence');
            $t->unsignedBigInteger('amount_toman');
            $t->date('due_date');
            $t->unsignedBigInteger('paid_amount_toman')->default(0);
            $t->date('paid_at')->nullable();
            $t->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $t->string('recorded_by_label')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_loan_installment_payments', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_loan_installment_id');
            $t->string('payment_method');
            $t->unsignedBigInteger('amount_toman');
            $t->date('reference_due_date')->nullable();
            $t->date('deposited_at');
            $t->text('note')->nullable();
            $t->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_deposit_declarations', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('customer_loan_file_id');
            $t->unsignedBigInteger('customer_loan_installment_id');
            $t->date('deposited_at');
            $t->unsignedBigInteger('amount_toman');
            $t->string('user_payment_method');
            $t->string('status');
            $t->string('tracking_number')->nullable();
            $t->text('customer_note')->nullable();
            $t->text('admin_note')->nullable();
            $t->string('attachment_path')->nullable();
            $t->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('applied_payment_id')->nullable();
            $t->timestamps();
        });
        foreach ([
            '2026_05_07_040000_create_app_settings_table.php',
            '2026_05_09_090000_create_customer_wallets_table.php',
            '2026_05_09_090100_create_customer_wallet_transactions_table.php',
            '2026_05_09_123000_add_request_uuid_to_customer_wallet_transactions_table.php',
            '2026_05_14_100000_create_customer_transactions_table.php',
            '2026_05_13_120000_create_customer_loan_installment_online_payment_intents_table.php',
            '2026_05_15_100000_create_customer_loan_full_settlement_online_payment_intents_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $admin = Admin::create(['name' => 'Test admin', 'username' => 'test-admin']);
        auth('admin')->setUser($admin);
        $this->customer = Customer::create(['first_name' => 'Test', 'last_name' => 'Customer']);
        $type = LoanType::create(['title' => 'Test', 'daily_late_coefficient' => 0.005]);
        $this->file = CustomerLoanFile::create([
            'customer_id' => $this->customer->id, 'loan_type_id' => $type->id, 'loan_code' => 'TEST',
            'loan_start_date' => '2026-07-01', 'amount_toman' => 900000, 'installments_count' => 1,
            'installment_interval_count' => 1, 'installment_interval_unit' => 'monthly',
            'installment_amount_toman' => 900000, 'profit_calculation_method' => 'monthly', 'effective_interest_rate' => 0,
        ]);
        $this->installment = CustomerLoanInstallment::create([
            'customer_loan_file_id' => $this->file->id, 'sequence' => 1, 'amount_toman' => 900000, 'due_date' => '2026-08-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function request(int $amount, string $date): Request
    {
        return Request::create('/', 'POST', ['payment_method' => 'cash', 'amount_toman' => $amount,
            'deposited_jdate' => Jalali::instance(Carbon::parse($date))->format('Y/m/d')]);
    }

    private function snapshot(): array
    {
        $this->file->refresh();
        $finance = app(LoanFileFinanceCalculator::class);

        return $finance->loanInstallmentFinancialSnapshot($this->file, $finance->totalRepayableToman($this->file));
    }

    private function loanEditRequest(array $changes = []): Request
    {
        $this->file->refresh();

        return Request::create('/', 'PUT', array_replace([
            'loan_start_jdate' => Jalali::instance($this->file->loan_start_date)->format('Y/m/d'),
            'loan_type_id' => $this->file->loan_type_id,
            'amount_toman' => $this->file->amount_toman,
            'installments_count' => $this->file->installments_count,
            'installment_interval_count' => $this->file->installment_interval_count,
            'installment_interval_unit' => $this->file->installment_interval_unit,
            'installment_amount_toman' => $this->file->installment_amount_toman,
            'down_payment_toman' => $this->file->down_payment_toman,
            'discount_amount_toman' => $this->file->discount_amount_toman,
            'has_custom_interest_rate' => $this->file->has_custom_interest_rate,
            'custom_interest_rate' => $this->file->custom_interest_rate,
            'has_custom_daily_late_coefficient' => false,
            'has_custom_daily_early_coefficient' => false,
        ], $changes));
    }

    public function test_default_contract_rates_survive_plan_changes_and_new_contracts_use_new_defaults(): void
    {
        $type = $this->file->loanType;
        $type->update(['daily_late_coefficient' => 0.009, 'daily_early_coefficient' => 0.003]);

        self::assertSame(0.005, $this->file->fresh()->dailyLateCoefficient());
        self::assertSame(0.0, $this->file->fresh()->dailyEarlyCoefficient());
        self::assertSame(90000, $this->snapshot()['late_fee_payable_toman']);

        $newFile = $this->file->replicate();
        $newFile->loan_code = 'NEW';
        $newFile->daily_late_coefficient = null;
        $newFile->daily_early_coefficient = null;
        $newFile->save();
        self::assertSame(0.009, $newFile->fresh()->dailyLateCoefficient());
        self::assertSame(0.003, $newFile->fresh()->dailyEarlyCoefficient());

        $zeroFile = $newFile->replicate();
        $zeroFile->loan_code = 'ZERO';
        $zeroFile->daily_late_coefficient = 0;
        $zeroFile->daily_early_coefficient = 0;
        $zeroFile->save();
        $type->update(['daily_late_coefficient' => 0.02, 'daily_early_coefficient' => 0.02]);
        self::assertSame(0.0, $zeroFile->fresh()->dailyLateCoefficient());
        self::assertSame(0.0, $zeroFile->fresh()->dailyEarlyCoefficient());
    }

    public function test_legacy_rate_migration_preserves_financial_records_and_already_saved_contract_rates(): void
    {
        DB::table('customer_loan_files')->where('id', $this->file->id)
            ->update(['daily_late_coefficient' => null, 'daily_early_coefficient' => null]);
        $before = $this->file->fresh()->getRawOriginal();
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $beforeBalance = $this->snapshot()['remaining_amount_toman'];
        $custom = $this->file->replicate();
        $custom->loan_code = 'CUSTOM';
        $custom->daily_late_coefficient = 0;
        $custom->daily_early_coefficient = 0.002;
        $custom->save();

        (require database_path('migrations/2026_11_02_130100_snapshot_existing_loan_file_daily_coefficients.php'))->up();

        $after = $this->file->fresh()->getRawOriginal();
        unset($before['daily_late_coefficient'], $before['daily_early_coefficient'], $after['daily_late_coefficient'], $after['daily_early_coefficient']);
        self::assertSame($before, $after);
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
        self::assertSame($beforeBalance, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(0.0, $custom->fresh()->dailyLateCoefficient());
        self::assertSame(0.002, $custom->fresh()->dailyEarlyCoefficient());

        $this->file->loanType->update(['daily_late_coefficient' => 0.01]);
        (require database_path('migrations/2026_11_02_130100_snapshot_existing_loan_file_daily_coefficients.php'))->up();
        self::assertSame($beforeBalance, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_custom_interest_recalculates_only_the_selected_contract_and_preserves_recorded_receipts(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(100000, '2026-07-20'), $this->customer, $this->file, $this->installment);
        $beforeReceipt = $this->installment->payments()->firstOrFail()->getRawOriginal();
        $other = $this->file->replicate();
        $other->loan_code = 'OTHER';
        $other->save();
        $beforeOther = $other->fresh()->getRawOriginal();
        $this->file->loanType->update(['interest_rate' => 25]);

        $response = app(CustomerController::class)->updateLoan($this->loanEditRequest([
            'has_custom_interest_rate' => true,
            'custom_interest_rate' => 10,
            'installment_amount_toman' => 990000,
        ]), $this->customer, $this->file);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame(10.0, (float) $this->file->fresh()->effective_interest_rate);
        self::assertSame(990000, app(LoanFileFinanceCalculator::class)->totalRepayableToman($this->file->fresh()));
        self::assertSame(990000, (int) $this->installment->fresh()->amount_toman);
        self::assertSame(100000, (int) $this->installment->fresh()->paid_amount_toman);
        self::assertSame($beforeReceipt, $this->installment->payments()->firstOrFail()->getRawOriginal());
        self::assertSame($beforeOther, $other->fresh()->getRawOriginal());
    }

    public function test_editing_contract_penalties_recalculates_receipts_without_rewriting_installments_or_other_loans(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $beforeReceipt = $this->installment->payments()->firstOrFail()->getRawOriginal();
        $other = $this->file->replicate();
        $other->loan_code = 'OTHER';
        $other->save();
        $beforeOther = $other->fresh()->getRawOriginal();
        $this->file->loanType->update(['interest_rate' => 25, 'daily_late_coefficient' => 0.02]);

        $response = app(CustomerController::class)->updateLoan($this->loanEditRequest([
            'has_custom_daily_late_coefficient' => true,
            'custom_daily_late_coefficient' => 0.01,
            'has_custom_daily_early_coefficient' => true,
            'custom_daily_early_coefficient' => 0.002,
        ]), $this->customer, $this->file);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame(60000, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(0.0, (float) $this->file->fresh()->effective_interest_rate);
        self::assertSame(0.002, $this->file->fresh()->dailyEarlyCoefficient());
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
        self::assertSame($beforeReceipt, $this->installment->payments()->firstOrFail()->getRawOriginal());
        self::assertSame($beforeOther, $other->fresh()->getRawOriginal());
    }

    public function test_unchecked_rate_controls_preserve_the_contract_and_legacy_inconsistent_schedule(): void
    {
        $this->file->update(['amount_toman' => 1800000]);
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $this->file->loanType->update(['interest_rate' => 25, 'daily_late_coefficient' => 0.02]);

        $response = app(CustomerController::class)->updateLoan($this->loanEditRequest(), $this->customer, $this->file);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame(0.005, $this->file->fresh()->dailyLateCoefficient());
        self::assertSame(0.0, (float) $this->file->fresh()->effective_interest_rate);
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
        self::assertFalse($this->snapshot()['requires_schedule_review']);
        self::assertTrue($this->snapshot()['has_contract_schedule_difference']);
    }

    public function test_older_edit_requests_preserve_custom_interest_when_rate_controls_are_omitted(): void
    {
        $this->file->update(['has_custom_interest_rate' => true, 'custom_interest_rate' => 10, 'effective_interest_rate' => 10]);
        $this->file->loanType->update(['interest_rate' => 25, 'installment_gap_unit' => 'weekly']);
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $request = $this->loanEditRequest();
        foreach (['has_custom_interest_rate', 'custom_interest_rate', 'has_custom_daily_late_coefficient', 'has_custom_daily_early_coefficient'] as $key) {
            $request->request->remove($key);
        }

        $response = app(CustomerController::class)->updateLoan($request, $this->customer, $this->file);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame(10.0, (float) $this->file->fresh()->effective_interest_rate);
        self::assertSame(0.005, $this->file->fresh()->dailyLateCoefficient());
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
    }

    public function test_short_payment_keeps_fee_in_all_balances_and_allows_payment_of_remainder(): void
    {
        $controller = app(CustomerController::class);
        $response = $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        self::assertSame(200, $response->getStatusCode());
        $snap = $this->snapshot();
        self::assertSame(15000, $snap['remaining_amount_toman']);
        self::assertSame(0, $snap['paid_installments_count']);
        self::assertFalse($this->file->is_settled);
        $summary = new \ReflectionMethod($controller, 'loanInstallmentsModalSummary');
        self::assertSame(15000, $summary->invoke($controller, $this->file, $snap)['remaining_amount_toman']);
        $quote = app(CustomerLoanPortalPresenter::class)->fullSettlementOnlinePaymentQuote($this->file);
        self::assertSame(['principal_toman' => 0, 'late_fee_toman' => 15000, 'amount_toman' => 15000], $quote);
        $scopes = (new \ReflectionMethod($controller, 'customerDebtScopeIds'))->invoke($controller);
        self::assertContains($this->customer->id, $scopes['debt']);
        self::assertContains($this->customer->id, $scopes['overdue']);
        $resolved = app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id);
        self::assertTrue($resolved['ok']);
        self::assertSame(15000, $resolved['amount_toman']);
        $dashboard = app(AdminDashboardStatisticsService::class);
        $overdue = (new \ReflectionMethod($dashboard, 'buildOverdueInstallmentStats'))->invoke($dashboard, Carbon::today());
        self::assertSame(['amount_toman' => 15000, 'count' => 1], $overdue);
        $response = $controller->storeLoanInstallmentPayment($this->request(15000, '2026-08-21'), $this->customer, $this->file, $this->installment->fresh());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(1, $this->snapshot()['paid_installments_count']);
    }

    public function test_receipt_ceiling_includes_late_fee_at_selected_date_and_edit_excludes_original(): void
    {
        $controller = app(CustomerController::class);
        self::assertSame(422, $controller->storeLoanInstallmentPayment($this->request(945001, '2026-08-11'), $this->customer, $this->file, $this->installment)->getStatusCode());
        self::assertSame(200, $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment)->getStatusCode());
        $payment = $this->installment->payments()->firstOrFail();
        $response = $controller->updateLoanInstallmentPayment($this->request(945000, '2026-08-11'), $this->customer, $this->file->fresh(), $this->installment->fresh(), $payment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $response->getData(true)['installment']['remaining_toman']);
        self::assertSame(0, $response->getData(true)['installment']['late_fee_remaining_toman']);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(1, $this->installment->payments()->count());
    }

    public function test_bulk_overdue_payment_includes_fees_when_principal_is_already_paid(): void
    {
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $service = app(AdminCustomerSettleAllOverdueService::class);
        $preview = $service->preview($this->customer);
        self::assertTrue($preview['settleable']);
        self::assertSame(0, $preview['principal_toman']);
        self::assertSame(15000, $preview['late_fee_toman']);
        self::assertSame(15000, $preview['amount_toman']);
        $result = $service->settle($this->customer, 'cash', Carbon::today(), null, 1);
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_full_settlement_records_fee_receipt_instead_of_only_marking_file_closed(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $quote = app(CustomerLoanPortalPresenter::class)->fullSettlementOnlinePaymentQuote($this->file->fresh());
        app(LoanFullSettlementOnlinePrincipalAllocator::class)->allocateDebtAcrossInstallments($this->file->fresh(), $quote['amount_toman'], 'TEST');
        self::assertSame(945000, (int) $this->installment->payments()->sum('amount_toman'));
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    private function shortPayment(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
    }

    public function test_portal_balance_progress_and_report_filters_include_fee_shortfall(): void
    {
        $this->shortPayment();
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertSame(15000, $portal['remaining_amount_toman']);
        self::assertFalse($portal['settled_for_ui']);
        self::assertLessThan(100, $portal['progress_percent']);
        self::assertSame(15000, $portal['installments'][0]['slot_remaining_toman']);
        self::assertSame(15000, $portal['installments'][0]['payment_ceiling_toman']);
        $reports = app(InstallmentDueDatesByDateReportService::class);
        $range = [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')];
        $partial = $reports->fetchRows(...[...$range, '', 'partial', 'yes']);
        self::assertCount(1, $partial);
        self::assertSame(15000, $partial[0]['remaining_amount_toman']);
        self::assertSame(15000, $reports->summarizeRows($partial)['unpaid_amount_total']);
        self::assertCount(0, $reports->fetchRows(...[...$range, '', 'paid', '']));
    }

    public function test_discount_is_applied_consistently_to_portal_report_and_payment_ceiling(): void
    {
        $this->shortPayment();
        $this->file->update(['discount_amount_toman' => 5000]);
        $snapshot = $this->snapshot();
        self::assertSame(10000, $snapshot['remaining_amount_toman']);
        self::assertSame(10000, $snapshot['installment_payables'][$this->installment->id]['remaining_toman']);
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertSame(10000, $portal['remaining_amount_toman']);
        self::assertSame(10000, $portal['late_fee_estimate_toman']);
        self::assertSame(10000, $portal['full_settlement_online_toman']);
        self::assertSame(10000, $portal['installments'][0]['slot_remaining_toman']);
        self::assertSame(10000, app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['amount_toman']);
        $dashboard = app(AdminDashboardStatisticsService::class);
        self::assertSame(['amount_toman' => 10000, 'count' => 1], (new \ReflectionMethod($dashboard, 'buildOverdueInstallmentStats'))->invoke($dashboard, Carbon::today()));
        $this->file->update(['discount_amount_toman' => 15000]);
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertTrue($portal['settled_for_ui']);
        self::assertSame(0, $portal['remaining_installments_count']);
        self::assertSame(0, $portal['installments'][0]['slot_remaining_toman']);
        self::assertFalse($portal['installments'][0]['wallet_pay_eligible']);
    }

    public function test_settled_installment_rejects_additional_backdated_receipt(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(945000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $extra = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(1000, '2026-08-10'), $this->customer, $this->file->fresh(), $this->installment->fresh());
        self::assertSame(422, $extra->getStatusCode());
        self::assertSame(1, $this->installment->payments()->count());
    }

    public function test_pending_deposit_does_not_pay_debt_until_admin_applies_it(): void
    {
        $this->shortPayment();
        $declaration = app(DepositDeclarationUserService::class)->create($this->customer, [
            'customer_loan_file_id' => $this->file->id,
            'customer_loan_installment_id' => $this->installment->id,
            'amount_toman' => 15000,
            'deposited_jdate' => Jalali::instance(Carbon::today())->format('Y/m/d'),
            'user_payment_method' => 'cash',
        ], null);
        self::assertSame(15000, $this->snapshot()['remaining_amount_toman']);
        $applied = app(DepositDeclarationAdminService::class)->review($declaration, Admin::findOrFail(1), 'approve_apply', null);
        self::assertSame(CustomerDepositDeclaration::STATUS_APPROVED_APPLIED, $applied->status);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_wallet_pays_fee_balance_once_and_preserves_replay_protection(): void
    {
        $this->shortPayment();
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 50000, 'is_locked' => false]);
        $wallet = app(PortalLoanWalletPaymentService::class);
        $key = '11111111-1111-4111-8111-111111111111';
        $result = $wallet->payInstallmentFromWallet($this->customer, $this->installment->id, $key);
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(15000, $result['amount_toman']);
        self::assertSame(35000, (int) $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        $replay = $wallet->payInstallmentFromWallet($this->customer, $this->installment->id, $key);
        self::assertTrue($replay['ok'], $replay['message']);
        self::assertSame(35000, (int) $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(2, $this->installment->payments()->count());
    }

    public function test_wallet_full_settlement_records_the_entire_fee_payment(): void
    {
        $this->shortPayment();
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 50000, 'is_locked' => false]);
        $result = app(PortalLoanWalletPaymentService::class)->payFullSettlementFromWallet($this->customer, $this->file->id, '22222222-2222-4222-8222-222222222222');
        self::assertTrue($result['ok'], $result['message']);
        self::assertTrue($this->file->fresh()->is_settled);
        self::assertSame(945000, (int) $this->installment->payments()->sum('amount_toman'));
        self::assertSame(35000, (int) $this->customer->wallet()->firstOrFail()->balance_toman);
    }

    public function test_stale_deposit_approval_cannot_apply_the_same_receipt_twice(): void
    {
        $this->shortPayment();
        $declaration = CustomerDepositDeclaration::create([
            'customer_id' => $this->customer->id, 'customer_loan_file_id' => $this->file->id,
            'customer_loan_installment_id' => $this->installment->id, 'deposited_at' => '2026-08-21',
            'amount_toman' => 10000, 'user_payment_method' => 'cash', 'status' => 'pending',
        ]);
        $staleCopy = $declaration->fresh();
        $service = app(DepositDeclarationAdminService::class);
        $service->review($declaration, Admin::findOrFail(1), 'approve_apply', null);
        try {
            $service->review($staleCopy, Admin::findOrFail(1), 'approve_apply', null);
            self::fail('A stale declaration must not be applied twice.');
        } catch (ValidationException) {
            self::assertSame(2, $this->installment->payments()->count());
            self::assertSame(5000, $this->snapshot()['remaining_amount_toman']);
        }
    }

    public function test_sorting_and_nearest_due_include_customers_with_fee_only_debt(): void
    {
        $this->shortPayment();
        $otherCustomer = Customer::create(['first_name' => 'Other', 'last_name' => 'Customer']);
        $otherFile = $this->file->replicate();
        $otherFile->customer_id = $otherCustomer->id;
        $otherFile->loan_code = 'OTHER';
        $otherFile->amount_toman = 10000;
        $otherFile->save();
        CustomerLoanInstallment::create(['customer_loan_file_id' => $otherFile->id, 'sequence' => 1, 'amount_toman' => 10000, 'due_date' => '2026-09-01']);
        $controller = app(CustomerController::class);
        $sort = new \ReflectionMethod($controller, 'applyCustomerListSort');
        foreach (['loan_remaining' => 'desc', 'nearest_due' => 'asc'] as $column => $direction) {
            $query = Customer::query();
            $sort->invoke($controller, $query, ['sort' => $column, 'dir' => $direction]);
            self::assertSame([$this->customer->id, $otherCustomer->id], $query->pluck('id')->all());
        }
    }

    public function test_fully_paid_loan_is_reported_consistently_without_manual_settlement_flag(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(945000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        self::assertFalse($this->file->fresh()->is_settled);
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-08-31');
        $members = app(MemberLoansByDateReportService::class);
        $paid = $members->fetchRows($from, $to, '', 'yes');
        self::assertCount(1, $paid);
        self::assertTrue($paid[0]['is_settled']);
        self::assertSame(0, $paid[0]['remaining_installments_count']);
        self::assertCount(0, $members->fetchRows($from, $to, '', 'no'));
        $interest = app(LoanInterestFeesReportService::class)->fetchResult($from, $to, '', null, 'yes');
        self::assertCount(1, $interest['rows']);
        self::assertSame(0, $interest['summary']['remaining_total']);
    }

    public function test_gateway_confirmation_applies_fee_only_installment_payment_once(): void
    {
        $this->shortPayment();
        Http::preventStrayRequests();
        Http::fake(['gateway.zibal.ir/v1/verify' => Http::response(['result' => 100, 'amount' => 150000, 'refNumber' => 'TEST-REF'])]);
        $intent = CustomerLoanInstallmentOnlinePaymentIntent::create([
            'customer_id' => $this->customer->id, 'customer_loan_installment_id' => $this->installment->id,
            'expected_amount_toman' => 15000, 'expected_amount_rial' => 150000, 'track_id' => 123456,
            'status' => 'redirected', 'gateway_key' => 'zibal',
        ]);
        $service = app(InstallmentOnlinePaymentCompletionService::class);
        $finalize = new \ReflectionMethod($service, 'finalizeInstallmentIntent');
        $response = DB::transaction(fn () => $finalize->invoke($service, $intent, 'test-merchant', 123456));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('completed', $intent->fresh()->status);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(2, $this->installment->payments()->count());
        DB::transaction(fn () => $finalize->invoke($service, $intent->fresh(), 'test-merchant', 123456));
        self::assertSame(2, $this->installment->payments()->count());
        Http::assertSentCount(1);
    }

    public function test_gateway_full_settlement_records_fee_receipt_before_closing_loan(): void
    {
        $this->shortPayment();
        Http::preventStrayRequests();
        Http::fake(['gateway.zibal.ir/v1/verify' => Http::response(['result' => 100, 'amount' => 150000, 'refNumber' => 'TEST-FULL'])]);
        $intent = CustomerLoanFullSettlementOnlinePaymentIntent::create([
            'customer_id' => $this->customer->id, 'customer_loan_file_id' => $this->file->id,
            'expected_amount_toman' => 15000, 'expected_amount_rial' => 150000, 'principal_component_toman' => 0,
            'late_fee_component_toman' => 15000, 'track_id' => 234567, 'status' => 'redirected', 'gateway_key' => 'zibal',
        ]);
        $service = app(InstallmentOnlinePaymentCompletionService::class);
        $response = DB::transaction(fn () => (new \ReflectionMethod($service, 'finalizeLoanFullSettlementIntent'))->invoke($service, $intent, 'test-merchant', 234567));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('completed', $intent->fresh()->status);
        self::assertTrue($this->file->fresh()->is_settled);
        self::assertSame(945000, (int) $this->installment->payments()->sum('amount_toman'));
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_last_overdue_installment_accepts_principal_plus_fees_and_keeps_unpaid_fees_open(): void
    {
        $this->file->update(['amount_toman' => 4800000, 'installments_count' => 3, 'installment_amount_toman' => 1600000, 'daily_late_coefficient' => 0.002667]);
        $this->installment->update(['amount_toman' => 1600000, 'due_date' => '2026-06-01']);
        $this->installment->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1604267, 'deposited_at' => '2026-06-02']);
        $second = $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 1600000, 'due_date' => '2026-07-01']);
        $second->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1612802, 'deposited_at' => '2026-07-04']);
        $last = $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 1600000, 'due_date' => '2026-08-01']);
        $finance = app(LoanFileFinanceCalculator::class);
        $expectedFee = 85344; // 1,600,000 * .002667 * 20 days.
        self::assertSame(1600000 + $expectedFee, $finance->installmentPaymentAllowanceToman($this->file->fresh(), $last));
        $resolved = app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $last->id);
        self::assertTrue($resolved['ok']);
        self::assertSame(1600000 + $expectedFee, $resolved['amount_toman']);
        $controller = app(CustomerController::class);
        $response = $controller->storeLoanInstallmentPayment($this->request(1600000, '2026-08-21'), $this->customer, $this->file->fresh(), $last);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expectedFee, $response->getData(true)['installment']['late_fee_remaining_toman']);
        self::assertFalse($this->snapshot()['settled_by_balance']);
        self::assertSame($expectedFee, $this->snapshot()['remaining_amount_toman']);
        $response = $controller->storeLoanInstallmentPayment($this->request($expectedFee, '2026-08-21'), $this->customer, $this->file->fresh(), $last->fresh());
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->snapshot()['settled_by_balance']);
    }

    public function test_dated_payment_preview_excludes_edited_receipt_and_never_writes_records(): void
    {
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $payment = $this->installment->payments()->firstOrFail();
        $before = $payment->getRawOriginal();
        $preview = Request::create('/', 'GET', ['preview' => 1, 'deposited_jdate' => Jalali::instance(Carbon::parse('2026-08-11'))->format('Y/m/d'), 'payment_id' => $payment->id]);
        $response = $controller->loanInstallmentPayments($preview, $this->customer, $this->file->fresh(), $this->installment->fresh());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(945000, $response->getData(true)['max_payment_toman']);
        self::assertSame(45000, $response->getData(true)['balance']['late_fee_remaining_toman']);
        self::assertSame($before, $payment->fresh()->getRawOriginal());
        self::assertSame(1, $this->installment->payments()->count());
        self::assertSame(930000, $this->installment->fresh()->paid_amount_toman);
    }

    public function test_last_installment_accepts_one_receipt_above_nominal_for_the_full_debt(): void
    {
        $this->file->update(['amount_toman' => 4800000, 'installments_count' => 3]);
        $this->installment->update(['amount_toman' => 1600000, 'due_date' => '2026-06-01']);
        $this->installment->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1600000, 'deposited_at' => '2026-06-01']);
        $second = $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 1600000, 'due_date' => '2026-07-01']);
        $second->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1600000, 'deposited_at' => '2026-07-01']);
        $last = $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 1600000, 'due_date' => '2026-08-01']);
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(1760000, '2026-08-21'), $this->customer, $this->file->fresh(), $last);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
        self::assertSame(1760000, $last->payments()->sum('amount_toman'));
    }

    public function test_deleting_receipts_returns_fresh_debt_even_when_the_loan_relations_were_loaded(): void
    {
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $payment = $this->installment->payments()->firstOrFail();
        $loadedFile = $this->file->fresh(['loanType', 'installments.payments']);
        $response = $controller->destroyLoanInstallmentPayment($this->customer, $loadedFile, $this->installment->fresh(), $payment);
        self::assertSame(990000, $response->getData(true)['installment']['remaining_toman']);
        self::assertSame(90000, $response->getData(true)['installment']['late_fee_remaining_toman']);
        $controller->storeLoanInstallmentPayment($this->request(930000, '2026-08-11'), $this->customer, $this->file->fresh(), $this->installment->fresh());
        $loadedFile = $this->file->fresh(['loanType', 'installments.payments']);
        $response = $controller->destroyAllLoanInstallmentPayments($this->customer, $loadedFile, $this->installment->fresh());
        self::assertSame(990000, $response->getData(true)['installment']['remaining_toman']);
        self::assertSame(90000, $response->getData(true)['installment']['late_fee_remaining_toman']);
    }

    public function test_preview_of_legacy_receipt_does_not_backfill_or_change_it(): void
    {
        $this->installment->update(['paid_amount_toman' => 930000, 'paid_at' => '2026-08-11']);
        $before = $this->installment->fresh()->getRawOriginal();
        $preview = Request::create('/', 'GET', ['preview' => 1, 'deposited_jdate' => Jalali::instance(Carbon::today())->format('Y/m/d')]);
        $response = app(CustomerController::class)->loanInstallmentPayments($preview, $this->customer, $this->file, $this->installment);
        self::assertSame(15000, $response->getData(true)['max_payment_toman']);
        self::assertSame(0, $this->installment->payments()->count());
        self::assertSame($before, $this->installment->fresh()->getRawOriginal());
    }

    public function test_payment_context_uses_stored_installments_when_the_contract_formula_differs(): void
    {
        $this->file->update(['amount_toman' => 800000]);
        $this->installment->update(['due_date' => '2026-09-01']);
        $before = $this->file->fresh()->getRawOriginal();
        $controller = app(CustomerController::class);
        $context = (new \ReflectionMethod($controller, 'mapLoanInstallmentPayContext'))->invoke($controller, $this->installment, $this->customer, $this->file->fresh());
        self::assertFalse($context['requires_schedule_review']);
        self::assertTrue($context['has_contract_schedule_difference']);
        self::assertSame(800000, $context['contract_total_toman']);
        self::assertSame(900000, $context['scheduled_total_toman']);
        self::assertSame(100000, $context['schedule_difference_toman']);
        self::assertSame(900000, $context['max_payment_toman']);
        self::assertSame(0, $context['late_fee_assessed_toman']);
        self::assertSame($before, $this->file->fresh()->getRawOriginal());
    }

    public function test_split_receipts_use_the_same_assessed_fee_in_portal_and_admin_history(): void
    {
        $controller = app(CustomerController::class);
        self::assertSame(200, $controller->storeLoanInstallmentPayment($this->request(450000, '2026-08-06'), $this->customer, $this->file, $this->installment)->getStatusCode());
        // Five days on 900,000 plus five days on the remaining 450,000.
        self::assertSame(200, $controller->storeLoanInstallmentPayment($this->request(483750, '2026-08-11'), $this->customer, $this->file->fresh(), $this->installment->fresh())->getStatusCode());
        $installment = $this->installment->fresh();
        self::assertSame(33750, app(LoanFileFinanceCalculator::class)->estimatePenaltyAtDateToman($installment, 0.005, Carbon::parse('2026-08-11')));
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0]['installments'][0];
        self::assertSame(422, $controller->storeLoanInstallmentPayment($this->request(1, '2026-08-21'), $this->customer, $this->file->fresh(), $installment)->getStatusCode());
        self::assertStringContainsString('۳۳,۷۵۰', $portal['early_late_cell_fa']);
        self::assertStringNotContainsString('۴۵,۰۰۰', $portal['early_late_cell_fa']);
    }

    public function test_contract_formula_difference_does_not_block_existing_installment_payments(): void
    {
        $this->file->update(['amount_toman' => 1800000]);
        $beforeFile = $this->file->fresh()->getRawOriginal();
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $snapshot = $this->snapshot();
        self::assertFalse($snapshot['requires_schedule_review']);
        self::assertTrue($snapshot['has_contract_schedule_difference']);
        self::assertSame(990000, $snapshot['remaining_amount_toman']);
        self::assertSame(900000, $snapshot['scheduled_total_toman']);
        $portal = app(CustomerLoanPortalPresenter::class);
        $row = $portal->forDashboard($this->customer)['loans'][0];
        self::assertNull($row['ribbon']);
        self::assertFalse($row['contract_locked']);
        self::assertTrue($row['show_settle_button']);
        self::assertSame(990000, $portal->fullSettlementOnlinePaymentQuote($this->file->fresh())['amount_toman']);
        self::assertTrue(app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['ok']);
        self::assertSame($beforeFile, $this->file->fresh()->getRawOriginal());
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
        self::assertSame(0, $this->installment->payments()->count());
    }

    private function roundedLegacyLastInstallment(): CustomerLoanInstallment
    {
        $this->file->update(['amount_toman' => 4408000, 'effective_interest_rate' => 3.02,
            'installments_count' => 3, 'installment_amount_toman' => 1600000, 'daily_late_coefficient' => 0.002667]);
        $this->installment->update(['amount_toman' => 1600000, 'due_date' => '2026-06-01', 'paid_amount_toman' => 1604267, 'paid_at' => '2026-06-02']);
        $this->installment->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1604267, 'deposited_at' => '2026-06-02']);
        $second = $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 1600000, 'due_date' => '2026-07-01', 'paid_amount_toman' => 1612802, 'paid_at' => '2026-07-04']);
        $second->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1612802, 'deposited_at' => '2026-07-04']);
        $last = $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 1625000, 'due_date' => '2026-09-01', 'paid_amount_toman' => 1607365, 'paid_at' => '2026-08-21']);
        $last->payments()->create(['payment_method' => 'cash', 'amount_toman' => 1607365, 'deposited_at' => '2026-08-21']);

        return $last;
    }

    public function test_rounded_legacy_final_balance_is_identical_in_admin_portal_reports_and_payment_limits(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        $beforeFile = $this->file->fresh()->getRawOriginal();
        $beforeLast = $last->fresh()->getRawOriginal();
        $snapshot = $this->snapshot();
        self::assertSame(17635, $snapshot['remaining_amount_toman']);
        self::assertSame(17635, array_sum(array_column($snapshot['installment_payables'], 'remaining_toman')));
        self::assertFalse($snapshot['settled_by_balance']);
        $controller = app(CustomerController::class);
        $context = (new \ReflectionMethod($controller, 'mapLoanInstallmentPayContext'))->invoke($controller, $last, $this->customer, $this->file->fresh());
        self::assertSame(17635, $context['remaining_toman']);
        self::assertSame(17635, $context['max_payment_toman']);
        $mapped = (new \ReflectionMethod($controller, 'mapLoanFile'))->invoke($controller, $this->file->fresh());
        self::assertSame(17635, $mapped['remaining_amount_toman']);
        self::assertSame(4825000, $mapped['total_repayable_toman']);
        $portal = app(CustomerLoanPortalPresenter::class);
        $row = $portal->forDashboard($this->customer)['loans'][0];
        self::assertSame(17635, $row['remaining_amount_toman']);
        self::assertSame(17635, $row['installments'][2]['payment_ceiling_toman']);
        self::assertFalse($row['contract_locked']);
        self::assertSame(17635, $portal->fullSettlementOnlinePaymentQuote($this->file->fresh())['amount_toman']);
        self::assertSame(17635, app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $last->id)['amount_toman']);
        foreach ([MemberLoansByDateReportService::class, LoanInterestFeesReportService::class] as $reportClass) {
            $report = app($reportClass);
            $reportRow = (new \ReflectionMethod($report, 'mapRow'))->invoke($report, $this->file->fresh());
            self::assertSame(17635, $reportRow['remaining_amount_toman']);
            self::assertSame(4825000, $reportRow['total_repayable_toman']);
        }
        self::assertSame($beforeFile, $this->file->fresh()->getRawOriginal());
        self::assertSame($beforeLast, $last->fresh()->getRawOriginal());
        self::assertSame(200, $controller->storeLoanInstallmentPayment($this->request(17635, '2026-08-21'), $this->customer, $this->file->fresh(), $last)->getStatusCode());
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
    }

    public function test_wallet_can_pay_the_last_balance_beyond_the_recomputed_contract_total(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 20000, 'is_locked' => false]);
        $result = app(PortalLoanWalletPaymentService::class)->payInstallmentFromWallet($this->customer, $last->id, '33333333-3333-4333-8333-333333333333');
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(17635, $result['amount_toman']);
        self::assertSame(2365, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_bulk_overdue_payment_pays_rounded_final_principal_and_its_late_fee(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        Carbon::setTestNow('2026-09-11 12:00:00');
        $bulk = app(AdminCustomerSettleAllOverdueService::class);
        $quote = $bulk->preview($this->customer);
        self::assertTrue($quote['settleable']);
        self::assertSame(17635, $quote['principal_toman']);
        self::assertSame(470, $quote['late_fee_toman']);
        self::assertSame(18105, $quote['amount_toman']);
        $result = $bulk->settle($this->customer, 'cash', Carbon::today(), null, (int) auth('admin')->id());
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(1625470, $last->payments()->sum('amount_toman'));
    }

    public function test_full_settlement_allocator_respects_the_same_discounted_installment_balances(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        $this->file->update(['discount_amount_toman' => 5000]);
        $quote = app(CustomerLoanPortalPresenter::class)->fullSettlementOnlinePaymentQuote($this->file->fresh());
        self::assertSame(12635, $quote['amount_toman']);
        app(LoanFullSettlementOnlinePrincipalAllocator::class)->allocateDebtAcrossInstallments($this->file->fresh(), 12635, 'TEST');
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(5000, $last->fresh()->principalRemainingToman());
    }

    public function test_admin_bulk_full_settlement_records_the_discounted_final_balance_before_closing(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        $this->file->update(['discount_amount_toman' => 5000]);
        $service = app(AdminCustomerSettleAllLoansService::class);
        self::assertSame(12635, $service->preview($this->customer)['amount_toman']);
        $result = $service->settle($this->customer, 'cash', Carbon::today(), null, (int) auth('admin')->id());
        self::assertTrue($result['ok'], $result['message']);
        self::assertTrue($this->file->fresh()->is_settled);
        self::assertSame(1620000, $last->payments()->sum('amount_toman'));
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_stale_full_settlement_quote_cannot_create_partial_receipts_or_close_the_loan(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        $before = $last->fresh()->getRawOriginal();
        try {
            app(LoanFullSettlementOnlinePrincipalAllocator::class)->allocateDebtAcrossInstallments($this->file->fresh(), 17634, 'STALE');
            self::fail('An incomplete settlement must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('تسویه', $exception->getMessage());
        }
        self::assertSame(1, $last->payments()->count());
        self::assertSame($before, $last->fresh()->getRawOriginal());
        self::assertSame(17635, $this->snapshot()['remaining_amount_toman']);
        self::assertFalse($this->file->fresh()->is_settled);
    }

    public function test_gateway_confirmation_can_collect_the_last_balance_beyond_the_contract_formula_once(): void
    {
        $last = $this->roundedLegacyLastInstallment();
        Http::preventStrayRequests();
        Http::fake(['gateway.zibal.ir/v1/verify' => Http::response(['result' => 100, 'amount' => 176350, 'refNumber' => 'TEST-FINAL'])]);
        $intent = CustomerLoanInstallmentOnlinePaymentIntent::create([
            'customer_id' => $this->customer->id, 'customer_loan_installment_id' => $last->id,
            'expected_amount_toman' => 17635, 'expected_amount_rial' => 176350, 'track_id' => 345678,
            'status' => 'redirected', 'gateway_key' => 'zibal',
        ]);
        $service = app(InstallmentOnlinePaymentCompletionService::class);
        $finalize = new \ReflectionMethod($service, 'finalizeInstallmentIntent');
        DB::transaction(fn () => $finalize->invoke($service, $intent, 'test-merchant', 345678));
        self::assertSame('completed', $intent->fresh()->status);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(1625000, $last->payments()->sum('amount_toman'));
        DB::transaction(fn () => $finalize->invoke($service, $intent->fresh(), 'test-merchant', 345678));
        self::assertSame(2, $last->payments()->count());
        Http::assertSentCount(1);
    }

    public function test_incomplete_schedule_still_blocks_automatic_settlement_without_rewriting_rows(): void
    {
        $this->file->update(['installments_count' => 2]);
        $before = $this->installment->fresh()->getRawOriginal();
        self::assertTrue($this->snapshot()['requires_schedule_review']);
        self::assertNull(app(CustomerLoanPortalPresenter::class)->fullSettlementOnlinePaymentQuote($this->file->fresh()));
        self::assertFalse(app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['ok']);
        self::assertSame($before, $this->installment->fresh()->getRawOriginal());
    }

    public function test_reaching_the_formula_total_does_not_close_a_larger_stored_installment(): void
    {
        $this->file->update(['amount_toman' => 800000]);
        $this->installment->update(['due_date' => '2026-09-01']);
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(800000, '2026-08-21'), $this->customer, $this->file, $this->installment);
        self::assertSame(200, $response->getStatusCode());
        $snapshot = $this->snapshot();
        self::assertSame(100000, $snapshot['remaining_amount_toman']);
        self::assertFalse($snapshot['settled_by_balance']);
        $row = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertFalse($row['settled_for_ui']);
        self::assertNull($row['ribbon']);
    }
}
