<dialog class="cq-dialog cq-dialog-wide cq-details" id="cq-details" aria-labelledby="cq-details-title">
    <header class="cq-detail-header" data-admin-mw-head>
        <div>
            <h2 id="cq-details-title">جزئیات و سوابق چک</h2>
            <p id="cq-details-caption" class="cq-hint"></p>
        </div>
        <button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن جزئیات چک">بستن ×</button>
    </header>
    <div class="cq-detail-body">
        <div class="cq-summary" id="cq-detail-summary" aria-live="polite"></div>
        <nav class="cq-detail-tabs" role="tablist" aria-label="جزئیات چک">
            @foreach(['overview'=>'مشخصات و قرارداد', 'receipts'=>'دریافت‌ها', 'events'=>'گردش و انتقال‌ها', 'media'=>'تصاویر و مدارک', 'notes'=>'یادداشت‌ها'] as $key=>$label)
                <button type="button" id="cq-tab-{{ $key }}" role="tab" data-detail-tab="{{ $key }}" aria-controls="cq-panel-{{ $key }}" aria-selected="{{ $loop->first?'true':'false' }}" tabindex="{{ $loop->first?'0':'-1' }}">{{ $label }} <span data-detail-count="{{ $key }}"></span></button>
            @endforeach
        </nav>
        <section id="cq-panel-overview" role="tabpanel" aria-labelledby="cq-tab-overview" data-detail-panel="overview" tabindex="0"></section>
        <section id="cq-panel-receipts" role="tabpanel" aria-labelledby="cq-tab-receipts" data-detail-panel="receipts" tabindex="0" hidden></section>
        <section id="cq-panel-events" role="tabpanel" aria-labelledby="cq-tab-events" data-detail-panel="events" tabindex="0" hidden></section>
        <section id="cq-panel-media" role="tabpanel" aria-labelledby="cq-tab-media" data-detail-panel="media" tabindex="0" hidden>
            <section class="customer-media-gallery" id="customer-media-gallery" aria-label="تصاویر و مدارک مشتری چکی"></section>
        </section>
        <section id="cq-panel-notes" role="tabpanel" aria-labelledby="cq-tab-notes" data-detail-panel="notes" tabindex="0" hidden>
            <div class="cq-note-toolbar">
                <div><h3>یادداشت‌های همین چک</h3><p class="cq-hint">متن، نویسنده و زمان ثبت هر یادداشت در این بخش نگهداری می‌شود.</p></div>
                @adminCan('cheques.notes')<button type="button" class="cq-btn cq-primary" data-note-new><i class="fa-solid fa-note-sticky" aria-hidden="true"></i> افزودن یادداشت چک</button>@endadminCan
            </div>
            <div id="cq-detail-notes"></div>
        </section>
    </div>
</dialog>
