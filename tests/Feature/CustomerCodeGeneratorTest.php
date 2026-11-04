<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Models\Customer;
use App\Services\Customers\CustomerCodeGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class CustomerCodeGeneratorTest extends TestCase
{
    private CustomerCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'hashing.bcrypt.rounds' => 4]);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code', 40)->unique();
            foreach (['username', 'first_name', 'last_name', 'father_name', 'national_id', 'mobile', 'mobile2', 'phone_landline', 'email', 'password', 'password_print_encrypted', 'city', 'address', 'postal_code'] as $column) {
                $table->text($column)->nullable();
            }
            $table->date('membership_at')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_05_07_040000_create_app_settings_table.php'))->up();
        foreach (['customer_bank_accounts', 'customer_referrers'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->timestamps();
            });
        }
        Schema::create('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('balance_toman');
            $table->boolean('is_locked');
            $table->timestamps();
        });
        $this->generator = app(CustomerCodeGenerator::class);
    }

    private function createAutomatic(): Customer
    {
        return DB::transaction(fn () => Customer::create(['customer_code' => $this->generator->allocate()]));
    }

    private function payload(string $mobile, string $code = ''): array
    {
        return ['customer_code' => $code, 'first_name' => 'Test', 'last_name' => 'Customer', 'father_name' => '', 'national_id' => '', 'mobile' => $mobile, 'mobile2' => '', 'phone_landline' => '', 'membership_jdate' => '', 'birth_jdate' => '', 'email' => '', 'password' => 'test-password', 'city' => '', 'address' => '', 'postal_code' => '', 'accounts' => [], 'referrers' => []];
    }

    public function test_first_codes_are_one_two_three_and_deleted_codes_are_not_reused(): void
    {
        self::assertSame('1', $this->createAutomatic()->customer_code);
        $second = $this->createAutomatic();
        self::assertSame('2', $second->customer_code);
        $second->delete();
        self::assertSame('3', $this->createAutomatic()->customer_code);
        self::assertSame(1, DB::table('app_settings')->count());
    }

    public function test_legacy_codes_and_ids_are_preserved_and_first_number_uses_last_id(): void
    {
        DB::table('customers')->insert(['id' => 35, 'customer_code' => 'CUS-999ABC1234', 'first_name' => 'Existing']);
        $before = (array) DB::table('customers')->find(35);
        self::assertSame('36', $this->createAutomatic()->customer_code);
        self::assertSame('37', $this->createAutomatic()->customer_code);
        self::assertSame($before, (array) DB::table('customers')->find(35));
    }

    public function test_existing_numeric_codes_set_the_sequence_without_parsing_random_suffixes(): void
    {
        foreach (['CUS-FFFFFFFFFF', '000120', '9', 'CUS-9999999999'] as $code) {
            Customer::create(['customer_code' => $code]);
        }
        self::assertSame('121', $this->createAutomatic()->customer_code);
        self::assertSame('122', $this->createAutomatic()->customer_code);
        self::assertSame('000120', Customer::where('customer_code', '000120')->sole()->customer_code);
    }

    public function test_explicit_codes_are_preserved_and_numeric_codes_advance_the_counter(): void
    {
        self::assertSame('CUS-MANUAL', $this->generator->allocate('CUS-MANUAL'));
        self::assertSame('00500', $this->generator->allocate('00500'));
        self::assertSame('501', $this->createAutomatic()->customer_code);
        self::assertSame('3', $this->generator->allocate('3'));
        self::assertSame('502', $this->createAutomatic()->customer_code);
    }

    public function test_failed_registration_rolls_back_the_counter(): void
    {
        self::assertSame('1', $this->createAutomatic()->customer_code);
        try {
            DB::transaction(function (): void {
                Customer::create(['customer_code' => $this->generator->allocate()]);
                throw new RuntimeException('Simulated failure');
            });
            self::fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated failure', $exception->getMessage());
        }
        self::assertSame(1, Customer::count());
        self::assertSame('2', $this->createAutomatic()->customer_code);
    }

    public function test_large_numeric_codes_do_not_lose_precision(): void
    {
        Customer::create(['customer_code' => str_repeat('9', 39)]);
        self::assertSame('1'.str_repeat('0', 39), $this->createAutomatic()->customer_code);
        self::assertSame('1'.str_repeat('0', 38).'1', $this->createAutomatic()->customer_code);
    }

    public function test_exhausted_counter_cannot_create_an_invalid_customer(): void
    {
        Customer::create(['customer_code' => str_repeat('9', 40)]);
        try {
            $this->createAutomatic();
            self::fail('Expected capacity validation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('customer_code', $exception->errors());
        }
        self::assertSame(1, Customer::count());
    }

    public function test_form_and_excel_import_share_the_same_counter(): void
    {
        $controller = app(CustomerController::class);
        $controller->store(Request::create('/', 'POST', $this->payload('09121234567')));
        $method = new ReflectionMethod($controller, 'persistImportedCustomerCore');
        $imported = $method->invoke($controller, '', '09351234567', 'Excel', 'Customer', '', '', '09351234567', null, null, null, null, 'test-password', '', '', '');
        self::assertSame('2', $imported->customer_code);
        $controller->store(Request::create('/', 'POST', $this->payload('09191234567')));
        self::assertSame(['1', '2', '3'], Customer::orderBy('id')->pluck('customer_code')->all());
    }

    public function test_editing_keeps_legacy_code_and_tracks_an_explicit_new_numeric_code(): void
    {
        $controller = app(CustomerController::class);
        $controller->store(Request::create('/', 'POST', $this->payload('09121234567', 'CUS-KEEP')));
        $customer = Customer::sole();
        $payload = $this->payload('09121234567');
        $payload['password'] = '';
        $controller->update(Request::create('/', 'POST', $payload), $customer);
        self::assertSame('CUS-KEEP', $customer->fresh()->customer_code);
        self::assertSame(0, DB::table('app_settings')->count());
        $payload['customer_code'] = '300';
        $controller->update(Request::create('/', 'POST', $payload), $customer);
        self::assertSame('300', $customer->fresh()->customer_code);
        self::assertSame('301', $this->createAutomatic()->customer_code);
    }

    public function test_customer_code_sort_is_numeric_and_retains_legacy_codes(): void
    {
        foreach (['10', '2', '100', 'CUS-B', '0003', 'CUS-A'] as $code) {
            Customer::create(['customer_code' => $code]);
        }
        $method = new ReflectionMethod(CustomerController::class, 'applyCustomerCodeSort');
        $controller = app(CustomerController::class);
        self::assertSame(['2', '0003', '10', '100', 'CUS-A', 'CUS-B'], $method->invoke($controller, Customer::query(), 'asc')->pluck('customer_code')->all());
        self::assertSame(['100', '10', '0003', '2', 'CUS-B', 'CUS-A'], $method->invoke($controller, Customer::query(), 'desc')->pluck('customer_code')->all());
    }
}
