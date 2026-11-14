<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\ChequeInstrument;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Support\Facades\Schema;

final class ChequeStatistics
{
    public function build(): array
    {
        if (! Schema::hasTable('cheque_instruments')) {
            return ['rows' => [], 'charts' => []];
        }
        $today = Carbon::today(config('app.timezone'));
        $j = Jalali::instance($today);
        $currentYear = (int) $j->year;
        $currentMonth = $j->format('Y/m');
        $buckets = [];
        $rows = [
            'month' => ['label' => 'چک‌های ماه جاری بر اساس سررسید', 'count' => 0, 'amount' => 0],
            'cleared' => ['label' => 'چک‌های وصول مالی شده', 'count' => 0, 'amount' => 0],
            'open' => ['label' => 'چک‌های تسویه‌نشده؛ مبلغ اسمی (شامل انتقالی و عودتی)', 'count' => 0, 'amount' => 0],
            'bounced' => ['label' => 'چک‌های پوشه برگشتی', 'count' => 0, 'amount' => 0],
            'resolved' => ['label' => 'تعیین تکلیف اداری: وصول، انتقال یا عودت', 'count' => 0, 'amount' => 0],
        ];
        foreach ([-1 => 'سال قبل', 0 => 'سال جاری', 1 => 'سال آینده'] as $offset => $label) {
            $rows['year'.$offset] = ['label' => 'چک‌های '.$label.' بر اساس سررسید', 'count' => 0, 'amount' => 0];
        }
        ChequeInstrument::with(['sale', 'receipts'])->chunkById(150, function ($cheques) use (&$rows, &$buckets, $currentMonth, $currentYear): void {
            foreach ($cheques as $c) {
                $date = Jalali::instance($c->due_date);
                $month = $date->format('Y/m');
                $year = (int) $date->year;
                $buckets[$month] = ($buckets[$month] ?? 0) + $c->amount_toman;
                $buckets[(string) $year] = ($buckets[(string) $year] ?? 0) + $c->amount_toman;
                $keys = [];
                if ($month === $currentMonth) {
                    $keys[] = 'month';
                }
                if (abs($year - $currentYear) <= 1) {
                    $keys[] = 'year'.($year - $currentYear);
                }
                if ($c->cleared_at) {
                    $keys[] = 'cleared';
                } else {
                    $keys[] = 'open';
                }
                if ($c->folder === 'bounced') {
                    $keys[] = 'bounced';
                }
                if (in_array($c->folder, ['archive', 'returned', 'transferred'], true)) {
                    $keys[] = 'resolved';
                }
                foreach ($keys as $key) {
                    $rows[$key]['count']++;
                    $rows[$key]['amount'] += $c->amount_toman;
                }
            }
        });
        $charts = [];
        foreach ([['cheques-past', 'مبالغ چک‌ها؛ ۱۲ ماه قبل', -12, -1, false], ['cheques-future', 'مبالغ چک‌ها؛ ماه جاری و ۱۲ ماه آینده', 0, 12, false], ['cheques-years', 'مبالغ چک‌ها؛ ۹ سال قبل تا سال آینده', -9, 1, true]] as [$id, $title, $from, $to, $yearly]) {
            $series = [];
            for ($offset = $from; $offset <= $to; $offset++) {
                $period = Jalali::instance($today);
                $period = $yearly ? $period->startYear()->addYears($offset) : $period->startMonth()->addMonths($offset);
                $value = $buckets[$period->format($yearly ? 'Y' : 'Y/m')] ?? 0;
                $series[] = ['label' => Jalali::enToFaNumbers($period->format($yearly ? 'Y' : 'F')), 'period' => Jalali::enToFaNumbers($period->format($yearly ? 'Y' : 'F Y')), 'value' => $value, 'valueLabel' => Jalali::enToFaNumbers(number_format($value)).' تومان'];
            }
            $charts[$id] = ['widget_id' => $id, 'title' => $title, 'color' => '#0d9488', 'series' => $series];
        }

        return ['rows' => $rows, 'charts' => $charts];
    }
}
