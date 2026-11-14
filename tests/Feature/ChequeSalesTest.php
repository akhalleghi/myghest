<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Models\ChequeCustomer;
use App\Models\ChequeInstrument;
use App\Models\ChequeSale;
use App\Models\Customer;
use App\Models\SmsPanelSetting;
use App\Services\Cheques\ChequeFinance;
use App\Services\Cheques\ChequeStatistics;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ChequeSalesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private ChequeCustomer $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'Asia/Tehran'));
        config(['cache.default' => 'array']);
        $this->admin = Admin::create(['username' => 'owner', 'email' => 'owner@example.test', 'name' => 'Owner', 'password' => 'password', 'is_active' => true, 'is_super_admin' => true]);
        $this->actingAs($this->admin, 'admin');
        $this->buyer = ChequeCustomer::create(['first_name' => 'Cheque', 'last_name' => 'Buyer', 'mobile' => '09123456789', 'membership_at' => '2026-09-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function terms(): array
    {
        return ['purchased_at' => '2026-09-01', 'purchase_amount_toman' => 1000000, 'months' => 1,
            'interest_rate' => 0, 'profit_method' => 'monthly', 'down_payment_toman' => 0, 'discount_toman' => 0,
            'daily_late_coefficient' => '.001', 'daily_early_coefficient' => '.001', 'guarantee_by_self' => true,
            'cheques' => [['sayad_number' => '1234567890123456', 'serial' => '1', 'bank_name' => 'Test bank', 'due_date' => '2026-10-01', 'amount_toman' => 1000000]]];
    }

    private function cheque(?array $terms = null): ChequeInstrument
    {
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms ?? $this->terms())->assertOk();

        return ChequeInstrument::latest('id')->firstOrFail();
    }

    private function action(ChequeInstrument $cheque, string $action, array $extra = [])
    {
        return $this->postJson(route($action === 'receive' ? 'admin.cheques.receipts' : 'admin.cheques.actions', $cheque), [
            'action' => $action, 'date' => '2026-11-11', 'version' => $cheque->fresh()->version, 'note' => 'Test action', ...$extra,
        ]);
    }

    public function test_customer_identity_and_sales_are_completely_independent_of_installments(): void
    {
        Customer::create(['customer_code' => '1', 'username' => 'regular', 'first_name' => 'Regular', 'last_name' => 'Buyer', 'mobile' => $this->buyer->mobile, 'password' => 'password']);
        $this->cheque();
        self::assertSame(1, Customer::count());
        self::assertSame(1, ChequeCustomer::count());
        $this->assertDatabaseCount('customer_loan_files', 0);
        $this->assertDatabaseCount('customer_loan_installments', 0);
        $this->get(route('admin.cheques.index'))->assertOk()->assertSee('CC 1');
        $this->get(route('admin.cheques.index', ['view' => 'cheques']))->assertOk()->assertSee('1234567890123456');
    }

    public function test_customer_workspace_is_scoped_and_filters_without_rendering_another_page(): void
    {
        $own = $this->cheque();
        $other = ChequeCustomer::create(['first_name' => 'Other', 'last_name' => 'Person', 'mobile' => '09120000001', 'membership_at' => '2026-09-01']);
        $terms = $this->terms();
        $terms['cheques'][0]['sayad_number'] = '9999999999999999';
        $this->postJson(route('admin.cheques.sales.store', $other), $terms)->assertOk();
        $url = route('admin.cheques.index', ['view' => 'cheques', 'customer_id' => $this->buyer->id, 'workspace' => 1]);
        $this->get($url)->assertOk()->assertSee($own->sayad_number)->assertDontSee('9999999999999999')
            ->assertSee('data-workspace-filter', false)->assertSee('cq-filter-panel', false)->assertDontSee('<main', false);
        $this->get($url.'&min_amount=2000000')->assertOk()->assertDontSee($own->sayad_number)->assertSee('چکی با این فیلترها پیدا نشد.');
        $this->get(route('admin.cheques.index', ['workspace' => 1]))->assertUnprocessable();
        $this->get(route('admin.cheques.index', ['customer_id' => $this->buyer->id, 'view' => 'cheques']))->assertOk()
            ->assertSee('data-initial-customer="'.$this->buyer->id.'"', false)->assertSee('cq-management', false);
        $this->get(route('admin.cheques.index'))->assertOk()->assertSee('data-customer-manage', false)->assertSee('data-row-menu', false);
    }

    public function test_multi_cheque_contract_requires_exact_face_total_and_unique_sayad(): void
    {
        $terms = $this->terms();
        $terms['interest_rate'] = 10;
        $terms['months'] = 2;
        $terms['down_payment_toman'] = 100000;
        $terms['discount_toman'] = 50000;
        $terms['cheques'][0]['amount_toman'] = 500000;
        $terms['cheques'][] = [...$terms['cheques'][0], 'sayad_number' => '1234567890123457', 'amount_toman' => 550000];
        $this->cheque($terms);
        self::assertSame(1050000, (int) ChequeSale::sole()->total_toman);
        self::assertSame(200000, (int) ChequeSale::sole()->profit_toman);
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms)->assertUnprocessable();
        $this->assertDatabaseCount('cheque_sales', 1);
        $this->assertDatabaseCount('cheque_instruments', 2);
    }

    public function test_late_fees_start_only_after_bounce_and_all_views_use_same_remaining(): void
    {
        $cheque = $this->cheque();
        $finance = app(ChequeFinance::class);
        self::assertSame(1000000, $finance->balance($cheque)['remaining_toman']);
        $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        self::assertSame(1041000, $finance->balance($cheque->fresh())['remaining_toman']);
        $this->getJson(route('admin.cheques.details', $cheque))->assertOk()->assertJsonPath('balance.remaining_toman', 1041000);
        $this->postJson(route('admin.cheques.sms.preview', $cheque), ['type' => 'bounced'])->assertOk()->assertSee('1,041,000');
        $this->get(route('admin.cheques.reports'))->assertOk()->assertSee('۱,۰۴۱,۰۰۰');
    }

    public function test_partial_receipt_cannot_close_unpaid_penalty_and_retries_cannot_duplicate_money(): void
    {
        $cheque = $this->cheque();
        $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        $uuid = (string) Str::uuid();
        $this->action($cheque, 'receive', ['amount_toman' => 1000000, 'request_uuid' => $uuid, 'method' => 'cash'])->assertOk();
        self::assertNull($cheque->fresh()->cleared_at);
        self::assertSame(41000, app(ChequeFinance::class)->balance($cheque->fresh())['remaining_toman']);
        $futureBalance = app(ChequeFinance::class)->balance($cheque->fresh(), Carbon::parse('2026-11-12'));
        self::assertSame(0, $futureBalance['principal_remaining_toman']);
        self::assertSame(41000, $futureBalance['late_remaining_toman']);
        $this->action($cheque, 'receive', ['amount_toman' => 1000000, 'request_uuid' => $uuid, 'method' => 'cash'])->assertOk();
        $this->assertDatabaseCount('cheque_receipts', 1);
        $this->action($cheque, 'receive', ['amount_toman' => 41001, 'request_uuid' => (string) Str::uuid(), 'method' => 'cash'])->assertUnprocessable();
        $this->action($cheque, 'receive', ['amount_toman' => 41000, 'request_uuid' => (string) Str::uuid(), 'method' => 'cash'])->assertOk();
        self::assertSame('archive', $cheque->fresh()->folder);
        self::assertSame(0, app(ChequeFinance::class)->balance($cheque->fresh())['remaining_toman']);
        $this->action($cheque, 'person', ['destination' => ['name' => 'Receiver']])->assertUnprocessable();
    }

    public function test_early_payment_benefit_is_frozen_after_settlement(): void
    {
        $terms = $this->terms();
        $terms['cheques'][0]['due_date'] = '2026-12-01';
        $cheque = $this->cheque($terms);
        self::assertSame(980000, app(ChequeFinance::class)->balance($cheque)['remaining_toman']);
        $this->action($cheque, 'receive', ['amount_toman' => 980000, 'request_uuid' => (string) Str::uuid(), 'method' => 'bank_transfer'])->assertOk();
        Carbon::setTestNow('2027-01-10');
        $balance = app(ChequeFinance::class)->balance($cheque->fresh());
        self::assertSame(0, $balance['remaining_toman']);
        self::assertSame(20000, $balance['early_earned_toman']);
    }

    public function test_transfer_and_verified_return_do_not_clear_debt_or_hide_it_in_reports(): void
    {
        $cheque = $this->cheque();
        $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        $this->action($cheque, 'person', ['destination' => ['name' => 'Third party', 'mobile' => '09123456780']])->assertOk();
        self::assertSame('transferred', $cheque->fresh()->folder);
        self::assertSame(1041000, app(ChequeFinance::class)->balance($cheque->fresh())['remaining_toman']);
        $this->action($cheque, 'return_owner')->assertUnprocessable();
        $token = (string) Str::uuid();
        Cache::put('cheque_verified:'.$token, ['admin_id' => $this->admin->id, 'purpose' => 'return', 'context' => ['cheque_id' => $cheque->id, 'version' => (int) $cheque->fresh()->version, 'target' => 'return_owner']]);
        $this->action($cheque, 'return_owner', ['verification_token' => $token])->assertOk();
        self::assertSame('returned', $cheque->fresh()->folder);
        self::assertSame(1041000, app(ChequeFinance::class)->balance($cheque->fresh())['remaining_toman']);
        $stats = app(ChequeStatistics::class)->build()['rows'];
        self::assertSame(0, $stats['cleared']['count']);
        self::assertSame(1, $stats['resolved']['count']);
        self::assertSame(1, $stats['open']['count']);
        self::assertSame(0, $stats['bounced']['count']);
    }

    public function test_access_to_regular_customers_does_not_grant_cheque_access_or_financial_actions(): void
    {
        $cheque = $this->cheque();
        $admin = Admin::create(['username' => 'limited', 'name' => 'Limited', 'email' => 'limited@example.test', 'password' => 'password', 'is_active' => true]);
        AdminPermissionGrant::create(['admin_id' => $admin->id, 'permission_key' => 'customers.view']);
        $this->actingAs($admin, 'admin');
        $this->getJson(route('admin.cheques.index'))->assertForbidden();
        AdminPermissionGrant::create(['admin_id' => $admin->id, 'permission_key' => 'cheques.manage']);
        $admin->unsetRelation('permissionGrants');
        $this->postJson(route('admin.cheques.actions', $cheque), ['action' => 'receive'])->assertForbidden();
        $this->postJson(route('admin.cheques.receipts', $cheque), ['action' => 'receive'])->assertForbidden();
    }

    public function test_invalid_calendar_dates_and_mismatched_totals_roll_back_without_creating_sales(): void
    {
        $terms = $this->terms();
        $terms['purchased_at'] = '1405/13/01';
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms)->assertUnprocessable();
        $terms = $this->terms();
        $terms['cheques'][0]['amount_toman'] = 999999;
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms)->assertUnprocessable();
        $this->assertDatabaseCount('cheque_sales', 0);
        $terms = $this->terms();
        $terms['cheques'][0]['cleared_at'] = '2026-10-01';
        $terms['cheques'][0]['folder'] = 'archive';
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms)->assertUnprocessable();
        $this->assertDatabaseCount('cheque_instruments', 0);
    }

    public function test_other_owner_requires_bound_approval_and_changing_terms_invalidates_it(): void
    {
        $terms = $this->terms();
        $terms['guarantee_by_self'] = false;
        $terms['owner_details'] = ['name' => 'Other Owner', 'mobile' => '09123456780'];
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), $terms)->assertUnprocessable();
        $preview = $this->postJson(route('admin.cheques.verification.preview'), [...$terms, 'purpose' => 'owner', 'customer_id' => $this->buyer->id])
            ->assertOk()->assertJsonPath('mobile', '09123456780')->json();
        $payload = Cache::get('cheque_preview:'.$preview['preview_token']);
        $token = (string) Str::uuid();
        Cache::put('cheque_verified:'.$token, $payload);
        $changed = $terms;
        $changed['months'] = 2;
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), [...$changed, 'verification_token' => $token])->assertUnprocessable();
        $this->assertDatabaseCount('cheque_sales', 0);
        $token = (string) Str::uuid();
        Cache::put('cheque_verified:'.$token, $payload);
        $this->postJson(route('admin.cheques.sales.store', $this->buyer), [...$terms, 'verification_token' => $token])->assertOk();
        self::assertSame('09123456780', ChequeSale::sole()->owner_details['mobile']);
        self::assertNotNull(ChequeSale::sole()->owner_verified_at);
    }

    public function test_customer_verification_is_optional_and_changing_mobile_clears_it(): void
    {
        $data = ['first_name' => 'New', 'last_name' => 'Customer', 'mobile' => '09123456781', 'sms_enabled' => true, 'auto_reminders_enabled' => false];
        $preview = $this->postJson(route('admin.cheques.verification.preview'), ['purpose' => 'customer', 'name' => 'New Customer', 'mobile' => $data['mobile']])->assertOk()->json();
        $token = (string) Str::uuid();
        Cache::put('cheque_verified:'.$token, Cache::get('cheque_preview:'.$preview['preview_token']));
        $this->postJson(route('admin.cheques.customers.store'), [...$data, 'verification_token' => $token])->assertOk();
        $customer = ChequeCustomer::latest('id')->firstOrFail();
        self::assertNotNull($customer->mobile_verified_at);
        $data['mobile'] = '09123456782';
        $this->putJson(route('admin.cheques.customers.update', $customer), $data)->assertOk();
        self::assertNull($customer->fresh()->mobile_verified_at);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_wallet_is_separate_nonnegative_and_request_retries_are_idempotent(): void
    {
        $data = ['amount_toman' => 100000, 'note' => 'Credit', 'request_uuid' => (string) Str::uuid()];
        $this->postJson(route('admin.cheques.wallet', $this->buyer), $data)->assertOk();
        $this->postJson(route('admin.cheques.wallet', $this->buyer), $data)->assertOk();
        self::assertSame(100000, $this->buyer->fresh()->wallet_balance_toman);
        $this->postJson(route('admin.cheques.wallet', $this->buyer), ['amount_toman' => -100001, 'note' => 'Debit', 'request_uuid' => (string) Str::uuid()])->assertUnprocessable();
        self::assertSame(100000, $this->buyer->fresh()->wallet_balance_toman);
        $this->assertDatabaseCount('customer_wallets', 0);
    }

    public function test_report_dates_totals_and_excel_filter_the_same_cheques_and_escape_formulas(): void
    {
        $terms = $this->terms();
        $terms['cheques'][0]['serial'] = '=2+2';
        $this->cheque($terms);
        $terms['cheques'][0]['sayad_number'] = '1234567890123457';
        $terms['cheques'][0]['due_date'] = '2026-10-02';
        $this->cheque($terms);
        $filter = ['due_from' => '2026-10-01', 'due_to' => '2026-10-01'];
        $this->get(route('admin.cheques.reports', $filter))->assertOk()->assertViewHas('totals', fn ($totals) => $totals['count'] === 1 && $totals['face'] === 1000000);
        $response = $this->get(route('admin.cheques.export', $filter))->assertOk();
        $export = mb_convert_encoding(substr($response->streamedContent(), 2), 'UTF-8', 'UTF-16LE');
        self::assertStringContainsString('1234567890123456', $export);
        self::assertStringNotContainsString('1234567890123457', $export);
        self::assertStringContainsString("'=2+2", $export);
        $lines = explode("\r\n", rtrim($export, "\r\n"));
        self::assertSame(count(explode("\t", $lines[0])), count(explode("\t", $lines[1])));
    }

    public function test_reminder_requires_explicit_activation_and_sends_one_customer_summary_per_day(): void
    {
        $terms = $this->terms();
        $terms['cheques'][0]['amount_toman'] = 500000;
        $terms['cheques'][] = [...$terms['cheques'][0], 'sayad_number' => '1234567890123457'];
        $this->cheque($terms);
        foreach (ChequeInstrument::all() as $cheque) {
            $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        }
        $messages = [];
        $gateway = \Mockery::mock(SmsPanelGateway::class);
        $gateway->shouldReceive('providerKey')->andReturn('test');
        $gateway->shouldReceive('displayName')->andReturn('Test');
        $gateway->shouldReceive('sendTestMessage')->andReturnUsing(function ($username, $password, $mobile, $body) use (&$messages) {
            $messages[] = [$mobile, $body];

            return new SmsPanelConnectionResult(true, 'Success');
        });
        $this->app->instance(SmsPanelManager::class, new SmsPanelManager([$gateway]));
        SmsPanelSetting::create(['provider' => 'test', 'is_active' => true, 'username' => 'test', 'password' => Crypt::encryptString('test')]);
        $this->artisan('sms:send-cheque-reminders')->assertSuccessful();
        self::assertCount(0, $messages);
        $this->buyer->update(['auto_reminders_enabled' => true]);
        $this->artisan('sms:send-cheque-reminders')->assertSuccessful();
        $this->artisan('sms:send-cheque-reminders')->assertSuccessful();
        self::assertCount(1, $messages);
        self::assertStringContainsString('1,041,000', $messages[0][1]);
        $this->assertDatabaseCount('customer_loan_installment_payments', 0);
    }

    public function test_customer_summary_uses_same_debt_and_disabled_sms_blocks_delivery(): void
    {
        $cheque = $this->cheque();
        $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        $preview = $this->postJson(route('admin.cheques.customers.sms.preview', $this->buyer))->assertOk()->assertSee('1,041,000')->json();
        $this->buyer->update(['sms_enabled' => false]);
        $this->postJson(route('admin.cheques.customers.sms.send', $this->buyer), $preview)->assertUnprocessable();
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_otp_attempt_limit_and_stale_financial_form_are_enforced(): void
    {
        $session = (string) Str::uuid();
        Cache::put('cheque_otp:'.$session, ['admin_id' => $this->admin->id, 'code_hash' => hash('sha256', '123456'), 'attempts' => 0]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('admin.cheques.verification.confirm'), ['session' => $session, 'code' => '000000'])->assertUnprocessable();
        }
        $this->postJson(route('admin.cheques.verification.confirm'), ['session' => $session, 'code' => '123456'])->assertUnprocessable();
        $cheque = $this->cheque();
        $this->action($cheque, 'bank', ['destination' => ['name' => 'Bank']])->assertOk();
        $this->action($cheque, 'bounce', ['version' => 1])->assertUnprocessable();
        self::assertSame('bank', $cheque->fresh()->folder);
    }

    public function test_details_keep_note_action_inside_its_closed_dialog_and_load_authored_history(): void
    {
        $cheque = $this->cheque();
        $this->postJson(route('admin.cheques.notes', $cheque), ['note' => '<b>Cheque note</b>'])->assertOk();
        $data = $this->getJson(route('admin.cheques.details', $cheque))->assertOk()->json('cheque');
        self::assertSame('<b>Cheque note</b>', $data['events'][0]['payload']['note']);
        self::assertSame($this->admin->name, $data['events'][0]['admin']['name']);
        self::assertArrayNotHasKey('email', $data['events'][0]['admin']);
        self::assertCount(1, $data['sale']['cheques']);
        $html = $this->get(route('admin.cheques.index'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);
        self::assertSame(0, $xpath->query('//dialog[@open]')->length);
        self::assertSame(1, $xpath->query('//dialog[@id="cq-details"]//section[@data-detail-panel="notes" and @hidden]//button[@data-note-new]')->length);
        self::assertSame(0, $xpath->query('//button[@data-note-new and not(ancestor::dialog)]')->length);
    }

    public function test_customer_notes_aggregate_only_their_own_files_and_keep_authors(): void
    {
        $cheque = $this->cheque();
        $this->postJson(route('admin.cheques.notes', $cheque), ['note' => 'Cheque-specific note'])->assertOk();
        $this->postJson(route('admin.cheques.customers.notes.store', $this->buyer), ['note' => 'Customer-specific note'])->assertOk();
        $other = ChequeCustomer::create(['first_name' => 'Other', 'last_name' => 'Buyer', 'mobile' => '09123456780', 'membership_at' => '2026-09-01']);
        $this->postJson(route('admin.cheques.customers.notes.store', $other), ['note' => 'Private other customer note'])->assertOk();
        $response = $this->getJson(route('admin.cheques.customers.notes', $this->buyer))->assertOk();
        $response->assertSee('Cheque-specific note')->assertSee('Customer-specific note')->assertDontSee('Private other customer note');
        self::assertCount(2, $response->json('notes'));
        self::assertSame('CS 1', $response->json('notes.1.cheque.sale.code'));
    }

    public function test_full_customer_names_and_persian_mobile_search_match_list_report_and_excel(): void
    {
        $this->cheque();
        $this->get(route('admin.cheques.index', ['q' => 'Cheque Buyer']))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.cheques.reports', ['q' => 'Cheque Buyer']))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['count'] === 1);
        $this->get(route('admin.cheques.reports', ['q' => '۰۹۱۲۳۴۵۶۷۸۹']))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['count'] === 1);
        $this->getJson(route('admin.cheques.reports', ['min_amount' => 100, 'max_amount' => 10]))->assertUnprocessable();
    }

    public function test_transfer_details_are_preserved_and_repeated_bounce_keeps_original_fee_basis(): void
    {
        $cheque = $this->cheque();
        $this->action($cheque, 'bounce', ['date' => '2026-10-02'])->assertOk();
        $destination = ['name' => 'Bank', 'branch' => 'Central', 'account_number' => '0012345', 'iban' => 'IR000000000000000000000000'];
        $this->action($cheque, 'bank', ['destination' => $destination])->assertOk();
        $this->action($cheque, 'bounce')->assertOk();
        self::assertSame('2026-10-02', $cheque->fresh()->bounced_at->toDateString());
        self::assertSame(1041000, app(ChequeFinance::class)->balance($cheque->fresh())['remaining_toman']);
        $details = $this->getJson(route('admin.cheques.details', $cheque))->assertOk()->json('cheque.events');
        $bank = collect($details)->firstWhere('action', 'bank');
        self::assertSame('0012345', $bank['payload']['after']['destination']['account_number']);
        self::assertSame('Central', $bank['payload']['after']['destination']['branch']);
        $this->action($cheque, 'bank', ['destination' => ['name' => 'Bank', 'iban' => 'bad']])->assertUnprocessable();
    }

    public function test_formatted_money_is_identical_in_contract_receipts_filters_and_wallet(): void
    {
        $terms = $this->terms();
        $terms['purchase_amount_toman'] = '۱٬۰۰۰٬۰۰۰';
        $terms['cheques'][0]['amount_toman'] = '1,000,000';
        $cheque = $this->cheque($terms);
        self::assertSame(1000000, $cheque->amount_toman);
        $this->get(route('admin.cheques.reports', ['min_amount' => '۹۰۰٬۰۰۰', 'max_amount' => '1,100,000']))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['count'] === 1);
        $this->action($cheque, 'receive', ['amount_toman' => '۱٬۰۰۰٬۰۰۰', 'request_uuid' => (string) Str::uuid(), 'method' => 'cash'])->assertOk();
        self::assertSame(1000000, (int) $cheque->receipts()->sole()->amount_toman);
        $this->postJson(route('admin.cheques.wallet', $this->buyer), ['amount_toman' => '20,000', 'note' => 'Credit', 'request_uuid' => (string) Str::uuid()])->assertOk();
        $this->postJson(route('admin.cheques.wallet', $this->buyer), ['amount_toman' => '-۱۰٬۰۰۰', 'note' => 'Debit', 'request_uuid' => (string) Str::uuid()])->assertOk();
        self::assertSame(10000, $this->buyer->fresh()->wallet_balance_toman);
        $terms['purchase_amount_toman'] = '1.5';
        $this->postJson(route('admin.cheques.quote'), $terms)->assertUnprocessable();
    }

    public function test_otp_retries_do_not_extend_the_original_expiry(): void
    {
        $session = (string) Str::uuid();
        Cache::put('cheque_otp:'.$session, ['admin_id' => $this->admin->id, 'code_hash' => hash('sha256', '123456'), 'attempts' => 0, 'expires_at' => now()->addMinute()->timestamp], now()->addMinutes(10));
        $this->postJson(route('admin.cheques.verification.confirm'), ['session' => $session, 'code' => '000000'])->assertUnprocessable();
        Carbon::setTestNow(now()->addSeconds(61));
        $this->postJson(route('admin.cheques.verification.confirm'), ['session' => $session, 'code' => '123456'])->assertUnprocessable();
    }
}
