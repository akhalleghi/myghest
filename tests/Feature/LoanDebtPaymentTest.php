<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminCustomerNoteController;
use App\Http\Controllers\Admin\CustomerController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerAdminNote;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanFullSettlementOnlinePaymentIntent;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Models\CustomerWallet;
use App\Models\LoanType;
use App\Models\SmsLog;
use App\Services\Admin\AdminDashboardStatisticsService;
use App\Services\Admin\DashboardPlanningCharts;
use App\Services\Admin\Reports\CustomerFinancialReportService;
use App\Services\Admin\Reports\InstallmentDueDatesByDateReportService;
use App\Services\Admin\Reports\LoanInterestFeesReportService;
use App\Services\Admin\Reports\MemberLoansByDateReportService;
use App\Services\Customers\AdminCustomerSettleAllLoansService;
use App\Services\Customers\AdminCustomerSettleAllOverdueService;
use App\Services\Customers\CustomerAdminNoteService;
use App\Services\Deposits\DepositDeclarationAdminService;
use App\Services\Deposits\DepositDeclarationUserService;
use App\Services\Loans\CustomerLoanPortalPresenter;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanFullSettlementOnlinePrincipalAllocator;
use App\Services\Loans\LoanInstallmentPaidAmountSyncer;
use App\Services\Payment\InstallmentOnlinePaymentCompletionService;
use App\Services\Payment\InstallmentOnlinePaymentResolver;
use App\Services\Payment\PortalLoanWalletPaymentService;
use App\Services\Wallet\CustomerWalletService;
use App\Services\Wallet\InstallmentWalletRewardService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            $t->date('membership_at')->nullable();
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
            $t->boolean('overdue_rounding_applied')->default(false);
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
            '2026_11_05_220000_add_installment_wallet_rewards.php',
            '2026_11_09_215000_add_installment_late_fee_waivers.php',
            '2026_07_17_140000_create_customer_admin_notes_table.php',
            '2026_11_05_230000_add_note_scopes.php',
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

    public function test_early_payment_quote_and_receipt_close_the_same_net_debt_permanently(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->file->update(['daily_early_coefficient' => 0.01]);
        self::assertSame(810000, $this->snapshot()['remaining_amount_toman']);
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertSame(810000, $portal['installments'][0]['final_amount_toman']);
        self::assertSame(90000, $portal['installments'][0]['early_benefit_toman']);
        self::assertSame(810000, app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['amount_toman']);
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(810000, '2026-07-22'), $this->customer, $this->file->fresh(), $this->installment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $response->getData(true)['installment']['remaining_toman']);
        Carbon::setTestNow('2026-09-01');
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(90000, $this->snapshot()['early_benefit_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
    }

    public function test_partial_early_receipt_earns_only_its_share_and_remaining_principal_can_accrue_late_fees(): void
    {
        Carbon::setTestNow('2026-07-22');
        $this->file->update(['daily_early_coefficient' => 0.01]);
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(405000, '2026-07-22'), $this->customer, $this->file->fresh(), $this->installment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(405000, $this->snapshot()['remaining_amount_toman']);
        Carbon::setTestNow('2026-08-11');
        $snapshot = $this->snapshot();
        self::assertSame(45000, $snapshot['early_benefit_toman']);
        self::assertSame(23000, $snapshot['late_fee_payable_toman']);
        self::assertSame(473000, $snapshot['remaining_amount_toman']);
        self::assertSame(473000, array_sum(array_column($snapshot['installment_payables'], 'remaining_toman')));
    }

    public function test_early_rebate_and_manual_discount_are_applied_once_in_wallet_full_settlement(): void
    {
        Carbon::setTestNow('2026-07-22');
        $this->file->update(['daily_early_coefficient' => 0.01, 'discount_amount_toman' => 10000]);
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 900000, 'is_locked' => false]);
        self::assertSame(800000, $this->snapshot()['remaining_amount_toman']);
        $result = app(PortalLoanWalletPaymentService::class)->payFullSettlementFromWallet($this->customer, $this->file->id, '44444444-4444-4444-8444-444444444444');
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(800000, $this->installment->payments()->sum('amount_toman'));
        self::assertTrue($this->file->fresh()->is_settled);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_editing_or_deleting_an_early_receipt_recalculates_earned_rebate(): void
    {
        Carbon::setTestNow('2026-07-22');
        $this->file->update(['daily_early_coefficient' => 0.01]);
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(405000, '2026-07-22'), $this->customer, $this->file->fresh(), $this->installment);
        Carbon::setTestNow('2026-08-11');
        $payment = $this->installment->payments()->firstOrFail();
        $response = $controller->updateLoanInstallmentPayment($this->request(427500, '2026-07-27'), $this->customer, $this->file->fresh(), $this->installment->fresh(), $payment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(22500, $response->getData(true)['installment']['early_benefit_toman']);
        self::assertSame(472500, $this->snapshot()['remaining_amount_toman']);
        $controller->destroyLoanInstallmentPayment($this->customer, $this->file->fresh(), $this->installment->fresh(), $this->installment->payments()->firstOrFail());
        self::assertSame(0, $this->snapshot()['early_benefit_toman']);
        self::assertSame(945000, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_dashboard_collection_chart_uses_tehran_month_boundaries_and_actual_receipts_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Asia/Tehran'));
        foreach ([['2026-10-22', 15450000], ['2026-10-23', 1000000], ['2026-11-20', 3000000]] as [$date, $amount]) {
            $this->installment->payments()->create(['payment_method' => 'cash', 'amount_toman' => $amount, 'deposited_at' => $date]);
        }
        // The installment total and nominal amount must not be counted again.
        $this->installment->update(['paid_amount_toman' => 19450000]);
        $service = app(AdminDashboardStatisticsService::class);
        $buckets = (new \ReflectionMethod($service, 'lastTwelveJalaliMonthBuckets'))->invoke($service, Carbon::today());
        self::assertSame('2026-10-23', $buckets[11]['start']->toDateString());
        self::assertSame('2026-11-21', $buckets[11]['end']->toDateString());
        $charts = (new \ReflectionMethod($service, 'buildTwelveMonthCharts'))->invoke($service, Carbon::today());
        self::assertSame(1000000, $charts['installments']['series'][11]['value']);
        self::assertSame(1, $charts['installments']['series'][11]['receipt_count']);
        self::assertSame(15450000, $charts['installments']['series'][10]['value']);
        self::assertSame(1, $charts['installments']['series'][10]['receipt_count']);
    }

    public function test_planning_charts_group_unpaid_debt_and_membership_by_tehran_calendar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Asia/Tehran'));
        $this->customer->update(['membership_at' => '2026-10-23']);
        $this->file->update(['daily_late_coefficient' => 0, 'installments_count' => 4]);
        $this->installment->update(['due_date' => '2026-10-22']);
        $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 800000, 'due_date' => '2026-11-06']);
        $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 700000, 'due_date' => '2026-11-22']);
        $this->file->installments()->create(['sequence' => 4, 'amount_toman' => 600000, 'due_date' => '2026-08-01']);
        $charts = app(DashboardPlanningCharts::class)->build(Carbon::today());
        self::assertCount(12, $charts['upcoming']['series']);
        self::assertSame([900000, 800000, 700000], array_slice(array_column($charts['upcoming']['series'], 'value'), 0, 3));
        self::assertSame(2400000, array_sum(array_column($charts['upcoming']['series'], 'value')));
        self::assertSame(1, $charts['customers-monthly']['series'][11]['value']);
        self::assertSame(1, $charts['customers-yearly']['series'][11]['value']);
        self::assertSame(1500000, array_sum(array_column($charts['overdue']['monthly'], 'value')));
        self::assertSame(1500000, array_sum(array_column($charts['overdue']['yearly'], 'value')));
        self::assertSame(0, $charts['overdue']['monthly'][23]['value']);
    }

    private function enableRewards(float $percent = 3): void
    {
        app(InstallmentWalletRewardService::class)->saveSettings($this->customer, true, $percent);
    }

    public function test_reward_is_disabled_by_default_and_does_not_credit_historical_receipts_on_activation(): void
    {
        $controller = app(CustomerController::class);
        self::assertFalse($this->customer->fresh()->installment_reward_enabled);
        $controller->storeLoanInstallmentPayment($this->request(900000, '2026-08-01'), $this->customer, $this->file, $this->installment);
        self::assertSame(0, DB::table('installment_wallet_rewards')->count());
        $this->enableRewards();
        app(LoanInstallmentPaidAmountSyncer::class)->syncFromPaymentRows($this->installment->fresh());
        self::assertSame(0, DB::table('installment_wallet_rewards')->count());
        self::assertSame(0, CustomerWallet::count());
    }

    public function test_reward_credits_only_after_full_on_time_payment_once_and_uses_frozen_percent(): void
    {
        $this->enableRewards();
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(450000, '2026-07-31'), $this->customer, $this->file, $this->installment);
        self::assertSame(0, CustomerWallet::count());
        Bus::assertNotDispatched(\App\Jobs\SendInstallmentWalletRewardSmsJob::class);
        $response = $controller->storeLoanInstallmentPayment($this->request(450000, '2026-08-01'), $this->customer, $this->file->fresh(), $this->installment->fresh());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(1, DB::table('installment_wallet_rewards')->count());
        app(InstallmentWalletRewardService::class)->saveSettings($this->customer, true, 10);
        app(LoanInstallmentPaidAmountSyncer::class)->syncFromPaymentRows($this->installment->fresh());
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(1, DB::table('customer_wallet_transactions')->count());
        Bus::assertDispatchedTimes(\App\Jobs\SendInstallmentWalletRewardSmsJob::class, 1);
    }

    public function test_late_completion_and_legacy_import_never_earn_rewards(): void
    {
        $this->enableRewards();
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(904500, '2026-08-02'), $this->customer, $this->file, $this->installment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, DB::table('installment_wallet_rewards')->count());
        $this->installment->payments()->update(['payment_method' => 'legacy_import', 'deposited_at' => '2026-08-01']);
        app(LoanInstallmentPaidAmountSyncer::class)->syncFromPaymentRows($this->installment->fresh());
        self::assertSame(0, DB::table('installment_wallet_rewards')->count());
    }

    public function test_spent_reward_blocks_receipt_deletion_and_rolls_back_all_financial_changes(): void
    {
        $this->enableRewards();
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(900000, '2026-08-01'), $this->customer, $this->file, $this->installment);
        $payment = $this->installment->payments()->firstOrFail();
        $wallet = app(CustomerWalletService::class);
        $wallet->adjust($this->customer, 'withdraw', 27000, 'Test spending', null, null, null);
        try {
            $controller->destroyLoanInstallmentPayment($this->customer, $this->file->fresh(), $this->installment->fresh(), $payment);
            self::fail('Spent reward must block deletion.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('reward', $exception->errors());
        }
        self::assertSame(1, $this->installment->payments()->count());
        self::assertSame(900000, $this->installment->fresh()->paid_amount_toman);
        self::assertSame(27000, (int) DB::table('installment_wallet_rewards')->value('credited_amount_toman'));
        $wallet->adjust($this->customer, 'deposit', 27000, 'Test replenishment', null, null, null);
        $controller->destroyLoanInstallmentPayment($this->customer, $this->file->fresh(), $this->installment->fresh(), $this->installment->payments()->firstOrFail());
        self::assertSame(0, $this->installment->payments()->count());
        self::assertSame(0, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(0, (int) DB::table('installment_wallet_rewards')->value('credited_amount_toman'));
    }

    public function test_reward_from_wallet_payment_is_a_separate_credit_not_a_second_receipt(): void
    {
        Carbon::setTestNow('2026-08-01');
        $this->enableRewards();
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 900000, 'is_locked' => false]);
        $result = app(PortalLoanWalletPaymentService::class)->payInstallmentFromWallet($this->customer, $this->installment->id, '55555555-5555-4555-8555-555555555555');
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(900000, $this->installment->payments()->sum('amount_toman'));
        self::assertSame(1, $this->installment->payments()->count());
        self::assertSame(2, DB::table('customer_wallet_transactions')->count());
    }

    public function test_reward_gateway_replay_and_sms_sync_cannot_credit_twice(): void
    {
        Carbon::setTestNow('2026-08-01');
        $this->enableRewards();
        Http::preventStrayRequests();
        Http::fake(['gateway.zibal.ir/v1/verify' => Http::response(['result' => 100, 'amount' => 9000000, 'refNumber' => 'REWARD-TEST'])]);
        $intent = CustomerLoanInstallmentOnlinePaymentIntent::create([
            'customer_id' => $this->customer->id, 'customer_loan_installment_id' => $this->installment->id,
            'expected_amount_toman' => 900000, 'expected_amount_rial' => 9000000, 'track_id' => 456789,
            'status' => 'redirected', 'gateway_key' => 'zibal',
        ]);
        $service = app(InstallmentOnlinePaymentCompletionService::class);
        $finalize = new \ReflectionMethod($service, 'finalizeInstallmentIntent');
        DB::transaction(fn () => $finalize->invoke($service, $intent, 'test-merchant', 456789));
        DB::transaction(fn () => $finalize->invoke($service, $intent->fresh(), 'test-merchant', 456789));
        app(LoanInstallmentPaidAmountSyncer::class)->syncFromPaymentRows($this->installment->fresh(), false);
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(1, DB::table('customer_wallet_transactions')->count());
        Http::assertSentCount(1);
    }

    public function test_disabling_rewards_preserves_existing_credit_but_receipt_changes_still_reverse_it(): void
    {
        $this->enableRewards();
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(900000, '2026-08-01'), $this->customer, $this->file, $this->installment);
        app(InstallmentWalletRewardService::class)->saveSettings($this->customer, false, 3);
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
        $controller->destroyAllLoanInstallmentPayments($this->customer, $this->file->fresh(), $this->installment->fresh());
        self::assertSame(0, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(2, DB::table('customer_wallet_transactions')->count());
    }

    public function test_reward_is_reversed_when_a_full_receipt_is_edited_to_partial(): void
    {
        $this->enableRewards();
        $controller = app(CustomerController::class);
        $controller->storeLoanInstallmentPayment($this->request(900000, '2026-08-01'), $this->customer, $this->file, $this->installment);
        $payment = $this->installment->payments()->firstOrFail();
        $response = $controller->updateLoanInstallmentPayment($this->request(450000, '2026-08-01'), $this->customer, $this->file->fresh(), $this->installment->fresh(), $payment);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(450000, $payment->fresh()->amount_toman);
        self::assertSame(0, (int) DB::table('installment_wallet_rewards')->value('credited_amount_toman'));
    }

    public function test_reward_for_early_full_settlement_uses_nominal_installment_not_discounted_cash(): void
    {
        Carbon::setTestNow('2026-07-22');
        $this->file->update(['daily_early_coefficient' => 0.01]);
        $this->enableRewards();
        $service = app(AdminCustomerSettleAllLoansService::class);
        $result = $service->settle($this->customer, 'cash', Carbon::today(), null, (int) auth('admin')->id());
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(810000, $this->installment->payments()->sum('amount_toman'));
        self::assertSame(27000, $this->customer->wallet()->firstOrFail()->balance_toman);
    }

    public function test_scoped_notes_aggregate_without_changing_legacy_notes_or_crossing_customers(): void
    {
        $service = app(CustomerAdminNoteService::class);
        $controller = app(AdminCustomerNoteController::class);
        $service->create($this->customer, auth('admin')->user(), 'Legacy general note');
        $response = $controller->store(Request::create('/', 'POST', ['body' => 'Installment follow-up', 'loan_file_id' => $this->file->id, 'installment_id' => $this->installment->id]), $this->customer, $service);
        self::assertSame(201, $response->getStatusCode());
        self::assertStringContainsString('قسط 1', $response->getData(true)['note']['scope_label']);
        self::assertCount(2, $service->listForCustomer($this->customer, auth('admin')->user()));
        self::assertCount(1, $service->listForCustomer($this->customer, auth('admin')->user(), $this->file->id));
        self::assertCount(1, $service->listForCustomer($this->customer, auth('admin')->user(), $this->file->id, $this->installment->id));
        $other = Customer::create(['first_name' => 'Other', 'last_name' => 'Customer']);
        self::assertCount(0, $service->listForCustomer($other, auth('admin')->user()));
        try {
            $controller->store(Request::create('/', 'POST', ['body' => 'Invalid scope', 'loan_file_id' => $this->file->id]), $other, $service);
            self::fail('Cross-customer scope must be rejected.');
        } catch (ModelNotFoundException) {
            self::assertSame(2, CustomerAdminNote::count());
        }
    }

    public function test_customer_export_contains_wallet_credit_nearest_due_and_every_page(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_credit_ceiling_toman')->default(0);
        });
        Schema::create('customer_loan_guarantees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('type');
            $table->json('meta')->nullable();
        });
        $this->customer->update(['purchase_credit_ceiling_toman' => 2000000]);
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 123000, 'is_locked' => false]);
        for ($index = 0; $index < 150; $index++) {
            Customer::create(['first_name' => 'Additional', 'last_name' => (string) $index, 'customer_code' => 'C-'.$index]);
        }
        $response = app(CustomerController::class)->exportCustomersListExcel(Request::create('/', 'GET'));
        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();
        $decoded = mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        $lines = array_map(fn ($line) => explode("\t", $line), explode("\r\n", rtrim($decoded, "\r\n")));
        self::assertCount(152, $lines);
        $headers = array_shift($lines);
        foreach ($lines as $line) {
            self::assertCount(count($headers), $line);
        }
        $line = collect($lines)->first(fn ($cells) => $cells[0] === 'Test');
        self::assertSame('123000', $line[array_search('موجودی کیف پول (تومان)', $headers, true)]);
        self::assertSame('900000', $line[array_search('مجموع اصل وام‌های ثبت‌شده (تومان)', $headers, true)]);
        self::assertSame('1010000', $line[array_search('اعتبار باقیمانده (تومان)', $headers, true)]);
        self::assertSame(Jalali::instance($this->installment->due_date)->format('Y/m/d'), $line[array_search('نزدیک‌ترین سررسید', $headers, true)]);
    }

    public function test_financial_report_exports_have_matching_headers_and_explicit_debt_columns(): void
    {
        $this->shortPayment();
        foreach ([MemberLoansByDateReportService::class, LoanInterestFeesReportService::class] as $class) {
            $service = app($class);
            $row = (new \ReflectionMethod($service, 'mapRow'))->invoke($service, $this->file->fresh());
            $cells = $service->excelDataRow($row);
            self::assertCount(count($service->excelHeaderRow()), $cells);
            self::assertContains('شماره پرونده', $service->excelHeaderRow());
        }
        $service = app(InstallmentDueDatesByDateReportService::class);
        $installment = $this->installment->fresh(['loanFile.customer', 'loanFile.loanType']);
        $row = (new \ReflectionMethod($service, 'mapRow'))->invoke($service, $installment);
        $headers = $service->excelHeaderRow();
        $cells = $service->excelDataRow($row);
        self::assertCount(count($headers), $cells);
        self::assertSame('15000', $cells[array_search('مانده فعلی قسط (تومان)', $headers, true)]);
        self::assertSame('945000', $cells[array_search('مبلغ نهایی قسط (تومان)', $headers, true)]);
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

    private function createFinancialReportGuaranteesTable(): void
    {
        Schema::create('customer_loan_guarantees', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('loan_file_id');
            $t->string('type');
            $t->json('meta')->nullable();
        });
    }

    public function test_customer_financial_report_separates_period_activity_from_current_balances(): void
    {
        $this->createFinancialReportGuaranteesTable();
        $this->customer->update(['membership_at' => '2026-07-01']);
        $this->shortPayment();
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 123000]);
        DB::table('customer_loan_guarantees')->insert([
            ['customer_id' => $this->customer->id, 'loan_file_id' => $this->file->id, 'type' => 'other', 'meta' => json_encode(['amount_toman' => 100000])],
            ['customer_id' => $this->customer->id, 'loan_file_id' => $this->file->id, 'type' => 'other', 'meta' => json_encode(['amount_toman' => 50000, 'returned' => true])],
            ['customer_id' => $this->customer->id, 'loan_file_id' => $this->file->id, 'type' => 'org_self', 'meta' => '{}'],
        ]);
        $service = app(CustomerFinancialReportService::class);
        $result = $service->fetchResult(Carbon::parse('2026-08-10'), Carbon::parse('2026-08-12')->endOfDay());
        $row = $result['rows'][0];
        self::assertSame('قدیمی', $row['customer_group']);
        self::assertSame(0, $row['principal_toman']);
        self::assertSame(0, $row['profit_toman']);
        self::assertSame(930000, $row['paid_toman']);
        self::assertSame(9000, $row['late_assessed_toman']);
        self::assertSame(30000, $row['late_paid_toman']);
        self::assertSame(15000, $row['remaining_toman']);
        self::assertSame(15000, $row['late_remaining_toman']);
        self::assertSame(1, $row['current_count']);
        self::assertSame(0, $row['settled_count']);
        self::assertSame(123000, $row['wallet_toman']);
        self::assertSame(100000, $row['guarantee_toman']);
        self::assertSame(1, $row['unvalued_guarantees_count']);
        self::assertSame($row['paid_toman'], $result['summary']['paid_toman']);
        self::assertCount(count($service->columns()), $service->excelDataRow($row));
    }

    public function test_customer_financial_report_membership_groups_dates_and_legacy_receipts(): void
    {
        $this->createFinancialReportGuaranteesTable();
        $this->customer->update(['membership_at' => '2026-07-01']);
        Customer::create(['first_name' => 'New', 'last_name' => 'Member', 'membership_at' => '2026-07-15']);
        Customer::create(['first_name' => 'Future', 'last_name' => 'Member', 'membership_at' => '2026-09-01']);
        $this->file->update(['effective_interest_rate' => 10]);
        $this->installment->update(['paid_amount_toman' => 20000, 'paid_at' => '2026-07-15']);
        $service = app(CustomerFinancialReportService::class);
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-31')->endOfDay();
        $new = $service->fetchResult($from, $to, 'new');
        self::assertCount(2, $new['rows']);
        self::assertSame(900000, $new['summary']['principal_toman']);
        self::assertSame(90000, $new['summary']['profit_toman']);
        self::assertSame(20000, $new['summary']['paid_toman']);
        self::assertSame(1, $new['summary']['loans_count']);
        self::assertCount(0, $service->fetchResult($from, $to, 'old')['rows']);
        self::assertCount(1, $service->fetchResult($from, $to, 'all', 'New')['rows']);
        $this->file->update(['revoked_at' => now()]);
        $revoked = $service->fetchResult($from, $to);
        self::assertSame(0, $revoked['summary']['principal_toman']);
        self::assertSame(0, $revoked['summary']['paid_toman']);
        self::assertSame(0, $revoked['summary']['remaining_toman']);
    }

    public function test_customer_financial_report_counts_only_realized_early_benefit_in_period(): void
    {
        $this->createFinancialReportGuaranteesTable();
        $this->customer->update(['membership_at' => '2026-07-01']);
        Carbon::setTestNow('2026-07-22');
        $this->file->update(['daily_early_coefficient' => 0.01]);
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(810000, '2026-07-22'), $this->customer, $this->file->fresh(), $this->installment);
        $service = app(CustomerFinancialReportService::class);
        $before = $service->fetchResult(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-21')->endOfDay());
        self::assertSame(0, $before['summary']['early_earned_toman']);
        $paid = $service->fetchResult(Carbon::parse('2026-07-22'), Carbon::parse('2026-07-22')->endOfDay());
        self::assertSame(90000, $paid['summary']['early_earned_toman']);
        self::assertSame(810000, $paid['summary']['paid_toman']);
        self::assertSame(1, $paid['summary']['settled_count']);
        self::assertSame(0, $paid['summary']['current_count']);
        self::assertSame(0, $paid['summary']['remaining_toman']);
    }

    public function test_customer_financial_data_and_excel_share_filters_and_totals(): void
    {
        $this->createFinancialReportGuaranteesTable();
        $this->customer->update(['membership_at' => '2026-07-01']);
        $this->shortPayment();
        $params = [
            'from_jdate' => Jalali::instance(Carbon::parse('2026-08-10'))->format('Y/m/d'),
            'to_jdate' => Jalali::instance(Carbon::parse('2026-08-12'))->format('Y/m/d'),
            'group' => 'old',
            'q' => 'Test',
        ];
        $controller = app(\App\Http\Controllers\Admin\AdminReportsController::class);
        $data = $controller->customerFinancialData(Request::create('/', 'GET', $params))->getData(true);
        self::assertSame(1, $data['meta']['count']);
        self::assertSame(930000, $data['summary']['paid_toman']);
        ob_start();
        $controller->exportCustomerFinancialExcel(Request::create('/', 'GET', $params))->sendContent();
        $bytes = ob_get_clean();
        $lines = explode("\r\n", rtrim(mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE'), "\r\n"));
        self::assertCount(5, $lines);
        $headers = explode("\t", $lines[2]);
        $row = explode("\t", $lines[3]);
        $total = explode("\t", $lines[4]);
        self::assertCount(count($headers), $row);
        self::assertCount(count($headers), $total);
        self::assertSame('930000', $row[array_search('واریزی اقساط در بازه (تومان)', $headers)]);
        self::assertSame('930000', $total[array_search('واریزی اقساط در بازه (تومان)', $headers)]);
        $params['from_jdate'] = $params['to_jdate'];
        $params['to_jdate'] = '1400/01/01';
        $this->expectException(ValidationException::class);
        $controller->customerFinancialData(Request::create('/', 'GET', $params));
    }

    public function test_customer_sms_date_range_includes_boundaries_and_matches_excel(): void
    {
        (require database_path('migrations/2026_05_06_000003_create_sms_logs_table.php'))->up();
        $this->customer->update(['mobile' => '09123456789']);
        foreach ([
            ['2026-08-09 23:59:59', '09123456789', 'before'],
            ['2026-08-10 00:00:00', '09123456789', 'first'],
            ['2026-08-12 23:59:59', '+989123456789', 'last'],
            ['2026-08-13 00:00:00', '09123456789', 'after'],
            ['2026-08-11 12:00:00', '09999999999', 'other'],
        ] as [$date, $recipient, $message]) {
            SmsLog::create([
                'sent_at' => $date, 'recipient' => $recipient, 'message_text' => $message,
                'sms_panel' => 'test', 'status' => 'delivered', 'type' => 'test', 'cost' => 1,
            ]);
        }
        $params = [
            'from_jdate' => Jalali::instance(Carbon::parse('2026-08-10'))->format('Y/m/d'),
            'to_jdate' => Jalali::instance(Carbon::parse('2026-08-12'))->format('Y/m/d'),
        ];
        $controller = app(CustomerController::class);
        $result = $controller->customerSmsLogs(Request::create('/', 'GET', $params), $this->customer)->getData(true);
        self::assertSame(['last', 'first'], array_column($result['logs'], 'message_text'));
        self::assertSame($params['from_jdate'], $result['from_jdate']);
        self::assertSame($params['to_jdate'], $result['to_jdate']);
        ob_start();
        $controller->exportCustomerSmsLogsExcel(Request::create('/', 'GET', $params), $this->customer)->sendContent();
        $bytes = ob_get_clean();
        $lines = explode("\r\n", rtrim(mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE'), "\r\n"));
        self::assertCount(3, $lines);
        self::assertSame('last', explode("\t", $lines[1])[3]);
        self::assertSame('first', explode("\t", $lines[2])[3]);
        $day = $controller->customerSmsLogs(Request::create('/', 'GET', ['date' => '2026-08-10']), $this->customer)->getData(true);
        self::assertSame(['first'], array_column($day['logs'], 'message_text'));
        self::assertSame('2026-08-10', $day['date']);
        $this->customer->update(['mobile' => null]);
        $empty = $controller->customerSmsLogs(Request::create('/', 'GET', $params), $this->customer)->getData(true);
        self::assertFalse($empty['has_mobile']);
        self::assertSame([], $empty['logs']);
    }

    public function test_customer_sms_date_range_rejects_reversed_dates(): void
    {
        $this->expectException(ValidationException::class);
        app(CustomerController::class)->customerSmsLogs(Request::create('/', 'GET', [
            'from_jdate' => '1405/08/10', 'to_jdate' => '1405/08/01',
        ]), $this->customer);
    }

    public function test_system_statistics_include_five_current_jalali_month_indicators(): void
    {
        $today = Carbon::today();
        $start = Carbon::createFromTimestamp(Jalali::instance($today)->clone()->startMonth()->getTimestamp(), $today->getTimezone())->startOfDay();
        $this->customer->update(['membership_at' => $start]);
        Customer::create(['first_name' => 'Old', 'last_name' => 'Member', 'membership_at' => $start->copy()->subDay()]);
        Customer::create(['first_name' => 'Fallback', 'last_name' => 'Member']);
        $this->file->update(['loan_start_date' => $start]);
        $this->shortPayment();
        $this->installment->payments()->create([
            'payment_method' => 'cash', 'amount_toman' => 7000, 'deposited_at' => $today->copy()->addDay(),
        ]);
        $future = $this->file->replicate();
        $future->loan_code = 'FUTURE';
        $future->loan_start_date = $today->copy()->addDay();
        $future->created_at = $today->copy()->addDay();
        $future->save();
        $revoked = $this->file->replicate();
        $revoked->loan_code = 'REVOKED';
        $revoked->revoked_at = now();
        $revoked->save();
        $service = app(AdminDashboardStatisticsService::class);
        $rows = (new \ReflectionMethod($service, 'buildSystemStats'))->invoke(
            $service, CustomerLoanFile::whereNull('revoked_at')->with(['loanType', 'installments.payments'])->get()
        );
        $stats = array_column($rows, 'value', 'label');
        self::assertSame(Jalali::enToFaNumbers('2'), $stats['تعداد مشتریان جدید ماه جاری']);
        self::assertSame(Jalali::enToFaNumbers('1'), $stats['تعداد پرونده وام ایجادشده ماه جاری']);
        self::assertSame(Jalali::enToFaNumbers('930,000').' تومان', $stats['مجموع اقساط وصول‌شده ماه جاری']);
        self::assertSame(Jalali::enToFaNumbers('15,000').' تومان', $stats['مجموع اقساط وصول‌نشده ماه جاری']);
        self::assertSame(Jalali::enToFaNumbers('900,000').' تومان', $stats['مبلغ کل وام‌های پرداختی ماه جاری']);
        self::assertCount(18, $rows);
    }

    public function test_overdue_rounding_is_shared_by_admin_portal_sms_reports_and_settlement(): void
    {
        $this->file->update(['daily_late_coefficient' => 0]);
        $this->installment->update(['amount_toman' => 2026670]);
        self::assertSame(2027000, $this->snapshot()['remaining_amount_toman']);
        $portal = app(CustomerLoanPortalPresenter::class);
        self::assertSame(2027000, $portal->forDashboard($this->customer)['loans'][0]['installments'][0]['final_amount_toman']);
        self::assertSame(2027000, $portal->fullSettlementOnlinePaymentQuote($this->file->fresh())['amount_toman']);
        self::assertSame(2027000, app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['amount_toman']);
        self::assertSame(2027000, app(AdminCustomerSettleAllOverdueService::class)->preview($this->customer)['amount_toman']);
        $vars = app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class)->build($this->customer, $this->file->fresh(), $this->installment->fresh(), null, 'overdue');
        self::assertSame('2,027,000 تومان', $vars['installment_unpaid_amount']);
        $service = app(InstallmentDueDatesByDateReportService::class);
        $row = (new \ReflectionMethod($service, 'mapRow'))->invoke($service, $this->installment->fresh(['loanFile.customer', 'loanFile.loanType']));
        self::assertSame(2027000, $row['final_amount_toman']);
        // Paying only the old raw amount leaves the rounding charge outstanding.
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(2026670, '2026-08-21'), $this->customer, $this->file->fresh(), $this->installment->fresh());
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->installment->payments()->firstOrFail()->overdue_rounding_applied);
        self::assertSame(330, $this->snapshot()['remaining_amount_toman']);
        self::assertFalse($this->snapshot()['settled_by_balance']);
        app(LoanFullSettlementOnlinePrincipalAllocator::class)->allocateDebtAcrossInstallments($this->file->fresh(), 330, 'ROUND');
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
        self::assertSame(2027000, (int) $this->installment->payments()->sum('amount_toman'));
    }

    public function test_overdue_rounding_preserves_old_completed_receipts_and_does_not_round_on_time_debt(): void
    {
        $this->file->update(['daily_late_coefficient' => 0]);
        $this->installment->update(['amount_toman' => 2026670, 'due_date' => '2026-08-21']);
        self::assertSame(2026670, $this->snapshot()['remaining_amount_toman']);
        $this->installment->update(['due_date' => '2026-08-01']);
        $this->installment->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 2026670, 'deposited_at' => '2026-08-11']);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(0, $this->snapshot()['installment_balances'][$this->installment->id]['rounding_adjustment_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
    }

    public function test_wallet_collects_rounded_overdue_total_and_preserves_receipt_amount(): void
    {
        $this->file->update(['daily_late_coefficient' => 0]);
        $this->installment->update(['amount_toman' => 2026670]);
        CustomerWallet::create(['customer_id' => $this->customer->id, 'balance_toman' => 2030000, 'is_locked' => false]);
        $result = app(PortalLoanWalletPaymentService::class)->payInstallmentFromWallet($this->customer, $this->installment->id, '44444444-4444-4444-8444-444444444444');
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(2027000, $result['amount_toman']);
        self::assertSame(3000, $this->customer->wallet()->firstOrFail()->balance_toman);
        self::assertSame(2027000, (int) $this->installment->payments()->sum('amount_toman'));
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
    }

    public function test_member_loan_report_filters_one_or_multiple_loan_types(): void
    {
        $otherType = LoanType::create(['title' => 'Other', 'daily_late_coefficient' => 0]);
        $other = $this->file->replicate();
        $other->loan_code = 'OTHER';
        $other->loan_type_id = $otherType->id;
        $other->save();
        $service = app(MemberLoansByDateReportService::class);
        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-12-31');
        $rows = $service->fetchRows($from, $to, '', '', [$this->file->loan_type_id]);
        self::assertCount(1, $rows);
        self::assertSame($this->file->loan_type_id, $rows[0]['loan_type_id']);
        self::assertSame(900000, $rows[0]['principal_toman']);
        self::assertCount(2, $service->fetchRows($from, $to, '', '', [$this->file->loan_type_id, $otherType->id]));
        self::assertCount(0, $service->fetchRows($from, $to, 'missing', '', [$otherType->id]));
    }

    public function test_sms_loan_code_pattern_uses_the_selected_file_for_customer_with_multiple_loans(): void
    {
        $controller = app(\App\Http\Controllers\Admin\SmsManagementController::class);
        $patterns = (new \ReflectionMethod($controller, 'templatePatterns'))->invoke($controller);
        self::assertArrayHasKey('loan_code', $patterns);
        $other = $this->file->replicate();
        $other->loan_code = 'DS 96';
        $other->save();
        $slot = $other->installments()->create(['sequence' => 1, 'amount_toman' => 900000, 'due_date' => '2026-09-01']);
        $builder = app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class);
        $renderer = app(\App\Services\Sms\SmsTemplateRenderer::class);
        $first = $builder->build($this->customer, $this->file->fresh(), $this->installment->fresh());
        $second = $builder->build($this->customer, $other->fresh(), $slot);
        self::assertSame('TEST', $renderer->render('{{loan_code}}', $first));
        self::assertSame('DS 96', $renderer->render('{{loan_code}}', $second));
    }

    public function test_member_loan_date_range_accepts_persian_dates_and_includes_both_boundary_days(): void
    {
        $service = app(MemberLoansByDateReportService::class);
        $range = $service->resolveDateRange('۱۴۰۵/۰۷/۰۱', '۱۴۰۵/۰۸/۱۶');
        self::assertSame('2026-09-23', $range['from']->toDateString());
        self::assertSame('2026-11-07', $range['to']->toDateString());
        foreach (['2026-09-22', '2026-09-23', '2026-11-07', '2026-11-08'] as $date) {
            $file = $this->file->replicate();
            $file->loan_code = $date;
            $file->loan_start_date = $date;
            $file->save();
            DB::table('customer_loan_files')->where('id', $file->id)->update(['loan_start_date' => $date.' 18:30:00']);
        }
        $rows = $service->fetchRows($range['from'], $range['to']);
        self::assertSame(['2026-11-07', '2026-09-23'], array_column($rows, 'loan_code'));
        self::assertCount(1, $service->fetchRows($range['from'], $range['to'], '2026-11-07'));
        self::assertSame(1800000, array_sum(array_column($rows, 'principal_toman')));
    }

    public function test_member_loan_report_rejects_invalid_dates_instead_of_using_default_range(): void
    {
        $this->expectException(ValidationException::class);
        app(MemberLoansByDateReportService::class)->resolveDateRange('invalid', '1405/08/16');
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
        $this->installment->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 1604267, 'deposited_at' => '2026-06-02']);
        $second = $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 1600000, 'due_date' => '2026-07-01']);
        $second->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 1612802, 'deposited_at' => '2026-07-04']);
        $last = $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 1600000, 'due_date' => '2026-08-01']);
        $finance = app(LoanFileFinanceCalculator::class);
        $expectedFee = 86000; // Raw charge 85,344 plus rounding to a whole 1,000.
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
        self::assertSame(200, $controller->storeLoanInstallmentPayment($this->request(484000, '2026-08-11'), $this->customer, $this->file->fresh(), $this->installment->fresh())->getStatusCode());
        $installment = $this->installment->fresh();
        self::assertSame(34000, app(LoanFileFinanceCalculator::class)->estimatePenaltyAtDateToman($installment, 0.005, Carbon::parse('2026-08-11')));
        $portal = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0]['installments'][0];
        self::assertSame(422, $controller->storeLoanInstallmentPayment($this->request(1, '2026-08-21'), $this->customer, $this->file->fresh(), $installment)->getStatusCode());
        self::assertStringContainsString('۳۴,۰۰۰', $portal['early_late_cell_fa']);
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
        $this->installment->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 1604267, 'deposited_at' => '2026-06-02']);
        $second = $this->file->installments()->create(['sequence' => 2, 'amount_toman' => 1600000, 'due_date' => '2026-07-01', 'paid_amount_toman' => 1612802, 'paid_at' => '2026-07-04']);
        $second->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 1612802, 'deposited_at' => '2026-07-04']);
        $last = $this->file->installments()->create(['sequence' => 3, 'amount_toman' => 1625000, 'due_date' => '2026-09-01', 'paid_amount_toman' => 1607365, 'paid_at' => '2026-08-21']);
        $last->payments()->createQuietly(['payment_method' => 'cash', 'amount_toman' => 1607365, 'deposited_at' => '2026-08-21']);

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
        self::assertSame(1000, $quote['late_fee_toman']);
        self::assertSame(18635, $quote['amount_toman']);
        $result = $bulk->settle($this->customer, 'cash', Carbon::today(), null, (int) auth('admin')->id());
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertSame(1626000, $last->payments()->sum('amount_toman'));
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
    public function test_overdue_total_sms_rounds_only_the_aggregate_up_to_two_thousand(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01'));
        $this->file->update(['daily_late_coefficient' => 0, 'daily_early_coefficient' => 0]);
        $this->installment->update(['paid_amount_toman' => 1]);
        $customer = $this->customer->fresh();
        $customer->load(['loanFiles.installments.payments', 'loanFiles.loanType']);
        $controller = app(CustomerController::class);
        $summaryMethod = new \ReflectionMethod($controller, 'resolveCustomerOverdueSummary');
        $summary = $summaryMethod->invoke($controller, $customer);
        $before = $summary['total_toman'];
        $vars = (new \ReflectionMethod($controller, 'customerOverdueTotalSmsVars'))->invoke($controller, $customer);
        self::assertSame(number_format(intdiv($before + 1999, 2000) * 2000).' تومان', $vars['installment_amount']);
        self::assertSame($vars['installment_amount'], $vars['remaining_loan']);
        self::assertSame('1', $vars['overdue_installment_count']);
        self::assertSame($before, $summaryMethod->invoke($controller, $customer)['total_toman']);
    }

    public function test_reward_notification_is_deferred_until_commit_and_cancelled_on_rollback(): void
    {
        $this->enableRewards();
        DB::beginTransaction();
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(900000, '2026-08-01'), $this->customer, $this->file, $this->installment);
        Bus::assertNotDispatched(\App\Jobs\SendInstallmentWalletRewardSmsJob::class);
        DB::rollBack();
        Bus::assertNotDispatched(\App\Jobs\SendInstallmentWalletRewardSmsJob::class);
        self::assertSame(0, CustomerWallet::count());
    }

    public function test_late_fee_waiver_is_shared_by_admin_portal_reports_sms_and_settlement(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(910000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        self::assertSame(35000, $this->snapshot()['late_fee_payable_toman']);
        $waivers = app(\App\Services\Loans\InstallmentLateFeeWaiverService::class);
        $before = $waivers->preview($this->file->fresh(), $this->installment->fresh());
        $waivers->reduce($this->customer->id, $this->file->id, $this->installment->id, 1234, $before['version'], 'بخشودگی آزمایشی', auth('admin')->id());
        self::assertSame(1234, $this->snapshot()['remaining_amount_toman']);
        $portal = app(CustomerLoanPortalPresenter::class);
        self::assertSame(1234, $portal->forDashboard($this->customer)['loans'][0]['installments'][0]['slot_remaining_toman']);
        self::assertSame(1234, $portal->fullSettlementOnlinePaymentQuote($this->file->fresh())['amount_toman']);
        self::assertSame(1234, app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['amount_toman']);
        self::assertSame(1234, app(AdminCustomerSettleAllOverdueService::class)->preview($this->customer)['amount_toman']);
        $vars = app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class)->build($this->customer, $this->file->fresh(), $this->installment->fresh(), null, 'installment_overdue');
        self::assertSame('1,234 تومان', $vars['installment_unpaid_amount']);
        $aggregate = app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class)->buildOverdueTotal($this->customer);
        self::assertSame('1234', $aggregate['overdue_total_toman']);
        self::assertSame('2,000 تومان', $aggregate['installment_amount']);
        $service = app(InstallmentDueDatesByDateReportService::class);
        $row = (new \ReflectionMethod($service, 'mapRow'))->invoke($service, $this->installment->fresh(['loanFile.customer', 'loanFile.loanType']));
        self::assertSame(1234, $row['remaining_amount_toman']);
        self::assertSame(911234, $row['final_amount_toman']);
        $before = $waivers->preview($this->file->fresh(), $this->installment->fresh());
        $waivers->reduce($this->customer->id, $this->file->id, $this->installment->id, 0, $before['version'], 'بخشودگی کامل', auth('admin')->id());
        self::assertSame(0, $this->snapshot()['remaining_amount_toman']);
        self::assertTrue($this->snapshot()['settled_by_balance']);
        $portalCell = $portal->forDashboard($this->customer)['loans'][0]['installments'][0]['early_late_cell_fa'];
        self::assertStringContainsString(Jalali::enToFaNumbers('10,000'), $portalCell);
        self::assertSame(0, array_sum(array_column(app(DashboardPlanningCharts::class)->build(Carbon::today())['upcoming']['series'], 'value')));
        self::assertSame(0, app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class)->buildOverdueTotal($this->customer)['overdue_installment_count'] + 0);
    }

    public function test_schedule_edit_preserves_waivers_even_after_receipts_are_deleted(): void
    {
        app(CustomerController::class)->storeLoanInstallmentPayment($this->request(910000, '2026-08-11'), $this->customer, $this->file, $this->installment);
        $waivers = app(\App\Services\Loans\InstallmentLateFeeWaiverService::class);
        $before = $waivers->preview($this->file->fresh(), $this->installment->fresh());
        $waivers->reduce($this->customer->id, $this->file->id, $this->installment->id, 0, $before['version'], 'بخشودگی', auth('admin')->id());
        $this->installment->payments()->delete();
        $this->installment->update(['paid_amount_toman' => 0, 'paid_at' => null]);
        app(\App\Services\Loans\LoanInstallmentScheduleService::class)->syncScheduleFromLoanFile($this->file->fresh(), 900000);
        self::assertSame($this->installment->id, $this->file->installments()->firstOrFail()->id);
        self::assertSame(35000, $this->installment->fresh()->late_fee_waived_toman);
        self::assertSame(900000, $this->snapshot()['installment_balances'][$this->installment->id]['principal_remaining_toman']);
        self::assertSame(1, DB::table('installment_late_fee_waivers')->count());
    }

}
