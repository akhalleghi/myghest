<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;
use App\Models\CustomerLoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * اعلان «ویرایش درخواست وام توسط مشتری» برای ادمین‌ها (مدارک/مشخصات)؛ کانال: database.
 */
final class LoanRequestUpdatedForAdmins extends Notification
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
            'kind' => 'loan_request_updated',
            'title' => 'ویرایش درخواست وام',
            'body' => $customerLabel.' درخواست وام خود را به‌روزرسانی کرد (تغییر در مشخصات یا مدارک). لطفاً برای بازبینی به فهرست درخواست‌های وام مراجعه کنید.',
            'url_name' => 'admin.loan-requests.index',
            'url_query' => ['q' => (string) $this->loanRequestId],
            'icon' => 'fa-solid fa-pen-to-square',
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
