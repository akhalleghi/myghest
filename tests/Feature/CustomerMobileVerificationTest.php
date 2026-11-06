<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\SmsPanelSetting;
use App\Services\Customers\CustomerMobileVerification;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use App\Support\AdminPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class CustomerMobileVerificationTest extends TestCase
{
    use RefreshDatabase;

    private CustomerMobileVerification $verification;

    private array $messages = [];

    private bool $succeeds = true;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'hashing.bcrypt.rounds' => 4]);
        Cache::flush();
        $this->admin = Admin::create(['username' => 'owner', 'email' => 'owner@example.test', 'name' => 'Owner', 'password' => 'password', 'is_active' => true, 'is_super_admin' => true]);
        $this->actingAs($this->admin, 'admin');
        SmsPanelSetting::create(['provider' => 'test', 'is_active' => true, 'username' => 'test', 'password' => Crypt::encryptString('test')]);
        $gateway = Mockery::mock(SmsPanelGateway::class);
        $gateway->shouldReceive('providerKey')->andReturn('test');
        $gateway->shouldReceive('displayName')->andReturn('Test');
        $gateway->shouldReceive('sendTestMessage')->andReturnUsing(function ($username, $password, $recipient, $message) {
            $this->messages[] = $message;

            return new SmsPanelConnectionResult($this->succeeds, 'Test');
        });
        $this->app->instance(SmsPanelManager::class, new SmsPanelManager([$gateway]));
        $this->verification = app(CustomerMobileVerification::class);
    }

    private function send(): array
    {
        $session = $this->verification->send($this->admin->id, '09123456789', $this->verification->preview());
        preg_match('/[0-9]{6}/', end($this->messages), $matches);

        return [$session['otp_session'], $matches[0]];
    }

    private function payload(): array
    {
        return ['first_name' => 'Test', 'last_name' => 'Customer', 'mobile' => '09123456789', 'password' => 'password',
            'customer_code' => null, 'father_name' => null, 'national_id' => null, 'mobile2' => null, 'phone_landline' => null,
            'membership_jdate' => null, 'birth_jdate' => null, 'email' => null, 'city' => null, 'address' => null, 'postal_code' => null];
    }

    public function test_customer_form_renders_optional_mobile_verification_and_routes_are_permission_protected(): void
    {
        $html = app(CustomerController::class)->index(Request::create('/', 'GET'))->with('errors', new ViewErrorBag)->render();
        self::assertStringContainsString('id="cust-mobile-otp-required"', $html);
        self::assertStringContainsString('id="cust-mobile-otp-send"', $html);
        self::assertStringContainsString('id="cust-mobile-otp-verify"', $html);
        self::assertStringContainsString('عبارت {{code}} را حفظ کنید.', $html);
        $registry = app(AdminPermissionRegistry::class);
        foreach (['preview', 'send', 'verify'] as $action) {
            self::assertContains('customers.create', $registry->permissionsForRoute('admin.customers.mobile-verification.'.$action));
        }
    }

    public function test_code_expires_after_ten_minutes(): void
    {
        [$id, $code] = $this->send();
        $this->travel(11)->minutes();
        $this->expectException(ValidationException::class);
        $this->verification->verify($this->admin->id, '09123456789', $id, $code);
    }

    public function test_sms_text_requires_code_placeholder(): void
    {
        $this->expectException(ValidationException::class);
        $this->verification->send($this->admin->id, '09123456789', 'No code placeholder');
    }

    public function test_verified_mobile_is_persisted_and_proof_is_consumed_after_customer_creation(): void
    {
        [$id, $code] = $this->send();
        $proof = $this->verification->verify($this->admin->id, '09123456789', $id, $code);
        $token = $proof['verification_token'];
        $request = Request::create('/', 'POST', $this->payload() + ['mobile_verification_required' => 1, 'mobile_verification_token' => $token]);
        self::assertSame(302, app(CustomerController::class)->store($request)->getStatusCode());
        $customer = Customer::firstOrFail();
        self::assertNotNull($customer->mobile_verified_at);
        self::assertFalse(Cache::has('customer_mobile_verified:'.$token));
        $customer->update(['mobile' => '09123456780']);
        self::assertNull($customer->fresh()->mobile_verified_at);
    }

    public function test_optional_registration_works_without_sending_sms(): void
    {
        app(CustomerController::class)->store(Request::create('/', 'POST', $this->payload()));
        self::assertNull(Customer::firstOrFail()->mobile_verified_at);
        self::assertSame([], $this->messages);
    }

    public function test_enabling_verification_without_proof_cannot_create_customer(): void
    {
        $this->expectException(ValidationException::class);
        app(CustomerController::class)->store(Request::create('/', 'POST', $this->payload() + ['mobile_verification_required' => 1]));
    }

    public function test_proof_cannot_be_used_for_another_mobile(): void
    {
        [$id, $code] = $this->send();
        $proof = $this->verification->verify($this->admin->id, '09123456789', $id, $code);
        $this->expectException(ValidationException::class);
        $this->verification->proof($this->admin->id, '09123456780', $proof['verification_token']);
    }

    public function test_challenge_is_bound_to_admin_and_mobile(): void
    {
        [$id, $code] = $this->send();
        $this->expectException(ValidationException::class);
        $this->verification->verify($this->admin->id + 1, '09123456789', $id, $code);
    }

    public function test_five_incorrect_attempts_expire_the_challenge(): void
    {
        [$id, $code] = $this->send();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $this->verification->verify($this->admin->id, '09123456789', $id, '000000');
                self::fail('Incorrect code accepted');
            } catch (ValidationException) {
            }
        }
        self::assertFalse(Cache::has('customer_mobile_otp:'.$id));
        $this->expectException(ValidationException::class);
        $this->verification->verify($this->admin->id, '09123456789', $id, $code);
    }

    public function test_code_and_proof_expire_and_successful_code_is_single_use(): void
    {
        [$id, $code] = $this->send();
        $proof = $this->verification->verify($this->admin->id, '09123456789', $id, $code);
        self::assertFalse(Cache::has('customer_mobile_otp:'.$id));
        $this->travel(31)->minutes();
        $this->expectException(ValidationException::class);
        $this->verification->proof($this->admin->id, '09123456789', $proof['verification_token']);
    }

    public function test_resend_cooldown_and_code_placeholder_are_enforced(): void
    {
        $this->send();
        $this->expectException(ValidationException::class);
        $this->verification->send($this->admin->id, '09123456789', $this->verification->preview());
    }

    public function test_send_failure_removes_challenge_and_allows_retry(): void
    {
        $this->succeeds = false;
        try {
            $this->send();
            self::fail('Failed SMS accepted');
        } catch (ValidationException) {
        }
        $this->succeeds = true;
        [$id] = $this->send();
        self::assertTrue(Cache::has('customer_mobile_otp:'.$id));
    }
}
