<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SmsFreeSendCampaign;
use App\Models\SmsFreeSendCampaignRecipient;
use App\Services\Admin\RawSmsDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendSmsFreeSendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 55;

    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
    ) {}

    public function handle(RawSmsDispatcher $rawSms): void
    {
        $campaign = SmsFreeSendCampaign::query()->find($this->campaignId);
        if ($campaign === null || $campaign->status === 'completed') {
            return;
        }

        $campaign->update(['status' => 'processing']);
        $recipientIds = DB::transaction(function (): array {
            $rows = SmsFreeSendCampaignRecipient::query()
                ->where('campaign_id', $this->campaignId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(2)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $row->update(['status' => 'processing']);
            }

            return $rows->modelKeys();
        });

        foreach ($recipientIds as $index => $recipientId) {
            $recipient = SmsFreeSendCampaignRecipient::query()->find($recipientId);
            if ($recipient === null) {
                continue;
            }

            if ($index > 0) {
                usleep(350_000);
            }

            try {
                $result = $rawSms->send((string) $recipient->recipient, (string) $recipient->message_text, 'admin-free-send', [
                    'source' => 'sms_management_free_send',
                    'admin_id' => (int) ($campaign->created_by_admin_id ?? 0),
                    'campaign_id' => (int) $campaign->id,
                    'customer_id' => (int) ($recipient->customer_id ?? 0),
                ]);

                $recipient->update([
                    'status' => ($result['ok'] ?? false) ? 'sent' : 'failed',
                    'sms_log_id' => $result['sms_log_id'] ?? null,
                    'result_message' => mb_substr((string) ($result['message'] ?? ''), 0, 1000),
                ]);

                SmsFreeSendCampaign::query()->whereKey($campaign->id)->increment(
                    ($result['ok'] ?? false) ? 'sent_count' : 'failed_count'
                );
            } catch (Throwable $e) {
                $recipient->update([
                    'status' => 'failed',
                    'result_message' => mb_substr($e->getMessage(), 0, 1000),
                ]);
                SmsFreeSendCampaign::query()->whereKey($campaign->id)->increment('failed_count');
                Log::warning('sms_free_send_campaign_recipient_failed', [
                    'campaign_id' => (int) $campaign->id,
                    'recipient_id' => (int) $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $hasPending = SmsFreeSendCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->exists();
        if ($hasPending) {
            self::dispatch((int) $campaign->id);

            return;
        }

        $hasProcessing = SmsFreeSendCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'processing')
            ->exists();
        if (! $hasProcessing) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $campaign = SmsFreeSendCampaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        $stuckCount = SmsFreeSendCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'processing')
            ->update([
                'status' => 'failed',
                'result_message' => 'ارسال توسط پردازش صف متوقف شد.',
                'updated_at' => now(),
            ]);

        if ($stuckCount > 0) {
            $campaign->increment('failed_count', $stuckCount);
        }

        $campaign->update(['status' => 'failed', 'completed_at' => now()]);
        Log::error('sms_free_send_campaign_job_failed', [
            'campaign_id' => (int) $campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
