<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestStatusLog;
use Illuminate\Http\Request;

final class LoanRequestStatusTransitionLogger
{
    public function log(
        CustomerLoanRequest $request,
        ?string $fromStatus,
        string $toStatus,
        string $actorType,
        ?int $adminId,
        ?Request $httpRequest = null,
    ): void {
        CustomerLoanRequestStatusLog::query()->create([
            'customer_loan_request_id' => $request->id,
            'actor_type' => $actorType,
            'admin_id' => $adminId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'ip_address' => $httpRequest?->ip(),
            'user_agent' => $httpRequest !== null ? mb_substr((string) $httpRequest->userAgent(), 0, 2000) : null,
            'created_at' => now(),
        ]);
    }
}
