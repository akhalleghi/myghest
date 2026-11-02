<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\LoanCreationOtpController;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use App\Support\LoanCreationOtpSettings;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class LoanCreationOtpSmsTest extends TestCase
{
    private Customer $customer;

    private LoanCreationOtpController $controller;

    private array $messages = [];

    private bool $gatewaySucceeds = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Cache::flush();
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key')->nullable();
            $table->text('body');
            $table->timestamps();
        });
        foreach ([
            '2026_05_07_040000_create_app_settings_table.php',
            '2026_05_06_000003_create_sms_logs_table.php',
            '2026_05_06_120000_create_sms_panel_settings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        AppSetting::create(['key' => LoanCreationOtpSettings::SETTING_KEY, 'value' => '1']);
        AppSetting::create(['key' => 'app_display_name', 'value' => 'Test app']);
        $this->customer = Customer::create(['first_name' => 'Test', 'last_name' => 'Customer', 'mobile' => '09121234567']);
        SmsPanelSetting::create(['provider' => 'test', 'is_active' => true, 'username' => 'test', 'password' => Crypt::encryptString('test')]);
        $gateway = Mockery::mock(SmsPanelGateway::class);
        $gateway->shouldReceive('providerKey')->andReturn('test');
        $gateway->shouldReceive('displayName')->andReturn('Test SMS');
        $gateway->shouldReceive('sendTestMessage')->andReturnUsing(function (string $username, string $password, string $recipient, string $message): SmsPanelConnectionResult {
            $this->messages[] = ['recipient' => $recipient, 'body' => $message];

            return new SmsPanelConnectionResult($this->gatewaySucceeds, 'Test');
        });
        $this->controller = new LoanCreationOtpController(new RawSmsDispatcher(new SmsPanelManager([$gateway])));
    }

    public function test_preview_renders_the_system_template_without_sending_or_creating_an_otp(): void
    {
        SmsTemplate::create(['template_key' => 'default_loan_creation_otp', 'body' => '{{customer_name}} | {{app_name}} | {{code}}']);

        $response = $this->controller->preview(Request::create('/', 'POST', ['customer_name' => 'Customer $1']), $this->customer);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Customer $1 | Test app | {{code}}', $response->getData(true)['body']);
        self::assertArrayNotHasKey('otp_session', $response->getData(true));
        self::assertSame([], $this->messages);
        self::assertSame(0, SmsLog::count());
    }

    public function test_preview_is_available_through_the_registered_http_route(): void
    {
        $this->instance(LoanCreationOtpController::class, $this->controller);
        SmsTemplate::create(['template_key' => 'default_loan_creation_otp', 'body' => '{{customer_name}}: {{code}}']);

        $this->app->make(Kernel::class);
        $route = Route::getRoutes()->getByName('admin.customers.loan-creation-otp.preview');
        self::assertNotNull($route);
        // Exercise real model binding while isolating the admin session and permission infrastructure.
        foreach (Route::gatherRouteMiddleware($route) as $middleware) {
            $class = explode(':', $middleware, 2)[0];
            if ($class !== SubstituteBindings::class) {
                $this->withoutMiddleware($class);
            }
        }

        $this->postJson('/admin/customers/'.$this->customer->id.'/loan-creation-otp/preview', ['customer_name' => 'HTTP Customer'])
            ->assertOk()
            ->assertJsonPath('body', 'HTTP Customer: {{code}}');

        self::assertSame([], $this->messages);
        self::assertSame(0, SmsLog::count());
    }

    public function test_edited_text_sends_the_generated_code_and_the_same_code_verifies_successfully(): void
    {
        $template = SmsTemplate::create(['template_key' => 'default_loan_creation_otp', 'body' => 'Original {{code}}']);
        $edited = 'Edited message — {{ code }} — second copy: {{CODE}}';
        $sent = $this->controller->send(Request::create('/', 'POST', ['sms_text' => $edited]), $this->customer);
        self::assertSame(200, $sent->getStatusCode());
        $session = $sent->getData(true)['otp_session'];
        $otp = Cache::get('loan_creation_otp:'.$session);
        self::assertMatchesRegularExpression('/^\d{6}$/', $otp['code']);
        self::assertSame('Edited message — '.$otp['code'].' — second copy: '.$otp['code'], $this->messages[0]['body']);
        self::assertSame($this->customer->mobile, $this->messages[0]['recipient']);
        self::assertSame($this->messages[0]['body'], SmsLog::firstOrFail()->message_text);
        self::assertSame('Original {{code}}', $template->fresh()->body);

        // Opening and cancelling another preview must preserve the previously sent code.
        $this->controller->preview(Request::create('/', 'POST'), $this->customer);
        self::assertSame($otp, Cache::get('loan_creation_otp:'.$session));
        self::assertCount(1, $this->messages);

        $verified = $this->controller->verify(Request::create('/', 'POST', ['otp_session' => $session, 'code' => $otp['code']]), $this->customer);
        self::assertSame(200, $verified->getStatusCode());
        self::assertSame($this->customer->id, Cache::get('loan_creation_verified:'.$verified->getData(true)['verification_token'])['customer_id']);
        self::assertNull(Cache::get('loan_creation_otp:'.$session));
    }

    public function test_missing_code_placeholder_empty_and_oversized_messages_are_rejected_before_sending(): void
    {
        foreach (['No confirmation code', '', str_repeat('x', 1001).'{{code}}'] as $body) {
            try {
                $this->controller->send(Request::create('/', 'POST', ['sms_text' => $body]), $this->customer);
                self::fail('An invalid OTP message must not be sent.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('sms_text', $exception->errors());
            }
        }
        self::assertSame([], $this->messages);
        self::assertSame(0, SmsLog::count());
    }

    public function test_legacy_send_requests_still_use_the_default_template_and_preview_repairs_a_missing_code_token(): void
    {
        SmsTemplate::create(['template_key' => 'default_loan_creation_otp', 'body' => 'Hello {{customer_name}}']);
        $preview = $this->controller->preview(Request::create('/', 'POST'), $this->customer);
        self::assertStringContainsString('{{code}}', $preview->getData(true)['body']);

        $sent = $this->controller->send(Request::create('/', 'POST'), $this->customer);
        self::assertSame(200, $sent->getStatusCode());
        $otp = Cache::get('loan_creation_otp:'.$sent->getData(true)['otp_session']);
        self::assertStringContainsString('Hello Test Customer', $this->messages[0]['body']);
        self::assertStringContainsString($otp['code'], $this->messages[0]['body']);
    }

    public function test_disabled_otp_and_invalid_customer_mobile_cannot_preview_or_send(): void
    {
        AppSetting::where('key', LoanCreationOtpSettings::SETTING_KEY)->update(['value' => '0']);
        self::assertSame(422, $this->controller->preview(Request::create('/', 'POST'), $this->customer)->getStatusCode());
        self::assertSame(422, $this->controller->send(Request::create('/', 'POST'), $this->customer)->getStatusCode());
        AppSetting::where('key', LoanCreationOtpSettings::SETTING_KEY)->update(['value' => '1']);
        $this->customer->mobile = null;
        self::assertSame(422, $this->controller->preview(Request::create('/', 'POST'), $this->customer)->getStatusCode());
        self::assertSame(422, $this->controller->send(Request::create('/', 'POST'), $this->customer)->getStatusCode());
        self::assertSame([], $this->messages);
    }

    public function test_gateway_failure_does_not_return_a_verifiable_session(): void
    {
        $this->gatewaySucceeds = false;
        $response = $this->controller->send(Request::create('/', 'POST', ['sms_text' => 'Message {{code}}']), $this->customer);
        self::assertSame(422, $response->getStatusCode());
        self::assertArrayNotHasKey('otp_session', $response->getData(true));
        self::assertSame(SmsLog::STATUS_UNDELIVERED, SmsLog::firstOrFail()->status);
    }
}
