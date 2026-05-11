<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class UserPanelController extends Controller
{
    public function dashboard(): View
    {
        return view('user.portal.dashboard', [
            'pageTitle' => 'داشبورد',
        ]);
    }

    public function loans(): View
    {
        return view('user.portal.loans', [
            'pageTitle' => 'لیست وام‌ها',
        ]);
    }

    public function deposits(): View
    {
        return view('user.portal.deposits', [
            'pageTitle' => 'اعلام واریزی‌ها',
        ]);
    }

    public function loanRequest(): View
    {
        return view('user.portal.loan-request', [
            'pageTitle' => 'درخواست وام',
        ]);
    }
}
