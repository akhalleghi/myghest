<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\LoanType;
use Illuminate\Validation\ValidationException;

/**
 * همان قواعد مرحلهٔ ۱ ویزارد (سمت کلاینت) برای جلوگیری از دور زدن اعتبارسنجی.
 */
final class LoanWizardParameterValidator
{
    private const AMOUNT_MIN_TOMAN = 100_000;

    private const AMOUNT_FALLBACK_MAX_TOMAN = 10_000_000_000;

    private const INSTALLMENT_GAP_FALLBACK_MAX = 24;

    private const UNLIMITED_COUNT_MAX = 360;

    public function assertPlanAcceptsParameters(
        LoanType $plan,
        int $amountToman,
        int $installmentsCount,
        int $installmentGap,
        string $installmentGapUnit,
        string $description,
    ): void {
        $desc = trim($description);
        if (mb_strlen($desc) < 3) {
            throw ValidationException::withMessages(['description' => 'شرح کالاها و خدمات باید حداقل ۳ نویسه باشد.']);
        }
        if (mb_strlen($desc) > 2000) {
            throw ValidationException::withMessages(['description' => 'شرح کالاها و خدمات نباید بیشتر از ۲۰۰۰ نویسه باشد.']);
        }

        $unit = $installmentGapUnit;
        if ($unit !== LoanType::GAP_MONTHLY && $unit !== LoanType::GAP_WEEKLY) {
            throw ValidationException::withMessages(['installment_gap_unit' => 'واحد فاصلهٔ اقساط نامعتبر است.']);
        }
        if ($unit !== (string) $plan->installment_gap_unit) {
            throw ValidationException::withMessages(['installment_gap_unit' => 'واحد فاصلهٔ اقساط با طرح انتخاب‌شده هم‌خوانی ندارد.']);
        }

        $ab = $this->amountBounds($plan);
        if ($amountToman < $ab['min'] || $amountToman > $ab['max']) {
            throw ValidationException::withMessages(['amount_toman' => 'مبلغ وام خارج از محدودهٔ مجاز این طرح است.']);
        }

        $gb = $this->gapBounds($plan);
        if ($installmentGap < $gb['min'] || $installmentGap > $gb['max']) {
            throw ValidationException::withMessages(['installment_gap' => 'فاصلهٔ اقساط خارج از محدودهٔ مجاز این طرح است.']);
        }

        $cb = $this->countBoundsAndAllowed($plan);
        if ($cb['allowed'] !== null) {
            if (! in_array($installmentsCount, $cb['allowed'], true)) {
                throw ValidationException::withMessages(['installments_count' => 'تعداد اقساط برای این طرح مجاز نیست.']);
            }
        } elseif ($installmentsCount < $cb['min'] || $installmentsCount > $cb['max']) {
            throw ValidationException::withMessages(['installments_count' => 'تعداد اقساط خارج از محدودهٔ مجاز این طرح است.']);
        }

        $cap = $this->capForCount($plan, $installmentsCount);
        if ($cap !== null && $amountToman > $cap) {
            throw ValidationException::withMessages(['amount_toman' => 'با توجه به تعداد اقساط انتخاب‌شده، مبلغ از سقف تعریف‌شده برای این طرح بیشتر است.']);
        }
    }

    /**
     * @return array{min: int, max: int}
     */
    public function amountBounds(LoanType $plan): array
    {
        $max = $plan->max_loan_amount !== null
            ? (int) $plan->max_loan_amount
            : self::AMOUNT_FALLBACK_MAX_TOMAN;

        return ['min' => self::AMOUNT_MIN_TOMAN, 'max' => $max];
    }

    /**
     * @return array{min: int, max: int}
     */
    public function gapBounds(LoanType $plan): array
    {
        $max = $plan->max_installment_gap !== null
            ? max(1, (int) $plan->max_installment_gap)
            : self::INSTALLMENT_GAP_FALLBACK_MAX;

        return ['min' => 1, 'max' => $max];
    }

    /**
     * @return array{min: int, max: int, allowed: list<int>|null}
     */
    public function countBoundsAndAllowed(LoanType $plan): array
    {
        $rep = is_array($plan->repayment_periods) ? $plan->repayment_periods : ['type' => LoanType::REPAY_UNLIMITED];
        $type = (string) ($rep['type'] ?? LoanType::REPAY_UNLIMITED);

        if ($type === LoanType::REPAY_UNLIMITED) {
            return ['min' => 1, 'max' => self::UNLIMITED_COUNT_MAX, 'allowed' => null];
        }

        if ($type === LoanType::REPAY_MAX_UNTIL) {
            $mm = max(1, (int) ($rep['max_months'] ?? 1));

            return ['min' => 1, 'max' => $mm, 'allowed' => null];
        }

        if ($type === LoanType::REPAY_ALLOWED_MONTHS) {
            $opts = [];
            $rows = isset($rep['allowed_rows']) && is_array($rep['allowed_rows']) ? $rep['allowed_rows'] : [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $months = (int) ($row['months'] ?? 0);
                if ($months > 0) {
                    $opts[] = $months;
                }
            }
            $opts = array_values(array_unique($opts));
            sort($opts);
            if ($opts === []) {
                return ['min' => 1, 'max' => 1, 'allowed' => [1]];
            }

            return ['min' => $opts[0], 'max' => $opts[count($opts) - 1], 'allowed' => $opts];
        }

        return ['min' => 1, 'max' => self::UNLIMITED_COUNT_MAX, 'allowed' => null];
    }

    public function capForCount(LoanType $plan, int $countMonths): ?int
    {
        $rep = is_array($plan->repayment_periods) ? $plan->repayment_periods : [];
        if (($rep['type'] ?? '') !== LoanType::REPAY_ALLOWED_MONTHS) {
            return null;
        }
        $rows = isset($rep['allowed_rows']) && is_array($rep['allowed_rows']) ? $rep['allowed_rows'] : [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['months'] ?? 0) === $countMonths) {
                return isset($row['cap']) ? (int) $row['cap'] : null;
            }
        }

        return null;
    }
}
