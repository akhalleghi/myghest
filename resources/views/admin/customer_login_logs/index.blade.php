@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <style>
        .cll-page { width: 100%; max-width: 100%; box-sizing: border-box; }
        .cll-h1 { margin: 0 0 0.45rem; font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .cll-lead { margin: 0 0 1rem; font-size: 0.82rem; color: var(--muted); line-height: 1.55; }
        .cll-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; margin-bottom: 0.85rem; max-width: 28rem; }
        .cll-filters label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.25rem; }
        .cll-filters input { width: 100%; border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.48rem 0.6rem; font-size: 0.82rem; background: var(--bg-card); color: var(--text); font-family: inherit; }
        .cll-filters button {
            border: none; border-radius: 0.65rem; padding: 0.48rem 1rem; font-size: 0.82rem; font-weight: 800; cursor: pointer; font-family: inherit;
            background: linear-gradient(180deg, #2563eb, #1d4ed8); color: #fff;
        }
        .cll-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); }
        .cll-tbl { width: 100%; border-collapse: collapse; font-size: 0.72rem; min-width: 72rem; }
        .cll-tbl th, .cll-tbl td { padding: 0.5rem 0.55rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .cll-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .cll-tbl td { color: var(--muted); font-weight: 600; }
        .cll-tbl tr:last-child td { border-bottom: 0; }
        .cll-tbl tbody tr:hover td { background: rgba(37, 99, 235, 0.04); }
        .cll-ua { max-width: 18rem; word-break: break-word; font-size: 0.68rem; line-height: 1.45; color: var(--text); }
        .cll-empty { text-align: center; padding: 1.5rem 1rem; color: var(--muted); font-weight: 600; }
        .cll-pagination { margin-top: 0.75rem; }
    </style>
@endpush

@section('content')
    <div class="cll-page">
        <h1 class="cll-h1">{{ $pageTitle }}</h1>
        <p class="cll-lead">
            هر ورود موفق مشتری به پنل کاربری از این به بعد در این گزارش ثبت می‌شود؛ شامل زمان دقیق، آدرس IP، نوع دستگاه، مرورگر، سیستم‌عامل و رشتهٔ User-Agent.
        </p>

        <form method="get" action="{{ route('admin.customer-login-logs.index') }}" class="cll-filters">
            <div style="flex:1;min-width:12rem">
                <label for="cll-q">جستجو (نام، نام خانوادگی، نام کاربری، کد ملی، موبایل، کد مشتری)</label>
                <input type="search" name="q" id="cll-q" value="{{ e($search) }}" maxlength="120" placeholder="مثلاً بخشی از موبایل یا نام" autocomplete="off">
            </div>
            <button type="submit">اعمال</button>
        </form>

        <div class="cll-wrap" role="region" aria-label="جدول گزارش ورود">
            <table class="cll-tbl">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">تاریخ و ساعت ورود</th>
                        <th scope="col">نام و نام خانوادگی</th>
                        <th scope="col">نام کاربری</th>
                        <th scope="col">کد ملی</th>
                        <th scope="col">موبایل</th>
                        <th scope="col">کد مشتری</th>
                        <th scope="col">IP</th>
                        <th scope="col">دستگاه</th>
                        <th scope="col">مرورگر</th>
                        <th scope="col">سیستم‌عامل</th>
                        <th scope="col">User-Agent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $row)
                        <tr>
                            <td>{{ $row['id_fa'] }}</td>
                            <td>{{ $row['logged_in_at_fa'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['username_fa'] }}</td>
                            <td>{{ $row['national_id_fa'] }}</td>
                            <td>{{ $row['mobile_fa'] }}</td>
                            <td>{{ $row['customer_code_fa'] }}</td>
                            <td>{{ $row['ip_fa'] }}</td>
                            <td>{{ $row['device_type_fa'] }}</td>
                            <td>{{ e($row['browser']) }}</td>
                            <td>{{ e($row['platform']) }}</td>
                            <td class="cll-ua" title="{{ e($row['user_agent']) }}">{{ e(\Illuminate\Support\Str::limit($row['user_agent'], 180)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="cll-empty">هنوز ردیفی ثبت نشده است. پس از ورود مشتریان به پنل، اینجا پر می‌شود.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.list-pagination', ['paginator' => $logs])
    </div>
@endsection
