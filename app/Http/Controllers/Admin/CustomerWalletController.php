<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerWalletTransaction;
use App\Models\SmsLog;
use App\Models\SmsPanelSetting;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsPanelManager;
use App\Services\Wallet\CustomerWalletService;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomerWalletController extends Controller
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
        private readonly SmsPanelManager $panelManager,
    ) {}

    public function show(Customer $customer): JsonResponse
    {
        $wallet = $this->walletService->ensureWallet($customer);

        return response()->json([
            'wallet' => [
                'customer_id' => $customer->id,
                'balance_toman' => (int) $wallet->balance_toman,
                'is_locked' => (bool) $wallet->is_locked,
                'locked_at' => optional($wallet->locked_at)?->toDateTimeString(),
            ],
            'sms_templates' => SmsTemplate::query()
                ->latest('id')
                ->get(['id', 'title', 'category', 'body'])
                ->map(static fn (SmsTemplate $tpl): array => [
                    'id' => $tpl->id,
                    'title' => $tpl->title,
                    'category' => $tpl->category,
                    'body' => $tpl->body,
                ])
                ->values(),
        ]);
    }

    public function setLock(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'is_locked' => ['required', 'boolean'],
        ]);

        $wallet = $this->walletService->setLock(
            $customer,
            (bool) $validated['is_locked'],
            auth('admin')->id()
        );

        return response()->json([
            'message' => $wallet->is_locked ? 'کیف پول قفل شد.' : 'کیف پول از حالت قفل خارج شد.',
            'wallet' => [
                'balance_toman' => (int) $wallet->balance_toman,
                'is_locked' => (bool) $wallet->is_locked,
                'locked_at' => optional($wallet->locked_at)?->toDateTimeString(),
            ],
        ]);
    }

    public function adjust(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:deposit,withdraw'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:500'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'request_id' => ['required', 'string', 'uuid'],
        ]);

        try {
            [$wallet, $tx, $isDuplicate] = $this->walletService->adjust(
                $customer,
                (string) $validated['direction'],
                (int) $validated['amount_toman'],
                trim((string) ($validated['description'] ?? '')),
                auth('admin')->id(),
                $request->ip(),
                $request->userAgent(),
                (string) $validated['request_id'],
                null
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $smsFeedback = '';
        if (! $isDuplicate && (bool) ($validated['send_sms'] ?? false)) {
            $smsText = trim((string) ($validated['sms_text'] ?? ''));
            if ($smsText === '' && isset($validated['sms_template_id'])) {
                $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
                if ($template !== null) {
                    $smsText = $this->renderSmsTemplate($template->body, [
                        'customer_name' => $customer->fullName(),
                        'paid_amount' => number_format((int) $tx->amount_toman, 0, '.', ',').' تومان',
                        'remaining_loan' => number_format((int) $wallet->balance_toman, 0, '.', ',').' تومان',
                        'installment_amount' => number_format((int) $tx->amount_toman, 0, '.', ',').' تومان',
                        'store_name' => $this->appDisplayName(),
                    ]);
                }
            }
            if ($smsText === '') {
                $dirFa = $tx->direction === CustomerWalletTransaction::DIRECTION_DEPOSIT ? 'واریز' : 'برداشت';
                $smsText = 'سامانه '.$this->appDisplayName()."\n"
                    .'مشتری: '.$customer->fullName()."\n"
                    .'نوع تراکنش: '.$dirFa."\n"
                    .'مبلغ: '.number_format((int) $tx->amount_toman, 0, '.', ',').' تومان'."\n"
                    .'موجودی کیف پول: '.number_format((int) $wallet->balance_toman, 0, '.', ',').' تومان';
            }

            $smsResult = $this->sendRawSms($customer->mobile, $smsText, 'wallet-transaction');
            $smsFeedback = ' '.$smsResult['message'];
        }

        return response()->json([
            'message' => $isDuplicate
                ? 'این درخواست قبلا ثبت شده بود؛ تراکنش تکراری اعمال نشد.'
                : 'تراکنش کیف پول ثبت شد.'.$smsFeedback,
            'wallet' => [
                'balance_toman' => (int) $wallet->balance_toman,
                'is_locked' => (bool) $wallet->is_locked,
                'locked_at' => optional($wallet->locked_at)?->toDateTimeString(),
            ],
            'transaction' => $this->mapTransaction($tx),
        ]);
    }

    public function transactions(Customer $customer): JsonResponse
    {
        $this->walletService->ensureWallet($customer);

        $rows = CustomerWalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->with('actorAdmin:id,name,username')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (CustomerWalletTransaction $tx): array => $this->mapTransaction($tx))
            ->values();

        return response()->json([
            'transactions' => $rows,
        ]);
    }

    public function exportTransactionsExcel(Customer $customer): StreamedResponse
    {
        $this->walletService->ensureWallet($customer);

        $rows = CustomerWalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->with('actorAdmin:id,name,username')
            ->latest('id')
            ->get();

        $filename = 'wallet-transactions-customer-'.$customer->id.'-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows, $customer): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, [
                'کد مشتری',
                'نام مشتری',
                'زمان',
                'نوع تراکنش',
                'مبلغ (تومان)',
                'موجودی بعد از تراکنش (تومان)',
                'توضیحات',
                'اپراتور',
            ]);

            foreach ($rows as $tx) {
                $createdAtText = '';
                if ($tx->created_at !== null) {
                    $createdAtText = Jalali::instance($tx->created_at)->format('Y/m/d H:i');
                }
                if ($createdAtText !== '') {
                    // Force Excel to keep datetime as text (prevents #### rendering in some locales/column widths).
                    $createdAtText = "'".$createdAtText;
                }

                $this->writeExcelUnicodeRow($out, [
                    (string) $customer->customer_code,
                    $customer->fullName(),
                    $createdAtText,
                    $tx->direction === CustomerWalletTransaction::DIRECTION_DEPOSIT ? 'واریز' : 'برداشت',
                    (string) $tx->amount_toman,
                    (string) $tx->balance_after_toman,
                    (string) ($tx->description ?? ''),
                    (string) ($tx->actorAdmin?->name ?? $tx->actorAdmin?->username ?? '—'),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    private function mapTransaction(CustomerWalletTransaction $tx): array
    {
        $createdAt = '';
        if ($tx->created_at !== null) {
            $createdAt = Jalali::instance($tx->created_at)->format('Y/m/d H:i');
        }

        return [
            'id' => $tx->id,
            'direction' => $tx->direction,
            'amount_toman' => (int) $tx->amount_toman,
            'balance_after_toman' => (int) $tx->balance_after_toman,
            'description' => (string) ($tx->description ?? ''),
            'created_at' => $createdAt,
            'actor_name' => (string) ($tx->actorAdmin?->name ?? $tx->actorAdmin?->username ?? '—'),
        ];
    }

    private function renderSmsTemplate(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
        }

        return trim($out);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function sendRawSms(string $recipient, string $messageText, string $type): array
    {
        $active = SmsPanelSetting::query()->where('is_active', true)->first();
        if ($active === null) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک فعال نیست).'];
        }

        $providerOptions = $this->panelManager->providerOptions();
        $providerKey = $active->provider;
        if (! isset($providerOptions[$providerKey])) {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (پنل پیامک پیکربندی نشده).'];
        }

        $password = $this->decryptPasswordOrEmpty((string) $active->password);
        if ($password === '') {
            return ['ok' => false, 'message' => 'پیامک ارسال نشد (رمز پنل ذخیره نشده).'];
        }

        $gateway = $this->panelManager->gateway($providerKey);
        $result = $gateway->sendTestMessage(
            (string) $active->username,
            $password,
            $recipient,
            $messageText,
            [
                'domain_name' => (string) ($active->domain_name ?: 'sepahansms'),
                'sender_number' => (string) ($active->sender_number ?: '50003300'),
            ]
        );

        SmsLog::query()->create([
            'sms_panel' => (string) ($providerOptions[$providerKey] ?? $providerKey),
            'status' => $result->ok ? SmsLog::STATUS_DELIVERED : SmsLog::STATUS_UNDELIVERED,
            'sent_at' => now(),
            'message_text' => $messageText,
            'recipient' => $recipient,
            'type' => $type,
            'cost' => 0,
            'meta' => [
                'provider' => $providerKey,
                'response_code' => $result->code,
                'result_message' => $result->message,
            ],
        ]);

        // Do not overwrite panel connection state from wallet operations.
        // Connection status should only be managed in SMS settings/test flow.

        return [
            'ok' => $result->ok,
            'message' => $result->ok
                ? 'پیامک برای کاربر ارسال شد.'
                : 'عملیات ذخیره شد اما ارسال پیامک ناموفق بود: '.$result->message,
        ];
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $v : (string) config('app.name');
    }

    private function decryptPasswordOrEmpty(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  resource  $out
     * @param  array<int, string>  $cells
     */
    private function writeExcelUnicodeRow($out, array $cells): void
    {
        $cleanCells = array_map(static function (string $value): string {
            return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);
        }, $cells);

        $line = implode("\t", $cleanCells)."\r\n";
        fwrite($out, mb_convert_encoding($line, 'UTF-16LE', 'UTF-8'));
    }
}
