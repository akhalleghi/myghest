<?php

declare(strict_types=1);
use App\Http\Controllers\Admin\ChequeSalesController;
use App\Models\Admin;
use App\Models\ChequeCustomer;
use App\Models\ChequeInstrument;
use App\Models\ChequeSale;
use App\Services\Cheques\ChequeWorkflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

// Read-only browser fixtures. Every database operation stays in SQLite memory.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'APP_CONFIG_CACHE' => 'bootstrap/cache/config.preview-disabled.php', 'APP_ROUTES_CACHE' => 'bootstrap/cache/routes.preview-disabled.php',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('UI preview refuses persistent databases.');
}
Artisan::call('migrate', ['--force' => true]);
URL::forceRootUrl('http://myghest.test');
$admin = Admin::create(['username' => 'ui-preview', 'email' => 'preview@example.test', 'name' => 'مدیر آزمایشی', 'password' => 'preview-only', 'is_active' => true, 'is_super_admin' => true]);
auth('admin')->setUser($admin);
$buyer = ChequeCustomer::create(['first_name' => 'مشتری', 'last_name' => 'آزمایشی', 'mobile' => '09120000000', 'membership_at' => today(), 'note' => 'یادداشت آزمایشی مشتری', 'address' => 'آدرس آزمایشی']);
$sale = ChequeSale::create(['cheque_customer_id' => $buyer->id, 'code' => 'CS 1', 'purchased_at' => today()->subDays(60), 'purchase_amount_toman' => 900000, 'months' => 2, 'interest_rate' => 5.5556, 'profit_toman' => 100000, 'down_payment_toman' => 0, 'discount_toman' => 0, 'total_toman' => 1000000, 'daily_late_coefficient' => .001, 'daily_early_coefficient' => .001, 'guarantee_by_self' => false, 'owner_details' => ['name' => 'دارنده آزمایشی', 'mobile' => '09120000001', 'father_name' => 'پدر آزمایشی', 'address' => 'نشانی آزمایشی'], 'owner_verified_at' => now(), 'note' => 'یادداشت پرونده آزمایشی']);
$controller = app(ChequeSalesController::class);
foreach ([1 => -40, 2 => 30] as $number => $days) {
    $cheque = $sale->cheques()->create(['sayad_number' => '123456789012345'.$number, 'serial' => '100'.$number, 'bank_name' => 'بانک آزمایشی', 'branch' => 'شعبه مرکزی', 'amount_toman' => 500000, 'due_date' => today()->addDays($days), 'note' => 'توضیح اولیه چک '.$number]);
    $cheque->events()->create(['cheque_customer_id' => $buyer->id, 'action' => 'created', 'payload' => [], 'admin_id' => $admin->id]);
    if ($number === 1) {
        $workflow = app(ChequeWorkflow::class);
        $workflow->act($cheque->id, ['action' => 'bounce', 'version' => 1, 'date' => today()->subDays(39)->toDateString(), 'note' => 'ثبت برگشت آزمایشی']);
        $workflow->act($cheque->id, ['action' => 'receive', 'version' => 2, 'date' => today()->subDay()->toDateString(), 'note' => 'واریز جزئی آزمایشی', 'amount_toman' => 200000, 'method' => 'bank_transfer', 'request_uuid' => (string) Str::uuid()]);
        $workflow->act($cheque->id, ['action' => 'bank', 'version' => 3, 'date' => today()->toDateString(), 'note' => 'ارائه مجدد آزمایشی', 'destination' => ['name' => 'بانک مقصد آزمایشی', 'branch' => 'شعبه بازار', 'account_number' => '0012345', 'details' => 'مشخصات آزمایشی انتقال']]);
        $controller->note(Request::create('/', 'POST', ['note' => 'یادداشت آزمایشی با ثبت نویسنده و زمان']), $cheque);
    }
}
$folder = sys_get_temp_dir().'/myghest-cheque-ui-preview';
if (! is_dir($folder)) {
    mkdir($folder, 0700, true);
}
foreach (['customers', 'cheques'] as $view) {
    $request = Request::create('http://myghest.test/admin/cheque-sales?view='.$view, 'GET');
    $html = $controller->index($request)->with('errors', new ViewErrorBag)->render();
    $html = str_replace('data-base="http://myghest.test/admin/cheque-sales"', 'data-base="http://127.0.0.1:18857/api"', $html);
    $html = str_replace('data-base-url="http://myghest.test/admin/cheque-sales/customers"', 'data-base-url="http://127.0.0.1:18857/api/customers"', $html);
    $html = preg_replace('#(src|href)="http://myghest\.test/(build|css|js|vendor|fonts)/#', '$1="http://127.0.0.1:18857/$2/', $html);
    $html = str_replace('<main class="cq-page"', '<aside style="padding:8px;background:#fef3c7;color:#78350f">پیش‌نمایش آزمایشی؛ بدون داده واقعی</aside><main class="cq-page"', $html);
    file_put_contents($folder.'/'.($view === 'cheques' ? 'index' : 'customers').'.html', $html);
}
$workspaceRequest = Request::create('http://myghest.test/admin/cheque-sales?workspace=1&customer_id='.$buyer->id, 'GET');
$workspace = $controller->index($workspaceRequest)->render();
file_put_contents($folder.'/api/workspace.html', $workspace);
foreach (ChequeInstrument::all() as $cheque) {
    $directory = $folder.'/api/cheques';
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory.'/'.$cheque->id, $controller->details(Request::create('/', 'GET'), $cheque)->getContent());
}
$directory = $folder.'/api/customers/'.$buyer->id;
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
file_put_contents($directory.'/notes', $controller->customerNotes($buyer)->getContent());
file_put_contents($folder.'/preview-photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
file_put_contents($directory.'/media', json_encode(['available' => true, 'items' => [
    ['id' => 1, 'kind' => 'portrait', 'label' => 'عکس آزمایشی مشتری', 'type' => 'image', 'url' => 'http://127.0.0.1:18857/preview-photo.png', 'original_name' => 'preview-photo.png'],
]]));
echo 'Read-only fixture: '.$folder.PHP_EOL;
