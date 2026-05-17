<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoginLog;
use App\Support\ListPerPage;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminCustomerLoginReportController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $logs = CustomerLoginLog::query()
            ->with(['customer:id,first_name,last_name,username,national_id,mobile,customer_code'])
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.$search.'%';
                $q->whereHas('customer', function (Builder $c) use ($like): void {
                    $c->where('username', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->orderByDesc('logged_in_at')
            ->orderByDesc('id')
            ->paginate(ListPerPage::resolve($request))
            ->withQueryString();

        $rows = $logs->getCollection()->map(fn (CustomerLoginLog $log): array => $this->mapRow($log));

        $logs->setCollection($rows);

        return view('admin.customer_login_logs.index', [
            'pageTitle' => 'گزارش ورود',
            'logs' => $logs,
            'search' => $search,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(CustomerLoginLog $log): array
    {
        $c = $log->customer;
        $at = $log->logged_in_at instanceof Carbon ? $log->logged_in_at : Carbon::parse((string) $log->logged_in_at);

        $name = $c !== null ? trim($c->first_name.' '.$c->last_name) : '—';

        return [
            'id' => (int) $log->id,
            'id_fa' => Jalali::enToFaNumbers((string) $log->id),
            'logged_in_at_fa' => Jalali::enToFaNumbers(Jalali::instance($at)->format('Y/m/d')).' '.Jalali::enToFaNumbers($at->format('H:i:s')),
            'customer_name' => $name !== '' ? $name : '—',
            'username_fa' => $c !== null ? Jalali::enToFaNumbers((string) $c->username) : '—',
            'national_id_fa' => $c !== null ? Jalali::enToFaNumbers((string) $c->national_id) : '—',
            'mobile_fa' => $c !== null ? Jalali::enToFaNumbers((string) $c->mobile) : '—',
            'customer_code_fa' => $c !== null ? Jalali::enToFaNumbers((string) ($c->customer_code ?? '')) : '—',
            'ip_fa' => Jalali::enToFaNumbers((string) ($log->ip_address ?? '—')),
            'device_type' => (string) ($log->device_type ?? 'unknown'),
            'device_type_fa' => $this->deviceTypeFa((string) ($log->device_type ?? 'unknown')),
            'browser' => $log->browser !== null && trim($log->browser) !== '' ? $log->browser : '—',
            'platform' => $log->platform !== null && trim($log->platform) !== '' ? $log->platform : '—',
            'user_agent' => (string) ($log->user_agent ?? ''),
        ];
    }

    private function deviceTypeFa(string $type): string
    {
        return match ($type) {
            'mobile' => 'موبایل',
            'tablet' => 'تبلت',
            'desktop' => 'دسکتاپ',
            'bot' => 'ربات / خزنده',
            default => 'نامشخص',
        };
    }
}
