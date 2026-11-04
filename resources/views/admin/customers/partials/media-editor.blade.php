<section class="cust-section customer-media-editor" data-customer-media-editor
    data-base-url="{{ url('/admin/customers') }}" data-available="{{ ($customerMediaAvailable ?? false) ? '1' : '0' }}">
    <div class="cust-section-head">
        <h3>تصاویر و مدارک مشتری <small>(اختیاری)</small></h3>
        <p>می‌توانید اکنون فایل انتخاب کنید یا بعداً از ویرایش مشتری اضافه کنید. عکس: JPG، PNG یا WEBP تا ۵ مگابایت؛ ویدئو: MP4، WEBM یا MOV تا ۲۰ مگابایت.</p>
    </div>
    @if (! ($customerMediaAvailable ?? false))
        <p class="cust-field-hint">این بخش پس از اجرای مهاجرت دیتابیس نسخه جدید فعال می‌شود؛ ثبت و ویرایش سایر اطلاعات مشتری برقرار است.</p>
    @endif
    @error('customer_media')<p class="cust-field-error">{{ $message }}</p>@enderror
    <fieldset class="customer-media-grid" @disabled(! ($customerMediaAvailable ?? false))>
        <legend class="customer-media-sr-only">انتخاب مدارک اختیاری مشتری</legend>
        @foreach (\App\Models\CustomerMedia::KINDS as $kind => $label)
            <div class="customer-media-slot" data-media-slot="{{ $kind }}">
                <label for="customer-media-{{ $kind }}" class="customer-media-label">{{ $label }}</label>
                <div class="customer-media-preview" data-media-preview>فایلی ثبت نشده است.</div>
                <input type="file" id="customer-media-{{ $kind }}" name="customer_media[{{ $kind }}]"
                    accept="{{ $kind === 'video' ? 'video/mp4,video/webm,video/quicktime' : 'image/jpeg,image/png,image/webp' }}"
                    data-media-input="{{ $kind }}" aria-label="انتخاب {{ $label }}">
                <div class="customer-media-actions">
                    <button type="button" class="cust-mini-btn" data-media-capture="{{ $kind }}">{{ $kind === 'video' ? 'ضبط فیلم با دوربین' : 'ثبت عکس با دوربین' }}</button>
                    <button type="button" class="cust-mini-btn" data-media-clear hidden>لغو فایل جدید</button>
                </div>
                <label class="customer-media-remove" data-media-remove-label hidden>
                    <input type="checkbox" name="remove_customer_media[]" value="{{ $kind }}" data-media-remove>
                    حذف فایل فعلی هنگام ذخیره
                </label>
                <p class="cust-field-error" data-media-error role="alert"></p>
                @error('customer_media.'.$kind)<p class="cust-field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </fieldset>
    <p class="cust-field-hint" data-media-status role="status" aria-live="polite"></p>
</section>
