<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChequeCustomer;
use App\Models\ChequeEvent;
use App\Models\ChequeInstrument;
use App\Models\ChequeSale;
use App\Models\SmsTemplate;
use App\Rules\IranNationalId;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Cheques\ChequeCustomerMediaService;
use App\Services\Cheques\ChequeFinance;
use App\Services\Cheques\ChequeSms;
use App\Services\Cheques\ChequeStatistics;
use App\Services\Cheques\ChequeVerification;
use App\Services\Cheques\ChequeWorkflow;
use App\Services\Sms\SmsTemplateRenderer;
use App\Support\IranMobile;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChequeSalesController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredCheques($request);
        $cheques = (clone $query)->latest('id')->paginate(30, ['*'], 'cheque_page')->withQueryString();
        $finance = app(ChequeFinance::class);
        $totals = ['count' => 0, 'face' => 0, 'paid' => 0, 'late' => 0, 'early' => 0, 'remaining' => 0];
        (clone $query)->chunkById(100, function ($rows) use (&$totals, $finance): void {
            foreach ($rows as $row) {
                $b = $finance->balance($row);
                $totals['count']++;
                $totals['face'] += $row->amount_toman;
                $totals['paid'] += $b['paid_toman'];
                $totals['late'] += $b['late_remaining_toman'];
                $totals['early'] += $b['early_earned_toman'];
                $totals['remaining'] += $b['remaining_toman'];
            }
        });
        $selectedCustomer = $request->filled('customer_id') ? ChequeCustomer::findOrFail($request->integer('customer_id')) : null;

        if ($request->boolean('workspace')) {
            abort_unless($selectedCustomer, 422, 'مشتری را انتخاب کنید.');

            return view('admin.cheques.partials.customer-workspace', compact('selectedCustomer', 'cheques', 'totals', 'finance'));
        }

        $customers = ChequeCustomer::query()->when($request->filled('q'), function ($q) use ($request): void {
            $this->searchCustomer($q, $request->string('q')->toString());
        })->with('sales.cheques.receipts')->latest('id')->paginate(25, ['*'], 'customer_page')->withQueryString();
        foreach ($customers as $customer) {
            foreach ($customer->sales as $sale) {
                foreach ($sale->cheques as $cheque) {
                    $cheque->setRelation('sale', $sale);
                }
            }
        }

        return view('admin.cheques.index', [
            'customers' => $customers, 'cheques' => $cheques, 'totals' => $totals, 'selectedCustomer' => null,
            'initialCustomerId' => $selectedCustomer?->id,
            'finance' => $finance, 'statistics' => app(ChequeStatistics::class)->build(),
            'activeView' => $request->routeIs('admin.cheques.reports') ? 'reports' : ($selectedCustomer ? 'customers' : (string) $request->query('view', 'customers')),
            'customerMediaAvailable' => app(ChequeCustomerMediaService::class)->available(),
        ]);
    }

    public function saveCustomer(Request $request, ?ChequeCustomer $chequeCustomer = null)
    {
        foreach (['mobile', 'national_id', 'postal_code'] as $key) {
            if ($request->has($key)) {
                $request->merge([$key => JalaliInputParser::toEnglishDigits(trim((string) $request->input($key))) ?: null]);
            }
        }
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'national_id' => ['nullable', 'digits:10', new IranNationalId, Rule::unique('cheque_customers')->ignore($chequeCustomer?->id)],
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/D', Rule::unique('cheque_customers')->ignore($chequeCustomer?->id)],
            'phone_landline' => ['nullable', 'string', 'max:32'], 'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'], 'postal_code' => ['nullable', 'digits:10'], 'email' => ['nullable', 'email', 'max:191'],
            'sms_enabled' => ['required', 'boolean'], 'auto_reminders_enabled' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:5000'],
            'verification_token' => ['nullable', 'uuid'],
        ]);
        $token = $data['verification_token'] ?? null;
        unset($data['verification_token']);
        $customer = app(ChequeCustomerMediaService::class)->withChanges($request, fn () => DB::transaction(function () use ($data, $token, $chequeCustomer) {
            $customer = $chequeCustomer ? ChequeCustomer::lockForUpdate()->findOrFail($chequeCustomer->id) : new ChequeCustomer;
            $before = $customer->getAttributes();
            if ($customer->mobile !== $data['mobile']) {
                $customer->mobile_verified_at = null;
            }
            if ($token) {
                app(ChequeVerification::class)->consume($token, 'customer', ['mobile' => $data['mobile']]);
                $customer->mobile_verified_at = now();
            }
            $customer->fill($data);
            $customer->membership_at ??= today();
            $customer->save();
            ChequeEvent::create(['cheque_customer_id' => $customer->id, 'action' => $chequeCustomer ? 'customer_updated' : 'customer_created', 'payload' => ['before' => $before, 'after' => $customer->getAttributes()], 'admin_id' => auth('admin')->id()]);

            return $customer;
        }));

        return response()->json(['message' => 'مشتری چکی ذخیره شد.', 'customer_id' => $customer->id]);
    }

    public function quote(Request $request)
    {
        $data = $this->saleData($request, false);

        return response()->json(app(ChequeFinance::class)->contract($data));
    }

    public function storeSale(Request $request, ChequeCustomer $chequeCustomer)
    {
        $data = $this->saleData($request);
        $owner = $this->owner($request, $chequeCustomer);
        $quote = app(ChequeFinance::class)->contract($data);
        if ($quote['total_toman'] < 1 || array_sum(array_column($data['cheques'], 'amount_toman')) !== $quote['total_toman']) {
            throw ValidationException::withMessages(['cheques' => 'جمع مبالغ چک‌ها باید دقیقاً برابر مبلغ خرید با کارمزد، پس از کسر پیش‌پرداخت و تخفیف باشد: '.number_format($quote['total_toman']).' تومان']);
        }
        foreach ($data['cheques'] as $row) {
            if ($row['due_date'] < $data['purchased_at']) {
                throw ValidationException::withMessages(['cheques' => 'سررسید چک نباید قبل از خرید باشد.']);
            }
        }
        $sale = DB::transaction(function () use ($request, $chequeCustomer, $data, $owner, $quote) {
            $verified = $request->filled('verification_token');
            if (! $request->boolean('guarantee_by_self', true) || $verified) {
                app(ChequeVerification::class)->consume((string) $request->input('verification_token'), 'owner', $this->ownerContext($chequeCustomer, $data, $owner));
            }
            $rows = $data['cheques'];
            unset($data['cheques']);
            $sale = ChequeSale::create([...$data, ...$quote, 'cheque_customer_id' => $chequeCustomer->id,
                'code' => (string) Str::uuid(), 'owner_details' => $owner, 'guarantee_by_self' => $request->boolean('guarantee_by_self', true),
                'owner_verified_at' => $verified ? now() : null, 'created_by_admin_id' => auth('admin')->id()]);
            $sale->update(['code' => 'CS '.$sale->id]);
            foreach ($rows as $row) {
                $cheque = $sale->cheques()->create($row);
                ChequeEvent::create(['cheque_customer_id' => $chequeCustomer->id, 'cheque_instrument_id' => $cheque->id,
                    'action' => 'created', 'payload' => ['contract' => $sale->getAttributes(), 'cheque' => $cheque->getAttributes()], 'admin_id' => auth('admin')->id()]);
            }

            return $sale;
        });

        return response()->json(['message' => 'پرونده فروش چکی '.$sale->code.' ثبت شد.']);
    }

    public function action(Request $request, ChequeInstrument $cheque)
    {
        $this->normalizeAmounts($request);
        abort_if(($request->input('action') === 'receive') !== $request->routeIs('admin.cheques.receipts'), 403);
        if (is_array($request->input('destination'))) {
            $destination = $request->input('destination');
            foreach (['mobile', 'national_id', 'account_number', 'iban'] as $key) {
                if (isset($destination[$key]) && is_string($destination[$key])) {
                    $destination[$key] = strtoupper(JalaliInputParser::toEnglishDigits(trim($destination[$key])));
                }
            }
            $request->merge(['destination' => $destination]);
        }
        $data = $request->validate([
            'action' => ['required', Rule::in(['receive', 'bounce', 'bank', 'person', 'return_owner', 'return_buyer', 'recover'])],
            'version' => ['required', 'integer', 'min:1'], 'date' => ['required', 'string'],
            'amount_toman' => ['required_if:action,receive', 'nullable', 'integer', 'min:1', 'max:1000000000000'],
            'request_uuid' => ['required_if:action,receive', 'nullable', 'uuid'],
            'method' => ['required_if:action,receive', 'nullable', Rule::in(['bank_transfer', 'cash', 'cheque_collection'])],
            'destination' => ['required_if:action,bank,person', 'nullable', 'array:name,mobile,details,branch,account_number,iban,national_id,address'],
            'destination.name' => ['required_if:action,bank,person', 'nullable', 'string', 'max:200'],
            'destination.mobile' => ['nullable', 'regex:/^09[0-9]{9}$/D'],
            'destination.details' => ['nullable', 'string', 'max:2000'],
            'destination.branch' => ['nullable', 'string', 'max:120'],
            'destination.account_number' => ['nullable', 'string', 'regex:/^[0-9]{1,30}$/D'],
            'destination.iban' => ['nullable', 'string', 'regex:/^IR[0-9]{24}$/D'],
            'destination.national_id' => ['nullable', 'digits:10', new IranNationalId],
            'destination.address' => ['nullable', 'string', 'max:2000'],
            'note' => ['required', 'string', 'max:2000'], 'verification_token' => ['nullable', 'uuid'],
        ]);
        $data['date'] = $this->date($data['date']);
        app(ChequeWorkflow::class)->act($cheque->id, $data);

        return response()->json(['message' => 'عملیات و سابقه آن ثبت شد.']);
    }

    public function details(Request $request, ChequeInstrument $cheque)
    {
        $cheque->load(['sale.customer', 'sale.cheques', 'receipts.admin:id,name,first_name,last_name', 'events' => fn ($q) => $q->latest('id'), 'events.admin:id,name,first_name,last_name']);

        $at = $request->filled('at') ? Carbon::parse($this->date((string) $request->input('at'))) : null;

        return response()->json(['cheque' => $cheque, 'balance' => app(ChequeFinance::class)->balance($cheque, $at)]);
    }

    public function customerNotes(ChequeCustomer $chequeCustomer)
    {
        $notes = $chequeCustomer->events()->where('action', 'note')
            ->with(['admin:id,name,first_name,last_name', 'cheque.sale:id,code'])->latest('id')->get();

        return response()->json(['customer' => $chequeCustomer->only(['id', 'first_name', 'last_name', 'note']),
            'notes' => $notes,
            'sales' => $chequeCustomer->sales()->with('cheques:id,cheque_sale_id,sayad_number,note')->get(['id', 'code', 'note'])]);
    }

    public function customerNoteStore(Request $request, ChequeCustomer $chequeCustomer)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $chequeCustomer->events()->create(['action' => 'note', 'payload' => $data, 'admin_id' => auth('admin')->id()]);

        return response()->json(['message' => 'یادداشت مشتری چکی ثبت شد.']);
    }

    public function note(Request $request, ChequeInstrument $cheque)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($data, $cheque): void {
            $row = ChequeInstrument::with('sale')->lockForUpdate()->findOrFail($cheque->id);
            $row->events()->create(['cheque_customer_id' => $row->sale->cheque_customer_id, 'action' => 'note', 'payload' => $data, 'admin_id' => auth('admin')->id()]);
        });

        return response()->json(['message' => 'یادداشت در سابقه چک ثبت شد.']);
    }

    public function verificationPreview(Request $request)
    {
        $purpose = $request->validate(['purpose' => ['required', Rule::in(['customer', 'owner', 'return'])]])['purpose'];
        if ($purpose === 'customer') {
            $mobile = IranMobile::normalize((string) $request->input('mobile'));
            if (! $mobile) {
                throw ValidationException::withMessages(['mobile' => 'شماره موبایل معتبر نیست.']);
            }
            $name = $request->validate(['name' => ['required', 'string', 'max:200']])['name'];

            return response()->json(app(ChequeVerification::class)->preview($purpose, ['mobile' => $mobile], $mobile, $name));
        }
        if ($purpose === 'owner') {
            $customer = ChequeCustomer::findOrFail($request->integer('customer_id'));
            $data = $this->saleData($request);
            $owner = $this->owner($request, $customer);

            return response()->json(app(ChequeVerification::class)->preview($purpose, $this->ownerContext($customer, $data, $owner), $owner['mobile'], $owner['name'], ['cheque_buyer_name' => $customer->fullName(), 'cheque_amount' => number_format(app(ChequeFinance::class)->contract($data)['total_toman']).' تومان']));
        }
        $cheque = ChequeInstrument::with('sale.customer')->findOrFail($request->integer('cheque_id'));
        $target = $request->validate(['target' => ['required', Rule::in(['return_owner', 'return_buyer'])]])['target'];
        $party = $target === 'return_owner' ? $cheque->sale->owner_details : ['name' => $cheque->sale->customer->fullName(), 'mobile' => $cheque->sale->customer->mobile];

        return response()->json(app(ChequeVerification::class)->preview('return', ['cheque_id' => $cheque->id, 'version' => (int) $cheque->version, 'target' => $target], $party['mobile'], $party['name'], ['cheque_number' => $cheque->sayad_number]));
    }

    public function verificationSend(Request $request)
    {
        $data = $request->validate(['preview_token' => ['required', 'uuid'], 'body' => ['required', 'string', 'max:1000']]);

        return response()->json(app(ChequeVerification::class)->send($data['preview_token'], $data['body']));
    }

    public function verificationConfirm(Request $request)
    {
        $data = $request->validate(['session' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);

        return response()->json(app(ChequeVerification::class)->verify($data['session'], $data['code']));
    }

    public function smsPreview(Request $request, ChequeInstrument $cheque)
    {
        $type = $request->validate(['type' => ['required', Rule::in(['received', 'cleared', 'bounced', 'due'])]])['type'];
        if (($type === 'cleared' && ! $cheque->cleared_at) || ($type === 'bounced' && ! $cheque->bounced_at)) {
            throw ValidationException::withMessages(['type' => 'وضعیت چک با نوع پیامک انتخاب‌شده مطابقت ندارد.']);
        }
        $token = (string) Str::uuid();
        Cache::put('cheque_sms:'.$token, ['id' => $cheque->id, 'version' => (int) $cheque->version, 'admin_id' => auth('admin')->id()], now()->addMinutes(5));

        return response()->json(['body' => app(ChequeSms::class)->text($cheque, $type), 'preview_token' => $token]);
    }

    public function customerSmsPreview(ChequeCustomer $chequeCustomer)
    {
        $chequeCustomer->load('sales.cheques.receipts');
        $total = 0;
        foreach ($chequeCustomer->sales as $sale) {
            foreach ($sale->cheques as $cheque) {
                $cheque->setRelation('sale', $sale);
                $total += app(ChequeFinance::class)->balance($cheque)['remaining_toman'];
            }
        }
        $body = app(SmsTemplateRenderer::class)->render(SmsTemplate::where('template_key', 'cheque_customer_summary')->firstOrFail()->body,
            ['customer_name' => $chequeCustomer->fullName(), 'cheque_debt' => number_format($total).' تومان', 'wallet_balance' => number_format($chequeCustomer->wallet_balance_toman).' تومان']);
        $token = (string) Str::uuid();
        Cache::put('cheque_customer_sms:'.$token, ['customer_id' => $chequeCustomer->id, 'mobile' => $chequeCustomer->mobile, 'admin_id' => auth('admin')->id()], now()->addMinutes(5));

        return response()->json(['body' => $body, 'preview_token' => $token]);
    }

    public function customerSmsSend(Request $request, ChequeCustomer $chequeCustomer)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'preview_token' => ['required', 'uuid']]);
        $payload = Cache::pull('cheque_customer_sms:'.$data['preview_token']);
        if (! $chequeCustomer->sms_enabled || $payload !== ['customer_id' => $chequeCustomer->id, 'mobile' => $chequeCustomer->mobile, 'admin_id' => auth('admin')->id()]) {
            throw ValidationException::withMessages(['body' => 'پیامک مشتری غیرفعال است یا پیش‌نمایش معتبر نیست.']);
        }
        $result = app(RawSmsDispatcher::class)->send($chequeCustomer->mobile, $data['body'], 'cheque-notification', ['module' => 'cheque_sales', 'cheque_customer_id' => $chequeCustomer->id, 'admin_id' => auth('admin')->id()]);

        return response()->json(['message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    public function smsSend(Request $request, ChequeInstrument $cheque)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'preview_token' => ['required', 'uuid']]);
        $payload = Cache::pull('cheque_sms:'.$data['preview_token']);
        if (! is_array($payload) || $payload !== ['id' => $cheque->id, 'version' => (int) $cheque->version, 'admin_id' => auth('admin')->id()]) {
            throw ValidationException::withMessages(['body' => 'پیش‌نمایش منقضی است یا چک تغییر کرده است.']);
        }
        $result = app(ChequeSms::class)->send($cheque, $data['body']);

        return response()->json(['message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    public function wallet(Request $request, ChequeCustomer $chequeCustomer)
    {
        $this->normalizeAmounts($request);
        $data = $request->validate(['amount_toman' => ['required', 'integer', 'min:-1000000000000', 'max:1000000000000', 'not_in:0'], 'note' => ['required', 'string', 'max:1000'], 'request_uuid' => ['required', 'uuid']]);
        DB::transaction(function () use ($data, $chequeCustomer): void {
            $customer = ChequeCustomer::lockForUpdate()->findOrFail($chequeCustomer->id);
            if ($old = $customer->events()->where('action', 'wallet')->where('payload->request_uuid', $data['request_uuid'])->first()) {
                if ((int) $old->payload['amount_toman'] !== (int) $data['amount_toman']) {
                    throw ValidationException::withMessages(['request_uuid' => 'شناسه درخواست قبلاً برای مبلغ دیگری استفاده شده است.']);
                }

                return;
            }
            $before = (int) $customer->wallet_balance_toman;
            if ($before + $data['amount_toman'] < 0) {
                throw ValidationException::withMessages(['amount_toman' => 'موجودی کیف پول کافی نیست.']);
            }
            $customer->wallet_balance_toman = $before + $data['amount_toman'];
            $customer->save();
            $customer->events()->create(['action' => 'wallet', 'payload' => [...$data, 'before' => $before, 'after' => $customer->wallet_balance_toman], 'admin_id' => auth('admin')->id()]);
        });

        return response()->json(['message' => 'تراکنش کیف پول چکی ثبت شد.']);
    }

    public function export(Request $request)
    {
        $query = $this->filteredCheques($request);

        return response()->streamDownload(function () use ($query): void {
            echo "\xFF\xFE";
            $write = function (array $cells): void {
                $cells = array_map(function ($value) {
                    $s = str_replace(["\t", "\r", "\n"], ' ', (string) $value);

                    return preg_match('/^[\s]*[=+@-]/u', $s) ? "'".$s : $s;
                }, $cells);
                echo mb_convert_encoding(implode("\t", $cells)."\r\n", 'UTF-16LE', 'UTF-8');
            };
            $write(['پرونده چکی', 'کد مشتری چکی', 'مشتری', 'موبایل', 'دارنده چک', 'موبایل دارنده', 'تاریخ خرید', 'سررسید واریز به مشتری', 'اصل خرید پرونده', 'ماه', 'درصد کارمزد', 'کارمزد پرونده', 'پیش‌پرداخت پرونده', 'تخفیف پرونده', 'کل قرارداد', 'ضریب روزانه دیرکرد', 'ضریب روزانه زودکرد', 'شماره صیاد', 'شماره چک', 'بانک', 'شعبه', 'سررسید', 'روز تا سررسید', 'مبلغ چک', 'پرداختی', 'دیرکرد مانده', 'زودکرد محقق‌شده', 'زودکرد تسویه امروز', 'مانده بدهی امروز', 'پوشه', 'محل نگهداری', 'مقصد', 'برگشت', 'وصول', 'یادداشت چک', 'یادداشت پرونده']);
            $query->chunkById(100, function ($rows) use ($write): void {
                foreach ($rows as $c) {
                    $s = $c->sale;
                    $b = app(ChequeFinance::class)->balance($c);
                    $j = fn ($d) => $d ? Jalali::instance($d)->format('Y/m/d') : '';
                    $write([$s->code, 'CC '.$s->customer->id, $s->customer->fullName(), $s->customer->mobile, $s->owner_details['name'], $s->owner_details['mobile'], $j($s->purchased_at), $j($s->disbursement_due_date), $s->purchase_amount_toman, $s->months, $s->interest_rate, $s->profit_toman, $s->down_payment_toman, $s->discount_toman, $s->total_toman, $s->daily_late_coefficient, $s->daily_early_coefficient,
                        $c->sayad_number, $c->serial, $c->bank_name, $c->branch, $j($c->due_date), $b['days_to_due'], $c->amount_toman, $b['paid_toman'], $b['late_remaining_toman'], $b['early_earned_toman'], $b['early_quote_toman'], $b['remaining_toman'], ChequeInstrument::FOLDERS[$c->folder], $c->custody, json_encode($c->destination, JSON_UNESCAPED_UNICODE), $j($c->bounced_at), $j($c->cleared_at), $c->note, $s->note]);
                }
            });
        }, 'cheque-sales-'.now()->format('Ymd').'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE', 'Cache-Control' => 'no-store']);
    }

    private function filteredCheques(Request $request): Builder
    {
        $this->normalizeAmounts($request);
        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'folder' => ['nullable', Rule::in(array_keys(ChequeInstrument::FOLDERS))],
            'min_amount' => ['nullable', 'integer', 'min:0'], 'max_amount' => ['nullable', 'integer', 'min:0'], 'customer_id' => ['nullable', 'integer', 'exists:cheque_customers,id']]);
        $q = ChequeInstrument::with(['sale.customer', 'receipts']);
        if ($request->filled('q')) {
            $term = '%'.trim(JalaliInputParser::toEnglishDigits($request->string('q')->toString())).'%';
            $q->where(fn ($q) => $q->where('sayad_number', 'like', $term)->orWhere('serial', 'like', $term)->orWhere('bank_name', 'like', $term)
                ->orWhereHas('sale.customer', fn ($q) => $this->searchCustomer($q, $request->string('q')->toString())));
        }
        if ($request->filled('customer_id')) {
            $q->whereHas('sale', fn ($q) => $q->where('cheque_customer_id', $request->integer('customer_id')));
        }
        foreach (['folder' => '=', 'min_amount' => '>=', 'max_amount' => '<='] as $key => $operator) {
            if ($request->filled($key)) {
                $q->where($key === 'folder' ? 'folder' : 'amount_toman', $operator, $request->input($key));
            }
        }
        if ($request->filled('min_amount') && $request->filled('max_amount') && $request->integer('min_amount') > $request->integer('max_amount')) {
            throw ValidationException::withMessages(['max_amount' => 'حداکثر مبلغ نباید کمتر از حداقل باشد.']);
        }
        foreach (['due' => 'due_date', 'purchase' => 'purchased_at'] as $prefix => $column) {
            $from = $request->filled($prefix.'_from') ? $this->date((string) $request->input($prefix.'_from')) : null;
            $to = $request->filled($prefix.'_to') ? $this->date((string) $request->input($prefix.'_to')) : null;
            if ($from && $to && $from > $to) {
                throw ValidationException::withMessages([$prefix.'_to' => 'پایان بازه نباید قبل از شروع باشد.']);
            }
            $apply = function ($q) use ($column, $from, $to): void {
                if ($from) {
                    $q->whereDate($column, '>=', $from);
                }
                if ($to) {
                    $q->whereDate($column, '<=', $to);
                }
            };
            $prefix === 'purchase' ? $q->whereHas('sale', $apply) : $apply($q);
        }

        return $q;
    }

    private function searchCustomer(Builder $query, string $search): void
    {
        $search = trim(JalaliInputParser::toEnglishDigits($search));
        $parts = array_slice(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 10);
        $query->where(function (Builder $query) use ($search, $parts): void {
            $query->where('mobile', 'like', '%'.$search.'%')->orWhere('national_id', 'like', '%'.$search.'%')
                ->orWhere(function (Builder $names) use ($parts): void {
                    foreach ($parts as $part) {
                        $names->where(fn (Builder $name) => $name->where('first_name', 'like', '%'.$part.'%')->orWhere('last_name', 'like', '%'.$part.'%'));
                    }
                });
        });
    }

    private function saleData(Request $request, bool $withCheques = true): array
    {
        $this->normalizeAmounts($request);
        $data = $request->validate([
            'purchase_amount_toman' => ['required', 'integer', 'min:1', 'max:1000000000000'], 'months' => ['required', 'integer', 'between:1,120'],
            'interest_rate' => ['required', 'numeric', 'between:0,100'], 'profit_method' => ['required', Rule::in(['monthly', 'bank'])],
            'down_payment_toman' => ['required', 'integer', 'min:0', 'max:1000000000000'], 'discount_toman' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'daily_late_coefficient' => ['required', 'numeric', 'between:0,0.1'], 'daily_early_coefficient' => ['required', 'numeric', 'between:0,0.1'],
            ...($withCheques ? ['purchased_at' => ['required', 'string'], 'disbursement_due_date' => ['nullable', 'string'], 'note' => ['nullable', 'string', 'max:5000'],
                'cheques' => ['required', 'array', 'min:1', 'max:100'], 'cheques.*' => ['required', 'array:sayad_number,serial,bank_name,branch,amount_toman,due_date,note'], 'cheques.*.sayad_number' => ['required', 'digits:16', 'distinct', 'unique:cheque_instruments,sayad_number'],
                'cheques.*.serial' => ['required', 'string', 'max:64'], 'cheques.*.bank_name' => ['required', 'string', 'max:120'], 'cheques.*.branch' => ['nullable', 'string', 'max:120'],
                'cheques.*.amount_toman' => ['required', 'integer', 'min:1', 'max:1000000000000'], 'cheques.*.due_date' => ['required', 'string'], 'cheques.*.note' => ['nullable', 'string', 'max:2000']] : []),
        ]);
        foreach (['purchase_amount_toman', 'months', 'down_payment_toman', 'discount_toman'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        foreach (['interest_rate', 'daily_late_coefficient', 'daily_early_coefficient'] as $key) {
            $data[$key] = number_format((float) $data[$key], $key === 'interest_rate' ? 4 : 8, '.', '');
        }
        if ($withCheques) {
            $data['purchased_at'] = $this->date($data['purchased_at']);
            $data['disbursement_due_date'] = empty($data['disbursement_due_date']) ? null : $this->date($data['disbursement_due_date']);
            foreach ($data['cheques'] as &$row) {
                $row['due_date'] = $this->date($row['due_date']);
                $row['amount_toman'] = (int) $row['amount_toman'];
            }
            unset($row);
        }

        return $data;
    }

    private function owner(Request $request, ChequeCustomer $customer): array
    {
        $request->validate(['guarantee_by_self' => ['required', 'boolean']]);
        if ($request->boolean('guarantee_by_self')) {
            return ['name' => $customer->fullName(), 'father_name' => $customer->father_name, 'national_id' => $customer->national_id, 'mobile' => $customer->mobile, 'address' => $customer->address];
        }
        $validated = $request->validate(['owner_details' => ['required', 'array:name,mobile,father_name,national_id,address'], 'owner_details.name' => ['required', 'string', 'max:240'],
            'owner_details.mobile' => ['required', 'regex:/^09[0-9]{9}$/D'], 'owner_details.father_name' => ['nullable', 'string', 'max:120'],
            'owner_details.national_id' => ['nullable', 'digits:10', new IranNationalId], 'owner_details.address' => ['nullable', 'string', 'max:2000']]);

        return $validated['owner_details'];
    }

    private function ownerContext(ChequeCustomer $customer, array $data, array $owner): array
    {
        return ['customer_id' => $customer->id, 'terms_hash' => hash('sha256', json_encode([$data, $owner], JSON_UNESCAPED_UNICODE))];
    }

    private function normalizeAmounts(Request $request): void
    {
        $clean = static fn (string $value): string => preg_replace('/[,\x{066c}\s\x{200e}\x{200f}]/u', '', JalaliInputParser::toEnglishDigits($value)) ?? $value;
        $values = [];
        foreach (['min_amount', 'max_amount', 'purchase_amount_toman', 'down_payment_toman', 'discount_toman', 'amount_toman'] as $key) {
            if (is_string($request->input($key))) {
                $values[$key] = $clean($request->input($key));
            }
        }
        $cheques = $request->input('cheques');
        if (is_array($cheques)) {
            foreach ($cheques as &$row) {
                if (is_array($row) && is_string($row['amount_toman'] ?? null)) {
                    $row['amount_toman'] = $clean($row['amount_toman']);
                }
            }
            unset($row);
            $values['cheques'] = $cheques;
        }
        if ($values) {
            $request->merge($values);
        }
    }

    private function date(string $value): string
    {
        $value = JalaliInputParser::toEnglishDigits(trim($value));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            try {
                $date = Carbon::createFromFormat('!Y-m-d', $value);
                if ($date->format('Y-m-d') === $value && $date->year >= 1900 && $date->year <= 2200) {
                    return $value;
                }
            } catch (\Throwable) {
            }
        } elseif (preg_match('/^\d{4}\/\d{2}\/\d{2}$/D', $value)) {
            $date = JalaliInputParser::toCarbonDate($value);
            if ($date && Jalali::instance($date)->format('Y/m/d') === $value) {
                return $date->toDateString();
            }
        }
        throw ValidationException::withMessages(['date' => 'تاریخ معتبر شمسی با قالب ۱۴۰۵/۰۸/۰۱ وارد کنید.']);
    }
}
