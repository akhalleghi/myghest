<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Services\Sms\SmsFreeSendAudienceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SmsFreeSendAudienceResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('customer_code')->nullable();
            $table->boolean('sms_sending_enabled')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->unique();
            $table->unsignedBigInteger('balance_toman')->default(0);
            $table->timestamps();
        });
        Schema::create('customer_loan_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function test_all_customers_includes_disabled_sms_while_other_audiences_preserve_the_setting(): void
    {
        $positiveWalletCustomer = Customer::query()->create([
            'first_name' => 'Wallet', 'last_name' => 'Customer', 'mobile' => '09121234567', 'sms_sending_enabled' => true,
        ]);
        CustomerWallet::query()->create(['customer_id' => $positiveWalletCustomer->id, 'balance_toman' => 250000]);
        Customer::query()->create([
            'first_name' => 'No', 'last_name' => 'Balance', 'mobile' => '09351234567', 'sms_sending_enabled' => true,
        ]);
        $disabledCustomer = Customer::query()->create([
            'first_name' => 'Opted', 'last_name' => 'Out', 'mobile' => '09125551234', 'sms_sending_enabled' => false,
        ]);
        Customer::query()->create([
            'first_name' => 'No', 'last_name' => 'Phone', 'mobile' => null, 'sms_sending_enabled' => true,
        ]);

        $resolver = app(SmsFreeSendAudienceResolver::class);
        $all = $resolver->resolve('all_customers');
        $wallet = $resolver->resolve('wallet_positive_customers');
        $noDebt = $resolver->resolve('no_debt_customers');

        self::assertCount(3, $all);
        self::assertContains($disabledCustomer->id, array_column($all, 'customer_id'));
        self::assertSame([], $resolver->resolve('selected_customers', [$disabledCustomer->id]));
        self::assertFalse($disabledCustomer->fresh()->receivesOutboundSms());
        self::assertCount(1, $wallet);
        self::assertSame(250000, $wallet[0]['wallet_balance_toman']);
        self::assertCount(2, $noDebt);
    }

    public function test_all_customers_keeps_mobile_validation_deduplication_and_legacy_defaults(): void
    {
        foreach (['09121234567', '+989121234567', 'invalid', null] as $mobile) {
            Customer::create(['mobile' => $mobile, 'sms_sending_enabled' => null]);
        }
        $all = app(SmsFreeSendAudienceResolver::class)->resolve('all_customers');
        self::assertCount(1, $all);
        self::assertSame('09121234567', $all[0]['mobile']);
    }
}
