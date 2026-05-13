<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CustomerWalletService
{
    public function ensureWallet(Customer $customer): CustomerWallet
    {
        /** @var CustomerWallet $wallet */
        $wallet = CustomerWallet::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'balance_toman' => 0,
                'is_locked' => false,
                'locked_at' => null,
                'locked_by_admin_id' => null,
            ]
        );

        return $wallet;
    }

    public function setLock(Customer $customer, bool $lock, ?int $adminId): CustomerWallet
    {
        return DB::transaction(function () use ($customer, $lock, $adminId): CustomerWallet {
            /** @var CustomerWallet $wallet */
            $wallet = CustomerWallet::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = $this->ensureWallet($customer);
                $wallet = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $wallet->is_locked = $lock;
            $wallet->locked_at = $lock ? now() : null;
            $wallet->locked_by_admin_id = $lock ? $adminId : null;
            $wallet->save();

            return $wallet;
        });
    }

    public function adjust(
        Customer $customer,
        string $direction,
        int $amountToman,
        ?string $description,
        ?int $adminId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $requestUuid = null,
        ?array $meta = null
    ): array {
        if ($amountToman <= 0) {
            throw new RuntimeException('مبلغ تراکنش باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($customer, $direction, $amountToman, $description, $adminId, $ipAddress, $userAgent, $requestUuid, $meta): array {
            if ($requestUuid !== null && $requestUuid !== '') {
                /** @var CustomerWalletTransaction|null $existingTx */
                $existingTx = CustomerWalletTransaction::query()
                    ->where('request_uuid', $requestUuid)
                    ->where('customer_id', $customer->id)
                    ->first();
                if ($existingTx !== null) {
                    $wallet = $this->ensureWallet($customer);

                    return [$wallet, $existingTx, true];
                }
            }

            /** @var CustomerWallet $wallet */
            $wallet = CustomerWallet::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = $this->ensureWallet($customer);
                $wallet = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            if ($wallet->is_locked) {
                throw new RuntimeException('کیف پول قفل است و امکان ثبت تراکنش وجود ندارد.');
            }

            $newBalance = $wallet->balance_toman;
            if ($direction === CustomerWalletTransaction::DIRECTION_DEPOSIT) {
                $newBalance += $amountToman;
            } elseif ($direction === CustomerWalletTransaction::DIRECTION_WITHDRAW) {
                if ($wallet->balance_toman < $amountToman) {
                    throw new RuntimeException('موجودی کیف پول برای برداشت کافی نیست.');
                }
                $newBalance -= $amountToman;
            } else {
                throw new RuntimeException('نوع تراکنش معتبر نیست.');
            }

            $wallet->balance_toman = $newBalance;
            $wallet->save();

            /** @var CustomerWalletTransaction $tx */
            $tx = CustomerWalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'direction' => $direction,
                'amount_toman' => $amountToman,
                'balance_after_toman' => $newBalance,
                'description' => $description !== '' ? $description : null,
                'actor_admin_id' => $adminId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'meta' => $meta,
                'request_uuid' => $requestUuid !== '' ? $requestUuid : null,
                'created_at' => now(),
            ]);

            return [$wallet, $tx, false];
        });
    }
}
