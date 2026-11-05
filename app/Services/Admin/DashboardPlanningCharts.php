<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Services\Loans\LoanFileFinanceCalculator;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;

final class DashboardPlanningCharts
{
    public function build(Carbon $today): array
    {
        $months = $this->periods($today, -23, 0);
        $future = $this->periods($today, -1, 10);
        $years = $this->periods($today, -11, 0, true);
        $customersMonthly = array_fill(0, 24, 0);
        $customersYearly = array_fill(0, 12, 0);
        foreach (Customer::query()->select(['membership_at', 'created_at'])->cursor() as $customer) {
            $date = $customer->membership_at ?? $customer->created_at;
            if ($date === null || $date->gt($today->copy()->endOfDay())) {
                continue;
            }
            $this->add($months, $customersMonthly, $date->toDateString(), 1);
            $this->add($years, $customersYearly, $date->toDateString(), 1);
        }

        $futureValues = array_fill(0, 12, 0);
        $overdueMonthly = array_fill(0, 24, 0);
        $overdueYearly = array_fill(0, 12, 0);
        $finance = app(LoanFileFinanceCalculator::class);
        CustomerLoanFile::query()->whereNull('revoked_at')->where('is_settled', false)
            ->with(['loanType', 'installments.payments'])->chunkById(100, function ($files) use ($finance, $today, $future, $months, $years, &$futureValues, &$overdueMonthly, &$overdueYearly): void {
                foreach ($files as $file) {
                    $snapshot = $finance->loanInstallmentFinancialSnapshot($file, $finance->totalRepayableToman($file), $today);
                    foreach ($file->installments as $installment) {
                        $remaining = (int) $snapshot['installment_payables'][(int) $installment->id]['remaining_toman'];
                        if ($remaining <= 0) {
                            continue;
                        }
                        $date = $installment->due_date->toDateString();
                        $this->add($future, $futureValues, $date, $remaining);
                        if ($date < $today->toDateString()) {
                            $this->add($months, $overdueMonthly, $date, $remaining);
                            $this->add($years, $overdueYearly, $date, $remaining);
                        }
                    }
                }
            });

        return [
            'upcoming' => $this->chart('chart-upcoming-installments', 'اقساط پرداخت‌نشده؛ ماه قبل تا ۱۰ ماه آینده', $future, $futureValues, '#8b5cf6'),
            'customers-monthly' => $this->chart('chart-new-customers-monthly', 'مشتریان جدید؛ ۱۲ ماه اخیر', array_slice($months, -12), array_slice($customersMonthly, -12), '#0891b2', true),
            'customers-yearly' => $this->chart('chart-new-customers-yearly', 'مشتریان جدید؛ ۱۲ سال اخیر', $years, $customersYearly, '#16a34a', true),
            'overdue' => array_merge($this->chart('chart-overdue-history', 'ماندهٔ فعلی اقساط معوق بر اساس سررسید', $months, $overdueMonthly, '#e11d48'), [
                'monthly' => $this->chart('chart-overdue-history', 'معوق بر اساس ماه سررسید', $months, $overdueMonthly, '#e11d48')['series'],
                'yearly' => $this->chart('chart-overdue-history', 'معوق بر اساس سال سررسید', $years, $overdueYearly, '#e11d48')['series'],
            ]),
        ];
    }

    private function periods(Carbon $today, int $from, int $to, bool $yearly = false): array
    {
        $periods = [];
        for ($offset = $from; $offset <= $to; $offset++) {
            $j = Jalali::instance($today);
            $j = $yearly ? $j->startYear()->addYears($offset) : $j->startMonth()->addMonths($offset);
            $start = Carbon::createFromTimestamp($j->getTimestamp(), $today->getTimezone());
            $endJ = $yearly ? $j->clone()->endYear() : $j->clone()->endMonth();
            $end = Carbon::createFromTimestamp($endJ->getTimestamp(), $today->getTimezone());
            $periods[] = ['start' => $start->toDateString(), 'end' => $end->toDateString(),
                'label' => Jalali::enToFaNumbers($j->format($yearly ? 'Y' : 'F Y')),
                'short_label' => Jalali::enToFaNumbers($j->format($yearly ? 'Y' : 'F'))];
        }

        return $periods;
    }

    private function add(array $periods, array &$values, string $date, int $value): void
    {
        foreach ($periods as $index => $period) {
            if ($date >= $period['start'] && $date <= $period['end']) {
                $values[$index] += $value;

                return;
            }
        }
    }

    private function chart(string $widget, string $title, array $periods, array $values, string $color, bool $counts = false): array
    {
        $series = [];
        foreach ($periods as $index => $period) {
            $series[] = ['label' => $period['short_label'], 'period' => $period['label'], 'value' => $values[$index],
                'valueLabel' => Jalali::enToFaNumbers(number_format($values[$index])).($counts ? ' مشتری' : ' تومان')];
        }

        return ['widget_id' => $widget, 'title' => $title, 'color' => $color, 'series' => $series];
    }
}
