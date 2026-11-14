<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChequeCustomer;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Cheques\ChequeFinance;
use App\Services\Sms\SmsTemplateRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class SendChequeReminders extends Command
{
    protected $signature = 'sms:send-cheque-reminders';

    protected $description = 'Send one daily cheque summary only to explicitly enabled cheque customers';

    public function handle(ChequeFinance $finance, RawSmsDispatcher $sms, SmsTemplateRenderer $renderer): int
    {
        if (! Schema::hasTable('cheque_customers')) {
            return self::SUCCESS;
        }
        $template = SmsTemplate::where('template_key', 'cheque_auto_reminder')->first();
        if (! $template) {
            return self::SUCCESS;
        }
        ChequeCustomer::where('sms_enabled', true)->where('auto_reminders_enabled', true)->with('sales.cheques.receipts')->chunkById(100, function ($customers) use ($finance, $sms, $renderer, $template): void {
            foreach ($customers as $customer) {
                Cache::lock('cheque_reminder:'.$customer->id, 120)->get(function () use ($customer, $finance, $sms, $renderer, $template): void {
                    if (SmsLog::where('type', 'cheque-auto-reminder')->whereDate('sent_at', today()->toDateString())
                        ->where('meta->cheque_customer_id', $customer->id)->where('status', SmsLog::STATUS_DELIVERED)->exists()) {
                        return;
                    }
                    $total = 0;
                    foreach ($customer->sales as $sale) {
                        foreach ($sale->cheques as $cheque) {
                            if (! $cheque->cleared_at && ($cheque->bounced_at || $cheque->due_date->isToday())) {
                                $total += $finance->balance($cheque)['remaining_toman'];
                            }
                        }
                    }
                    if ($total <= 0) {
                        return;
                    }
                    $text = $renderer->render($template->body, ['customer_name' => $customer->fullName(), 'cheque_debt' => number_format($total).' تومان']);
                    $sms->send($customer->mobile, $text, 'cheque-auto-reminder', ['module' => 'cheque_sales', 'cheque_customer_id' => $customer->id]);
                });
            }
        });

        return self::SUCCESS;
    }
}
