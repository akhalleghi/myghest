<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SmsManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);
        $query = $this->buildFilteredQuery($filters['from'], $filters['to'], $filters['status'], $filters['search']);

        $logs = $query->latest('sent_at')->paginate(20)->withQueryString();

        return view('admin.sms.index', [
            'logs' => $logs,
            'status' => $filters['status'],
            'search' => $filters['search'],
            'isRangeMode' => $filters['isRangeMode'],
            'selectedDate' => $filters['selectedDate'],
            'selectedDateJalali' => Jalali::instance($filters['selectedDate'])->format('Y/m/d'),
            'fromJDate' => $filters['fromJDate'] !== '' ? $filters['fromJDate'] : Jalali::instance($filters['from'])->format('Y/m/d'),
            'toJDate' => $filters['toJDate'] !== '' ? $filters['toJDate'] : Jalali::instance($filters['to'])->format('Y/m/d'),
            'prevDate' => $filters['selectedDate']->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $filters['selectedDate']->copy()->addDay()->format('Y-m-d'),
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $rows = $this->buildFilteredQuery($filters['from'], $filters['to'], $filters['status'], $filters['search'])
            ->latest('sent_at')
            ->get();

        $filename = 'sms-logs-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            // UTF-16LE BOM for proper Persian display in Excel on Windows.
            fwrite($out, "\xFF\xFE");

            $this->writeExcelUnicodeRow($out, [
                'پنل پیامک',
                'وضعیت',
                'زمان ارسال',
                'متن پیام',
                'دریافت کننده',
                'نوع',
                'هزینه',
            ]);

            foreach ($rows as $log) {
                $sentAt = $log->sent_at ? jalali($log->sent_at)->format('Y/m/d H:i') : '';

                $this->writeExcelUnicodeRow($out, [
                    (string) ($log->sms_panel ?? ''),
                    $log->statusLabel(),
                    $sentAt,
                    (string) ($log->message_text ?? ''),
                    (string) ($log->recipient ?? ''),
                    (string) ($log->type ?? ''),
                    number_format((float) $log->cost, 0, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * @return array{
     *   status:string,
     *   search:string,
     *   isRangeMode:bool,
     *   fromJDate:string,
     *   toJDate:string,
     *   selectedDate:Carbon,
     *   from:Carbon,
     *   to:Carbon
     * }
     */
    private function resolveFilters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $mode = (string) $request->query('mode', 'day');

        $allowedStatuses = [
            SmsLog::STATUS_PENDING,
            SmsLog::STATUS_DELIVERED,
            SmsLog::STATUS_UNDELIVERED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $isRangeMode = false;
        $fromJDate = (string) $request->query('from_jdate', '');
        $toJDate = (string) $request->query('to_jdate', '');
        $selectedDate = Carbon::today();

        if ($mode === 'range') {
            $from = $this->parseJalaliDate($fromJDate);
            $to = $this->parseJalaliDate($toJDate);

            if ($from !== null && $to !== null) {
                $isRangeMode = true;
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        if (! $isRangeMode) {
            $rawDate = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
            $parsedDate = Carbon::createFromFormat('Y-m-d', $rawDate);
            $selectedDate = $parsedDate ?: Carbon::today();
            $from = $selectedDate->copy()->startOfDay();
            $to = $selectedDate->copy()->endOfDay();
        }

        return [
            'status' => $status,
            'search' => $search,
            'isRangeMode' => $isRangeMode,
            'fromJDate' => $fromJDate,
            'toJDate' => $toJDate,
            'selectedDate' => $selectedDate,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function buildFilteredQuery(Carbon $from, Carbon $to, string $status, string $search): Builder
    {
        return SmsLog::query()
            ->whereBetween('sent_at', [$from, $to])
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('sms_panel', 'like', "%$search%")
                        ->orWhere('message_text', 'like', "%$search%")
                        ->orWhere('recipient', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%");
                });
            });
    }

    /**
     * @param resource $out
     * @param array<int, string> $cells
     */
    private function writeExcelUnicodeRow($out, array $cells): void
    {
        $cleanCells = array_map(static function (string $value): string {
            return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);
        }, $cells);

        $line = implode("\t", $cleanCells)."\r\n";
        fwrite($out, mb_convert_encoding($line, 'UTF-16LE', 'UTF-8'));
    }

    private function parseJalaliDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        try {
            $j = Jalali::parseFormat('Y/m/d', $value);

            return Carbon::createFromTimestamp($j->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }
}
