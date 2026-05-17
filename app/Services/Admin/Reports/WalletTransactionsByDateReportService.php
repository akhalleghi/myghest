<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\CustomerWalletOnlinePaymentIntent;
use App\Models\CustomerWalletTransaction;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class WalletTransactionsByDateReportService
{
    public const SOURCE_ONLINE = 'online';

    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_ADMIN = 'admin';

    /** @deprecated use SOURCE_PORTAL */
    public const SOURCE_INTERNAL = 'portal';

    /**
     * @return array<string, string>
     */
    public static function sourceFilterOptions(): array
    {
        return [
            self::SOURCE_ONLINE => 'شارژ آنلاین (درگاه — پنل کاربر)',
            self::SOURCE_PORTAL => 'پنل کاربر (پرداخت از کیف پول)',
            self::SOURCE_ADMIN => 'پنل ادمین',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function directionFilterOptions(): array
    {
        return [
            CustomerWalletTransaction::DIRECTION_DEPOSIT => 'واریز',
            CustomerWalletTransaction::DIRECTION_WITHDRAW => 'برداشت',
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, from_jdate: string, to_jdate: string}
     */
    public function resolveDateRange(?string $fromJdate, ?string $toJdate): array
    {
        $from = JalaliInputParser::toCarbonDate($fromJdate);
        $to = JalaliInputParser::toCarbonDate($toJdate);

        if ($from === null || $to === null) {
            $today = Carbon::today();
            $jToday = Jalali::instance($today);
            $from = Carbon::createFromTimestamp($jToday->clone()->startYear()->getTimestamp())->startOfDay();
            $to = $today->copy()->endOfDay();
        } else {
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            } else {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_jdate' => Jalali::instance($from)->format('Y/m/d'),
            'to_jdate' => Jalali::instance($to)->format('Y/m/d'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(
        Carbon $from,
        Carbon $to,
        string $search = '',
        string $directionFilter = '',
        string $sourceFilter = '',
    ): array {
        $sourceFilter = $this->normalizeSourceFilter($sourceFilter);

        /** @var Collection<int, CustomerTransaction> $ledgerRows */
        $ledgerRows = $this->buildLedgerQuery($from, $to, $search)->get();

        $walletTxIds = [];
        $intentIds = [];

        foreach ($ledgerRows as $ledger) {
            if ($ledger->source_type === CustomerWalletTransaction::class && $ledger->source_id !== null) {
                $walletTxIds[] = (int) $ledger->source_id;
            }
            if ($ledger->kind === CustomerTransaction::KIND_WALLET_TOPUP
                && $ledger->source_type === CustomerWalletOnlinePaymentIntent::class
                && $ledger->source_id !== null) {
                $intentIds[] = (int) $ledger->source_id;
            }
        }

        /** @var Collection<int, CustomerWalletOnlinePaymentIntent> $intents */
        $intents = $intentIds !== []
            ? CustomerWalletOnlinePaymentIntent::query()->whereIn('id', $intentIds)->get()->keyBy('id')
            : collect();

        foreach ($intents as $intent) {
            $linked = CustomerWalletTransaction::query()
                ->where('request_uuid', 'wallet-topup-intent-'.(int) $intent->id)
                ->value('id');
            if ($linked !== null) {
                $walletTxIds[] = (int) $linked;
            }
        }

        $walletTxIds = array_values(array_unique(array_filter($walletTxIds)));

        /** @var Collection<int, CustomerWalletTransaction> $walletTxs */
        $walletTxs = $walletTxIds !== []
            ? CustomerWalletTransaction::query()
                ->whereIn('id', $walletTxIds)
                ->with([
                    'customer:id,first_name,last_name,mobile,national_id,customer_code',
                    'actorAdmin:id,name,username',
                ])
                ->get()
                ->keyBy('id')
            : collect();

        $seenKeys = [];
        $rows = collect();

        foreach ($ledgerRows as $ledger) {
            $walletTx = null;
            $topupIntent = null;

            if ($ledger->source_type === CustomerWalletTransaction::class && $ledger->source_id !== null) {
                $walletTx = $walletTxs->get((int) $ledger->source_id);
            }

            if ($ledger->kind === CustomerTransaction::KIND_WALLET_TOPUP
                && $ledger->source_type === CustomerWalletOnlinePaymentIntent::class
                && $ledger->source_id !== null) {
                $topupIntent = $intents->get((int) $ledger->source_id);
                if ($walletTx === null && $topupIntent !== null) {
                    $linkedId = CustomerWalletTransaction::query()
                        ->where('request_uuid', 'wallet-topup-intent-'.(int) $topupIntent->id)
                        ->value('id');
                    if ($linkedId !== null) {
                        $walletTx = $walletTxs->get((int) $linkedId);
                    }
                }
            }

            $row = $walletTx !== null
                ? $this->mapWalletTransactionRow($walletTx, $topupIntent, $ledger)
                : $this->mapLedgerRow($ledger);

            $dedupKey = $walletTx !== null
                ? 'wtx:'.(int) $walletTx->id
                : 'ledger:'.(int) $ledger->id;

            if (isset($seenKeys[$dedupKey])) {
                continue;
            }

            $seenKeys[$dedupKey] = true;

            if ($directionFilter !== '' && (string) ($row['direction'] ?? '') !== $directionFilter) {
                continue;
            }

            if ($sourceFilter !== '' && (string) ($row['source_key'] ?? '') !== $sourceFilter) {
                continue;
            }

            $row['sort_at'] = $this->ledgerDisplayCarbon($ledger)->toDateTimeString();
            $rows->push($row);
        }

        $walletOnlyQuery = CustomerWalletTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->with([
                'customer:id,first_name,last_name,mobile,national_id,customer_code',
                'actorAdmin:id,name,username',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($directionFilter === CustomerWalletTransaction::DIRECTION_DEPOSIT
            || $directionFilter === CustomerWalletTransaction::DIRECTION_WITHDRAW) {
            $walletOnlyQuery->where('direction', $directionFilter);
        }

        $this->applySearchToWalletQuery($walletOnlyQuery, $search);

        /** @var Collection<int, CustomerWalletTransaction> $walletOnlyRows */
        $walletOnlyRows = $walletOnlyQuery->get();

        $extraIntentIds = $walletOnlyRows
            ->map(fn (CustomerWalletTransaction $tx): ?int => $this->resolveTopupIntentId($tx))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($extraIntentIds !== []) {
            $extraIntents = CustomerWalletOnlinePaymentIntent::query()
                ->whereIn('id', $extraIntentIds)
                ->get()
                ->keyBy('id');
            $intents = $intents->merge($extraIntents);
        }

        foreach ($walletOnlyRows as $tx) {
            $dedupKey = 'wtx:'.(int) $tx->id;
            if (isset($seenKeys[$dedupKey])) {
                continue;
            }

            $intentId = $this->resolveTopupIntentId($tx);
            $intent = $intentId !== null ? $intents->get($intentId) : null;
            $ledger = CustomerTransaction::query()
                ->where('source_type', CustomerWalletTransaction::class)
                ->where('source_id', (int) $tx->id)
                ->first();

            $row = $this->mapWalletTransactionRow($tx, $intent, $ledger);

            if ($sourceFilter !== '' && (string) ($row['source_key'] ?? '') !== $sourceFilter) {
                continue;
            }

            $seenKeys[$dedupKey] = true;
            $row['sort_at'] = $tx->created_at !== null
                ? Carbon::parse((string) $tx->created_at)->toDateTimeString()
                : '';
            $rows->push($row);
        }

        return $rows
            ->sortByDesc(static fn (array $row): string => (string) ($row['sort_at'] ?? ''))
            ->values()
            ->map(static function (array $row): array {
                unset($row['sort_at']);

                return $row;
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'زمان',
            'درگاه',
            'نوع',
            'مبلغ',
            'مشتری',
            'جزئیات تراکنش',
            'ثبت نهایی پرداخت',
            'توضیحات',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        return [
            (string) ($row['created_at_fa'] ?? ''),
            (string) ($row['gateway_label'] ?? ''),
            (string) ($row['direction_label'] ?? ''),
            (string) ($row['amount_excel'] ?? ''),
            (string) ($row['customer_excel'] ?? ''),
            (string) ($row['details_excel'] ?? ''),
            (string) ($row['finalized_at_fa'] ?? ''),
            (string) ($row['description_text'] ?? ''),
        ];
    }

    private function normalizeSourceFilter(string $sourceFilter): string
    {
        return match ($sourceFilter) {
            'internal' => self::SOURCE_PORTAL,
            default => $sourceFilter,
        };
    }

    /**
     * @return list<string>
     */
    private function walletLedgerKinds(): array
    {
        return [
            CustomerTransaction::KIND_WALLET_TOPUP,
            CustomerTransaction::KIND_INSTALLMENT_WALLET_PAYMENT,
            CustomerTransaction::KIND_FULL_SETTLEMENT_WALLET_PAYMENT,
        ];
    }

    private function buildLedgerQuery(Carbon $from, Carbon $to, string $search): Builder
    {
        $query = CustomerTransaction::query()
            ->whereIn('kind', $this->walletLedgerKinds())
            ->with(['customer:id,first_name,last_name,mobile,national_id,customer_code'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyLedgerDateRange($query, $from, $to);
        $this->applySearchToLedgerQuery($query, $search);

        return $query;
    }

    private function applyLedgerDateRange(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where(function (Builder $outer) use ($from, $to): void {
            $outer->where(function (Builder $q) use ($from, $to): void {
                $q->where('status', CustomerTransaction::STATUS_COMPLETED)
                    ->whereBetween('updated_at', [$from, $to]);
            })->orWhere(function (Builder $q) use ($from, $to): void {
                $q->where('status', '!=', CustomerTransaction::STATUS_COMPLETED)
                    ->whereBetween('created_at', [$from, $to]);
            })->orWhere(function (Builder $q) use ($from, $to): void {
                $q->where('status', CustomerTransaction::STATUS_COMPLETED)
                    ->whereBetween('created_at', [$from, $to]);
            });
        });
    }

    private function ledgerDisplayCarbon(CustomerTransaction $ledger): Carbon
    {
        if ((string) $ledger->status === CustomerTransaction::STATUS_COMPLETED) {
            return Carbon::parse((string) $ledger->updated_at);
        }

        return Carbon::parse((string) $ledger->created_at);
    }

    private function applySearchToLedgerQuery(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $q) use ($like, $search): void {
            $q->where('title', 'like', $like)
                ->orWhere('detail', 'like', $like)
                ->orWhere('failure_reason', 'like', $like)
                ->orWhere('bank_reference', 'like', $like)
                ->orWhereHas('customer', function (Builder $c) use ($like): void {
                    $c->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('customer_code', 'like', $like);
                });
            if (ctype_digit($search)) {
                $q->orWhere('track_id', (int) $search)
                    ->orWhere('id', (int) $search);
            }
        });
    }

    private function applySearchToWalletQuery(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $q) use ($like, $search): void {
            $q->where('description', 'like', $like)
                ->orWhere('id', 'like', $like)
                ->orWhere('request_uuid', 'like', $like)
                ->orWhere('meta->track_id', 'like', $like)
                ->orWhere('meta->channel', 'like', $like)
                ->orWhereHas('customer', function (Builder $c) use ($like): void {
                    $c->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('customer_code', 'like', $like);
                });
            if (ctype_digit($search)) {
                $q->orWhere('meta->wallet_topup_intent_id', (int) $search)
                    ->orWhere('meta->track_id', (int) $search);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWalletTransactionRow(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent,
        ?CustomerTransaction $ledger,
    ): array {
        $customer = $tx->customer;
        $sourceKey = $this->resolveSourceKey($tx, $topupIntent);
        $gatewayLabel = $this->gatewayLabel($tx, $topupIntent, $sourceKey);
        $direction = (string) $tx->direction;
        $isDeposit = $direction === CustomerWalletTransaction::DIRECTION_DEPOSIT;
        $amount = (int) $tx->amount_toman;
        $createdAt = $tx->created_at !== null ? Carbon::parse((string) $tx->created_at) : null;
        $finalizedAt = $this->resolveFinalizedAt($tx, $topupIntent, $ledger);

        $customerName = $customer !== null ? trim($customer->fullName()) : '—';
        $customerCode = $customer !== null ? trim((string) ($customer->customer_code ?? '')) : '';
        $customerMobile = $customer !== null ? (string) ($customer->mobile ?? '') : '';
        $customerId = $customer !== null ? (int) $customer->id : (int) $tx->customer_id;

        $detailsLines = $this->buildDetailsLines($tx, $topupIntent, $ledger, $customer, $sourceKey);
        $description = trim((string) ($tx->description ?? ''));

        return $this->buildReportRow(
            id: (int) $tx->id,
            customerId: $customerId,
            customerName: $customerName,
            customerMobile: $customerMobile,
            customerCode: $customerCode,
            direction: $direction,
            isDeposit: $isDeposit,
            amount: $amount,
            createdAt: $createdAt,
            finalizedAt: $finalizedAt,
            sourceKey: $sourceKey,
            gatewayLabel: $gatewayLabel,
            description: $description,
            detailsLines: $detailsLines,
            finalizedStatusFa: $this->finalizedStatusLabel($tx, $topupIntent, $ledger),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLedgerRow(CustomerTransaction $ledger): array
    {
        $customer = $ledger->customer;
        $isDeposit = $ledger->kind === CustomerTransaction::KIND_WALLET_TOPUP;
        $direction = $isDeposit
            ? CustomerWalletTransaction::DIRECTION_DEPOSIT
            : CustomerWalletTransaction::DIRECTION_WITHDRAW;
        $sourceKey = $ledger->kind === CustomerTransaction::KIND_WALLET_TOPUP
            ? self::SOURCE_ONLINE
            : self::SOURCE_PORTAL;
        $gatewayLabel = $this->gatewayKeyLabelFa((string) ($ledger->gateway_key ?? ''));
        if ($gatewayLabel === '—' && $sourceKey === self::SOURCE_PORTAL) {
            $gatewayLabel = 'پنل کاربر — کیف پول';
        }

        $createdAt = $this->ledgerDisplayCarbon($ledger);
        $finalizedAt = (string) $ledger->status === CustomerTransaction::STATUS_COMPLETED
            ? Carbon::parse((string) $ledger->updated_at)
            : $createdAt;

        $customerName = $customer !== null ? trim($customer->fullName()) : '—';
        $customerCode = $customer !== null ? trim((string) ($customer->customer_code ?? '')) : '';
        $customerMobile = $customer !== null ? (string) ($customer->mobile ?? '') : '';
        $customerId = (int) $ledger->customer_id;

        $detailsLines = $this->buildLedgerDetailsLines($ledger, $customer, $sourceKey);
        $description = trim((string) ($ledger->title ?? ''));
        if ($ledger->detail !== null && trim((string) $ledger->detail) !== '') {
            $description .= ($description !== '' ? ' — ' : '').trim((string) $ledger->detail);
        }

        return $this->buildReportRow(
            id: (int) $ledger->id,
            customerId: $customerId,
            customerName: $customerName,
            customerMobile: $customerMobile,
            customerCode: $customerCode,
            direction: $direction,
            isDeposit: $isDeposit,
            amount: (int) $ledger->amount_toman,
            createdAt: $createdAt,
            finalizedAt: $finalizedAt,
            sourceKey: $sourceKey,
            gatewayLabel: $gatewayLabel,
            description: $description,
            detailsLines: $detailsLines,
            finalizedStatusFa: $this->ledgerStatusLabel($ledger),
            ledgerOnly: true,
        );
    }

    /**
     * @param  list<array{text: string, ltr?: bool}>  $detailsLines
     * @return array<string, mixed>
     */
    private function buildReportRow(
        int $id,
        int $customerId,
        string $customerName,
        string $customerMobile,
        string $customerCode,
        string $direction,
        bool $isDeposit,
        int $amount,
        ?Carbon $createdAt,
        ?Carbon $finalizedAt,
        string $sourceKey,
        string $gatewayLabel,
        string $description,
        array $detailsLines,
        string $finalizedStatusFa,
        bool $ledgerOnly = false,
    ): array {
        $detailsHtml = $this->linesToHtml($detailsLines);
        $detailsExcel = $this->linesToPlain($detailsLines);

        return [
            'id' => $id,
            'ledger_only' => $ledgerOnly,
            'direction' => $direction,
            'direction_label' => $isDeposit ? 'واریز' : 'برداشت',
            'source_key' => $sourceKey,
            'sort_at' => $createdAt?->toDateTimeString() ?? '',
            'created_at_fa' => $createdAt !== null
                ? Jalali::enToFaNumbers(Jalali::instance($createdAt)->format('Y/m/d H:i'))
                : '—',
            'gateway_label' => $gatewayLabel,
            'amount_toman' => $amount,
            'amount_formatted' => $this->formatSignedAmount($amount, $isDeposit),
            'amount_excel' => ($isDeposit ? '+' : '-').number_format($amount, 0, '.', ','),
            'customer_id' => $customerId,
            'customer_name' => $customerName !== '' ? $customerName : '—',
            'customer_mobile_fa' => $customerMobile !== '' ? Jalali::enToFaNumbers($customerMobile) : '—',
            'customer_code_fa' => $customerCode !== '' ? Jalali::enToFaNumbers($customerCode) : '—',
            'customer_excel' => $customerName.' — '.$customerMobile.($customerCode !== '' ? ' — '.$customerCode : ''),
            'customer_manage_url' => route('admin.customers.index', [
                'open_loan_manage' => 1,
                'customer_id' => $customerId,
            ]),
            'details_lines' => $detailsLines,
            'details_html' => $detailsHtml,
            'details_excel' => $detailsExcel,
            'finalized_at_fa' => $finalizedAt !== null
                ? Jalali::enToFaNumbers(Jalali::instance($finalizedAt)->format('Y/m/d H:i'))
                : '—',
            'finalized_status_fa' => $finalizedStatusFa,
            'description_text' => $description !== '' ? $description : '—',
        ];
    }

    private function resolveSourceKey(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent = null,
    ): string {
        $meta = is_array($tx->meta) ? $tx->meta : [];
        $channel = (string) ($meta['channel'] ?? '');

        if ($channel === 'admin' || $tx->actor_admin_id !== null) {
            return self::SOURCE_ADMIN;
        }

        $purpose = (string) ($meta['purpose'] ?? '');
        if (in_array($purpose, ['installment_wallet_pay', 'loan_full_settlement_wallet'], true)) {
            return self::SOURCE_PORTAL;
        }

        if ($this->walletTopupIntentId($tx) !== null
            || isset($meta['track_id'])
            || $topupIntent !== null) {
            return self::SOURCE_ONLINE;
        }

        $desc = (string) ($tx->description ?? '');
        if ($tx->direction === CustomerWalletTransaction::DIRECTION_DEPOSIT
            && (str_contains($desc, 'شارژ آنلاین') || str_contains($desc, 'زیبال'))) {
            return self::SOURCE_ONLINE;
        }

        if ($channel === 'portal' || $tx->actor_admin_id === null) {
            return self::SOURCE_PORTAL;
        }

        return self::SOURCE_ADMIN;
    }

    private function gatewayLabel(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent,
        string $sourceKey,
    ): string {
        return match ($sourceKey) {
            self::SOURCE_ADMIN => 'پنل ادمین',
            self::SOURCE_PORTAL => 'پنل کاربر — کیف پول',
            self::SOURCE_ONLINE => $this->gatewayKeyLabelFa(
                trim((string) ($topupIntent?->gateway_key ?? 'zibal'))
            ),
            default => '—',
        };
    }

    private function gatewayKeyLabelFa(string $key): string
    {
        return match (mb_strtolower($key)) {
            'zibal' => 'زیبال',
            '' => '—',
            default => $key,
        };
    }

    private function walletTopupIntentId(CustomerWalletTransaction $tx): ?int
    {
        return $this->resolveTopupIntentId($tx);
    }

    private function resolveTopupIntentId(CustomerWalletTransaction $tx): ?int
    {
        $meta = is_array($tx->meta) ? $tx->meta : [];
        $id = $meta['wallet_topup_intent_id'] ?? null;

        if (is_numeric($id) && (int) $id > 0) {
            return (int) $id;
        }

        $uuid = (string) ($tx->request_uuid ?? '');
        if (str_starts_with($uuid, 'wallet-topup-intent-')) {
            $parsed = (int) substr($uuid, strlen('wallet-topup-intent-'));

            return $parsed > 0 ? $parsed : null;
        }

        return null;
    }

    private function resolveFinalizedAt(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent,
        ?CustomerTransaction $ledger,
    ): ?Carbon {
        if ($ledger !== null && $ledger->status === CustomerTransaction::STATUS_COMPLETED) {
            return Carbon::parse((string) $ledger->updated_at);
        }

        if ($topupIntent !== null && $topupIntent->status === CustomerWalletOnlinePaymentIntent::STATUS_COMPLETED) {
            return Carbon::parse((string) $topupIntent->updated_at);
        }

        if ($tx->created_at !== null) {
            return Carbon::parse((string) $tx->created_at);
        }

        return null;
    }

    private function finalizedStatusLabel(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent,
        ?CustomerTransaction $ledger,
    ): string {
        if ($ledger !== null) {
            return $this->ledgerStatusLabel($ledger);
        }

        if ($topupIntent !== null) {
            return match ((string) $topupIntent->status) {
                CustomerWalletOnlinePaymentIntent::STATUS_COMPLETED => 'پرداخت موفق',
                CustomerWalletOnlinePaymentIntent::STATUS_FAILED => 'ناموفق',
                CustomerWalletOnlinePaymentIntent::STATUS_REDIRECTED => 'هدایت به درگاه',
                CustomerWalletOnlinePaymentIntent::STATUS_CREATED => 'ثبت درخواست',
                default => (string) $topupIntent->status,
            };
        }

        return $tx->actor_admin_id !== null ? 'ثبت دستی (ادمین)' : 'ثبت در پنل کاربر';
    }

    private function ledgerStatusLabel(CustomerTransaction $ledger): string
    {
        return match ((string) $ledger->status) {
            CustomerTransaction::STATUS_COMPLETED => 'پرداخت موفق',
            CustomerTransaction::STATUS_FAILED => 'ناموفق',
            CustomerTransaction::STATUS_REDIRECTED => 'هدایت به درگاه',
            CustomerTransaction::STATUS_CREATED => 'ثبت درخواست',
            default => (string) $ledger->status,
        };
    }

    /**
     * @return list<array{text: string, ltr?: bool}>
     */
    private function buildDetailsLines(
        CustomerWalletTransaction $tx,
        ?CustomerWalletOnlinePaymentIntent $topupIntent,
        ?CustomerTransaction $ledger,
        ?Customer $customer,
        string $sourceKey,
    ): array {
        $lines = $this->buildCustomerLine($customer);
        $lines[] = ['text' => 'کانال: '.$this->channelLabelFa($sourceKey)];

        $meta = is_array($tx->meta) ? $tx->meta : [];
        $lines[] = ['text' => 'شناسه تراکنش کیف پول: '.Jalali::enToFaNumbers((string) $tx->id), 'ltr' => true];

        $trackId = $meta['track_id'] ?? $topupIntent?->track_id ?? $ledger?->track_id;
        if ($trackId !== null && (string) $trackId !== '') {
            $lines[] = ['text' => 'شناسه پیگیری: '.Jalali::enToFaNumbers((string) $trackId), 'ltr' => true];
        }

        $ref = $topupIntent?->zibal_ref_number ?? $ledger?->bank_reference;
        if (is_string($ref) && trim($ref) !== '') {
            $lines[] = ['text' => 'مرجع بانکی: '.Jalali::enToFaNumbers(trim($ref)), 'ltr' => true];
        }

        $purpose = (string) ($meta['purpose'] ?? '');
        if ($purpose === 'installment_wallet_pay') {
            $lines[] = ['text' => 'پرداخت قسط از کیف پول (پنل کاربر)'];
        } elseif ($purpose === 'loan_full_settlement_wallet') {
            $lines[] = ['text' => 'تسویهٔ کلی بدهی از کیف پول (پنل کاربر)'];
        }

        if (isset($meta['loan_file_id']) && is_numeric($meta['loan_file_id'])) {
            $lines[] = ['text' => 'پرونده وام: #'.Jalali::enToFaNumbers((string) $meta['loan_file_id']), 'ltr' => true];
        }

        $lines[] = ['text' => 'موجودی پس از تراکنش: '.$this->formatAmount((int) $tx->balance_after_toman).' تومان'];

        return $lines;
    }

    /**
     * @return list<array{text: string, ltr?: bool}>
     */
    private function buildLedgerDetailsLines(
        CustomerTransaction $ledger,
        ?Customer $customer,
        string $sourceKey,
    ): array {
        $lines = $this->buildCustomerLine($customer);
        $lines[] = ['text' => 'کانال: '.$this->channelLabelFa($sourceKey)];
        $lines[] = ['text' => 'شناسه دفتر تراکنش: '.Jalali::enToFaNumbers((string) $ledger->id), 'ltr' => true];

        if ($ledger->track_id !== null) {
            $lines[] = ['text' => 'شناسه پیگیری: '.Jalali::enToFaNumbers((string) $ledger->track_id), 'ltr' => true];
        }

        if ($ledger->bank_reference !== null && trim((string) $ledger->bank_reference) !== '') {
            $lines[] = ['text' => 'مرجع بانکی: '.Jalali::enToFaNumbers(trim((string) $ledger->bank_reference)), 'ltr' => true];
        }

        if ($ledger->failure_reason !== null && trim((string) $ledger->failure_reason) !== '') {
            $lines[] = ['text' => 'علت: '.trim((string) $ledger->failure_reason)];
        }

        return $lines;
    }

    /**
     * @return list<array{text: string, ltr?: bool}>
     */
    private function buildCustomerLine(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        $code = trim((string) ($customer->customer_code ?? ''));
        $mobile = (string) ($customer->mobile ?? '');
        $line = trim($customer->fullName());
        if ($mobile !== '') {
            $line .= ' — '.Jalali::enToFaNumbers($mobile);
        }
        if ($code !== '') {
            $line .= ' — کد: '.Jalali::enToFaNumbers($code);
        }

        return [['text' => $line]];
    }

    private function channelLabelFa(string $sourceKey): string
    {
        return match ($sourceKey) {
            self::SOURCE_ADMIN => 'پنل ادمین',
            self::SOURCE_ONLINE => 'پنل کاربر — درگاه آنلاین',
            self::SOURCE_PORTAL => 'پنل کاربر',
            default => '—',
        };
    }

    /**
     * @param  list<array{text: string, ltr?: bool}>  $lines
     */
    private function linesToHtml(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $text = htmlspecialchars((string) ($line['text'] ?? ''), ENT_QUOTES, 'UTF-8');
            if (! empty($line['ltr'])) {
                $parts[] = '<span class="rpt-val-ltr">'.$text.'</span>';
            } else {
                $parts[] = '<span>'.$text.'</span>';
            }
        }

        return $parts !== [] ? implode('<br>', $parts) : '—';
    }

    /**
     * @param  list<array{text: string, ltr?: bool}>  $lines
     */
    private function linesToPlain(array $lines): string
    {
        return implode(' | ', array_map(static fn (array $line): string => (string) ($line['text'] ?? ''), $lines));
    }

    private function formatAmount(int $toman): string
    {
        return Jalali::enToFaNumbers(number_format($toman, 0, '.', ','));
    }

    private function formatSignedAmount(int $toman, bool $isDeposit): string
    {
        $prefix = $isDeposit ? '+' : '−';

        return Jalali::enToFaNumbers($prefix.number_format($toman, 0, '.', ',')).' تومان';
    }
}
