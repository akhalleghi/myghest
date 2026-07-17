@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    @include('partials.support-ticket-chat-styles')
    <style>
        .itk-page { width: 100%; max-width: 100%; }
        .itk-head { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; justify-content: space-between; margin-bottom: 0.85rem; }
        .itk-h1 { margin: 0; font-size: 1.12rem; font-weight: 800; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
        .itk-lead { margin: 0.2rem 0 0; font-size: 0.82rem; color: var(--muted); line-height: 1.55; width: 100%; }
        .itk-btn { font-family: inherit; font-size: 0.78rem; font-weight: 800; padding: 0.48rem 0.85rem; border-radius: 0.62rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.38rem; }
        .itk-btn--pri { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; border-color: var(--primary-dark); }
        .itk-btn--ghost { background: transparent; }
        .itk-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 0.75rem; }
        .itk-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.8rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .itk-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .itk-tab-badge { font-size: 0.68rem; font-weight: 800; padding: 0.12rem 0.42rem; border-radius: 999px; background: rgba(148, 163, 184, 0.2); }
        .itk-tab.is-active .itk-tab-badge { background: rgba(37, 99, 235, 0.18); }
        .itk-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .itk-search form { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .itk-search input { min-width: min(100%, 16rem); border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.45rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .itk-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); transition: opacity 0.2s ease; }
        .itk-wrap[aria-busy="true"] { opacity: 0.65; pointer-events: none; }
        .itk-tbl { width: 100%; border-collapse: collapse; font-size: 0.76rem; min-width: 52rem; }
        .itk-tbl th, .itk-tbl td { padding: 0.5rem 0.55rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .itk-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .itk-tbl tr:last-child td { border-bottom: 0; }
        .itk-subject { font-weight: 800; color: var(--text); max-width: 12rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .itk-excerpt { max-width: 16rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .itk-party { max-width: 11rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); font-weight: 700; }
        .itk-dt { white-space: nowrap; color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
        .itk-att { color: var(--primary-dark); font-size: 0.85rem; }
        .itk-empty { padding: 2rem 1rem; text-align: center; color: var(--muted); font-size: 0.84rem; }
        .itk-pagination { margin-top: 0.75rem; }

        #itk-compose-dialog,
        #itk-detail-dialog {
            display: none;
            padding: 0;
            border: none;
            border-radius: 1rem;
            max-width: min(96vw, 44rem);
            width: min(96vw, 44rem);
            max-height: min(92vh, 52rem);
            background: var(--bg-card);
            color: var(--text);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }
        #itk-compose-dialog[open],
        #itk-detail-dialog[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            margin: auto;
        }
        #itk-compose-dialog::backdrop,
        #itk-detail-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }
        .itk-dialog-inner {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
            width: 100%;
            max-height: inherit;
            position: relative;
            overflow: hidden;
        }
        .itk-dialog-close { position: absolute; top: 0.45rem; inset-inline-end: 0.45rem; width: 2rem; height: 2rem; border: none; background: transparent; color: var(--muted); font-size: 1.35rem; cursor: pointer; z-index: 2; border-radius: 0.4rem; }
        .itk-dialog-close:hover { background: var(--primary-soft); color: var(--text); }
        .itk-dialog-head { flex-shrink: 0; padding: 1rem 2.5rem 0.5rem 1rem; border-bottom: 1px dashed var(--border); background: var(--bg-card); }
        .itk-dialog-title { margin: 0; font-size: 1rem; font-weight: 800; }
        #itk-compose-form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }
        .itk-dialog-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 0.85rem 1.15rem;
            -webkit-overflow-scrolling: touch;
        }
        .itk-dialog-inner--detail { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
        #itk-detail-dialog {
            width: min(96vw, 54rem);
            max-width: min(96vw, 54rem);
            height: min(90vh, 48rem);
            max-height: min(90vh, 48rem);
        }
        #itk-detail-dialog .itk-dialog-head {
            padding-block: 0.85rem;
            border-bottom-style: solid;
            box-shadow: 0 1px 0 rgba(148, 163, 184, 0.08);
            z-index: 3;
        }
        #itk-detail-dialog .itk-dialog-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        #itk-detail-dialog .st-detail-layout {
            position: relative;
            flex: 1 1 0;
            min-height: 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 15% 15%, rgba(37, 99, 235, 0.055), transparent 28%),
                radial-gradient(circle at 85% 80%, rgba(16, 185, 129, 0.05), transparent 30%),
                color-mix(in oklab, var(--bg-card) 94%, var(--primary-soft));
        }
        #itk-detail-dialog .st-detail-messages {
            flex: 1 1 0;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 1.25rem 1.35rem;
            scroll-behavior: smooth;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
        }
        #itk-detail-dialog .st-detail-meta {
            position: relative;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border: 1px solid color-mix(in oklab, var(--border) 82%, transparent);
            border-radius: 0.8rem;
            background: color-mix(in oklab, var(--bg-card) 92%, transparent);
            box-shadow: 0 5px 16px rgba(15, 23, 42, 0.04);
        }
        #itk-detail-dialog .itk-status-row {
            padding: 0.55rem 0.65rem;
            border: 1px dashed var(--border);
            border-radius: 0.7rem;
            background: color-mix(in oklab, var(--bg-card) 88%, transparent);
        }
        #itk-detail-dialog .st-chat {
            gap: 0.8rem;
            padding: 0.25rem 0.15rem;
        }
        #itk-detail-dialog .st-msg {
            position: relative;
            max-width: min(88%, 36rem);
        }
        #itk-detail-dialog .st-msg__bubble {
            padding: 0.68rem 0.85rem;
            border-radius: 1rem;
            line-height: 1.75;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }
        #itk-detail-dialog .st-msg--staff .st-msg__bubble {
            border-end-start-radius: 0.25rem;
        }
        #itk-detail-dialog .st-msg--customer .st-msg__bubble {
            border-end-end-radius: 0.25rem;
        }
        #itk-detail-dialog .st-msg__meta {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
        }
        #itk-detail-dialog .st-msg__meta::before {
            content: "";
            width: 0.4rem;
            height: 0.4rem;
            flex: 0 0 auto;
            border-radius: 999px;
            background: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        #itk-detail-dialog .st-msg--staff .st-msg__meta {
            justify-content: flex-start;
        }
        #itk-detail-dialog .st-msg--staff .st-msg__meta::before {
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }
        #itk-detail-dialog .st-detail-reply-zone {
            flex: 0 0 auto;
            max-height: min(46%, 17rem);
            overflow-y: auto;
            padding: 0.7rem 1.15rem 0.8rem;
            margin: 0;
            border-top: 1px solid var(--border);
            background: color-mix(in oklab, var(--bg-card) 96%, var(--primary-soft));
            box-shadow: 0 -8px 22px rgba(15, 23, 42, 0.07);
            z-index: 2;
        }
        #itk-detail-dialog .st-detail-reply-zone form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.55rem 0.7rem;
            align-items: end;
        }
        #itk-detail-dialog .st-detail-reply-zone .itk-ck-wrap {
            grid-column: 1 / -1;
            min-width: 0;
            margin: 0;
        }
        #itk-detail-dialog .st-detail-reply-zone .itk-field {
            margin: 0;
        }
        #itk-detail-dialog .st-detail-reply-zone .itk-ck-wrap .ck-editor__editable {
            min-height: 3.25rem !important;
            max-height: 6.5rem !important;
            overflow-y: auto;
        }
        #itk-detail-dialog .st-detail-reply-zone input[type="file"] {
            padding: 0.38rem 0.5rem;
            font-size: 0.74rem;
        }
        #itk-detail-dialog .st-detail-reply-zone .itk-btn--pri {
            min-height: 2.35rem;
            white-space: nowrap;
            justify-content: center;
        }
        #itk-detail-dialog .itk-dialog-footer {
            padding: 0.5rem 0.85rem;
            border-top-style: solid;
        }
        @media (max-width: 620px) {
            #itk-detail-dialog {
                width: 96vw;
                height: 94vh;
                max-height: 94vh;
            }
            #itk-detail-dialog .st-detail-messages {
                padding: 0.75rem 0.7rem 1rem;
            }
            #itk-detail-dialog .st-msg {
                max-width: 94%;
            }
            #itk-detail-dialog .st-detail-reply-zone {
                max-height: 48%;
                padding: 0.65rem 0.7rem;
            }
            #itk-detail-dialog .st-detail-reply-zone form {
                grid-template-columns: 1fr;
            }
            #itk-detail-dialog .st-detail-reply-zone .itk-btn--pri {
                width: 100%;
            }
        }
        .itk-dialog-footer {
            flex-shrink: 0;
            padding: 0.75rem 1rem 1rem;
            border-top: 1px dashed var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: flex-end;
            background: var(--bg-card);
            position: relative;
            z-index: 1;
        }
        .itk-field { margin-bottom: 0.75rem; }
        .itk-field label { display: block; font-size: 0.74rem; font-weight: 800; color: var(--muted); margin-bottom: 0.28rem; }
        .itk-field input[type="text"],
        .itk-field input[type="file"],
        .itk-field select { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .itk-recipient-modes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.55rem; }
        .itk-recipient-mode { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 700; cursor: pointer; padding: 0.35rem 0.55rem; border: 1px solid var(--border); border-radius: 0.55rem; background: var(--bg-card); }
        .itk-recipient-mode input { accent-color: var(--primary); }
        .itk-recipient-panel { border: 1px dashed var(--border); border-radius: 0.65rem; padding: 0.55rem; background: rgba(248, 250, 252, 0.5); }
        html[data-theme="dark"] .itk-recipient-panel { background: rgba(15, 23, 42, 0.35); }
        .itk-recipient-panel[hidden] { display: none !important; }
        .itk-all-hint { margin: 0; font-size: 0.76rem; color: var(--muted); line-height: 1.55; }
        .itk-ck-wrap .ck-editor__editable { min-height: 10rem; max-height: 16rem; direction: rtl; text-align: right; }
        .itk-detail-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.45rem 0.65rem; margin-bottom: 0.85rem; font-size: 0.78rem; }
        @media (max-width: 560px) { .itk-detail-meta { grid-template-columns: 1fr; } }
        .itk-detail-meta-item span { display: block; color: var(--muted); font-weight: 800; margin-bottom: 0.12rem; }
        .itk-detail-meta-item strong { font-weight: 700; color: var(--text); }
        .itk-detail-body { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.75rem; background: var(--bg-card); line-height: 1.75; font-size: 0.84rem; overflow-x: auto; }
        .itk-detail-body p { margin: 0 0 0.5rem; }
        .itk-detail-att { margin-top: 0.65rem; display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .itk-detail-att a { font-size: 0.76rem; font-weight: 700; text-decoration: none; color: var(--primary-dark); display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.32rem 0.55rem; border: 1px solid var(--border); border-radius: 0.5rem; }
        .select2-container { z-index: 2500; }
        .itk-status { display: inline-block; padding: 0.14rem 0.48rem; border-radius: 999px; font-size: 0.67rem; font-weight: 800; background: var(--primary-soft); color: var(--primary-dark); white-space: nowrap; }
        .itk-status--closed { background: rgba(148, 163, 184, 0.22); color: var(--muted); }
        .itk-status--hold { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .itk-detail-reply { margin: 0; }
        .itk-status-row { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; margin-bottom: 0.75rem; }
        .itk-status-row select { flex: 1; min-width: 10rem; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.42rem 0.55rem; font-family: inherit; font-size: 0.8rem; background: var(--bg-card); color: var(--text); }
    </style>
@endpush

@section('content')
    <div class="itk-page">
        <div class="itk-head">
            <div>
                <h1 class="itk-h1">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                    {{ $pageTitle }}
                </h1>
                <p class="itk-lead">مکاتبه و یادداشت داخلی بین کاربران ادمین؛ پیگیری تیکت‌های دریافتی و ارسالی میان همکاران.</p>
            </div>
            <button type="button" class="itk-btn itk-btn--pri" id="itk-open-compose">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                ارسال تیکت جدید
            </button>
        </div>
        @if(session('internal_ticket_flash_success'))
            <p class="itk-lead" style="color:#047857;font-weight:800;margin-bottom:0.65rem">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                {{ session('internal_ticket_flash_success') }}
            </p>
        @endif

        <div class="itk-tabs" role="tablist">
            <a href="{{ route('admin.internal-tickets.index', ['tab' => 'received', 'q' => $searchQ ?: null]) }}"
               class="itk-tab @if($activeTab === 'received') is-active @endif">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                تیکت‌های دریافتی
                <span class="itk-tab-badge">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $receivedCount) }}</span>
            </a>
            <a href="{{ route('admin.internal-tickets.index', ['tab' => 'sent', 'q' => $searchQ ?: null]) }}"
               class="itk-tab @if($activeTab === 'sent') is-active @endif">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                تیکت‌های ارسالی
                <span class="itk-tab-badge">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $sentCount) }}</span>
            </a>
        </div>

        <div class="itk-toolbar">
            <div class="itk-search">
                <form method="get" action="{{ route('admin.internal-tickets.index') }}">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="search" name="q" value="{{ $searchQ }}" placeholder="جستجو در موضوع، متن، نام ادمین…" autocomplete="off">
                    <button type="submit" class="itk-btn itk-btn--ghost">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        جستجو
                    </button>
                </form>
            </div>
        </div>

        <div class="itk-wrap" id="itk-wrap">
            <div id="itk-table-root">
            @if($rows->isEmpty())
                <div class="itk-empty" id="itk-empty">
                    <i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>
                    @if($activeTab === 'received')
                        تیکت دریافتی ثبت نشده است. تیکت‌های ارسالی از سمت سایر ادمین‌ها در این بخش نمایش داده می‌شوند.
                    @else
                        هنوز تیکتی ارسال نکرده‌اید. با دکمه «ارسال تیکت جدید» اولین تیکت را بفرستید.
                    @endif
                </div>
            @else
                <table class="itk-tbl">
                    <thead>
                        <tr>
                            <th scope="col">تاریخ و ساعت</th>
                            <th scope="col">{{ $activeTab === 'sent' ? 'گیرنده' : 'فرستنده' }}</th>
                            <th scope="col">موضوع</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col">متن تیکت</th>
                            <th scope="col">ضمیمه</th>
                            <th scope="col">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td class="itk-dt">{{ $row['datetime_fa'] }}</td>
                                <td class="itk-party" title="{{ $row['party_label'] }}">{{ $row['party_label'] }}</td>
                                <td class="itk-subject" title="{{ $row['subject'] }}">{{ $row['subject'] }}</td>
                                <td>
                                    @php($st = $row['status'] ?? '')
                                    <span class="itk-status @if($st === 'closed') itk-status--closed @elseif($st === 'on_hold') itk-status--hold @endif">{{ $row['status_label'] ?? $st }}</span>
                                </td>
                                <td class="itk-excerpt" title="{{ $row['excerpt'] }}">{{ $row['excerpt'] }}</td>
                                <td>
                                    @if($row['has_attachment'])
                                        <span class="itk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                                    @else
                                        <span class="itk-dt">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button"
                                        class="itk-btn itk-btn--ghost itk-view-btn"
                                        data-ticket-id="{{ $row['id'] }}">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        مشاهده جزئیات
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            </div>
        </div>

        <div id="itk-pagination-wrap">
            @include('partials.list-pagination', ['paginator' => $rows, 'standalone' => true])
        </div>
    </div>

    <dialog id="itk-compose-dialog" aria-labelledby="itk-compose-title">
        <div class="itk-dialog-inner">
            <button type="button" class="itk-dialog-close" data-itk-close-compose aria-label="بستن">&times;</button>
            <div class="itk-dialog-head">
                <h2 id="itk-compose-title" class="itk-dialog-title">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    ارسال تیکت جدید
                </h2>
            </div>
            <form method="post" action="{{ route('admin.internal-tickets.store') }}" enctype="multipart/form-data" id="itk-compose-form" novalidate>
                @csrf
                <div class="itk-dialog-scroll" id="itk-compose-scroll">
                    <div class="itk-field">
                        <label for="itk-subject">عنوان تیکت</label>
                        <input type="text" name="subject" id="itk-subject" required maxlength="255" placeholder="موضوع پیام" value="{{ old('subject') }}">
                    </div>
                    <div class="itk-field">
                        <span style="display:block;font-size:0.74rem;font-weight:800;color:var(--muted);margin-bottom:0.35rem">گیرنده</span>
                        <div class="itk-recipient-modes" role="radiogroup" aria-label="نوع گیرنده">
                            <label class="itk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="single" @checked(old('recipient_mode', 'single') === 'single')>
                                یک ادمین
                            </label>
                            <label class="itk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="multiple" @checked(old('recipient_mode') === 'multiple')>
                                چند ادمین
                            </label>
                            <label class="itk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="all" @checked(old('recipient_mode') === 'all')>
                                همه ادمین‌ها
                            </label>
                        </div>
                        <div class="itk-recipient-panel" id="itk-panel-single">
                            <label for="itk-admin-single">انتخاب ادمین</label>
                            <select id="itk-admin-single" class="itk-admin-select" style="width:100%"></select>
                        </div>
                        <div class="itk-recipient-panel" id="itk-panel-multiple" hidden>
                            <label for="itk-admin-multiple">انتخاب ادمین‌ها</label>
                            <select id="itk-admin-multiple" class="itk-admin-select" multiple style="width:100%"></select>
                            <p class="itk-all-hint" style="margin-top:0.35rem">
                                برای هر ادمین انتخاب‌شده یک تیکت جداگانه ایجاد می‌شود؛ گفتگوها بین گیرندگان مشترک نیست.
                            </p>
                        </div>
                        <div class="itk-recipient-panel" id="itk-panel-all" hidden>
                            <p class="itk-all-hint">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                برای هر ادمین فعال (به‌جز خودتان) یک تیکت جداگانه ایجاد می‌شود و گفتگو فقط بین شما و همان گیرنده قابل مشاهده است.
                            </p>
                        </div>
                        <div id="itk-admin-ids-hidden"></div>
                    </div>
                    <div class="itk-field itk-ck-wrap">
                        <label for="itk-compose-body">متن تیکت</label>
                        <textarea name="body_html" id="itk-compose-body" rows="6">{{ old('body_html') }}</textarea>
                    </div>
                    <div class="itk-field">
                        <label for="itk-attachment">فایل ضمیمه (اختیاری)</label>
                        <input type="file" name="attachment" id="itk-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        <p class="itk-all-hint" style="margin-top:0.35rem">حداکثر ۵ مگابایت — jpg، png، pdf، doc، docx، zip</p>
                    </div>
                </div>
                <div class="itk-dialog-footer">
                    <button type="button" class="itk-btn itk-btn--ghost" data-itk-close-compose>انصراف</button>
                    <button type="submit" class="itk-btn itk-btn--pri" id="itk-compose-submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="itk-detail-dialog" aria-labelledby="itk-detail-title">
        <div class="itk-dialog-inner itk-dialog-inner--detail">
            <button type="button" class="itk-dialog-close" data-itk-close-detail aria-label="بستن">&times;</button>
            <div class="itk-dialog-head">
                <h2 id="itk-detail-title" class="itk-dialog-title">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                    <span id="itk-detail-title-text">جزئیات تیکت</span>
                </h2>
            </div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="itk-detail-body">
                    <p class="itk-all-hint">در حال بارگذاری…</p>
                </div>
                <div class="st-detail-reply-zone itk-detail-reply" id="itk-detail-reply-wrap" hidden>
                    <form id="itk-reply-form" enctype="multipart/form-data" novalidate>
                        <div class="itk-field itk-ck-wrap">
                            <label for="itk-reply-body">
                                <i class="fa-regular fa-message" aria-hidden="true"></i>
                                نوشتن پیام
                            </label>
                            <textarea id="itk-reply-body" name="body_html" rows="3"></textarea>
                        </div>
                        <div class="itk-field">
                            <label for="itk-reply-attachment">
                                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                ضمیمه اختیاری
                            </label>
                            <input type="file" name="attachment" id="itk-reply-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        </div>
                        <button type="submit" class="itk-btn itk-btn--pri" id="itk-reply-submit">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            ارسال پیام
                        </button>
                    </form>
                </div>
            </div>
            <div class="itk-dialog-footer">
                <button type="button" class="itk-btn itk-btn--ghost" data-itk-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__ITK_PAGE__ = {
            snapshots: @json($rowSnapshots),
            adminSearchUrl: @json(route('admin.internal-tickets.admins-search')),
            composeStoreUrl: @json(route('admin.internal-tickets.store')),
            ticketsAdminBase: @json(url('admin/internal-tickets')),
            ticketShowBase: @json(url('admin/internal-tickets')),
            csrf: @json(csrf_token()),
            flashSuccess: @json(session('internal_ticket_flash_success')),
            activeTab: @json($activeTab),
            searchQ: @json($searchQ),
            ticketsListUrl: @json(route('admin.internal-tickets.list')),
            totalAdminCount: @json($totalAdminCount ?? 0),
            appDisplayName: @json($appDisplayName ?? 'سامانه'),
        };
    </script>
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
    @vite(['resources/js/admin-tickets-ckeditor.js', 'resources/js/admin-internal-tickets-index.js'])
@endpush
