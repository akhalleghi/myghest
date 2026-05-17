<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * صفحهٔ «تراکنش‌های من» — دفتر عمومی {@see CustomerTransaction}.
 */
final class CustomerOnlinePaymentTransactionsPresenter
{
    private const PER_PAGE = 15;

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForCustomer(Customer $customer, ?string $search, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = CustomerTransaction::query()
            ->where('customer_id', (int) $customer->id)
            ->orderByDesc('id');

        $term = $search !== null ? trim($search) : '';
        if ($term !== '') {
            $this->applySearch($query, $term);
        }

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CustomerTransaction $tx): array => $this->mapRow($tx));
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $query->where(function (Builder $w) use ($like, $digits): void {
            $w->where('title', 'like', $like)
                ->orWhere('detail', 'like', $like)
                ->orWhere('failure_reason', 'like', $like)
                ->orWhere('kind', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('gateway_key', 'like', $like)
                ->orWhere('bank_reference', 'like', $like);

            if ($digits !== '' && ctype_digit($digits)) {
                $n = (int) $digits;
                if ($n > 0) {
                    $w->orWhere('id', $n)
                        ->orWhere('track_id', $n);
                }
            }

            $w->orWhere('meta->loan_code', 'like', $like);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerTransaction $tx): array
    {
        $status = (string) $tx->status;
        $tone = $this->statusTone($status);
        $displayAt = $this->resolveDisplayCarbon($tx);

        $trackId = $tx->track_id;
        $bankRef = $tx->bank_reference;
        $fail = $tx->failure_reason;

        return [
            'id' => (int) $tx->id,
            'kind' => (string) $tx->kind,
            'kind_label_fa' => $this->kindLabelFa((string) $tx->kind),
            'title' => (string) $tx->title,
            'detail' => $tx->detail !== null && trim((string) $tx->detail) !== '' ? (string) $tx->detail : null,
            'amount_toman' => (int) $tx->amount_toman,
            'amount_fa' => $this->formatMoneyFa((int) $tx->amount_toman),
            'gateway_key' => $tx->gateway_key,
            'gateway_label_fa' => $this->gatewayLabelFa((string) ($tx->gateway_key ?? '')),
            'track_id' => $trackId,
            'track_id_fa' => $trackId !== null ? Jalali::enToFaNumbers((string) $trackId) : '—',
            'bank_ref_fa' => $bankRef !== null && trim((string) $bankRef) !== ''
                ? Jalali::enToFaNumbers(trim((string) $bankRef))
                : '—',
            'status' => $status,
            'status_label_fa' => $this->statusLabelFa($status),
            'status_tone' => $tone,
            'datetime_fa' => $this->formatDateTimeFa($displayAt),
            'failure_reason_fa' => $fail !== null && trim((string) $fail) !== ''
                ? $this->truncateFa((string) $fail, 180)
                : null,
        ];
    }

    private function resolveDisplayCarbon(CustomerTransaction $tx): Carbon
    {
        $status = (string) $tx->status;
        if ($status === 'completed' || $status === 'failed') {
            return Carbon::parse($tx->updated_at);
        }

        return Carbon::parse($tx->created_at);
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'completed' => 'ok',
            'failed' => 'danger',
            'redirected' => 'pending',
            default => 'muted',
        };
    }

    private function statusLabelFa(string $status): string
    {
        return match ($status) {
            'completed' => 'پرداخت موفق',
            'failed' => 'ناموفق / لغو',
            'redirected' => 'هدایت به درگاه',
            'created' => 'ثبت درخواست',
            default => $status,
        };
    }

    private function kindLabelFa(string $kind): string
    {
        return match ($kind) {
            CustomerTransaction::KIND_INSTALLMENT_ONLINE_PAYMENT => 'پرداخت قسط (درگاه)',
            CustomerTransaction::KIND_INSTALLMENT_WALLET_PAYMENT => 'پرداخت قسط (کیف پول)',
            CustomerTransaction::KIND_WALLET_TOPUP => 'شارژ کیف پول',
            CustomerTransaction::KIND_FULL_SETTLEMENT_ONLINE_PAYMENT => 'تسویهٔ کلی بدهی (درگاه)',
            CustomerTransaction::KIND_FULL_SETTLEMENT_WALLET_PAYMENT => 'تسویهٔ کلی بدهی (کیف پول)',
            default => $kind,
        };
    }

    private function gatewayLabelFa(string $key): string
    {
        return match (mb_strtolower($key)) {
            'zibal' => 'زیبال',
            '' => '—',
            default => $key,
        };
    }

    private function formatMoneyFa(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format(max(0, $toman), 0, '.', ',')).' تومان';
    }

    private function formatDateTimeFa(Carbon $c): string
    {
        $jDate = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d'));
        $time = Jalali::enToFaNumbers($c->format('H:i'));

        return $jDate.'، '.$time;
    }

    private function truncateFa(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLen - 1)).'…';
    }
}
