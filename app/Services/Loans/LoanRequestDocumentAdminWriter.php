<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\CustomerLoanRequest;
use App\Models\CustomerLoanRequestDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class LoanRequestDocumentAdminWriter
{
    public function updateReview(
        CustomerLoanRequestDocument $document,
        string $reviewStatus,
        ?string $expertNote,
    ): void {
        $document->review_status = $reviewStatus;
        $document->expert_note = $expertNote !== null && trim($expertNote) !== ''
            ? mb_substr(trim($expertNote), 0, 5000)
            : null;
        $document->save();
    }

    public function deleteDocument(CustomerLoanRequest $request, CustomerLoanRequestDocument $document): void
    {
        DB::transaction(function () use ($request, $document): void {
            $presetKey = (string) $document->preset_key;
            $path = (string) $document->stored_path;
            if ($path !== '') {
                Storage::disk('local')->delete($path);
            }
            $document->delete();

            $remaining = CustomerLoanRequestDocument::query()
                ->where('customer_loan_request_id', $request->id)
                ->where('preset_key', $presetKey)
                ->count();

            if ($remaining === 0) {
                $keys = $request->waived_initial_preset_keys;
                $keys = is_array($keys) ? $keys : [];
                if (! in_array($presetKey, $keys, true)) {
                    $keys[] = $presetKey;
                }
                $request->waived_initial_preset_keys = array_values(array_unique($keys));
                $request->save();
            }
        });
    }
}
