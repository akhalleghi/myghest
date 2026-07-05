<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Support\JalaliInputParser;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Database\Eloquent\Builder;

final class AdminActivityReportService
{
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
     * @return list<array<string, mixed>>
     */
    public function fetchRows(
        Carbon $from,
        Carbon $to,
        ?int $adminId = null,
        string $actionFilter = '',
        string $search = '',
    ): array {
        $query = AdminActivityLog::query()
            ->with(['admin:id,first_name,last_name,name,username'])
            ->whereBetween('performed_at', [$from, $to])
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if ($adminId !== null && $adminId > 0) {
            $query->where('admin_id', $adminId);
        }

        if ($actionFilter !== '') {
            $query->where('action', $actionFilter);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('description', 'like', $like)
                    ->orWhere('route_name', 'like', $like)
                    ->orWhere('url_path', 'like', $like)
                    ->orWhere('http_method', 'like', $like)
                    ->orWhereHas('admin', function (Builder $a) use ($like): void {
                        $a->where('username', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        return $query
            ->limit(5000)
            ->get()
            ->map(fn (AdminActivityLog $log): array => $this->mapRow($log))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    public function searchAdminsForSelect(?string $term, int $limit = 40): array
    {
        $query = Admin::query()->orderBy('username')->orderBy('id');
        $search = $term !== null ? trim($term) : '';

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->where('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
        }

        return $query
            ->limit(max(1, min(80, $limit)))
            ->get(['id', 'first_name', 'last_name', 'name', 'username'])
            ->map(function (Admin $admin): array {
                $name = $admin->fullName();
                $text = $name !== '' ? $name : 'ادمین #'.$admin->id;
                $username = trim((string) $admin->username);
                if ($username !== '') {
                    $text .= ' ('.$username.')';
                }

                return ['id' => (int) $admin->id, 'text' => $text];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function actionFilterOptions(): array
    {
        return [
            '' => 'همه',
            AdminActivityLog::ACTION_LOGIN => 'ورود',
            AdminActivityLog::ACTION_LOGOUT => 'خروج',
            AdminActivityLog::ACTION_SESSION_EXPIRED => 'پایان نشست',
            AdminActivityLog::ACTION_HTTP => 'فعالیت در سامانه',
        ];
    }

    /**
     * @return list<string>
     */
    public function excelHeaderRow(): array
    {
        return [
            'زمان',
            'ادمین',
            'نام کاربری',
            'نوع',
            'شرح',
            'مسیر',
            'متد',
            'IP',
            'مرورگر',
            'سکو',
            'دستگاه',
            'وضعیت HTTP',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function excelDataRow(array $row): array
    {
        return [
            (string) ($row['performed_at_fa'] ?? ''),
            (string) ($row['admin_name'] ?? ''),
            (string) ($row['admin_username'] ?? ''),
            (string) ($row['action_label'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['url_path'] ?? ''),
            (string) ($row['http_method'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['browser'] ?? ''),
            (string) ($row['platform'] ?? ''),
            (string) ($row['device_type_fa'] ?? ''),
            (string) ($row['http_status'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(AdminActivityLog $log): array
    {
        $admin = $log->admin;
        $at = $log->performed_at instanceof Carbon ? $log->performed_at : Carbon::parse((string) $log->performed_at);

        return [
            'id' => (int) $log->id,
            'performed_at_fa' => Jalali::enToFaNumbers(Jalali::instance($at)->format('Y/m/d')).' '.Jalali::enToFaNumbers($at->format('H:i:s')),
            'admin_name' => $admin !== null ? ($admin->fullName() !== '' ? $admin->fullName() : '—') : '—',
            'admin_username' => $admin !== null ? (string) $admin->username : '—',
            'admin_username_fa' => $admin !== null ? Jalali::enToFaNumbers((string) $admin->username) : '—',
            'action' => (string) $log->action,
            'action_label' => $this->actionLabel((string) $log->action),
            'description' => (string) $log->description,
            'route_name' => (string) ($log->route_name ?? ''),
            'http_method' => (string) ($log->http_method ?? ''),
            'url_path' => (string) ($log->url_path ?? ''),
            'ip_address' => (string) ($log->ip_address ?? ''),
            'ip_fa' => Jalali::enToFaNumbers((string) ($log->ip_address ?? '—')),
            'browser' => $log->browser !== null && trim($log->browser) !== '' ? $log->browser : '—',
            'platform' => $log->platform !== null && trim($log->platform) !== '' ? $log->platform : '—',
            'device_type' => (string) ($log->device_type ?? 'unknown'),
            'device_type_fa' => $this->deviceTypeFa((string) ($log->device_type ?? 'unknown')),
            'http_status' => $log->http_status !== null ? (string) $log->http_status : '—',
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            AdminActivityLog::ACTION_LOGIN => 'ورود',
            AdminActivityLog::ACTION_LOGOUT => 'خروج',
            AdminActivityLog::ACTION_SESSION_EXPIRED => 'پایان نشست',
            AdminActivityLog::ACTION_HTTP => 'فعالیت',
            default => $action,
        };
    }

    private function deviceTypeFa(string $type): string
    {
        return match ($type) {
            'mobile' => 'موبایل',
            'tablet' => 'تبلت',
            'desktop' => 'دسکتاپ',
            'bot' => 'ربات',
            default => 'نامشخص',
        };
    }
}
