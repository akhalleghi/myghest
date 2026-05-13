<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerOnlinePaymentTransactionsPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UserPaymentTransactionsController extends Controller
{
    public function index(Request $request, CustomerOnlinePaymentTransactionsPresenter $presenter): View
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer !== null, 403);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $q = isset($validated['q']) && is_string($validated['q']) ? $validated['q'] : null;
        $rows = $presenter->paginateForCustomer($customer, $q);

        return view('user.portal.payment-transactions', [
            'pageTitle' => 'تراکنش‌های من',
            'rows' => $rows,
            'searchQ' => $q !== null ? trim($q) : '',
        ]);
    }
}
