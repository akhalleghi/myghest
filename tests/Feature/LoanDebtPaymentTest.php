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
            $t->decimal('daily_late_coefficient', 12, 8);
            $t->timestamps();
        });
        Schema::create('customer_loan_files', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('loan_type_id');
            $t->string('loan_code');
            $t->date('loan_start_date');
            $t->unsignedBigInteger('amount_toman');
            $t->integer('installments_count');
            $t->integer('installment_interval_count');
            $t->string('installment_interval_unit');
            $t->unsignedBigInteger('installment_amount_toman');
            $t->decimal('effective_interest_rate', 6, 2)->default(0);
            $t->string('profit_calculation_method');
            $t->unsignedBigInteger('down_payment_toman')->default(0);
            $t->unsignedBigInteger('discount_amount_toman')->default(0);
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

    public function test_inconsistent_contract_and_schedule_are_flagged_without_rewriting_records(): void
    {
        $this->file->update(['amount_toman' => 1800000]);
        $beforeFile = $this->file->fresh()->getRawOriginal();
        $beforeInstallment = $this->installment->fresh()->getRawOriginal();
        $snapshot = $this->snapshot();
        self::assertTrue($snapshot['requires_schedule_review']);
        self::assertSame(1890000, $snapshot['remaining_amount_toman']);
        self::assertSame(900000, $snapshot['scheduled_total_toman']);
        $portal = app(CustomerLoanPortalPresenter::class);
        $row = $portal->forDashboard($this->customer)['loans'][0];
        self::assertSame('review', $row['ribbon']);
        self::assertTrue($row['contract_locked']);
        self::assertFalse($row['show_settle_button']);
        self::assertNull($portal->fullSettlementOnlinePaymentQuote($this->file->fresh()));
        self::assertFalse(app(InstallmentOnlinePaymentResolver::class)->resolveForCustomer($this->customer, $this->installment->id)['ok']);
        self::assertSame($beforeFile, $this->file->fresh()->getRawOriginal());
        self::assertSame($beforeInstallment, $this->installment->fresh()->getRawOriginal());
        self::assertSame(0, $this->installment->payments()->count());
    }

    public function test_inconsistent_schedule_cannot_be_automatically_labeled_settled(): void
    {
        $this->file->update(['amount_toman' => 800000]);
        $this->installment->update(['due_date' => '2026-09-01']);
        $response = app(CustomerController::class)->storeLoanInstallmentPayment($this->request(800000, '2026-08-21'), $this->customer, $this->file, $this->installment);
        self::assertSame(200, $response->getStatusCode());
        $snapshot = $this->snapshot();
        self::assertSame(0, $snapshot['remaining_amount_toman']);
        self::assertFalse($snapshot['settled_by_balance']);
        $row = app(CustomerLoanPortalPresenter::class)->forDashboard($this->customer)['loans'][0];
        self::assertFalse($row['settled_for_ui']);
        self::assertSame('review', $row['ribbon']);
    }
}
