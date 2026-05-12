<?php

declare(strict_types=1);

namespace App\Services\Loans;

/**
 * برچسب و کلاس نمایشی وضعیت درخواست وام (ادمین / کاربر).
 */
final class LoanRequestStatusPresentation
{
    /**
     * @param  array<string, string>  $titleByCode
     * @return array{label: string, class: string}
     */
    public function adminBadge(string $statusCode, array $titleByCode): array
    {
        $label = $titleByCode[$statusCode] ?? 'نامشخص';

        return [
            'label' => $label,
            'class' => 'lrq-badge '.$this->adminBadgeVariant($statusCode),
        ];
    }

    /**
     * @param  array<string, string>  $titleByCode
     * @return array{label: string, class: string}
     */
    public function userChip(string $statusCode, array $titleByCode): array
    {
        $label = $titleByCode[$statusCode] ?? 'نامشخص';

        return [
            'label' => $label,
            'class' => 'lr-status-chip--'.$this->userChipVariant($statusCode),
        ];
    }

    private function adminBadgeVariant(string $code): string
    {
        return match ($code) {
            'rejected' => 'lrq-badge--rejected',
            'paid', 'prioritized' => 'lrq-badge--approved',
            'pending_expert_review', 'needs_followup', 'documents_incomplete', 'initial' => 'lrq-badge--pending',
            'documents_complete', 'expert_re_review' => 'lrq-badge--review',
            default => 'lrq-badge--muted',
        };
    }

    private function userChipVariant(string $code): string
    {
        return match ($code) {
            'rejected' => 'rejected',
            'paid', 'prioritized' => 'approved',
            'pending_expert_review', 'needs_followup', 'documents_incomplete', 'initial' => 'pending',
            'documents_complete', 'expert_re_review' => 'review',
            default => 'draft',
        };
    }
}
