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

final class AggregateOverdueReminderTest extends TestCase
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

    public function test_reminders_are_grouped_and_not_resent_when_scheduler_runs_again(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-09 10:00:00'));
        $customer = $this->customer();
        $type = \App\Models\LoanType::create(['title' => 'Test', 'profit_calculation_method' => 'monthly', 'interest_rate' => 0, 'daily_late_coefficient' => 0, 'daily_early_coefficient' => 0, 'installment_gap_unit' => 'monthly', 'repayment_periods' => [1]]);
        $file = \App\Models\CustomerLoanFile::create([
            'customer_id' => $customer->id, 'loan_type_id' => $type->id, 'loan_code' => 'DS 10',
            'loan_start_date' => now(), 'amount_toman' => 900000, 'installments_count' => 1,
            'installment_interval_count' => 1, 'installment_interval_unit' => 'monthly',
            'installment_amount_toman' => 900000, 'profit_calculation_method' => 'monthly', 'base_interest_rate' => 0, 'effective_interest_rate' => 0,
        ]);
        $installment = \App\Models\CustomerLoanInstallment::create([
            'customer_loan_file_id' => $file->id, 'sequence' => 1, 'amount_toman' => 900000, 'due_date' => now()->subDays(10),
        ]);
        $file->update(['amount_toman' => 1800000, 'installments_count' => 2]);
        \App\Models\CustomerLoanInstallment::create([
            'customer_loan_file_id' => $file->id, 'sequence' => 2,
            'amount_toman' => 900000, 'due_date' => now()->subDays(3),
        ]);
        $template = \App\Models\SmsTemplate::where('template_key', 'default_customer_overdue_total')->firstOrFail();
        foreach (['sms_reminder_enabled' => '1', 'sms_reminder_overdue_template_id' => (string) $template->id,
            'sms_reminder_overdue_repeat_mode' => 'daily', 'sms_reminder_overdue_days_after' => '1'] as $key => $value) {
            \App\Models\AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $service = app(\App\Services\Sms\InstallmentSmsReminderService::class);
        $customer->update(['overdue_sms_minimum_toman' => 1800001]);
        self::assertSame(0, $service->run(now(), false, true)->overdueSent);
        $installment->update(['paid_amount_toman' => 1]);
        $vars = app(\App\Services\Sms\InstallmentSmsTemplateVarsBuilder::class)->buildOverdueTotal($customer);
        self::assertSame('1799999', $vars['overdue_total_toman']);
        $customer->update(['overdue_sms_minimum_toman' => 1799999]);
        self::assertSame(0, $service->run(now(), false, true)->overdueSent);
        self::assertSame([], $this->messages);
        self::assertSame(0, \App\Models\InstallmentSmsReminderDispatch::count());
        $customer->update(['overdue_sms_minimum_toman' => 1799998]);
        self::assertSame(1, $service->run(now(), true, true)->overdueSent);
        self::assertSame([], $this->messages);
        $customer->update(['overdue_sms_minimum_toman' => null]);
        $installment->update(['paid_amount_toman' => 0]);
        $this->succeeds = false;
        self::assertSame(1, $service->run(now(), false, true)->failed);
        self::assertSame(0, \App\Models\InstallmentSmsReminderDispatch::count());
        $this->messages = [];
        $this->succeeds = true;
        self::assertSame(1, $service->run(now(), false, true)->overdueSent);
        self::assertCount(1, $this->messages);
        self::assertStringContainsString('1,800,000', $this->messages[0]);
        self::assertSame(2, \App\Models\InstallmentSmsReminderDispatch::count());
        self::assertSame(0, $service->run(now(), false, true)->overdueSent);
        self::assertCount(1, $this->messages);
        self::assertSame(1, $service->run(now()->addDay(), false, true)->overdueSent);
        \Carbon\Carbon::setTestNow();
    }
    public function test_minimum_can_be_saved_cleared_and_validated_for_each_customer(): void
    {
        $customer = $this->customer();
        $other = Customer::create(['customer_code' => '2', 'username' => '09123456780', 'first_name' => 'Other', 'last_name' => 'User', 'mobile' => '09123456780', 'password' => 'secret123']);
        $url = route('admin.customers.overdue-sms-minimum', $customer);
        $this->putJson($url, ['overdue_sms_minimum_toman' => 1000000])->assertOk()->assertJsonPath('overdue_sms_minimum_toman', 1000000);
        self::assertSame(1000000, $customer->fresh()->overdue_sms_minimum_toman);
        self::assertNull($other->fresh()->overdue_sms_minimum_toman);
        $this->getJson(route('admin.customers.loan-manage-modal-context', $customer))->assertOk()->assertJsonPath('overdue_sms_minimum_toman', 1000000);
        foreach ([-1, 1.5, 1000000000000, 'invalid'] as $invalid) {
            $this->putJson($url, ['overdue_sms_minimum_toman' => $invalid])->assertUnprocessable();
        }
        $this->putJson($url, [])->assertUnprocessable();
        $this->putJson($url, ['overdue_sms_minimum_toman' => null])->assertOk();
        self::assertNull($customer->fresh()->overdue_sms_minimum_toman);
        $limitedAdmin = Admin::create(['username' => 'limited', 'email' => 'limited@example.test', 'name' => 'Limited', 'password' => 'password', 'is_active' => true, 'is_super_admin' => false]);
        $this->actingAs($limitedAdmin, 'admin')->putJson($url, ['overdue_sms_minimum_toman' => 1])->assertForbidden();
        self::assertNull($customer->fresh()->overdue_sms_minimum_toman);
    }

}
