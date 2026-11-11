<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendSmsFreeSendCampaignBatchJob;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\SmsFreeSendCampaign;
use App\Models\SmsFreeSendCampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class SmsFreeSendAllCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_campaign_include_both_sms_settings_without_changing_them(): void
    {
        Bus::fake();
        $admin = Admin::create(['username' => 'owner', 'email' => 'owner@example.test', 'name' => 'Owner', 'password' => 'password', 'is_active' => true, 'is_super_admin' => true]);
        $this->actingAs($admin, 'admin');
        foreach ([true, false] as $index => $enabled) {
            Customer::create([
                'customer_code' => (string) ($index + 1), 'username' => 'customer'.$index,
                'first_name' => 'Test', 'last_name' => 'Customer'.$index,
                'mobile' => '0912345678'.$index, 'password' => 'password', 'sms_sending_enabled' => $enabled,
            ]);
        }

        $this->postJson(route('admin.sms.free-send.audience-preview'), ['recipient_source' => 'all_customers'])
            ->assertOk()->assertJsonPath('count', 2);
        $this->post(route('admin.sms.free-send'), ['recipient_source' => 'all_customers', 'message' => 'Test message'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.sms.index'));

        $campaign = SmsFreeSendCampaign::sole();
        self::assertSame(2, (int) $campaign->total_count);
        self::assertSame(Customer::orderBy('id')->pluck('id')->all(), SmsFreeSendCampaignRecipient::orderBy('customer_id')->pluck('customer_id')->all());
        self::assertSame([true, false], Customer::orderBy('id')->get()->map(fn (Customer $customer) => $customer->receivesOutboundSms())->all());
        Bus::assertDispatched(SendSmsFreeSendCampaignBatchJob::class);
    }
}
