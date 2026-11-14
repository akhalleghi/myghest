<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminPermissionGrant;
use App\Models\ChequeCustomer;
use App\Models\ChequeCustomerMedia;
use App\Services\Cheques\ChequeCustomerMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ChequeCustomerMediaTest extends TestCase
{
    use RefreshDatabase;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }

    private function payload(): array
    {
        return ['first_name' => 'Media', 'last_name' => 'Customer', 'mobile' => '09123456789', 'sms_enabled' => '1', 'auto_reminders_enabled' => '0'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $admin = Admin::create(['username' => 'owner', 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin, 'admin');
    }

    public function test_optional_media_and_shared_editor_do_not_require_documents(): void
    {
        $this->postJson(route('admin.cheques.customers.store'), $this->payload())->assertOk();
        $this->assertDatabaseCount('cheque_customer_media', 0);
        $html = $this->get(route('admin.cheques.index'))->assertOk()->getContent();
        self::assertStringContainsString('data-customer-media-editor', $html);
        self::assertStringContainsString('/admin/cheque-sales/customers', $html);
        self::assertStringContainsString('customer_media[national_card_front]', $html);
        self::assertStringContainsString('id="customer-camera-overlay"', $html);
        self::assertStringContainsString('id="cq-panel-media"', $html);
    }

    public function test_create_then_multipart_edit_preserves_credentials_free_identity_and_other_documents(): void
    {
        $this->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['portrait' => $this->image(), 'national_card_front' => $this->image()]])->assertOk();
        $customer = ChequeCustomer::sole();
        $old = $customer->media()->where('kind', 'portrait')->sole();
        $front = $customer->media()->where('kind', 'national_card_front')->sole();
        Storage::disk('local')->assertExists($old->path);
        $this->post(route('admin.cheques.customers.update', $customer), [...$this->payload(), '_method' => 'PUT', 'customer_media' => ['portrait' => $this->image()]])->assertOk();
        $new = $customer->media()->where('kind', 'portrait')->sole();
        self::assertNotSame($old->path, $new->path);
        Storage::disk('local')->assertMissing($old->path);
        Storage::disk('local')->assertExists($new->path);
        Storage::disk('local')->assertExists($front->path);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_media', 0);
        $this->assertDatabaseCount('customer_loan_files', 0);
    }

    public function test_explicit_removal_happens_only_when_customer_is_saved(): void
    {
        $this->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['portrait' => $this->image()]])->assertOk();
        $customer = ChequeCustomer::sole();
        $path = ChequeCustomerMedia::sole()->path;
        $this->putJson(route('admin.cheques.customers.update', $customer), [...$this->payload(), 'remove_customer_media' => ['portrait']])->assertOk();
        $this->assertDatabaseCount('cheque_customer_media', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_private_download_checks_customer_and_permission_and_hides_disk_paths(): void
    {
        $this->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['portrait' => $this->image()]])->assertOk();
        $customer = ChequeCustomer::sole();
        $media = ChequeCustomerMedia::sole();
        $this->getJson(route('admin.cheques.media.index', $customer))->assertOk()->assertJsonPath('items.0.kind', 'portrait')->assertDontSee('private/cheque-customer-media');
        $url = route('admin.cheques.media.show', ['chequeCustomer' => $customer->id, 'media' => $media->id]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = ChequeCustomer::create([...$this->payload(), 'mobile' => '09123456780', 'membership_at' => today()]);
        $this->get(route('admin.cheques.media.show', ['chequeCustomer' => $other->id, 'media' => $media->id]))->assertNotFound();
        $limited = Admin::create(['username' => 'limited', 'name' => 'Limited', 'email' => 'limited@example.test', 'password' => 'password', 'is_active' => true]);
        AdminPermissionGrant::create(['admin_id' => $limited->id, 'permission_key' => 'customers.view']);
        $this->actingAs($limited, 'admin')->getJson($url)->assertForbidden();
    }

    public function test_invalid_file_and_customer_validation_do_not_leave_files_or_partial_customers(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['portrait' => UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;')->mimeType('text/plain')]])->assertUnprocessable();
        $this->assertDatabaseCount('cheque_customers', 0);
        self::assertSame([], Storage::disk('local')->allFiles());
        $service = app(ChequeCustomerMediaService::class);
        $request = Request::create('/', 'POST', [], [], ['customer_media' => ['portrait' => $this->image()]]);
        try {
            $service->withChanges($request, function () {
                ChequeCustomer::create([...$this->payload(), 'membership_at' => today()]);
                throw new \RuntimeException('rollback fixture');
            });
            self::fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            self::assertSame('rollback fixture', $exception->getMessage());
        }
        $this->assertDatabaseCount('cheque_customers', 0);
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_image_and_video_limits_match_installment_customer_media(): void
    {
        foreach ([$this->image()->size(5121), UploadedFile::fake()->create('bad.svg', 5, 'image/svg+xml')] as $file) {
            $this->withHeaders(['Accept' => 'application/json'])->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['portrait' => $file]])->assertUnprocessable();
        }
        $video = UploadedFile::fake()->createWithContent('clip.mp4', hex2bin('000000186674797069736f6d0000020069736f6d6d703432'))->mimeType('video/mp4');
        $this->post(route('admin.cheques.customers.store'), [...$this->payload(), 'customer_media' => ['video' => $video]])->assertOk();
        self::assertSame('video/mp4', ChequeCustomerMedia::sole()->mime_type);
        self::assertStringStartsWith('private/cheque-customer-media/', ChequeCustomerMedia::sole()->path);
    }
}
