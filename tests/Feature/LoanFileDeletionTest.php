<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Middleware\EnsureAdminHasPermission;
use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class LoanFileDeletionTest extends TestCase
{
    private Customer $customer;

    private CustomerLoanFile $file;

    private Admin $superAdmin;

    private Admin $operator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });
        Schema::create('customer_loan_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('loan_code');
            $table->unsignedBigInteger('amount_toman');
            $table->timestamps();
        });
        Schema::create('customer_loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_loan_file_id')->constrained('customer_loan_files')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_toman');
        });
        Schema::create('customer_loan_installment_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_loan_installment_id')->constrained('customer_loan_installments')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_toman');
        });
        $this->superAdmin = Admin::create(['username' => 'owner', 'is_super_admin' => true]);
        $this->operator = Admin::create(['username' => 'operator', 'is_super_admin' => false]);
        $this->operator->setRelation('permissionGrants', new Collection([
            new AdminPermissionGrant(['permission_key' => 'customers']),
            new AdminPermissionGrant(['permission_key' => 'customers.loans']),
            new AdminPermissionGrant(['permission_key' => 'customers.loans.delete']),
            new AdminPermissionGrant(['permission_key' => 'customers.loans.update']),
        ]));
        $this->customer = Customer::create(['first_name' => 'Test', 'last_name' => 'Customer']);
        $fileId = DB::table('customer_loan_files')->insertGetId(['customer_id' => $this->customer->id, 'loan_code' => 'LF-0000001', 'amount_toman' => 900000]);
        $this->file = CustomerLoanFile::findOrFail($fileId);
        $installmentId = DB::table('customer_loan_installments')->insertGetId(['customer_loan_file_id' => $fileId, 'amount_toman' => 900000]);
        DB::table('customer_loan_installment_payments')->insert(['customer_loan_installment_id' => $installmentId, 'amount_toman' => 100000]);

        $this->app->make(Kernel::class);
        $route = Route::getRoutes()->getByName('admin.customers.loan-files.destroy');
        self::assertNotNull($route);
        // Keep authorization and real route binding; isolate sessions, throttling and activity logging.
        foreach (Route::gatherRouteMiddleware($route) as $middleware) {
            $class = explode(':', $middleware, 2)[0];
            if (! in_array($class, [Authenticate::class, EnsureAdminHasPermission::class, SubstituteBindings::class], true)) {
                $this->withoutMiddleware($class);
            }
        }
    }

    private function url(?Customer $customer = null): string
    {
        return '/admin/customers/'.($customer ?? $this->customer)->id.'/loan-files/'.$this->file->id;
    }

    private function confirmation(): array
    {
        return ['acknowledgements' => [true, true, true], 'confirmation_code' => $this->file->loan_code];
    }

    private function assertRecordsPreserved(): void
    {
        self::assertTrue($this->file->fresh() !== null);
        self::assertSame(1, DB::table('customer_loan_installments')->count());
        self::assertSame(1, DB::table('customer_loan_installment_payments')->count());
    }

    public function test_operator_with_existing_delete_permission_cannot_delete_even_with_complete_confirmation(): void
    {
        self::assertFalse(app(AdminPermissionService::class)->canAccessRoute($this->operator, 'admin.customers.loan-files.destroy'));
        self::assertTrue(app(AdminPermissionService::class)->canAccessRoute($this->operator, 'admin.customers.loan-files.update'));
        $this->actingAs($this->operator, 'admin')->deleteJson($this->url(), [
            ...$this->confirmation(), 'is_super_admin' => true,
        ])->assertForbidden();
        $this->assertRecordsPreserved();
    }

    public function test_controller_independently_rejects_non_primary_admin(): void
    {
        $this->actingAs($this->operator, 'admin');
        try {
            app(CustomerController::class)->destroyLoan(Request::create('/', 'DELETE', $this->confirmation()), $this->customer, $this->file);
            self::fail('The controller must enforce super admin access independently of middleware.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $this->assertRecordsPreserved();
    }

    public function test_guest_cannot_delete(): void
    {
        $this->deleteJson($this->url(), $this->confirmation())->assertUnauthorized();
        $this->assertRecordsPreserved();
    }

    public function test_primary_admin_cannot_delete_without_all_three_acknowledgements(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $this->deleteJson($this->url())->assertUnprocessable()->assertJsonValidationErrors('acknowledgements');
        foreach ([[true], [true, true], [true, true, false]] as $acknowledgements) {
            $this->deleteJson($this->url(), [
                ...$this->confirmation(), 'acknowledgements' => $acknowledgements,
            ])->assertUnprocessable();
            $this->assertRecordsPreserved();
        }
    }

    public function test_primary_admin_must_type_the_matching_loan_code(): void
    {
        $this->actingAs($this->superAdmin, 'admin')->deleteJson($this->url(), [
            ...$this->confirmation(), 'confirmation_code' => 'LF-0000002',
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmation_code');
        $this->assertRecordsPreserved();
    }

    public function test_primary_admin_cannot_delete_a_loan_through_another_customer(): void
    {
        $other = Customer::create(['first_name' => 'Other', 'last_name' => 'Customer']);
        $this->actingAs($this->superAdmin, 'admin')->deleteJson($this->url($other), $this->confirmation())->assertNotFound();
        $this->assertRecordsPreserved();
    }

    public function test_only_confirmed_target_is_deleted_and_other_loans_remain_intact(): void
    {
        $otherId = DB::table('customer_loan_files')->insertGetId(['customer_id' => $this->customer->id, 'loan_code' => 'LF-0000002', 'amount_toman' => 250000]);
        $beforeOther = (array) DB::table('customer_loan_files')->find($otherId);
        $this->actingAs($this->superAdmin, 'admin')->deleteJson($this->url(), $this->confirmation())->assertOk();

        self::assertNull($this->file->fresh());
        self::assertSame(0, DB::table('customer_loan_installments')->count());
        self::assertSame(0, DB::table('customer_loan_installment_payments')->count());
        self::assertSame($beforeOther, (array) DB::table('customer_loan_files')->find($otherId));
        self::assertNotNull($this->customer->fresh());
    }
}
