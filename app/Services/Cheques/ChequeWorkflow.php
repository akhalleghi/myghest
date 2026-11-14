<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\ChequeEvent;
use App\Models\ChequeInstrument;
use App\Models\ChequeReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChequeWorkflow
{
    public function __construct(private readonly ChequeFinance $finance) {}

    public function act(int $id, array $data): ChequeInstrument
    {
        return DB::transaction(function () use ($id, $data): ChequeInstrument {
            $cheque = ChequeInstrument::with(['sale.customer', 'receipts'])->lockForUpdate()->findOrFail($id);
            $action = $data['action'];
            if ($action === 'receive' && ($old = ChequeReceipt::where('request_uuid', $data['request_uuid'])->first())) {
                if ((int) $old->cheque_instrument_id !== $id || (int) $old->amount_toman !== (int) $data['amount_toman'] || $old->paid_at->toDateString() !== $data['date']) {
                    throw ValidationException::withMessages(['request_uuid' => 'شناسه درخواست قبلاً برای دریافت دیگری استفاده شده است.']);
                }

                return $cheque;
            }
            if ((int) $data['version'] !== (int) $cheque->version) {
                throw ValidationException::withMessages(['version' => 'این چک تغییر کرده است؛ صفحه را تازه و دوباره بررسی کنید.']);
            }
            if ($cheque->cleared_at !== null) {
                throw ValidationException::withMessages(['action' => 'چک تسویه‌شده قابل تغییر مالی یا انتقال نیست.']);
            }
            $date = Carbon::parse($data['date'])->startOfDay();
            $lastEventDate = $cheque->events()->get()->pluck('payload.date')->filter()->max();
            if ($date->isFuture() || $date->lt($cheque->sale->purchased_at)
                || ($lastEventDate && $date->lt(Carbon::parse($lastEventDate)))
                || ($cheque->bounced_at && $date->lt($cheque->bounced_at))
                || ($cheque->receipts->max('paid_at') && $date->lt($cheque->receipts->max('paid_at')))) {
                throw ValidationException::withMessages(['date' => 'تاریخ عملیات نباید آینده، قبل از خرید یا قبل از آخرین دریافت باشد.']);
            }
            $before = $cheque->only(['folder', 'custody', 'destination', 'bounced_at', 'cleared_at', 'version']);
            if ($action === 'receive') {
                $balance = $this->finance->balance($cheque, $date);
                $amount = (int) $data['amount_toman'];
                if ($amount < 1 || $amount > $balance['remaining_toman']) {
                    throw ValidationException::withMessages(['amount_toman' => 'مبلغ دریافت باید حداکثر برابر مانده بدهی در تاریخ انتخابی باشد: '.number_format($balance['remaining_toman']).' تومان']);
                }
                $cheque->receipts()->create([
                    ...$this->finance->allocate($balance, $amount), 'amount_toman' => $amount, 'paid_at' => $date,
                    'request_uuid' => $data['request_uuid'], 'method' => $data['method'], 'note' => $data['note'] ?? null, 'admin_id' => auth('admin')->id(),
                ]);
                $cheque->unsetRelation('receipts');
                if ($this->finance->balance($cheque, $date)['remaining_toman'] === 0) {
                    $cheque->cleared_at = $date;
                    $cheque->folder = 'archive';
                }
            } elseif ($action === 'bounce') {
                if ($date->lt($cheque->due_date) || $cheque->folder === 'bounced' || in_array($cheque->custody, ['owner', 'buyer'], true)) {
                    throw ValidationException::withMessages(['action' => 'برگشت فقط از سررسید و پس از دریافت اصل چک ممکن است؛ چکِ موجود در پوشه برگشتی دوباره برگشت نمی‌خورد.']);
                }
                // Re-presentation may bounce again; retain the original fee basis.
                $cheque->bounced_at ??= $date;
                $cheque->folder = 'bounced';
                $cheque->custody = 'store';
                $cheque->destination = null;
            } elseif (in_array($action, ['bank', 'person'], true)) {
                if (in_array($cheque->custody, ['owner', 'buyer'], true)) {
                    throw ValidationException::withMessages(['action' => 'ابتدا دریافت مجدد چک عودت‌شده را ثبت کنید.']);
                }
                $cheque->custody = $action;
                $cheque->folder = $action === 'bank' ? 'bank' : 'transferred';
                $cheque->destination = $data['destination'];
            } elseif (in_array($action, ['return_owner', 'return_buyer'], true)) {
                if (in_array($cheque->custody, ['owner', 'buyer'], true)) {
                    throw ValidationException::withMessages(['action' => 'چک قبلاً عودت داده شده است؛ ابتدا دریافت مجدد اصل آن را ثبت کنید.']);
                }
                app(ChequeVerification::class)->consume((string) ($data['verification_token'] ?? ''), 'return', ['cheque_id' => $id, 'version' => (int) $cheque->version, 'target' => $action]);
                $cheque->custody = $action === 'return_owner' ? 'owner' : 'buyer';
                $cheque->folder = 'returned';
                $cheque->destination = $action === 'return_owner' ? $cheque->sale->owner_details : ['name' => $cheque->sale->customer->fullName(), 'mobile' => $cheque->sale->customer->mobile];
            } elseif ($action === 'recover') {
                $cheque->folder = $cheque->bounced_at ? 'bounced' : 'active';
                $cheque->custody = 'store';
                $cheque->destination = null;
            } else {
                throw ValidationException::withMessages(['action' => 'عملیات نامعتبر است.']);
            }
            $cheque->version++;
            $cheque->save();
            ChequeEvent::create(['cheque_customer_id' => $cheque->sale->cheque_customer_id, 'cheque_instrument_id' => $cheque->id,
                'action' => $action, 'payload' => ['before' => $before, 'after' => $cheque->only(array_keys($before)), 'date' => $data['date'], 'note' => $data['note'] ?? null, 'amount_toman' => $data['amount_toman'] ?? null], 'admin_id' => auth('admin')->id()]);

            return $cheque;
        });
    }
}
