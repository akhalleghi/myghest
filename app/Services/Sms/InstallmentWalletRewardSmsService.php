<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\AppSetting;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerWalletTransaction;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Support\IranMobile;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class InstallmentWalletRewardSmsService
{
    public const TYPE = 'installment-wallet-reward';

    public function __construct(
        private readonly RawSmsDispatcher $sms,
        private readonly SmsTemplateRenderer $renderer,
    ) {}

    public function send(int $walletTransactionId): void
    {
        Cache::lock('installment_wallet_reward_sms_'.$walletTransactionId, 120)->block(5, function () use ($walletTransactionId): void {
            $transaction = CustomerWalletTransaction::with(['customer', 'wallet'])->find($walletTransactionId);
            if ($transaction === null || $transaction->direction !== CustomerWalletTransaction::DIRECTION_DEPOSIT
                || ($transaction->meta['purpose'] ?? null) !== 'installment_reward' || $transaction->amount_toman <= 0
                || ! empty($transaction->meta['reward_sms_sent_at'])) {
                return;
            }
            $customer = $transaction->customer;
            $mobile = $customer !== null ? IranMobile::normalize((string) $customer->mobile) : null;
            if ($customer === null || ! $customer->receivesOutboundSms() || $mobile === null) {
                return;
            }
            if (SmsLog::where('type', self::TYPE)->where('status', SmsLog::STATUS_DELIVERED)
                ->where('meta->wallet_transaction_id', $walletTransactionId)->exists()) {
                $transaction->meta = [...$transaction->meta, 'reward_sms_sent_at' => now()->toIso8601String()];
                $transaction->save();
                return;
            }
            $installmentId = (int) ($transaction->meta['installment_id'] ?? 0);
            $installment = CustomerLoanInstallment::with('loanFile')->find($installmentId);
            if ($installment === null || $installment->loanFile === null
                || (int) $installment->loanFile->customer_id !== (int) $customer->id) {
                return;
            }
            // A reward reversed before the queued notification is processed must not be announced.
            if (CustomerWalletTransaction::where('customer_id', $customer->id)->where('id', '>', $transaction->id)
                ->where('direction', CustomerWalletTransaction::DIRECTION_WITHDRAW)
                ->where('meta->purpose', 'installment_reward')->where('meta->installment_id', $installmentId)->exists()) {
                return;
            }
            $template = SmsTemplate::where('template_key', 'default_installment_wallet_reward')->first();
            if ($template === null) {
                throw new RuntimeException('Wallet reward SMS template is missing.');
            }
            $storeName = AppSetting::where('key', 'app_display_name')->value('value');
            $body = $this->renderer->render($template->body, [
                'store_name' => is_string($storeName) && trim($storeName) !== '' ? trim($storeName) : (string) config('app.name'),
                'customer_name' => $customer->fullName(),
                'reward_amount' => number_format($transaction->amount_toman).' تومان',
                'reward_percent' => (string) ($transaction->meta['percent'] ?? ''),
                'wallet_balance' => number_format((int) ($transaction->wallet?->balance_toman ?? 0)).' تومان',
                'loan_code' => (string) $installment->loanFile->loan_code,
                'installment_number' => (string) $installment->sequence,
            ]);
            if ($body === '') {
                throw new RuntimeException('Wallet reward SMS template is empty.');
            }
            $result = $this->sms->send($mobile, $body, self::TYPE, [
                'customer_id' => (int) $customer->id, 'wallet_transaction_id' => $walletTransactionId,
                'installment_id' => $installmentId, 'loan_file_id' => (int) $installment->loanFile->id,
                'template_id' => (int) $template->id, 'automated' => true,
            ]);
            if (! $result['ok']) {
                throw new RuntimeException($result['message']);
            }
            $transaction->meta = [...$transaction->meta, 'reward_sms_sent_at' => now()->toIso8601String()];
            $transaction->save();
        });
    }
}
