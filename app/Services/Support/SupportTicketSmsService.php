<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\SmsPanelSetting;
use App\Models\SupportTicket;
use App\Services\Admin\RawSmsDispatcher;
use Illuminate\Support\Collection;

final class SupportTicketSmsService
{
    public function __construct(
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function isPanelAvailable(): bool
    {
        return SmsPanelSetting::query()->where('is_active', true)->exists();
    }

    /**
     * قالب پیش‌فرض ایجاد تیکت (با placeholder برای چند گیرنده).
     */
    public function buildComposeSmsTemplate(): string
    {
        $appName = $this->appDisplayName();

        return implode("\n", [
            '{customer_greeting}',
            'تیکت جدیدی در پنل کاربری شما در سامانه '.$appName.' ثبت شده است.',
            'موضوع: {subject}',
            'لطفاً با مراجعه به سامانه، تیکت خود را بررسی نمایید.',
        ]);
    }

    public function buildDefaultReplySmsText(Customer $customer, SupportTicket $ticket): string
    {
        $name = trim($customer->fullName());
        if ($name === '') {
            $name = 'مشتری گرامی';
        } else {
            $name = 'مشتری گرامی '.$name;
        }

        $appName = $this->appDisplayName();
        $subject = trim((string) $ticket->subject);

        $lines = [
            $name,
            'پاسخ جدیدی برای تیکت شما در پنل کاربری ثبت شده است.',
        ];
        if ($subject !== '') {
            $lines[1] = 'پاسخ جدیدی برای تیکت «'.$subject.'» در پنل کاربری شما ثبت شده است.';
        }
        $lines[] = 'لطفاً با مراجعه به سامانه '.$appName.' تیکت خود را بررسی نمایید.';

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, Customer>
     */
    public function customersForTicket(SupportTicket $ticket): Collection
    {
        $ticket->loadMissing(['createdByCustomer', 'recipients.customer']);

        if ($ticket->isCustomerOriginated()) {
            $creator = $ticket->createdByCustomer;
            if ($creator !== null) {
                return collect([$creator]);
            }
        }

        $customers = collect();
        foreach ($ticket->recipients as $recipient) {
            $customer = $recipient->customer;
            if ($customer !== null) {
                $customers->push($customer);
            }
        }

        return $customers->unique('id')->values();
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, detail: string}
     */
    public function sendReplyNotification(SupportTicket $ticket, string $messageText): array
    {
        return $this->sendTicketNotification($ticket, $messageText, 'support-ticket-reply');
    }

    public function sendNewTicketNotification(SupportTicket $ticket, string $messageText): array
    {
        return $this->sendTicketNotification($ticket, $messageText, 'support-ticket-new');
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, detail: string}
     */
    private function sendTicketNotification(SupportTicket $ticket, string $messageText, string $logType): array
    {
        $text = trim($messageText);
        if ($text === '') {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'detail' => 'متن پیامک خالی است.',
            ];
        }

        if (! $this->isPanelAvailable()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'detail' => 'پنل پیامک فعال نیست.',
            ];
        }

        $customers = $this->customersForTicket($ticket);
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $mobile = $this->normalizeMobile((string) $customer->mobile);
            if ($mobile === '') {
                $skipped++;
                continue;
            }

            $personalized = $this->personalizeMessage($text, $customer, $ticket);
            $result = $this->rawSms->send($mobile, $personalized, $logType, [
                'support_ticket_id' => (int) $ticket->id,
                'customer_id' => (int) $customer->id,
            ]);

            if ($result['ok']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($customers->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'detail' => 'گیرنده‌ای برای ارسال پیامک یافت نشد.',
            ];
        }

        $detail = match (true) {
            $sent > 0 && $failed === 0 && $skipped === 0 => 'پیامک به '.$sent.' مشتری ارسال شد.',
            $sent > 0 => 'پیامک به '.$sent.' مشتری ارسال شد'.($failed > 0 ? ' ('.$failed.' ناموفق)' : '').($skipped > 0 ? ' ('.$skipped.' بدون موبایل)' : '').'.',
            $failed > 0 => 'ارسال پیامک ناموفق بود.',
            $skipped > 0 => 'هیچ شماره موبایل معتبری برای مشتریان یافت نشد.',
            default => 'پیامک ارسال نشد.',
        };

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'detail' => $detail,
        ];
    }

    public function primaryCustomerForTicket(SupportTicket $ticket): ?Customer
    {
        return $this->customersForTicket($ticket)->first();
    }

    private function personalizeMessage(string $text, Customer $customer, SupportTicket $ticket): string
    {
        $name = trim($customer->fullName());
        $replacements = [
            '{customer_greeting}' => $name !== '' ? 'مشتری گرامی '.$name : 'مشتری گرامی',
            '{customer_name}' => $name !== '' ? $name : 'مشتری گرامی',
            '{app_name}' => $this->appDisplayName(),
            '{subject}' => trim((string) $ticket->subject),
        ];
        $out = str_replace(array_keys($replacements), array_values($replacements), $text);
        $out = preg_replace('/مشتری گرامی\s*\n/u', "مشتری گرامی\n", $out) ?? $out;
        $out = preg_replace('/مشتری گرامی\s+$/u', 'مشتری گرامی', $out) ?? $out;

        return trim($out);
    }

    public function appDisplayName(): string
    {
        $value = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($value) && trim($value) !== '' ? trim($value) : 'سامانه';
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
}
