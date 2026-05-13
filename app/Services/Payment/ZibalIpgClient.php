<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;

final class ZibalIpgClient
{
    private const REQUEST_URL = 'https://gateway.zibal.ir/v1/request';

    private const VERIFY_URL = 'https://gateway.zibal.ir/v1/verify';

    /**
     * @return array{ok: bool, track_id: ?int, message: string, raw: array<string, mixed>}
     */
    public function request(string $merchant, int $amountRial, string $callbackUrl, string $description, string $orderId): array
    {
        $resp = Http::timeout(25)
            ->acceptJson()
            ->asJson()
            ->post(self::REQUEST_URL, [
                'merchant' => $merchant,
                'amount' => $amountRial,
                'callbackUrl' => $callbackUrl,
                'description' => mb_substr($description, 0, 255),
                'orderId' => mb_substr($orderId, 0, 120),
            ]);

        if (! $resp->successful()) {
            return ['ok' => false, 'track_id' => null, 'message' => 'ارتباط با درگاه ناموفق بود.', 'raw' => []];
        }

        /** @var mixed $decoded */
        $decoded = $resp->json();
        $data = is_array($decoded) ? $decoded : [];

        $result = (int) ($data['result'] ?? 0);
        $trackId = isset($data['trackId']) ? (int) $data['trackId'] : null;

        if ($result !== 100 || $trackId === null || $trackId <= 0) {
            $msg = is_string($data['message'] ?? null) ? (string) $data['message'] : 'درخواست پرداخت توسط درگاه رد شد.';

            return ['ok' => false, 'track_id' => null, 'message' => $msg, 'raw' => $data];
        }

        return ['ok' => true, 'track_id' => $trackId, 'message' => 'ok', 'raw' => $data];
    }

    /**
     * @return array{ok: bool, amount_rial: int, ref_number: string, message: string, raw: array<string, mixed>}
     */
    public function verify(string $merchant, int $trackId): array
    {
        $resp = Http::timeout(25)->acceptJson()->asJson()->post(self::VERIFY_URL, [
            'merchant' => $merchant,
            'trackId' => $trackId,
        ]);

        if (! $resp->successful()) {
            return ['ok' => false, 'amount_rial' => 0, 'ref_number' => '', 'message' => 'ارتباط با درگاه ناموفق بود.', 'raw' => []];
        }

        /** @var mixed $decoded */
        $decoded = $resp->json();
        $data = is_array($decoded) ? $decoded : [];

        $result = (int) ($data['result'] ?? 0);
        if (! in_array($result, [100, 102], true)) {
            $msg = is_string($data['message'] ?? null) ? (string) $data['message'] : 'تأیید پرداخت ناموفق بود.';

            return ['ok' => false, 'amount_rial' => 0, 'ref_number' => '', 'message' => $msg, 'raw' => $data];
        }

        $amountRial = (int) ($data['amount'] ?? 0);
        if ($amountRial <= 0 && isset($data['paidAmount'])) {
            $amountRial = (int) $data['paidAmount'];
        }
        $ref = '';
        if (isset($data['refNumber']) && is_scalar($data['refNumber'])) {
            $ref = (string) $data['refNumber'];
        } elseif (isset($data['refnumber']) && is_scalar($data['refnumber'])) {
            $ref = (string) $data['refnumber'];
        }

        return ['ok' => true, 'amount_rial' => $amountRial, 'ref_number' => $ref, 'message' => 'ok', 'raw' => $data];
    }
}
