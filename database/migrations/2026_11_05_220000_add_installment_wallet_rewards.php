<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['installment_reward_enabled', 'installment_reward_percent', 'installment_reward_after_payment_id'] as $column) {
            if (! Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'installment_reward_enabled' => $table->boolean($column)->default(false),
                        'installment_reward_percent' => $table->decimal($column, 5, 2)->default(3),
                        default => $table->unsignedBigInteger($column)->default(0),
                    };
                });
            }
        }
        if (! Schema::hasTable('installment_wallet_rewards')) {
            Schema::create('installment_wallet_rewards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('installment_id')->nullable()->unique()->constrained('customer_loan_installments')->nullOnDelete();
                $table->decimal('percent', 5, 2);
                $table->unsignedBigInteger('credited_amount_toman')->default(0);
                $table->unsignedBigInteger('activation_payment_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Financial audit records must survive application rollback.
    }
};
