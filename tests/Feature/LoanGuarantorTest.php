<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\GuaranteeReturnOtpController;
use App\Http\Controllers\Admin\LoanCreationOtpController;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\LoanType;
use App\Models\SmsPanelSetting;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use App\Support\GuaranteeReturnOtpSettings;
use App\Support\LoanCreationOtpSettings;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

final class LoanGuarantorTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private LoanType $type;

    private array $messages = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        $admin = Admin::create(['username' => 'owner', 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'is_active' => true, 'is_super_admin' => true]);
        $this->actingAs($admin, 'admin');
        $this->customer = Customer::create(['first_name' => 'Buyer', 'last_name' => 'X', 'mobile' => '09123456789', 'username' => 'buyer', 'password' => 'password', 'customer_code' => 'X']);
        $this->type = LoanType::create(['title' => 'Test', 'profit_calculation_method' => 'monthly', 'interest_rate' => 0, 'daily_late_coefficient' => 0, 'daily_early_coefficient' => 0, 'installment_gap_unit' => 'monthly', 'repayment_periods' => ['type' => 'all']]);
        AppSetting::updateOrCreate(['key' => LoanCreationOtpSettings::SETTING_KEY], ['value' => '1']);
        AppSetting::updateOrCreate(['key' => GuaranteeReturnOtpSettings::SETTING_KEY], ['value' => '1']);
        SmsPanelSetting::create(['provider' => 'test', 'is_active' => true, 'username' => 'test', 'password' => Crypt::encryptString('test')]);
        $gateway = Mockery::mock(SmsPanelGateway::class);
        $gateway->shouldReceive('providerKey')->andReturn('test');
        $gateway->shouldReceive('displayName')->andReturn('Test');
        $gateway->shouldReceive('sendTestMessage')->andReturnUsing(function ($username, $password, $recipient, $message) {
            $this->messages[] = ['recipient' => $recipient, 'body' => $message];

            return new SmsPanelConnectionResult(true, 'Test');
        });
        $this->app->instance(SmsPanelManager::class, new SmsPanelManager([$gateway]));
    }

    private function party(): array
    {
        return ['first_name' => 'Guarantor', 'last_name' => 'Y', 'father_name' => 'Father', 'national_id' => '0012345679', 'mobile' => '09121234567', 'address' => 'Test address'];
    }

    private function payload(): array
    {
        return ['loan_start_jdate' => Jalali::instance(Carbon::today())->format('Y/m/d'), 'loan_type_id' => $this->type->id,
            'amount_toman' => 900000, 'installments_count' => 1, 'installment_interval_count' => 1, 'installment_interval_unit' => 'monthly',
            'installment_amount_toman' => 900000, 'guarantee_by_self' => 0, 'guarantor_details' => $this->party()];
    }

    private function verifiedToken(?array $party = null): string
    {
        $controller = app(LoanCreationOtpController::class);
        $data = ['guarantee_by_self' => 0, 'guarantor_details' => $party ?? $this->party()];
        $sent = $controller->send(Request::create('/', 'POST', $data), $this->customer)->getData(true);
        $session = Cache::get('loan_creation_otp:'.$sent['otp_session']);
        $verified = $controller->verify(Request::create('/', 'POST', $data + ['otp_session' => $sent['otp_session'], 'code' => $session['code']]), $this->customer);
        self::assertSame(200, $verified->getStatusCode());

        return $verified->getData(true)['verification_token'];
    }

    public function test_third_party_receives_otp_and_loan_and_installments_belong_to_buyer(): void
    {
        $token = $this->verifiedToken();
        self::assertSame($this->party()['mobile'], $this->messages[0]['recipient']);
        self::assertStringContainsString('Buyer X', $this->messages[0]['body']);
        $result = app(CustomerController::class)->storeLoan(Request::create('/', 'POST', $this->payload() + ['customer_verification_token' => $token]), $this->customer);
        self::assertSame(200, $result->getStatusCode(), $result->getContent());
        $file = CustomerLoanFile::firstOrFail();
        self::assertSame($this->customer->id, $file->customer_id);
        self::assertEquals($this->party(), $file->guarantor_details);
        self::assertSame($file->id, $file->installments()->firstOrFail()->customer_loan_file_id);
        self::assertNull(Cache::get('loan_creation_verified:'.$token));
    }

    public function test_third_party_cannot_skip_verification_even_when_global_otp_is_disabled(): void
    {
        AppSetting::where('key', LoanCreationOtpSettings::SETTING_KEY)->update(['value' => '0']);
        $result = app(CustomerController::class)->storeLoan(Request::create('/', 'POST', $this->payload() + ['skip_customer_verification' => 1]), $this->customer);
        self::assertSame(422, $result->getStatusCode());
        self::assertSame(0, CustomerLoanFile::count());
        $token = $this->verifiedToken();
        self::assertNotEmpty($token);
    }

    public function test_changing_guarantor_details_invalidates_the_verified_token(): void
    {
        $token = $this->verifiedToken();
        $data = $this->payload();
        $data['guarantor_details']['address'] = 'Different address';
        $data['customer_verification_token'] = $token;
        $result = app(CustomerController::class)->storeLoan(Request::create('/', 'POST', $data), $this->customer);
        self::assertSame(422, $result->getStatusCode());
        self::assertSame(0, CustomerLoanFile::count());
    }

    public function test_self_guarantee_preserves_existing_optional_flow(): void
    {
        $data = $this->payload();
        $data['guarantee_by_self'] = 1;
        $data['skip_customer_verification'] = 1;
        unset($data['guarantor_details']);
        $result = app(CustomerController::class)->storeLoan(Request::create('/', 'POST', $data), $this->customer);
        self::assertSame(200, $result->getStatusCode(), $result->getContent());
        self::assertNull(CustomerLoanFile::firstOrFail()->guarantor_details);
        self::assertSame([], $this->messages);
    }

    public function test_return_otp_uses_guarantor_mobile_and_is_bound_to_loan_and_party(): void
    {
        $token = $this->verifiedToken();
        app(CustomerController::class)->storeLoan(Request::create('/', 'POST', $this->payload() + ['customer_verification_token' => $token]), $this->customer);
        $file = CustomerLoanFile::firstOrFail();
        $controller = app(GuaranteeReturnOtpController::class);
        $sent = $controller->send(Request::create('/', 'POST'), $this->customer, $file)->getData(true);
        self::assertSame($this->party()['mobile'], end($this->messages)['recipient']);
        $session = Cache::get('guarantee_return_otp:'.$sent['otp_session']);
        $verified = $controller->verify(Request::create('/', 'POST', ['otp_session' => $sent['otp_session'], 'code' => $session['code']]), $this->customer, $file);
        self::assertSame(200, $verified->getStatusCode());
        $request = Request::create('/', 'POST', ['guarantee_return_verification_token' => $verified->getData(true)['verification_token']]);
        $consumer = new \ReflectionMethod(CustomerController::class, 'consumeGuaranteeReturnVerificationToken');
        self::assertTrue($consumer->invoke(app(CustomerController::class), $request, $this->customer, $file, null));
        self::assertFalse($consumer->invoke(app(CustomerController::class), $request, $this->customer, $file, null));
    }

    public function test_edit_preserves_unchanged_party_and_requires_new_approval_for_changed_party(): void
    {
        $token = $this->verifiedToken();
        $controller = app(CustomerController::class);
        $controller->storeLoan(Request::create('/', 'POST', $this->payload() + ['customer_verification_token' => $token]), $this->customer);
        $file = CustomerLoanFile::firstOrFail();
        $unchanged = $this->payload();
        $unchanged['guarantor_details'] = array_reverse($this->party(), true);
        $response = $controller->updateLoan(Request::create('/', 'PUT', $unchanged), $this->customer, $file);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $changed = $this->payload();
        $changed['guarantor_details']['address'] = 'New address';
        self::assertSame(422, $controller->updateLoan(Request::create('/', 'PUT', $changed), $this->customer, $file->fresh())->getStatusCode());
        $changed['customer_verification_token'] = $this->verifiedToken($changed['guarantor_details']);
        $response = $controller->updateLoan(Request::create('/', 'PUT', $changed), $this->customer, $file->fresh());
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame('New address', $file->fresh()->guarantor_details['address']);
        self::assertSame($this->customer->id, $file->fresh()->customer_id);
        self::assertSame(1, $file->installments()->count());
    }

    public function test_cheque_owner_fields_use_the_verified_party_instead_of_submitted_owner(): void
    {
        $token = $this->verifiedToken();
        $controller = app(CustomerController::class);
        $controller->storeLoan(Request::create('/', 'POST', $this->payload() + ['customer_verification_token' => $token]), $this->customer);
        $file = CustomerLoanFile::firstOrFail();
        $response = $controller->storeLoanGuarantee(Request::create('/', 'POST', [
            'type' => 'cheque', 'amount_toman' => 900000, 'cheque_owner_name' => 'Wrong owner',
            'cheque_owner_national_id' => '0000000000', 'cheque_owner_mobile' => $this->customer->mobile,
            'cheque_serial' => '12345', 'cheque_sayadi' => '1234567890123456', 'cheque_due_jdate' => $this->payload()['loan_start_jdate'],
        ]), $this->customer, $file);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $guarantee = $file->guarantees()->firstOrFail();
        self::assertSame('Guarantor Y', $guarantee->meta['cheque_owner_name']);
        self::assertSame($this->party()['mobile'], $guarantee->meta['cheque_owner_mobile']);
        self::assertSame($this->party()['national_id'], $guarantee->meta['cheque_owner_national_id']);
    }

    public function test_guarantor_optional_fields_can_be_omitted_during_verification_creation_and_edit(): void
    {
        $party = $this->party();
        unset($party['father_name'], $party['national_id'], $party['address']);
        $token = $this->verifiedToken($party);
        $payload = $this->payload();
        $payload['guarantor_details'] = $party;
        $payload['customer_verification_token'] = $token;
        $controller = app(CustomerController::class);
        $response = $controller->storeLoan(Request::create('/', 'POST', $payload), $this->customer);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $file = CustomerLoanFile::firstOrFail();
        foreach (['father_name', 'national_id', 'address'] as $key) {
            self::assertSame('', $file->guarantor_details[$key]);
        }
        unset($payload['customer_verification_token']);
        $response = $controller->updateLoan(Request::create('/', 'PUT', $payload), $this->customer, $file);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        $owner = app(\App\Services\Loans\LoanGuarantor::class)->chequeOwnerFields($file);
        self::assertArrayNotHasKey('cheque_owner_national_id', $owner);
    }

    public function test_supplied_guarantor_national_id_is_still_validated(): void
    {
        $party = $this->party();
        $party['national_id'] = '1111111111';
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\Loans\LoanGuarantor::class)->fromRequest(Request::create('/', 'POST', ['guarantee_by_self' => 0, 'guarantor_details' => $party]));
    }

    public function test_form_renders_guarantor_fields_and_self_checkbox(): void
    {
        $html = app(CustomerController::class)->index(Request::create('/', 'GET'))->with('errors', new ViewErrorBag)->render();
        self::assertStringContainsString('id="loan-guarantee-by-self"', $html);
        self::assertStringContainsString('id="loan-party-father_name"', $html);
        self::assertStringContainsString('id="loan-party-address"', $html);
    }
}
