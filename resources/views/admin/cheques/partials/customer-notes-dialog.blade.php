<dialog class="cq-dialog cq-details" id="cq-customer-notes" aria-labelledby="cq-customer-notes-title">
    <header class="cq-detail-header" data-admin-mw-head>
        <div><h2 id="cq-customer-notes-title">یادداشت‌های تجمیعی مشتری چکی</h2><p class="cq-hint" data-customer-notes-caption></p></div>
        <button type="button" class="cq-btn" data-close data-admin-mw-close aria-label="بستن یادداشت‌های مشتری چکی">بستن ×</button>
    </header>
    <div class="cq-detail-body">
        <div class="cq-note-toolbar">
            <p class="cq-hint">یادداشت مشتری، پرونده‌های خرید و تمام چک‌های او در همین بخش جمع شده‌اند.</p>
            @adminCan('cheques.notes')<button type="button" class="cq-btn cq-primary" data-customer-note-new>افزودن یادداشت مشتری</button>@endadminCan
        </div>
        <div data-customer-notes-list></div>
    </div>
</dialog>
