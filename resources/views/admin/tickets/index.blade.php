@extends('layouts.admin.app')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    @include('partials.support-ticket-chat-styles')
    <style>
        .tk-page { width: 100%; max-width: 100%; }
        .tk-head { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; justify-content: space-between; margin-bottom: 0.85rem; }
        .tk-h1 { margin: 0; font-size: 1.12rem; font-weight: 800; color: var(--text); display: inline-flex; align-items: center; gap: 0.45rem; }
        .tk-lead { margin: 0.2rem 0 0; font-size: 0.82rem; color: var(--muted); line-height: 1.55; width: 100%; }
        .tk-btn { font-family: inherit; font-size: 0.78rem; font-weight: 800; padding: 0.48rem 0.85rem; border-radius: 0.62rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.38rem; }
        .tk-btn--pri { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color: #fff; border-color: var(--primary-dark); }
        .tk-btn--ghost { background: transparent; }
        .tk-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 0.75rem; }
        .tk-tab { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.45rem 0.8rem; font-size: 0.78rem; font-weight: 700; color: var(--muted); background: var(--bg-card); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .tk-tab.is-active { background: var(--primary-soft); color: var(--primary-dark); border-color: rgba(37, 99, 235, 0.35); }
        .tk-tab-badge { font-size: 0.68rem; font-weight: 800; padding: 0.12rem 0.42rem; border-radius: 999px; background: rgba(148, 163, 184, 0.2); }
        .tk-tab.is-active .tk-tab-badge { background: rgba(37, 99, 235, 0.18); }
        .tk-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .tk-search form { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .tk-search input { min-width: min(100%, 16rem); border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.45rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .tk-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg-card); transition: opacity 0.2s ease; }
        .tk-wrap[aria-busy="true"] { opacity: 0.65; pointer-events: none; }
        .tk-tbl { width: 100%; border-collapse: collapse; font-size: 0.76rem; min-width: 52rem; }
        .tk-tbl th, .tk-tbl td { padding: 0.5rem 0.55rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: middle; }
        .tk-tbl th { background: var(--primary-soft); font-weight: 800; color: var(--text); white-space: nowrap; }
        .tk-tbl tr:last-child td { border-bottom: 0; }
        .tk-subject { font-weight: 800; color: var(--text); max-width: 12rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tk-excerpt { max-width: 16rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tk-party { max-width: 11rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); font-weight: 700; }
        .tk-dt { white-space: nowrap; color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
        .tk-att { color: var(--primary-dark); font-size: 0.85rem; }
        .tk-empty { padding: 2rem 1rem; text-align: center; color: var(--muted); font-size: 0.84rem; }
        .tk-pagination { margin-top: 0.75rem; }

        #tk-compose-dialog,
        #tk-detail-dialog {
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
        #tk-compose-dialog[open],
        #tk-detail-dialog[open] {
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0;
            margin: auto;
        }
        #tk-compose-dialog::backdrop,
        #tk-detail-dialog::backdrop {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }
        .tk-dialog-inner {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
            width: 100%;
            max-height: inherit;
            position: relative;
            overflow: hidden;
        }
        .tk-dialog-close { position: absolute; top: 0.45rem; inset-inline-end: 0.45rem; width: 2rem; height: 2rem; border: none; background: transparent; color: var(--muted); font-size: 1.35rem; cursor: pointer; z-index: 2; border-radius: 0.4rem; }
        .tk-dialog-close:hover { background: var(--primary-soft); color: var(--text); }
        .tk-dialog-head { flex-shrink: 0; padding: 1rem 2.5rem 0.5rem 1rem; border-bottom: 1px dashed var(--border); background: var(--bg-card); }
        .tk-dialog-title { margin: 0; font-size: 1rem; font-weight: 800; }
        #tk-compose-form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }
        .tk-dialog-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 0.85rem 1.15rem;
            -webkit-overflow-scrolling: touch;
        }
        .tk-dialog-inner--detail { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
        .tk-dialog-footer {
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
        .tk-field { margin-bottom: 0.75rem; }
        .tk-field label { display: block; font-size: 0.74rem; font-weight: 800; color: var(--muted); margin-bottom: 0.28rem; }
        .tk-field input[type="text"],
        .tk-field input[type="file"],
        .tk-field select { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit; font-size: 0.82rem; }
        .tk-recipient-modes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.55rem; }
        .tk-recipient-mode { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 700; cursor: pointer; padding: 0.35rem 0.55rem; border: 1px solid var(--border); border-radius: 0.55rem; background: var(--bg-card); }
        .tk-recipient-mode input { accent-color: var(--primary); }
        .tk-recipient-panel { border: 1px dashed var(--border); border-radius: 0.65rem; padding: 0.55rem; background: rgba(248, 250, 252, 0.5); }
        html[data-theme="dark"] .tk-recipient-panel { background: rgba(15, 23, 42, 0.35); }
        .tk-recipient-panel[hidden] { display: none !important; }
        .tk-all-hint { margin: 0; font-size: 0.76rem; color: var(--muted); line-height: 1.55; }
        .tk-ck-wrap .ck-editor__editable { min-height: 10rem; max-height: 16rem; direction: rtl; text-align: right; }
        .tk-detail-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.45rem 0.65rem; margin-bottom: 0.85rem; font-size: 0.78rem; }
        @media (max-width: 560px) { .tk-detail-meta { grid-template-columns: 1fr; } }
        .tk-detail-meta-item span { display: block; color: var(--muted); font-weight: 800; margin-bottom: 0.12rem; }
        .tk-detail-meta-item strong { font-weight: 700; color: var(--text); }
        .tk-detail-body { border: 1px solid var(--border); border-radius: 0.65rem; padding: 0.75rem; background: var(--bg-card); line-height: 1.75; font-size: 0.84rem; overflow-x: auto; }
        .tk-detail-body p { margin: 0 0 0.5rem; }
        .tk-detail-att { margin-top: 0.65rem; display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .tk-detail-att a { font-size: 0.76rem; font-weight: 700; text-decoration: none; color: var(--primary-dark); display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.32rem 0.55rem; border: 1px solid var(--border); border-radius: 0.5rem; }
        .select2-container { z-index: 2500; }
        .tk-status { display: inline-block; padding: 0.14rem 0.48rem; border-radius: 999px; font-size: 0.67rem; font-weight: 800; background: var(--primary-soft); color: var(--primary-dark); white-space: nowrap; }
        .tk-status--closed { background: rgba(148, 163, 184, 0.22); color: var(--muted); }
        .tk-status--hold { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .tk-detail-reply { margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed var(--border); }
        .tk-status-row { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; margin-bottom: 0.75rem; }
        .tk-status-row select { flex: 1; min-width: 10rem; border: 1px solid var(--border); border-radius: 0.62rem; padding: 0.42rem 0.55rem; font-family: inherit; font-size: 0.8rem; background: var(--bg-card); color: var(--text); }
        .tk-sms-option { margin-top: 0.35rem; padding-top: 0.45rem; border-top: 1px dashed var(--border); }
        .tk-sms-option[hidden] { display: none !important; }
        .tk-sms-check { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.76rem; font-weight: 700; color: var(--text); cursor: pointer; margin-bottom: 0.35rem; }
        .tk-sms-check input { accent-color: var(--primary); width: 1rem; height: 1rem; }
        .tk-sms-fields[hidden] { display: none !important; }
        .tk-sms-fields label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--muted); margin-bottom: 0.25rem; }
        .tk-sms-fields textarea {
            width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 0.62rem;
            padding: 0.48rem 0.62rem; background: var(--bg-card); color: var(--text); font-family: inherit;
            font-size: 0.78rem; line-height: 1.65; resize: vertical; min-height: 4.5rem;
        }
        .tk-sms-preview-wrap { margin-top: 0.5rem; }
        .tk-sms-preview-wrap[hidden] { display: none !important; }
        .tk-sms-preview-meta {
            display: block; font-size: 0.7rem; font-weight: 800; color: var(--primary-dark);
            margin-bottom: 0.35rem; line-height: 1.5;
        }
        .tk-sms-preview {
            margin: 0; padding: 0.55rem 0.65rem; border: 1px dashed var(--border); border-radius: 0.55rem;
            background: color-mix(in oklab, var(--primary-soft) 55%, var(--bg-card));
            font-family: inherit; font-size: 0.76rem; line-height: 1.65; white-space: pre-wrap;
            word-break: break-word; color: var(--text);
        }
        html[data-theme="dark"] .tk-sms-preview {
            background: rgba(37, 99, 235, 0.12);
        }
    </style>
@endpush

@section('content')
    <div class="tk-page">
        <div class="tk-head">
            <div>
                <h1 class="tk-h1">
                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                    {{ $pageTitle }}
                </h1>
                <p class="tk-lead">ارسال پیام به کاربران، پیگیری تیکت‌های دریافتی از مشتریان و مشاهده جزئیات هر تیکت.</p>
            </div>
            <button type="button" class="tk-btn tk-btn--pri" id="tk-open-compose">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                ارسال تیکت جدید
            </button>
        </div>
        @if(session('ticket_flash_success'))
            <p class="tk-lead" style="color:#047857;font-weight:800;margin-bottom:0.65rem">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                {{ session('ticket_flash_success') }}
            </p>
        @endif

        <div class="tk-tabs" role="tablist">
            <a href="{{ route('admin.tickets.index', ['tab' => 'received', 'q' => $searchQ ?: null]) }}"
               class="tk-tab @if($activeTab === 'received') is-active @endif">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                تیکت‌های دریافتی
                <span class="tk-tab-badge">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $receivedCount) }}</span>
            </a>
            <a href="{{ route('admin.tickets.index', ['tab' => 'sent', 'q' => $searchQ ?: null]) }}"
               class="tk-tab @if($activeTab === 'sent') is-active @endif">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                تیکت‌های ارسالی
                <span class="tk-tab-badge">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $sentCount) }}</span>
            </a>
        </div>

        <div class="tk-toolbar">
            <div class="tk-search">
                <form method="get" action="{{ route('admin.tickets.index') }}">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="search" name="q" value="{{ $searchQ }}" placeholder="جستجو در موضوع، متن، نام کاربر…" autocomplete="off">
                    <button type="submit" class="tk-btn tk-btn--ghost">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        جستجو
                    </button>
                </form>
            </div>
        </div>

        <div class="tk-wrap" id="tk-wrap">
            <div id="tk-table-root">
            @if($rows->isEmpty())
                <div class="tk-empty" id="tk-empty">
                    <i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>
                    @if($activeTab === 'received')
                        تیکت دریافتی ثبت نشده است. تیکت‌های ارسالی از سمت کاربران در این بخش نمایش داده می‌شوند.
                    @else
                        هنوز تیکتی ارسال نکرده‌اید. با دکمه «ارسال تیکت جدید» اولین تیکت را بفرستید.
                    @endif
                </div>
            @else
                <table class="tk-tbl">
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
                                <td class="tk-dt">{{ $row['datetime_fa'] }}</td>
                                <td class="tk-party" title="{{ $row['party_label'] }}">{{ $row['party_label'] }}</td>
                                <td class="tk-subject" title="{{ $row['subject'] }}">{{ $row['subject'] }}</td>
                                <td>
                                    @php($st = $row['status'] ?? '')
                                    <span class="tk-status @if($st === 'closed') tk-status--closed @elseif($st === 'on_hold') tk-status--hold @endif">{{ $row['status_label'] ?? $st }}</span>
                                </td>
                                <td class="tk-excerpt" title="{{ $row['excerpt'] }}">{{ $row['excerpt'] }}</td>
                                <td>
                                    @if($row['has_attachment'])
                                        <span class="tk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                                    @else
                                        <span class="tk-dt">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button"
                                        class="tk-btn tk-btn--ghost tk-view-btn"
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

        <div id="tk-pagination-wrap">
            @include('partials.list-pagination', ['paginator' => $rows, 'standalone' => true])
        </div>
    </div>

    <dialog id="tk-compose-dialog" aria-labelledby="tk-compose-title">
        <div class="tk-dialog-inner">
            <button type="button" class="tk-dialog-close" data-tk-close-compose aria-label="بستن">&times;</button>
            <div class="tk-dialog-head">
                <h2 id="tk-compose-title" class="tk-dialog-title">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    ارسال تیکت جدید
                </h2>
            </div>
            <form method="post" action="{{ route('admin.tickets.store') }}" enctype="multipart/form-data" id="tk-compose-form" novalidate>
                @csrf
                <div class="tk-dialog-scroll" id="tk-compose-scroll">
                    <div class="tk-field">
                        <label for="tk-subject">عنوان تیکت</label>
                        <input type="text" name="subject" id="tk-subject" required maxlength="255" placeholder="موضوع پیام" value="{{ old('subject') }}">
                    </div>
                    <div class="tk-field">
                        <span style="display:block;font-size:0.74rem;font-weight:800;color:var(--muted);margin-bottom:0.35rem">گیرنده</span>
                        <div class="tk-recipient-modes" role="radiogroup" aria-label="نوع گیرنده">
                            <label class="tk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="single" @checked(old('recipient_mode', 'single') === 'single')>
                                یک کاربر
                            </label>
                            <label class="tk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="multiple" @checked(old('recipient_mode') === 'multiple')>
                                چند کاربر
                            </label>
                            <label class="tk-recipient-mode">
                                <input type="radio" name="recipient_mode" value="all" @checked(old('recipient_mode') === 'all')>
                                همه کاربران
                            </label>
                        </div>
                        <div class="tk-recipient-panel" id="tk-panel-single">
                            <label for="tk-customer-single">انتخاب کاربر</label>
                            <select id="tk-customer-single" class="tk-customer-select" style="width:100%"></select>
                        </div>
                        <div class="tk-recipient-panel" id="tk-panel-multiple" hidden>
                            <label for="tk-customer-multiple">انتخاب کاربران</label>
                            <select id="tk-customer-multiple" class="tk-customer-select" multiple style="width:100%"></select>
                            <p class="tk-all-hint" style="margin-top:0.35rem">
                                برای هر کاربر انتخاب‌شده یک تیکت جداگانه ایجاد می‌شود؛ گفتگوها بین مشتریان مشترک نیست.
                            </p>
                        </div>
                        <div class="tk-recipient-panel" id="tk-panel-all" hidden>
                            <p class="tk-all-hint">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                برای هر کاربر یک تیکت جداگانه ایجاد می‌شود و گفتگوی هر مشتری فقط برای خودش قابل مشاهده است.
                            </p>
                        </div>
                        <div id="tk-customer-ids-hidden"></div>
                    </div>
                    <div class="tk-field tk-ck-wrap">
                        <label for="tk-compose-body">متن تیکت</label>
                        <textarea name="body_html" id="tk-compose-body" rows="6">{{ old('body_html') }}</textarea>
                    </div>
                    <div class="tk-field">
                        <label for="tk-attachment">فایل ضمیمه (اختیاری)</label>
                        <input type="file" name="attachment" id="tk-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        <p class="tk-all-hint" style="margin-top:0.35rem">حداکثر ۵ مگابایت — jpg، png، pdf، doc، docx، zip</p>
                    </div>
                    <div class="tk-sms-option" id="tk-compose-sms-option-wrap" @if(empty($smsPanelAvailable)) hidden @endif>
                        <label class="tk-sms-check">
                            <input type="checkbox" name="send_sms" value="1" id="tk-compose-send-sms">
                            ارسال پیامک اطلاع‌رسانی برای مشتری
                        </label>
                        <div class="tk-sms-fields" id="tk-compose-sms-fields" hidden>
                            <label for="tk-compose-sms-text">متن پیامک</label>
                            <textarea name="sms_text" id="tk-compose-sms-text" rows="4" maxlength="1000" placeholder="متن پیامک…"></textarea>
                            <p class="tk-all-hint" style="margin-top:0.35rem">
                                برای هر مشتری انتخاب‌شده جداگانه ارسال می‌شود. از
                                <code>{customer_greeting}</code> و <code>{subject}</code> می‌توانید استفاده کنید.
                            </p>
                            <div class="tk-sms-preview-wrap" id="tk-compose-sms-preview-wrap">
                                <span class="tk-sms-preview-meta" id="tk-compose-sms-preview-meta"></span>
                                <pre class="tk-sms-preview" id="tk-compose-sms-preview"></pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tk-dialog-footer">
                    <button type="button" class="tk-btn tk-btn--ghost" data-tk-close-compose>انصراف</button>
                    <button type="submit" class="tk-btn tk-btn--pri" id="tk-compose-submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="tk-detail-dialog" aria-labelledby="tk-detail-title">
        <div class="tk-dialog-inner tk-dialog-inner--detail">
            <button type="button" class="tk-dialog-close" data-tk-close-detail aria-label="بستن">&times;</button>
            <div class="tk-dialog-head">
                <h2 id="tk-detail-title" class="tk-dialog-title">جزئیات تیکت</h2>
            </div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="tk-detail-body">
                    <p class="tk-all-hint">در حال بارگذاری…</p>
                </div>
                <div class="st-detail-reply-zone tk-detail-reply" id="tk-detail-reply-wrap" hidden>
                <form id="tk-reply-form" enctype="multipart/form-data" novalidate>
                    <div class="tk-field tk-ck-wrap">
                        <label for="tk-reply-body">پاسخ شما</label>
                        <textarea id="tk-reply-body" name="body_html" rows="3"></textarea>
                    </div>
                    <div class="tk-field">
                        <label for="tk-reply-attachment">فایل ضمیمه (اختیاری)</label>
                        <input type="file" name="attachment" id="tk-reply-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                    </div>
                    <div class="tk-sms-option" id="tk-sms-option-wrap" hidden>
                        <label class="tk-sms-check">
                            <input type="checkbox" name="send_sms" value="1" id="tk-send-sms">
                            ارسال پیامک اطلاع‌رسانی برای مشتری
                        </label>
                        <div class="tk-sms-fields" id="tk-sms-fields" hidden>
                            <label for="tk-sms-text">متن پیامک</label>
                            <textarea name="sms_text" id="tk-sms-text" rows="4" maxlength="1000" placeholder="متن پیامک…"></textarea>
                            <p class="tk-all-hint" style="margin-top:0.35rem">در صورت چند گیرنده، پیامک برای هر مشتری ارسال می‌شود.</p>
                        </div>
                    </div>
                    <button type="submit" class="tk-btn tk-btn--pri" id="tk-reply-submit">
                        <i class="fa-solid fa-reply" aria-hidden="true"></i>
                        ارسال پاسخ
                    </button>
                </form>
                </div>
            </div>
            <div class="tk-dialog-footer">
                <button type="button" class="tk-btn tk-btn--ghost" data-tk-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__TK_PAGE__ = {
            snapshots: @json($rowSnapshots),
            customerSearchUrl: @json(route('admin.tickets.customers-search')),
            composeStoreUrl: @json(route('admin.tickets.store')),
            ticketsAdminBase: @json(url('admin/tickets')),
            csrf: @json(csrf_token()),
            flashSuccess: @json(session('ticket_flash_success')),
            activeTab: @json($activeTab),
            searchQ: @json($searchQ),
            ticketsListUrl: @json(route('admin.tickets.list')),
            smsPanelAvailable: @json($smsPanelAvailable ?? false),
            smsComposeTemplate: @json($smsComposeTemplate ?? ''),
            totalCustomerCount: @json($totalCustomerCount ?? 0),
            appDisplayName: @json($appDisplayName ?? 'سامانه'),
        };
    </script>
    <script src="{{ asset('vendor/persian-datepicker/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
    @vite(['resources/js/admin-tickets-ckeditor.js', 'resources/js/admin-tickets-index.js'])
@endpush
