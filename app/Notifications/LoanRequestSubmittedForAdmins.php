<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * اعلان «ثبت درخواست وام جدید توسط مشتری» برای ادمین‌ها؛ کانال: database.
 */
final class LoanRequestSubmittedForAdmins extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $loanRequestId,
        public readonly int $customerId,
        public readonly string $customerName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $customerLabel = trim($this->customerName) !== '' ? trim($this->customerName) : 'یک مشتری';

        return [
            'kind' => 'loan_request_submitted',
            'title' => 'درخواست وام جدید',
            'body' => $customerLabel.' یک درخواست وام جدید ثبت کرد. لطفاً برای بررسی به فهرست درخواست‌های وام مراجعه کنید.',
            'url_name' => 'admin.loan-requests.index',
            'url_query' => ['q' => (string) $this->loanRequestId],
            'icon' => 'fa-solid fa-file-circle-plus',
            'loan_request_id' => $this->loanRequestId,
            'customer_id' => $this->customerId,
            'customer_name' => $customerLabel,
        ];
    }

    public static function build(CustomerLoanRequest $request, Customer $customer): self
    {
        return new self(
            loanRequestId: (int) $request->id,
            customerId: (int) $customer->id,
            customerName: $customer->fullName(),
        );
    }
}
