<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Middleware\EnsureAdminHasPermission;
use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Models\Customer;
use App\Models\CustomerMedia;
use App\Services\Customers\CustomerMediaService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class CustomerMediaTest extends TestCase
{
    private Customer $customer;

    private CustomerMediaService $media;

    private Admin $owner;

    private array $beforeCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Storage::fake('local');
        Schema::create('admins', function (Blueprint $t): void {
            $t->id();
            $t->string('username');
            $t->boolean('is_super_admin')->default(false);
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t): void {
            $t->id();
            foreach (['customer_code', 'username', 'first_name', 'last_name', 'father_name', 'national_id', 'mobile', 'mobile2', 'phone_landline', 'email', 'password', 'password_print_encrypted', 'city', 'address', 'postal_code'] as $column) {
                $t->text($column)->nullable();
            }
            $t->date('membership_at')->nullable();
            $t->date('birth_date')->nullable();
            $t->timestamps();
        });
        foreach (['customer_bank_accounts', 'customer_referrers'] as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('customer_id');
                $t->timestamps();
            });
        }
        Schema::create('customer_wallets', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('balance_toman');
            $t->boolean('is_locked');
            $t->timestamps();
        });
        $this->customer = Customer::create(['customer_code' => 'C1', 'username' => '09121234567', 'first_name' => 'Existing', 'last_name' => 'Customer', 'mobile' => '09121234567', 'password' => 'previous-password']);
        $this->beforeCustomer = $this->customer->fresh()->getRawOriginal();
        (require database_path('migrations/2026_11_04_150000_create_customer_media_table.php'))->up();
        $this->media = app(CustomerMediaService::class);
        $this->owner = Admin::create(['username' => 'owner', 'is_super_admin' => true]);

        $this->app->make(Kernel::class);
        foreach (['admin.customers.media.index', 'admin.customers.media.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route);
            foreach (Route::gatherRouteMiddleware($route) as $middleware) {
                $class = explode(':', $middleware, 2)[0];
                if (! in_array($class, [Authenticate::class, EnsureAdminHasPermission::class, SubstituteBindings::class], true)) {
                    $this->withoutMiddleware($class);
                }
            }
        }
    }

    private function upload(string $kind = 'portrait', ?UploadedFile $file = null): Request
    {
        return Request::create('/', 'POST', [], [], ['customer_media' => [$kind => $file ?? $this->image()]]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }

    private function save(Request $request): Customer
    {
        return $this->media->withChanges($request, fn () => $this->customer);
    }

    private function customerPayload(): array
    {
        return ['customer_code' => 'NEW', 'first_name' => 'New', 'last_name' => 'Customer', 'father_name' => '', 'national_id' => '', 'mobile' => '09351234567', 'mobile2' => '', 'phone_landline' => '', 'membership_jdate' => '', 'birth_jdate' => '', 'email' => '', 'password' => 'test-password', 'city' => '', 'address' => '', 'postal_code' => '', 'accounts' => [], 'referrers' => []];
    }

    public function test_additive_migration_and_optional_media_preserve_existing_customers(): void
    {
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
        $this->save(Request::create('/', 'POST'));
        self::assertSame(0, CustomerMedia::count());
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
        Schema::drop('customer_media');
        $this->save(Request::create('/', 'POST'));
        self::assertSame([], $this->media->items($this->customer));
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
    }

    public function test_real_customer_store_and_later_update_accept_optional_files(): void
    {
        $request = Request::create('/', 'POST', $this->customerPayload(), [], ['customer_media' => ['portrait' => $this->image()]]);
        self::assertSame(302, app(CustomerController::class)->store($request)->getStatusCode());
        $created = Customer::where('customer_code', 'NEW')->sole();
        self::assertSame(1, $created->media()->count());
        $oldPath = $created->media()->firstOrFail()->path;
        $payload = $this->customerPayload();
        $payload['password'] = '';
        $update = Request::create('/', 'POST', $payload, [], ['customer_media' => ['national_card_front' => $this->image()]]);
        self::assertSame(302, app(CustomerController::class)->update($update, $created)->getStatusCode());
        self::assertSame(2, $created->media()->count());
        Storage::disk('local')->assertExists($oldPath);
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
    }

    public function test_replacement_and_explicit_removal_only_change_the_requested_slot(): void
    {
        $this->save($this->upload());
        $old = $this->customer->media()->sole()->path;
        $this->save($this->upload('national_card_back'));
        $back = $this->customer->media()->where('kind', 'national_card_back')->sole()->path;
        $this->save(Request::create('/', 'POST'));
        Storage::disk('local')->assertExists($old);
        $this->save($this->upload());
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($back);
        self::assertSame(2, $this->customer->media()->count());
        $replacement = $this->customer->media()->where('kind', 'portrait')->sole()->path;
        $this->save(Request::create('/', 'POST', ['remove_customer_media' => ['portrait']]));
        Storage::disk('local')->assertMissing($replacement);
        Storage::disk('local')->assertExists($back);
        self::assertSame(1, $this->customer->media()->count());
    }

    public function test_rollback_preserves_old_file_and_cleans_new_files_and_customer_changes(): void
    {
        $this->save($this->upload());
        $old = $this->customer->media()->sole()->getRawOriginal();
        try {
            $this->media->withChanges($this->upload(), function (): Customer {
                $this->customer->update(['first_name' => 'Must roll back']);
                throw new RuntimeException('Simulated database failure');
            });
            self::fail('Expected the simulated failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated database failure', $exception->getMessage());
        }
        self::assertSame($old, $this->customer->media()->sole()->getRawOriginal());
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
        self::assertSame([$old['path']], Storage::disk('local')->allFiles('private/customer-media'));
    }

    public function test_invalid_and_oversized_files_cannot_change_data(): void
    {
        foreach ([UploadedFile::fake()->create('bad.svg', 5, 'image/svg+xml'), $this->image()->size(5121)] as $file) {
            try {
                $this->save($this->upload('portrait', $file));
                self::fail('Invalid media accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('customer_media.portrait', $exception->errors());
            }
        }
        self::assertSame(0, CustomerMedia::count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_before_migration_fails_safely_without_creating_files(): void
    {
        Schema::drop('customer_media');
        try {
            $this->save($this->upload());
            self::fail('Missing migration must not accept uploads.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('customer_media', $exception->errors());
        }
        self::assertSame([], Storage::disk('local')->allFiles());
        self::assertSame($this->beforeCustomer, $this->customer->fresh()->getRawOriginal());
    }

    public function test_private_files_require_admin_view_permission_and_correct_customer(): void
    {
        $this->save($this->upload());
        $media = $this->customer->media()->sole();
        $url = route('admin.customers.media.show', ['customer' => $this->customer->id, 'media' => $media->id]);
        $this->getJson($url)->assertUnauthorized();
        $operator = Admin::create(['username' => 'operator']);
        $operator->setRelation('permissionGrants', new Collection);
        $this->actingAs($operator, 'admin')->getJson($url)->assertForbidden();
        $operator->setRelation('permissionGrants', new Collection([new AdminPermissionGrant(['permission_key' => 'customers.view'])]));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = Customer::create(['first_name' => 'Other']);
        $this->getJson(route('admin.customers.media.show', ['customer' => $other->id, 'media' => $media->id]))->assertNotFound();
        $response = $this->getJson(route('admin.customers.media.index', $this->customer));
        $response->assertOk()->assertJsonPath('items.0.kind', 'portrait');
        self::assertArrayNotHasKey('path', $response->json('items.0'));
    }

    public function test_video_stream_supports_byte_ranges_and_customer_deletion_cleans_private_files(): void
    {
        $file = UploadedFile::fake()->createWithContent('clip.mp4', hex2bin('000000186674797069736f6d0000020069736f6d6d703432'))->mimeType('video/mp4');
        $this->save($this->upload('video', $file));
        $media = $this->customer->media()->sole();
        $url = route('admin.customers.media.show', ['customer' => $this->customer->id, 'media' => $media->id]);
        $this->actingAs($this->owner, 'admin')->get($url, ['Range' => 'bytes=0-9'])->assertStatus(206);
        $path = $media->path;
        $this->media->deleteCustomer($this->customer);
        Storage::disk('local')->assertMissing($path);
        self::assertSame(0, CustomerMedia::count());
    }
}
