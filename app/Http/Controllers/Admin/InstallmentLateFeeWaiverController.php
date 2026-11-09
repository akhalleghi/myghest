<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanInstallment;
use App\Services\Loans\InstallmentLateFeeWaiverService;
use Hekmatinasser\Jalali\Jalali;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class InstallmentLateFeeWaiverController extends Controller
{
    public function __invoke(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanInstallment $installment, InstallmentLateFeeWaiverService $waivers): JsonResponse
    {
        abort_unless((int) $loanFile->customer_id === (int) $customer->id
            && (int) $installment->customer_loan_file_id === (int) $loanFile->id, 404);
        if ($request->isMethod('GET')) {
            $history = DB::table('installment_late_fee_waivers as w')->leftJoin('admins as a', 'a.id', '=', 'w.admin_id')
                ->where('w.installment_id', $installment->id)->orderByDesc('w.id')->limit(30)
                ->get(['w.old_remaining_toman', 'w.new_remaining_toman', 'w.reason', 'w.created_at', 'a.name as admin_name'])
                ->map(static fn ($row): array => [
                    'before' => (int) $row->old_remaining_toman, 'after' => (int) $row->new_remaining_toman,
                    'reason' => $row->reason, 'admin_name' => $row->admin_name ?? 'مدیر حذف‌شده',
                    'date' => Jalali::instance(Carbon::parse($row->created_at))->format('Y/m/d H:i'),
                ]);

            return response()->json([...$waivers->preview($loanFile, $installment), 'history' => $history]);
        }
        $data = $request->validate([
            'remaining_toman' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], ['remaining_toman' => 'جریمه پرداخت‌نشده جدید', 'reason' => 'دلیل بخشودگی']);
        $after = $waivers->reduce((int) $customer->id, (int) $loanFile->id, (int) $installment->id,
            (int) $data['remaining_toman'], $data['version'], trim($data['reason']), (int) $request->user('admin')->id);

        return response()->json(['message' => 'بخشودگی دیرکرد ثبت و مانده بدهی قسط و پرونده به‌روز شد.', ...$after]);
    }
}
