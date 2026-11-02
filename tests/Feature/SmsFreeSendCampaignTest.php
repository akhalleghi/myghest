<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\SmsManagementController;
use App\Jobs\SendSmsFreeSendCampaignBatchJob;
use App\Models\Admin;
use App\Models\SmsFreeSendCampaign;
use App\Models\SmsFreeSendCampaignRecipient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SmsFreeSendCampaignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'queue.default' => 'database']);
        DB::purge('sqlite');
        Bus::fake();

        Schema::create('sms_free_send_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->string('source', 40);
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_free_send_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('recipient', 20);
            $table->text('message_text');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('sms_log_id')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status', 'id']);
        });
    }

    public function test_free_sms_submission_creates_a_queued_campaign_without_sending_in_the_request(): void
    {
        $admin = new Admin;
        $admin->is_super_admin = true;
        Auth::guard('admin')->setUser($admin);

        $request = Request::create('/admin/sms-management/free-send', 'POST', [
            'recipient_source' => 'manual_numbers',
            'recipients' => '09121234567, 09351234567',
            'message' => 'سلام {{customer_name}}',
        ]);
        $response = app(SmsManagementController::class)->sendFreeSms($request);

        self::assertSame(302, $response->getStatusCode());
        $campaign = SmsFreeSendCampaign::query()->firstOrFail();
        self::assertSame('queued', $campaign->status);
        self::assertSame(2, $campaign->total_count);
        self::assertSame(2, SmsFreeSendCampaignRecipient::query()->where('campaign_id', $campaign->id)->count());
        self::assertSame('سلام {{customer_name}}', SmsFreeSendCampaignRecipient::query()->firstOrFail()->message_text);
        Bus::assertDispatched(SendSmsFreeSendCampaignBatchJob::class);
    }
}
