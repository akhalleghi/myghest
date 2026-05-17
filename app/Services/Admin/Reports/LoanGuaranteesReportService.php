<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanGuarantee;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LoanGuaranteesReportService
{
    /**
     * @return array<string, string>
     */
    public static function guaranteeTypeFilterOptions(): array
    {
        return [
            CustomerLoanGuarantee::TYPE_ORG_SELF => 'سازمانی - خودم',
            CustomerLoanGuarantee::TYPE_ORG_OTHER => 'سازمانی - شخص دیگر',
            CustomerLoanGuarantee::TYPE_CHEQUE => 'چک',
            CustomerLoanGuarantee::TYPE_GOLD => 'طلا',
            CustomerLoanGuarantee::TYPE_OTHER => 'سایر',
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolveDateRange(?string $fromJdate, ?string $toJdate): array
    {
        $from = JalaliInputParser::toCarbonDate($fromJdate);
        $to = JalaliInputParser::toCarbonDate($toJdate);

        if ($from === null || $to === null) {
            $today = Carbon::today();
            $jToday = Jalali::instance($today);
            $from = Carbon::createFromTimestamp($jToday->clone()->startYear()->getTimestamp())->startOfDay();
            $to = $today->copy()->endOfDay();
        } else {
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            } else {
                $from = $from->startOfDay();
                $to = $to->endOfDay();
            }
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function fetchResult(
        Carbon $from,
        Carbon $to,
        string $search = '',
        string $guaranteeType = '',
        string $settledFilter = '',
    ): array {
        $query = CustomerLoanGuarantee::query()
            ->select('customer_loan_guarantees.*')
            ->join('customer_loan_files as clf', 'clf.id', '=', 'customer_loan_guarantees.loan_file_id')
            ->whereNull('clf.revoked_at')
            ->whereBetween('clf.loan_start_date', [$from->toDateString(), $to->toDateString()])
            ->with([
                'loanFile' => function ($q): void {
                    $q->with(['loanType:id,title', 'customer:id,first_name,last_name,national_id,mobile,customer_code']);
                },
            ]);

        if ($settledFilter === 'yes') {
            $query->where('clf.is_settled', true);
        } elseif ($settledFilter === 'no') {
            $query->where('clf.is_settled', false);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('clf.loan_code', 'like', $like)
                    ->orWhere('clf.sub_file_number', 'like', $like)
                    ->orWhere('clf.description', 'like', $like)
                    ->orWhere('customer_loan_guarantees.type', 'like', $like)
                    ->orWhere('customer_loan_guarantees.description', 'like', $like)
                    ->orWhere('customer_loan_guarantees.meta', 'like', $like)
                    ->orWhereHas('loanFile.customer', function (Builder $c) use ($like): void {
                        $c->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('national_id', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('customer_code', 'like', $like);
                    })
                    ->orWhereHas('loanFile.loanType', static fn (Builder $lt): Builder => $lt->where('title', 'like', $like));
            });
        }

        $query->orderByDesc('clf.loan_start_date')->orderByDesc('customer_loan_guarantees.id');

        if ($guaranteeType !== '') {
            $query->where('customer_loan_guarantees.type', $guaranteeType);
        }

        /** @var Collection<int, CustomerLoanGuarantee> $guarantees */
        $guarantees = $query->get();

        $summary = [
            'total' => 0,
            'org_self' => 0,
            'org_other' => 0,
            'cheque' => 0,
            'gold' => 0,
            'other' => 0,
            'cheque_returned' => 0,
            'cheque_collected' => 0,
        ];

        $rows = [];
        foreach ($guarantees as $g) {
            $file = $g->loanFile;
            if ($file === null) {
                continue;
            }

            $customer = $file->customer;
            if ($customer === null) {
                continue;
            }

            $summary['total']++;
            $type = (string) $g->type;
            match ($type) {
                CustomerLoanGuarantee::TYPE_ORG_SELF => $summary['org_self']++,
                CustomerLoanGuarantee::TYPE_ORG_OTHER => $summary['org_other']++,
                CustomerLoanGuarantee::TYPE_CHEQUE => $summary['cheque']++,
                CustomerLoanGuarantee::TYPE_GOLD => $summary['gold']++,
                CustomerLoanGuarantee::TYPE_OTHER => $summary['other']++,
                default => null,
            };

            if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
                $meta = is_array($g->meta) ? $g->meta : [];
                if (! empty($meta['cheque_returned'])) {
                    $summary['cheque_returned']++;
                }
                if (! empty($meta['cheque_collected'])) {
                    $summary['cheque_collected']++;
                }
            }

            $detail = $this->guaranteeDetailForReport($g);
            $rows[] = $this->mapRow($g, $file, $customer, $detail);
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'شماره وام و نوع وام',
            'نام و نام خانوادگی',
            'کد ملی',
            'موبایل',
            'مبلغ وام (تومان)',
            'مبلغ قسط (تومان)',
            'نوع ضمانت',
            'اطلاعات ضمانت',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        $loanInfo = (string) ($row['loan_code'] ?? '').' — '.(string) ($row['loan_type_title'] ?? '—');
        $detailParts = [];
        if (! empty($row['guarantee_highlight_name'])) {
            $detailParts[] = (string) $row['guarantee_highlight_name'];
        }
        foreach ((array) ($row['guarantee_detail_lines'] ?? []) as $line) {
            $detailParts[] = (string) $line;
        }

        return [
            $loanInfo,
            (string) ($row['customer_full_name'] ?? ''),
            (string) ($row['customer_national_id'] ?? ''),
            (string) ($row['customer_mobile'] ?? ''),
            number_format((int) ($row['amount_toman'] ?? 0), 0, '.', ''),
            number_format((int) ($row['installment_amount_toman'] ?? 0), 0, '.', ''),
            (string) ($row['guarantee_type_label'] ?? ''),
            implode(' | ', $detailParts),
        ];
    }

    /**
     * @param  array{highlight: string, lines: list<string>}  $detail
     * @return array<string, mixed>
     */
    private function mapRow(
        CustomerLoanGuarantee $g,
        CustomerLoanFile $file,
        Customer $customer,
        array $detail,
    ): array {
        $typeLabels = self::guaranteeTypeFilterOptions();
        $customerUrl = route('admin.customers.index', ['open' => $customer->id]);
        $loanUrl = $customerUrl.'#loan-'.$file->id;

        return [
            'guarantee_id' => (int) $g->id,
            'loan_file_id' => (int) $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'loan_manage_url' => $loanUrl,
            'customer_id' => (int) $customer->id,
            'customer_full_name' => $customer->fullName(),
            'customer_national_id' => trim((string) ($customer->national_id ?? '')),
            'customer_mobile' => trim((string) ($customer->mobile ?? '')),
            'customer_manage_url' => $customerUrl,
            'amount_toman' => (int) $file->amount_toman,
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'guarantee_type' => (string) $g->type,
            'guarantee_type_label' => (string) ($typeLabels[$g->type] ?? $g->type),
            'guarantee_highlight_name' => $detail['highlight'],
            'guarantee_detail_lines' => $detail['lines'],
            'is_settled' => (bool) $file->is_settled,
        ];
    }

    /**
     * @return array{highlight: string, lines: list<string>}
     */
    public function guaranteeDetailForReport(CustomerLoanGuarantee $g): array
    {
        $meta = is_array($g->meta) ? $g->meta : [];
        $type = (string) $g->type;
        $highlight = '';
        $lines = [];

        $desc = trim((string) ($g->description ?? ''));
        if ($desc !== '') {
            $lines[] = 'توضیح: '.$desc;
        }

        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $orgSelfLbl = (string) ($meta['organization_name'] ?? $meta['org_name'] ?? '');
            if ($orgSelfLbl !== '') {
                $lines[] = 'سازمان: '.$orgSelfLbl;
            }
            if (! empty($meta['employee_no'])) {
                $lines[] = 'شماره پرسنلی: '.trim((string) $meta['employee_no']);
            }
        }

        if ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            if (! empty($meta['guarantor_name'])) {
                $highlight = trim((string) $meta['guarantor_name']);
            }
            $orgLbl = (string) ($meta['organization_name'] ?? $meta['org_name'] ?? '');
            if ($orgLbl !== '') {
                $lines[] = 'سازمان: '.$orgLbl;
            }
            if (! empty($meta['guarantor_national_id'])) {
                $lines[] = 'کد ملی: '.trim((string) $meta['guarantor_national_id']);
            }
            if (! empty($meta['guarantor_employee_no'])) {
                $lines[] = 'شماره پرسنلی: '.trim((string) $meta['guarantor_employee_no']);
            }
            if (! empty($meta['guarantor_phone'])) {
                $lines[] = 'موبایل ضامن: '.trim((string) $meta['guarantor_phone']);
            }
            $lines[] = 'موبایل ضامن احراز شده: '.((bool) ($meta['guarantor_mobile_verified'] ?? false) ? 'بله' : 'خیر');
        }

        if (! empty($meta['org_name']) && $type !== CustomerLoanGuarantee::TYPE_ORG_OTHER && $type !== CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $lines[] = 'سازمان: '.trim((string) $meta['org_name']);
        }
        if (! empty($meta['guarantor_name']) && $type !== CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $highlight = trim((string) $meta['guarantor_name']);
        }

        if (! empty($meta['cheque_owner_name'])) {
            $highlight = $highlight !== '' ? $highlight : trim((string) $meta['cheque_owner_name']);
            if ($type !== CustomerLoanGuarantee::TYPE_CHEQUE) {
                $lines[] = 'صاحب چک: '.trim((string) $meta['cheque_owner_name']);
            }
        }
        if (! empty($meta['cheque_owner_national_id'])) {
            $lines[] = 'کد ملی: '.trim((string) $meta['cheque_owner_national_id']);
        }
        if (! empty($meta['cheque_owner_mobile'])) {
            $lines[] = 'موبایل: '.trim((string) $meta['cheque_owner_mobile']);
        }
        if (! empty($meta['cheque_serial'])) {
            $lines[] = 'شماره چک: '.trim((string) $meta['cheque_serial']);
        }
        if (! empty($meta['cheque_sayadi'])) {
            $lines[] = 'صیادی: '.trim((string) $meta['cheque_sayadi']);
        }
        if (! empty($meta['cheque_due_jdate'])) {
            $lines[] = 'تاریخ چک: '.trim((string) $meta['cheque_due_jdate']);
        }
        if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            $lines[] = 'وصول شده؟ '.(! empty($meta['cheque_collected']) ? 'بله' : 'خیر');
            $lines[] = 'عودت شده؟ '.(! empty($meta['cheque_returned']) ? 'بله' : 'خیر');
        }

        $goldLabel = (string) ($meta['gold_item_label'] ?? $meta['gold_item_type'] ?? '');
        if ($goldLabel !== '') {
            $lines[] = 'نوع طلا: '.$goldLabel;
        }
        if (isset($meta['gold_weight_gram']) && $meta['gold_weight_gram'] !== '' && $meta['gold_weight_gram'] !== null) {
            $lines[] = 'وزن: '.trim((string) $meta['gold_weight_gram']).' گرم';
        }
        if (isset($meta['gold_quantity']) && $meta['gold_quantity'] !== '' && $meta['gold_quantity'] !== null) {
            $lines[] = 'تعداد: '.trim((string) $meta['gold_quantity']);
        }
        if (! empty($meta['gold_rate_toman'])) {
            $lines[] = 'نرخ: '.number_format((int) $meta['gold_rate_toman'], 0, '.', ',').' تومان';
        }

        $amt = isset($meta['amount_toman']) ? (int) $meta['amount_toman'] : 0;
        if ($amt > 0 && ($type === CustomerLoanGuarantee::TYPE_GOLD || $type === CustomerLoanGuarantee::TYPE_OTHER)) {
            $lines[] = 'مبلغ: '.number_format($amt, 0, '.', ',').' تومان';
        }

        return ['highlight' => $highlight, 'lines' => $lines];
    }
}
