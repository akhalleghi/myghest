<div class="customer-media-overlay" id="customer-camera-overlay" hidden>
    <section class="customer-media-dialog" role="dialog" aria-modal="true" aria-labelledby="customer-camera-title">
        <header class="customer-media-dialog-head">
            <h2 id="customer-camera-title">ثبت با دوربین</h2>
            <button type="button" class="cust-mini-btn" data-camera-close aria-label="بستن دوربین">بستن</button>
        </header>
        <label class="customer-media-device-label">دوربین <select id="customer-camera-device" aria-label="انتخاب دوربین"></select></label>
        <video id="customer-camera-live" autoplay muted playsinline></video>
        <p id="customer-camera-status" role="status" aria-live="polite"></p>
        <p id="customer-camera-error" role="alert" class="cust-field-error"></p>
        <div class="customer-media-actions">
            <button type="button" class="cust-mini-btn" id="customer-camera-photo" disabled>ثبت عکس</button>
            <button type="button" class="cust-mini-btn" id="customer-camera-record" disabled>شروع ضبط (حداکثر ۳۰ ثانیه)</button>
            <button type="button" class="cust-mini-btn" id="customer-camera-stop" hidden>پایان ضبط و انتخاب فیلم</button>
            <button type="button" class="cust-mini-btn" data-camera-close>انصراف</button>
        </div>
        <p class="cust-field-hint">پس از ثبت، عکس یا فیلم در فرم انتخاب می‌شود و با ذخیره اطلاعات مشتری بارگذاری خواهد شد. دوربین به اجازه مرورگر و اتصال HTTPS یا localhost نیاز دارد. فیلم بدون صدا ضبط می‌شود.</p>
    </section>
</div>
<div class="customer-media-overlay customer-media-viewer" id="customer-media-viewer" hidden>
    <section class="customer-media-dialog" role="dialog" aria-modal="true" aria-labelledby="customer-media-viewer-title">
        <header class="customer-media-dialog-head">
            <h2 id="customer-media-viewer-title">نمایش مدرک</h2>
            <div class="customer-media-actions">
                <button type="button" class="cust-mini-btn" id="customer-media-fullscreen">تمام‌صفحه</button>
                <button type="button" class="cust-mini-btn" id="customer-media-viewer-close">بستن</button>
            </div>
        </header>
        <div id="customer-media-viewer-content"></div>
    </section>
</div>
