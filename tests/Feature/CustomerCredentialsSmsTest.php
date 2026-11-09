<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerCredentialsSmsController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\SmsPanelSetting;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class CustomerCredentialsSmsTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function customer(): Customer
    {
        return Customer::create(['customer_code' => '1', 'username' => '09123456789', 'first_name' => 'Test', 'last_name' => 'User', 'mobile' => '09123456789', 'password' => 'secret123', 'password_print_encrypted' => Crypt::encryptString('secret123')]);
    }

    public function test_preview_contains_saved_credentials_and_login_link_without_sending(): void
    {
        $customer = $this->customer();
        $data = app(CustomerCredentialsSmsController::class)->preview($customer)->getData(true);
        self::assertStringContainsString('09123456789', $data['body']);
        self::assertStringContainsString('secret123', $data['body']);
        self::assertStringContainsString(route('customer.login'), $data['body']);
        self::assertSame([], $this->messages);
        self::assertNull($customer->fresh()->credentials_sms_sent_at);
    }

    public function test_explicit_send_uses_edited_preview_once_and_does_not_modify_customer_credentials(): void
    {
        $customer = $this->customer();
        $before = $customer->getRawOriginal();
        $controller = app(CustomerCredentialsSmsController::class);
        $preview = $controller->preview($customer)->getData(true);
        $request = Request::create('/', 'POST', ['sms_text' => 'Edited message '.route('customer.login'), 'preview_token' => $preview['preview_token']]);
        self::assertSame(200, $controller->send($request, $customer, app(RawSmsDispatcher::class))->getStatusCode());
        self::assertSame(['Edited message '.route('customer.login')], $this->messages);
        self::assertNotNull($customer->fresh()->credentials_sms_sent_at);
        self::assertSame($before['password'], $customer->fresh()->getRawOriginal('password'));
        self::assertSame($before['mobile'], $customer->fresh()->mobile);
        $this->expectException(ValidationException::class);
        $controller->send($request, $customer, app(RawSmsDispatcher::class));
    }

    public function test_changed_mobile_invalidates_old_preview(): void
    {
        $customer = $this->customer();
        $controller = app(CustomerCredentialsSmsController::class);
        $preview = $controller->preview($customer)->getData(true);
        $customer->update(['mobile' => '09123456780']);
        $this->expectException(ValidationException::class);
        $controller->send(Request::create('/', 'POST', ['sms_text' => 'message', 'preview_token' => $preview['preview_token']]), $customer->fresh(), app(RawSmsDispatcher::class));
    }

    public function test_unavailable_password_requires_saved_new_password(): void
    {
        $customer = $this->customer();
        $customer->update(['password_print_encrypted' => null]);
        $this->expectException(ValidationException::class);
        app(CustomerCredentialsSmsController::class)->preview($customer->fresh());
    }

    public function test_draft_form_credentials_can_be_previewed_and_sent_before_customer_is_saved(): void
    {
        $controller = app(CustomerCredentialsSmsController::class);
        $data = ['mobile' => '09123456789', 'password' => 'draftSecret123'];
        $preview = $controller->draftPreview(Request::create('/', 'POST', $data))->getData(true);
        self::assertStringContainsString('draftSecret123', $preview['body']);
        self::assertStringContainsString(route('customer.login'), $preview['body']);
        self::assertSame(0, Customer::count());
        self::assertSame([], $this->messages);
        $request = Request::create('/', 'POST', $data + ['sms_text' => $preview['body'], 'preview_token' => $preview['preview_token']]);
        self::assertSame(200, $controller->draftSend($request, app(RawSmsDispatcher::class))->getStatusCode());
        self::assertSame([$preview['body']], $this->messages);
        self::assertSame(0, Customer::count());
        $this->expectException(ValidationException::class);
        $controller->draftSend($request, app(RawSmsDispatcher::class));
    }

    public function test_changing_draft_password_rejects_old_preview(): void
    {
        $controller = app(CustomerCredentialsSmsController::class);
        $data = ['mobile' => '09123456789', 'password' => 'draftSecret123'];
        $preview = $controller->draftPreview(Request::create('/', 'POST', $data))->getData(true);
        $data['password'] = 'changedSecret123';
        $this->expectException(ValidationException::class);
        $controller->draftSend(Request::create('/', 'POST', $data + ['sms_text' => $preview['body'], 'preview_token' => $preview['preview_token']]), app(RawSmsDispatcher::class));
    }

    public function test_draft_credentials_cannot_be_sent_for_an_existing_customer_mobile(): void
    {
        $this->customer();
        $this->expectException(ValidationException::class);
        app(CustomerCredentialsSmsController::class)->draftPreview(Request::create('/', 'POST', ['mobile' => '09123456789', 'password' => 'draftSecret123']));
    }

    public function test_customer_form_renders_standalone_send_button(): void
    {
        $html = app(CustomerController::class)->index(Request::create('/', 'GET'))->with('errors', new ViewErrorBag)->render();
        self::assertStringContainsString('id="cust-send-credentials"', $html);
        self::assertStringNotContainsString('id="cust-send-chk"', $html);
    }

    public function test_system_template_edits_are_used_for_saved_and_draft_credentials(): void
    {
        $template = \App\Models\SmsTemplate::where('template_key', 'default_customer_credentials')->firstOrFail();
        $body = '{{customer_name}} | {{username}} | {{password}} | {{app_url}}';
        $this->put(route('admin.sms.templates.update', $template), [
            'title' => $template->title, 'category' => $template->category, 'body' => $body,
        ])->assertSessionHasNoErrors();
        self::assertSame($body, $template->fresh()->body);
        self::assertTrue($template->fresh()->is_system);

        $customer = $this->customer();
        $controller = app(CustomerCredentialsSmsController::class);
        $preview = $controller->preview($customer)->getData(true);
        self::assertSame('Test User | 09123456789 | secret123 | '.route('customer.login'), $preview['body']);
        self::assertSame([], $this->messages);
        $controller->send(Request::create('/', 'POST', ['sms_text' => $preview['body'], 'preview_token' => $preview['preview_token']]), $customer, app(RawSmsDispatcher::class));

        $draft = ['mobile' => '09123456780', 'password' => 'draftSecret123', 'first_name' => 'New', 'last_name' => 'Member'];
        $preview = $controller->draftPreview(Request::create('/', 'POST', $draft))->getData(true);
        self::assertSame('New Member | 09123456780 | draftSecret123 | '.route('customer.login'), $preview['body']);
        $controller->draftSend(Request::create('/', 'POST', $draft + ['sms_text' => $preview['body'], 'preview_token' => $preview['preview_token']]), app(RawSmsDispatcher::class));
        self::assertCount(2, $this->messages);
        self::assertSame($preview['body'], $this->messages[1]);
        self::assertSame(1, Customer::count());

        (require database_path('migrations/2026_11_09_216000_seed_customer_credentials_sms_template.php'))->up();
        self::assertSame($body, $template->fresh()->body);
    }
}
