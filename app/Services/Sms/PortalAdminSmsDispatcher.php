<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Jobs\SendCustomerDepositDeclarationNotifyAdminSmsJob;
use App\Jobs\SendCustomerFullSettlementNotifyAdminSmsJob;
use App\Jobs\SendCustomerInstallmentPaymentNotifyAdminSmsJob;
use App\Jobs\SendCustomerInstallmentPaymentThanksSmsJob;
use App\Jobs\SendCustomerLoanRequestNotifyAdminSmsJob;
use App\Jobs\SendCustomerLoginNotifyAdminSmsJob;
use App\Jobs\SendCustomerSupportTicketNotifyAdminSmsJob;
use App\Models\CustomerDepositDeclaration;
use App\Models\CustomerLoanInstallmentPayment;

/**
 * صف‌گذاری اعلان‌های پیامکی پس از پاسخ HTTP — بدون تأثیر بر سرعت عملیات کاربر.
 */
final class PortalAdminSmsDispatcher
{
    public static function afterCustomerLogin(int $customerId): void
    {
        if ($customerId < 1) {
            return;
        }

        SendCustomerLoginNotifyAdminSmsJob::dispatchAfterResponse($customerId);
    }

    public static function afterInstallmentPayment(CustomerLoanInstallmentPayment $payment): void
    {
        if (! app(CustomerInstallmentPaymentNotifyAdminSmsService::class)->isPortalInstallmentPayment($payment)) {
            return;
        }

        $paymentId = (int) $payment->id;
        SendCustomerInstallmentPaymentNotifyAdminSmsJob::dispatchAfterResponse($paymentId);
        SendCustomerInstallmentPaymentThanksSmsJob::dispatchAfterResponse($paymentId);
    }

    public static function afterFullSettlement(
        int $customerId,
        int $loanFileId,
        int $amountToman,
        ?string $paymentMethod = null,
    ): void {
        if ($customerId < 1 || $loanFileId < 1 || $amountToman < 1) {
            return;
        }

        SendCustomerFullSettlementNotifyAdminSmsJob::dispatchAfterResponse(
            $customerId,
            $loanFileId,
            $amountToman,
            $paymentMethod,
        );
    }

    public static function afterDepositDeclaration(CustomerDepositDeclaration $declaration): void
    {
        if ((int) $declaration->id < 1) {
            return;
        }

        SendCustomerDepositDeclarationNotifyAdminSmsJob::dispatchAfterResponse((int) $declaration->id);
    }

    public static function afterSupportTicket(int $ticketId): void
    {
        if ($ticketId < 1) {
            return;
        }

        SendCustomerSupportTicketNotifyAdminSmsJob::dispatchAfterResponse($ticketId);
    }

    public static function afterLoanRequest(int $loanRequestId): void
    {
        if ($loanRequestId < 1) {
            return;
        }

        SendCustomerLoanRequestNotifyAdminSmsJob::dispatchAfterResponse($loanRequestId);
    }
}
