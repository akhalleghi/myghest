<?php

declare(strict_types=1);

use App\Models\CustomerLoanInstallmentOnlinePaymentIntent;
use App\Services\Payment\CustomerTransactionLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_transactions')) {
            return;
        }

        $ledger = app(CustomerTransactionLedgerService::class);

        CustomerLoanInstallmentOnlinePaymentIntent::query()
            ->orderBy('id')
            ->chunkById(200, static function ($chunk) use ($ledger): void {
                foreach ($chunk as $intent) {
                    $ledger->syncFromInstallmentIntent($intent);
                }
            });
    }

    public function down(): void
    {
        // بدون حذف خودکار؛ دادهٔ تراکنش ممکن است پس از مهاجرت دستی حذف شود.
    }
};
