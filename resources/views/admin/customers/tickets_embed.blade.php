@extends('layouts.admin.embed_iframe')

@section('title', $pageTitle)

@push('head')
    @include('partials.support-ticket-chat-styles')
    @include('admin.customers.partials.ctk-embed-styles')
@endpush

@section('content')
    <div class="ctk-page">
        <div class="ctk-head">
            <div class="ctk-head-text">
                <h1 class="ctk-h1">
                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                    تیکت‌های مشتری
                </h1>
                <p class="ctk-lead">تیکت‌های ارسالی و دریافتی مربوط به <strong>{{ $customerLabel }}</strong></p>
            </div>
            <button type="button" class="ctk-btn ctk-btn--pri" id="ctk-open-compose">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                ارسال تیکت جدید
            </button>
        </div>

        <div class="ctk-tabs" role="tablist">
            <button type="button" class="ctk-tab @if($activeTab === 'received') is-active @endif" data-ctk-tab="received">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                دریافتی از مشتری
                <span class="ctk-tab-badge" data-ctk-count="received">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $receivedCount) }}</span>
            </button>
            <button type="button" class="ctk-tab @if($activeTab === 'sent') is-active @endif" data-ctk-tab="sent">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                ارسالی به مشتری
                <span class="ctk-tab-badge" data-ctk-count="sent">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $sentCount) }}</span>
            </button>
        </div>

        <div class="ctk-toolbar">
            <div class="ctk-search">
                <form id="ctk-search-form" autocomplete="off">
                    <input type="search" name="q" id="ctk-search-input" value="{{ $searchQ }}" placeholder="جستجو در موضوع و متن تیکت…">
                    <button type="submit" class="ctk-btn ctk-btn--ghost">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        جستجو
                    </button>
                </form>
            </div>
        </div>

        <div class="ctk-wrap" id="ctk-wrap">
            <div id="ctk-table-root">
                @if($rows->isEmpty())
                    <div class="ctk-empty" id="ctk-empty">
                        <i class="fa-regular fa-folder-open" style="font-size:1.5rem;opacity:0.5;display:block;margin-bottom:0.5rem" aria-hidden="true"></i>
                        @if($activeTab === 'received')
                            این مشتری هنوز تیکتی ارسال نکرده است.
                        @else
                            هنوز تیکتی برای این مشتری ارسال نشده است.
                        @endif
                    </div>
                @else
                    <table class="ctk-tbl">
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
                                    <td class="ctk-dt">{{ $row['datetime_fa'] }}</td>
                                    <td class="ctk-party" title="{{ $row['party_label'] }}">{{ $row['party_label'] }}</td>
                                    <td class="ctk-subject" title="{{ $row['subject'] }}">{{ $row['subject'] }}</td>
                                    <td>
                                        @php($st = $row['status'] ?? '')
                                        <span class="ctk-status @if($st === 'closed') ctk-status--closed @elseif($st === 'on_hold') ctk-status--hold @endif">{{ $row['status_label'] ?? $st }}</span>
                                    </td>
                                    <td class="ctk-excerpt" title="{{ $row['excerpt'] }}">{{ $row['excerpt'] }}</td>
                                    <td>
                                        @if($row['has_attachment'])
                                            <span class="ctk-att" title="دارای فایل ضمیمه"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                                        @else
                                            <span class="ctk-dt">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="ctk-btn ctk-btn--ghost ctk-view-btn" data-ticket-id="{{ $row['id'] }}">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            مشاهده
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div id="ctk-pagination-wrap">
            @include('partials.list-pagination', ['paginator' => $rows, 'standalone' => true])
        </div>
    </div>

    <dialog id="ctk-compose-dialog" aria-labelledby="ctk-compose-title">
        <div class="ctk-dialog-inner">
            <button type="button" class="ctk-dialog-close" data-ctk-close-compose aria-label="بستن">&times;</button>
            <div class="ctk-dialog-head">
                <h2 id="ctk-compose-title" class="ctk-dialog-title">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    ارسال تیکت به {{ $customerLabel }}
                </h2>
            </div>
            <form method="post" action="{{ route('admin.customers.tickets.store', $customer) }}" enctype="multipart/form-data" id="ctk-compose-form" novalidate>
                @csrf
                <div class="ctk-dialog-scroll" id="ctk-compose-scroll">
                    <div class="ctk-field">
                        <label for="ctk-subject">عنوان تیکت</label>
                        <input type="text" name="subject" id="ctk-subject" required maxlength="255" placeholder="موضوع پیام">
                    </div>
                    <div class="ctk-field ctk-ck-wrap">
                        <label for="ctk-compose-body">متن تیکت</label>
                        <textarea name="body_html" id="ctk-compose-body" rows="6"></textarea>
                    </div>
                    <div class="ctk-field">
                        <label for="ctk-attachment">فایل ضمیمه (اختیاری)</label>
                        <input type="file" name="attachment" id="ctk-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        <p class="ctk-hint">حداکثر ۵ مگابایت — jpg، png، pdf، doc، docx، zip</p>
                    </div>
                    <div class="ctk-sms-option" id="ctk-compose-sms-option-wrap" @if(empty($smsPanelAvailable)) hidden @endif>
                        <label class="ctk-sms-check">
                            <input type="checkbox" name="send_sms" value="1" id="ctk-compose-send-sms">
                            ارسال پیامک اطلاع‌رسانی
                        </label>
                        <div class="ctk-sms-fields" id="ctk-compose-sms-fields" hidden>
                            <label for="ctk-compose-sms-text">متن پیامک</label>
                            <textarea name="sms_text" id="ctk-compose-sms-text" rows="4" maxlength="1000" placeholder="متن پیامک…"></textarea>
                            <p class="ctk-hint">از <code>{customer_greeting}</code> و <code>{subject}</code> می‌توانید استفاده کنید.</p>
                            <div class="ctk-sms-preview-wrap" id="ctk-compose-sms-preview-wrap" hidden>
                                <span class="ctk-sms-preview-meta" id="ctk-compose-sms-preview-meta"></span>
                                <pre class="ctk-sms-preview" id="ctk-compose-sms-preview"></pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ctk-dialog-footer">
                    <button type="button" class="ctk-btn ctk-btn--ghost" data-ctk-close-compose>انصراف</button>
                    <button type="submit" class="ctk-btn ctk-btn--pri" id="ctk-compose-submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        ارسال
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="ctk-detail-dialog" aria-labelledby="ctk-detail-title">
        <div class="ctk-dialog-inner ctk-dialog-inner--detail">
            <button type="button" class="ctk-dialog-close" data-ctk-close-detail aria-label="بستن">&times;</button>
            <div class="ctk-dialog-head">
                <h2 id="ctk-detail-title" class="ctk-dialog-title">جزئیات تیکت</h2>
            </div>
            <div class="st-detail-layout">
                <div class="st-detail-messages" id="ctk-detail-body">
                    <p class="ctk-hint">در حال بارگذاری…</p>
                </div>
                <div class="st-detail-reply-zone ctk-detail-reply" id="ctk-detail-reply-wrap" hidden>
                    <form id="ctk-reply-form" enctype="multipart/form-data" novalidate>
                        <div class="ctk-field ctk-ck-wrap">
                            <label for="ctk-reply-body">پاسخ شما</label>
                            <textarea id="ctk-reply-body" name="body_html" rows="3"></textarea>
                        </div>
                        <div class="ctk-field">
                            <label for="ctk-reply-attachment">فایل ضمیمه (اختیاری)</label>
                            <input type="file" name="attachment" id="ctk-reply-attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                        </div>
                        <div class="ctk-sms-option" id="ctk-sms-option-wrap" hidden>
                            <label class="ctk-sms-check">
                                <input type="checkbox" name="send_sms" value="1" id="ctk-send-sms">
                                ارسال پیامک اطلاع‌رسانی
                            </label>
                            <div class="ctk-sms-fields" id="ctk-sms-fields" hidden>
                                <label for="ctk-sms-text">متن پیامک</label>
                                <textarea name="sms_text" id="ctk-sms-text" rows="4" maxlength="1000" placeholder="متن پیامک…"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="ctk-btn ctk-btn--pri" id="ctk-reply-submit">
                            <i class="fa-solid fa-reply" aria-hidden="true"></i>
                            ارسال پاسخ
                        </button>
                    </form>
                </div>
            </div>
            <div class="ctk-dialog-footer">
                <button type="button" class="ctk-btn ctk-btn--ghost" data-ctk-close-detail>بستن</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.__CTK_EMBED__ = {
            customerId: @json((int) $customer->id),
            customerLabel: @json($customerLabel),
            snapshots: @json($rowSnapshots),
            listUrl: @json(route('admin.customers.tickets.list', $customer)),
            storeUrl: @json(route('admin.customers.tickets.store', $customer)),
            ticketApiBase: @json(url('admin/customers/'.$customer->id.'/tickets')),
            attachmentUrlBase: @json(url('admin/tickets/attachments')),
            csrf: @json(csrf_token()),
            activeTab: @json($activeTab),
            searchQ: @json($searchQ),
            smsPanelAvailable: @json($smsPanelAvailable ?? false),
            smsComposeTemplate: @json($smsComposeTemplate ?? ''),
            appDisplayName: @json($appDisplayName ?? 'سامانه'),
        };
    </script>
    @vite(['resources/js/admin-tickets-ckeditor.js', 'resources/js/admin-customer-tickets-embed.js', 'resources/js/admin-modal-windowing.js'])
@endpush
