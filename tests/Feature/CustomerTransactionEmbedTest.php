<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminCustomerTransactionController;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerTransaction;
use App\Models\LoanType;
use App\Services\Admin\AdminCustomerTransactionListService;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class CustomerTransactionEmbedTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['customer_code' => '1', 'username' => '09123456789', 'first_name' => 'Test', 'last_name' => 'User', 'mobile' => '09123456789', 'password' => 'secret123']);
    }

    public function test_customer_transaction_embed_renders(): void
    {
        $customer = $this->customer();
        CustomerTransaction::create(['customer_id' => $customer->id, 'kind' => 'installment_online_payment', 'status' => 'completed', 'amount_toman' => 100000, 'amount_rial' => 1000000, 'title' => 'Today transaction']);
        $view = app(AdminCustomerTransactionController::class)->customerEmbedPanel(Request::create('/', 'GET'), $customer, app(AdminCustomerTransactionListService::class));
        $html = $view->with('errors', new ViewErrorBag)->render();
        self::assertStringContainsString('ctx-date-from', $html);
        self::assertStringContainsString('Today transaction', $html);
        $data = $view->getData();
        $today = Jalali::instance(now())->format('Y/m/d');
        self::assertSame($today, $data['filterInputs']['date_from']);
        self::assertSame($today, $data['filterInputs']['date_to']);
        self::assertSame($customer->id, $data['filters']['customer_id']);
    }

    public function test_manual_receipts_follow_customer_and_deposit_date_and_reflect_edits(): void
    {
        $customer = $this->customer();
        $type = LoanType::create(['title' => 'Test', 'profit_calculation_method' => 'monthly', 'interest_rate' => 0, 'daily_late_coefficient' => 0, 'daily_early_coefficient' => 0, 'installment_gap_unit' => 'monthly', 'repayment_periods' => [1]]);
        $file = CustomerLoanFile::create([
            'customer_id' => $customer->id, 'loan_type_id' => $type->id, 'loan_code' => 'DS 10',
            'loan_start_date' => now(), 'amount_toman' => 900000, 'installments_count' => 1,
            'installment_interval_count' => 1, 'installment_interval_unit' => 'monthly',
            'installment_amount_toman' => 900000, 'profit_calculation_method' => 'monthly', 'base_interest_rate' => 0, 'effective_interest_rate' => 0,
        ]);
        $installment = CustomerLoanInstallment::create([
            'customer_loan_file_id' => $file->id, 'sequence' => 1, 'amount_toman' => 900000, 'due_date' => now()->addMonth(),
        ]);
        $receipt = CustomerLoanInstallmentPayment::create([
            'customer_loan_installment_id' => $installment->id, 'payment_method' => 'bank_transfer',
            'amount_toman' => 100000, 'deposited_at' => now()->subDays(2), 'note' => 'Manual receipt',
        ]);
        $list = app(AdminCustomerTransactionListService::class);
        $filters = ['customer_id' => $customer->id, 'date_from' => now()->subDays(2), 'date_to' => now()->subDays(2)];
        $row = $list->makeFilteredQuery($filters)->firstOrFail();
        self::assertSame(100000, $row->amount_toman);
        self::assertSame('DS 10', $row->meta['loan_code']);
        self::assertSame(1, $list->paginate($filters)->total());
        self::assertSame(0, $list->makeFilteredQuery(['customer_id' => $customer->id + 1])->count());
        self::assertSame(0, $list->makeFilteredQuery(['date_from' => now(), 'date_to' => now()])->count());
        self::assertSame(0, $list->makeFilteredQuery($filters + ['kind' => 'wallet_topup'])->count());
        $view = app(AdminCustomerTransactionController::class)->customerEmbedPanel(
            Request::create('/', 'GET', ['date_from' => '', 'date_to' => '']), $customer, $list);
        self::assertStringContainsString('Manual receipt', $view->with('errors', new ViewErrorBag)->render());
        $receipt->update(['amount_toman' => 150000]);
        self::assertSame(150000, $list->makeFilteredQuery($filters)->firstOrFail()->amount_toman);
        $receipt->delete();
        self::assertSame(0, $list->makeFilteredQuery($filters)->count());
        self::assertSame(0, CustomerTransaction::count());
    }
}
