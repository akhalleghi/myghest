<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
    public function paginate(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->transactionQuery();

        $this->applyFilters($query, $filters);

        $size = $perPage ?? self::PER_PAGE;

        return $query->paginate($size)->withQueryString();
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
        $query = $this->transactionQuery();

        $this->applyFilters($query, $filters);

        return $query;
    }

    /** Include admin receipts directly so edits and deletions are reflected without ledger backfills. */
    private function transactionQuery(): Builder
    {
        $columns = ['id', 'customer_id', 'kind', 'status', 'amount_toman', 'amount_rial',
            'gateway_key', 'track_id', 'bank_reference', 'title', 'detail', 'meta',
            'source_type', 'source_id', 'failure_reason', 'created_at', 'updated_at'];
        $ledger = DB::table('customer_transactions')->select($columns);
        $receipts = DB::table('customer_loan_installment_payments as p')
            ->join('customer_loan_installments as i', 'i.id', '=', 'p.customer_loan_installment_id')
            ->join('customer_loan_files as f', 'f.id', '=', 'i.customer_loan_file_id')
            ->where(function ($query): void {
                $query->whereIn('p.payment_method', [
                    CustomerLoanInstallmentPayment::METHOD_CASH,
                    CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER,
                    CustomerLoanInstallmentPayment::METHOD_CARD_TERMINAL,
                    CustomerLoanInstallmentPayment::METHOD_GOLD,
                    CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED,
                ])->orWhere(function ($online): void {
                    $online->where('p.payment_method', CustomerLoanInstallmentPayment::METHOD_ONLINE)
                        ->whereNotNull('p.recorded_by_admin_id');
                });
            })
            ->selectRaw('(-CAST(p.id AS SIGNED)) as id, f.customer_id, ? as kind, ? as status, p.amount_toman, p.amount_toman * 10 as amount_rial,
                NULL as gateway_key, NULL as track_id, NULL as bank_reference, ? as title, p.note as detail, JSON_OBJECT(\'loan_code\', f.loan_code, \'installment_sequence\', i.sequence, \'payment_method\', p.payment_method) as meta,
                ? as source_type, p.id as source_id, NULL as failure_reason,
                COALESCE(p.deposited_at, p.created_at) as created_at, p.updated_at', [
                CustomerTransaction::KIND_INSTALLMENT_ADMIN_PAYMENT, CustomerTransaction::STATUS_COMPLETED,
                'واریزی قسط ثبت‌شده توسط ادمین', CustomerLoanInstallmentPayment::class,
            ]);

        return CustomerTransaction::query()->fromSub($ledger->unionAll($receipts), 'customer_transactions')
            ->with(['customer:id,customer_code,first_name,last_name,mobile,national_id'])
            ->orderByDesc('created_at')->orderByDesc('id');
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
                            ->orWhere('source_id', $n)
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
