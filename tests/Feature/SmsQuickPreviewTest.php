<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Models\Customer;
use App\Models\SmsTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SmsQuickPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->text('password_print_encrypted')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('mobile')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->text('body');
            $table->string('template_key')->nullable();
            $table->boolean('is_system')->default(false);
            $table->json('placeholders')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_selected_template_preview_renders_credentials_for_the_selected_customer(): void
    {
        $customer = Customer::query()->create([
            'username' => 'customer-123',
            'password' => 'hashed-password',
            'password_print_encrypted' => Crypt::encryptString('Secret$123\\Pass'),
            'first_name' => 'Test',
            'last_name' => 'Customer',
        ]);
        $template = SmsTemplate::query()->create([
            'title' => 'Credentials',
            'category' => 'general',
            'body' => '{{username}}|{{password}}',
        ]);

        $request = Request::create('/admin/customers/'.$customer->id.'/sms-modal-preview', 'GET', [
            'sms_type' => 'welcome',
            'template_id' => $template->id,
        ]);
        $response = app(CustomerController::class)->quickSmsModalPreview($request, $customer);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('customer-123|Secret$123\\Pass', $response->getData(true)['body']);
        self::assertSame($template->id, $response->getData(true)['template_id']);
    }
}
