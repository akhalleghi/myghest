<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\ChequeInstrument;
use App\Models\SmsTemplate;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Sms\SmsTemplateRenderer;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Validation\ValidationException;

final class ChequeSms
{
    public function text(ChequeInstrument $cheque, string $type): string
    {
        $cheque->loadMissing('sale.customer');
        $template = SmsTemplate::where('template_key', 'cheque_'.$type)->firstOrFail();
        $balance = app(ChequeFinance::class)->balance($cheque);

        return app(SmsTemplateRenderer::class)->render($template->body, [
            'customer_name' => $cheque->sale->customer->fullName(), 'cheque_number' => $cheque->sayad_number,
            'cheque_amount' => number_format($cheque->amount_toman).' تومان', 'cheque_due_date' => Jalali::instance($cheque->due_date)->format('Y/m/d'),
            'cheque_sale_code' => $cheque->sale->code, 'cheque_debt' => number_format($balance['remaining_toman']).' تومان',
        ]);
    }

    public function send(ChequeInstrument $cheque, string $body): array
    {
        $cheque->loadMissing('sale.customer');
        if (! $cheque->sale->customer->sms_enabled) {
            throw ValidationException::withMessages(['body' => 'پیامک این مشتری چکی غیرفعال است.']);
        }

        return app(RawSmsDispatcher::class)->send($cheque->sale->customer->mobile, $body, 'cheque-notification', [
            'module' => 'cheque_sales', 'cheque_instrument_id' => $cheque->id, 'cheque_customer_id' => $cheque->sale->cheque_customer_id,
            'admin_id' => auth('admin')->id(),
        ]);
    }
}
