<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CustomerLoanRequest;
use App\Models\LoanRequestStatusDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * اعلان «تغییر وضعیت درخواست وام» برای مشتری؛ کانال: database.
 *
 * پیام به‌صورت رسمی و دوستانه نوشته شده تا کاربر را بدون هشدار غیرضروری به‌جریان حل موضوع برساند.
 */
final class LoanRequestStatusChangedForCustomer extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $loanRequestId,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
        public readonly string $toStatusLabel,
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
        return [
            'kind' => 'loan_request_status_changed',
            'title' => 'به‌روزرسانی وضعیت درخواست وام شما',
            'body' => 'وضعیت درخواست وام شما به «'
                .$this->toStatusLabel
                .'» تغییر یافت. لطفاً برای مشاهدهٔ جزئیات و انجام اقدامات لازم به صفحهٔ درخواست وام مراجعه فرمایید.',
            'url_name' => 'user.loan-request',
            'icon' => 'fa-solid fa-circle-info',
            'loan_request_id' => $this->loanRequestId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'to_status_label' => $this->toStatusLabel,
        ];
    }

    /**
     * کمک‌متد ساخت اعلان از روی درخواست + کد وضعیت (label از تعاریف وضعیت‌ها استخراج می‌شود).
     */
    public static function build(CustomerLoanRequest $request, ?string $fromStatus, string $toStatus): self
    {
        $titles = LoanRequestStatusDefinition::titlesByCode();
        $label = $titles[$toStatus] ?? $toStatus;

        return new self(
            loanRequestId: (int) $request->id,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            toStatusLabel: (string) $label,
        );
    }
}
