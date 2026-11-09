<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\SmsPanelSetting;
use App\Services\Sms\Contracts\SmsPanelGateway;
use App\Services\Sms\SmsPanelConnectionResult;
use App\Services\Sms\SmsPanelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

final class InstallmentWalletRewardSmsTest extends TestCase
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

    private function rewardTransaction(): \App\Models\CustomerWalletTransaction
    {
        $customer = $this->customer();
        $type = \App\Models\LoanType::create(['title' => 'Test', 'profit_calculation_method' => 'monthly', 'interest_rate' => 0, 'daily_late_coefficient' => 0, 'daily_early_coefficient' => 0, 'installment_gap_unit' => 'monthly', 'repayment_periods' => [1]]);
        $file = \App\Models\CustomerLoanFile::create([
            'customer_id' => $customer->id, 'loan_type_id' => $type->id, 'loan_code' => 'DS 10',
            'loan_start_date' => now(), 'amount_toman' => 900000, 'installments_count' => 1,
            'installment_interval_count' => 1, 'installment_interval_unit' => 'monthly',
            'installment_amount_toman' => 900000, 'profit_calculation_method' => 'monthly', 'base_interest_rate' => 0, 'effective_interest_rate' => 0,
        ]);
        $installment = \App\Models\CustomerLoanInstallment::create([
            'customer_loan_file_id' => $file->id, 'sequence' => 1, 'amount_toman' => 900000, 'due_date' => now()->addMonth(),
        ]);
        [, $transaction] = app(\App\Services\Wallet\CustomerWalletService::class)->adjust(
            $customer, 'deposit', 27000, 'Reward', null, null, null, null,
            ['purpose' => 'installment_reward', 'installment_id' => $installment->id, 'loan_file_id' => $file->id, 'percent' => 3]);
        return $transaction;
    }

    public function test_customized_template_is_rendered_and_credit_is_announced_once(): void
    {
        $transaction = $this->rewardTransaction();
        \App\Models\SmsTemplate::where('template_key', 'default_installment_wallet_reward')->update([
            'body' => '{{reward_amount}} | {{reward_percent}} | {{wallet_balance}} | {{app_url}}',
        ]);
        $service = app(\App\Services\Sms\InstallmentWalletRewardSmsService::class);
        $service->send($transaction->id);
        self::assertSame(['27,000 تومان | 3 | 27,000 تومان | '.route('customer.login')], $this->messages);
        self::assertNotEmpty($transaction->fresh()->meta['reward_sms_sent_at']);
        \App\Models\SmsLog::query()->delete();
        $service->send($transaction->id);
        self::assertCount(1, $this->messages);
        self::assertSame(27000, $transaction->wallet->fresh()->balance_toman);
    }

    public function test_failed_sms_can_retry_without_altering_wallet_or_reward(): void
    {
        $transaction = $this->rewardTransaction();
        $service = app(\App\Services\Sms\InstallmentWalletRewardSmsService::class);
        $this->succeeds = false;
        try {
            $service->send($transaction->id);
            self::fail('Failed SMS must be retryable.');
        } catch (\RuntimeException) {
            self::assertArrayNotHasKey('reward_sms_sent_at', $transaction->fresh()->meta);
        }
        self::assertSame(27000, $transaction->wallet->fresh()->balance_toman);
        $this->succeeds = true;
        $service->send($transaction->id);
        self::assertNotEmpty($transaction->fresh()->meta['reward_sms_sent_at']);
        self::assertSame(1, \App\Models\CustomerWalletTransaction::count());
    }

    public function test_disabled_customer_and_reversed_reward_are_not_announced(): void
    {
        $transaction = $this->rewardTransaction();
        $transaction->customer->update(['sms_sending_enabled' => false]);
        $service = app(\App\Services\Sms\InstallmentWalletRewardSmsService::class);
        $service->send($transaction->id);
        self::assertSame([], $this->messages);
        $transaction->customer->update(['sms_sending_enabled' => true]);
        [, $reversal] = app(\App\Services\Wallet\CustomerWalletService::class)->adjust(
            $transaction->customer, 'withdraw', 27000, 'Reverse', null, null, null, null, $transaction->meta);
        $service->send($transaction->id);
        $service->send($reversal->id);
        self::assertSame([], $this->messages);
        self::assertSame(0, $transaction->wallet->fresh()->balance_toman);
    }
}
