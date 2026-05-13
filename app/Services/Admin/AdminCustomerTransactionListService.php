<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CustomerTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminCustomerTransactionListService
{
    private const PER_PAGE = 25;

    /**
     * @param  array{
     *     q?: string|null,
     *     kind?: string|null,
     *     status?: string|null,
     *     gateway?: string|null,
     *     customer_id?: int|null,
     *     date_from?: Carbon|null,
     *     date_to?: Carbon|null
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = CustomerTransaction::query()
            ->with(['customer:id,customer_code,first_name,last_name,mobile,national_id'])
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * همان فیلترهای لیست، بدون صفحه‌بندی (برای خروجی اکسل و گزارش).
     *
     * @param  array{
     *     q?: string|null,
     *     kind?: string|null,
     *     status?: string|null,
     *     gateway?: string|null,
     *     customer_id?: int|null,
     *     date_from?: Carbon|null,
     *     date_to?: Carbon|null
     * }  $filters
     */
    public function makeFilteredQuery(array $filters): Builder
    {
        $query = CustomerTransaction::query()
            ->with(['customer:id,customer_code,first_name,last_name,mobile,national_id'])
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $kind = isset($filters['kind']) && is_string($filters['kind']) ? trim($filters['kind']) : '';
        if ($kind !== '' && in_array($kind, CustomerTransaction::kindKeys(), true)) {
            $query->where('kind', $kind);
        }

        $status = isset($filters['status']) && is_string($filters['status']) ? trim($filters['status']) : '';
        if ($status !== '' && in_array($status, CustomerTransaction::statusKeys(), true)) {
            $query->where('status', $status);
        }

        $gateway = isset($filters['gateway']) && is_string($filters['gateway']) ? trim($filters['gateway']) : '';
        if ($gateway !== '') {
            $query->where('gateway_key', $gateway);
        }

        $customerId = $filters['customer_id'] ?? null;
        if (is_int($customerId) && $customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        $from = $filters['date_from'] ?? null;
        if ($from instanceof Carbon) {
            $query->where('customer_transactions.created_at', '>=', $from->copy()->startOfDay());
        }

        $to = $filters['date_to'] ?? null;
        if ($to instanceof Carbon) {
            $query->where('customer_transactions.created_at', '<=', $to->copy()->endOfDay());
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $digits = preg_replace('/\D+/', '', $q) ?? '';

            $query->where(function (Builder $w) use ($like, $digits, $q): void {
                $w->where('title', 'like', $like)
                    ->orWhere('detail', 'like', $like)
                    ->orWhere('failure_reason', 'like', $like)
                    ->orWhere('kind', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('gateway_key', 'like', $like)
                    ->orWhere('bank_reference', 'like', $like)
                    ->orWhere('source_type', 'like', $like);

                if ($digits !== '' && ctype_digit($digits)) {
                    $n = (int) $digits;
                    if ($n > 0) {
                        $w->orWhere('id', $n)
                            ->orWhere('customer_id', $n)
                            ->orWhere('track_id', $n);
                    }
                }

                $w->orWhereHas('customer', function (Builder $c) use ($like): void {
                    $c->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('customer_code', 'like', $like);
                });

                $trim = trim($q);
                if ($trim !== '' && preg_match('/^\d+$/', $trim) === 1) {
                    $w->orWhere('meta->loan_code', 'like', $like);
                }
            });
        }
    }
}
