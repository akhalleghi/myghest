<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\SmsManagementController;
use App\Models\Admin;
use App\Models\SmsTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SmsFreeSendTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('category', 64)->index();
            $table->text('body');
            $table->json('placeholders')->nullable();
            $table->timestamps();
        });
    }

    public function test_free_sms_template_is_saved_without_changing_existing_templates(): void
    {
        $admin = new Admin;
        $admin->is_super_admin = true;
        Auth::guard('admin')->setUser($admin);
        $existing = SmsTemplate::query()->create([
            'title' => 'قالب قبلی',
            'category' => 'installment',
            'body' => 'متن قبلی',
        ]);

        $request = Request::create('/admin/sms-management/free-send/templates', 'POST', [
            'title' => 'اطلاع‌رسانی تعطیلی',
            'body' => "سلام، فروشگاه فردا تعطیل است.\nبا سپاس",
        ]);
        $response = app(SmsManagementController::class)->storeFreeSmsTemplate($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(2, SmsTemplate::query()->count());
        self::assertSame('متن قبلی', $existing->fresh()->body);
        $saved = SmsTemplate::query()->where('category', 'free_send')->firstOrFail();
        self::assertSame('اطلاع‌رسانی تعطیلی', $saved->title);
        self::assertSame("سلام، فروشگاه فردا تعطیل است.\nبا سپاس", $saved->body);
    }
}
