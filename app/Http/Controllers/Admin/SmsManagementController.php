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

final class SmsManagementController extends Controller
{
    public function index(Request $request): View
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

        $query = SmsLog::query()
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

        $logs = $query->latest('sent_at')->paginate(20)->withQueryString();

        return view('admin.sms.index', [
            'logs' => $logs,
            'status' => $status,
            'search' => $search,
            'isRangeMode' => $isRangeMode,
            'selectedDate' => $selectedDate,
            'selectedDateJalali' => Jalali::instance($selectedDate)->format('Y/m/d'),
            'fromJDate' => $fromJDate !== '' ? $fromJDate : Jalali::instance($from)->format('Y/m/d'),
            'toJDate' => $toJDate !== '' ? $toJDate : Jalali::instance($to)->format('Y/m/d'),
            'prevDate' => $selectedDate->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $selectedDate->copy()->addDay()->format('Y-m-d'),
        ]);
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
