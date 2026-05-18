@php
    $context = $loginBgContext ?? 'admin';
    $state = ($loginBgPickerStates ?? [])[$context] ?? ['mode' => 'fixed', 'selected' => null, 'bundled' => [], 'custom' => []];
    $title = $loginBgTitle ?? 'پیش‌زمینه صفحه لاگین';
    $description = $loginBgDescription ?? '';
    $collage = $loginBgCollageUrls ?? [];
    $isRandom = ($state['mode'] ?? 'fixed') === 'random';
    $selectedPath = $state['selected'] ?? null;
    $previewUrl = null;
    if (! $isRandom && is_string($selectedPath) && $selectedPath !== '') {
        foreach (array_merge($state['bundled'] ?? [], $state['custom'] ?? []) as $img) {
            if (($img['id'] ?? '') === $selectedPath) {
                $previewUrl = $img['url'] ?? null;
                break;
            }
        }
    }
    if ($previewUrl === null && ! empty($state['bundled'])) {
        $previewUrl = $state['bundled'][0]['url'] ?? null;
    }
    $bundledCount = count($state['bundled'] ?? []);
@endphp
<div
    class="app-login-bg-picker"
    data-login-bg-picker
    data-context="{{ $context }}"
    data-preference-url="{{ route('admin.app-settings.login-background.preference.update', ['context' => $context]) }}"
    data-upload-url="{{ route('admin.app-settings.login-background.upload', ['context' => $context]) }}"
    data-delete-url="{{ route('admin.app-settings.login-background.destroy', ['context' => $context]) }}"
    data-mode="{{ $isRandom ? 'random' : 'fixed' }}"
    data-selected="{{ $selectedPath }}"
>
    <div class="app-settings-card app-login-bg-card">
        <h4>{{ $title }}</h4>
        <p class="app-settings-card-desc">{{ $description }}</p>

        <div class="app-login-bg-preview" data-login-bg-preview aria-live="polite">
            @if($isRandom)
                <div class="app-login-bg-preview-random" data-login-bg-preview-random>
                    <div class="app-login-bg-preview-collage" aria-hidden="true">
                        @foreach(array_slice($collage, 0, 4) as $collageUrl)
                            <span style="background-image:url('{{ $collageUrl }}')"></span>
                        @endforeach
                        @for($i = count($collage); $i < 4; $i++)
                            <span class="app-login-bg-preview-collage-fallback"></span>
                        @endfor
                    </div>
                    <span class="app-login-bg-preview-random-ico" aria-hidden="true">
                        <i class="fa-solid fa-shuffle"></i>
                    </span>
                    <span class="app-login-bg-preview-label">نمایش تصادفی</span>
                </div>
            @else
                <div class="app-login-bg-preview-fixed" data-login-bg-preview-fixed @if(empty($previewUrl)) hidden @endif>
                    <img
                        src="{{ $previewUrl ?? '' }}"
                        alt=""
                        data-login-bg-preview-img
                        @if(empty($previewUrl)) hidden @endif
                    >
                </div>
            @endif
        </div>

        <label class="app-login-bg-upload">
            <input
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                data-login-bg-file
                hidden
            >
            <span class="app-login-bg-upload-inner">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <span>برای افزودن تصویر جدید کلیک کنید یا فایل را اینجا رها کنید</span>
                <span class="app-login-bg-upload-hint">JPG، PNG یا WebP — حداکثر ۵ مگابایت</span>
            </span>
        </label>
        <p class="app-login-bg-msg" data-login-bg-msg role="status" hidden></p>

        <div class="app-login-bg-section">
            <div class="app-login-bg-section-head">
                <span class="app-login-bg-section-title">تصاویر پیش‌فرض</span>
                @if($bundledCount > 4)
                    <button type="button" class="app-login-bg-show-all" data-login-bg-toggle-expand>
                        <span data-login-bg-toggle-label>نمایش همه</span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
            <div class="app-login-bg-grid @if($bundledCount > 4) is-collapsed @endif" data-login-bg-grid>
                <button
                    type="button"
                    class="app-login-bg-tile app-login-bg-tile--random @if($isRandom) is-selected @endif"
                    data-login-bg-select
                    data-mode="random"
                    aria-pressed="{{ $isRandom ? 'true' : 'false' }}"
                    title="نمایش تصادفی"
                >
                    <span class="app-login-bg-tile-collage" aria-hidden="true">
                        @foreach(array_slice($collage, 0, 4) as $collageUrl)
                            <span style="background-image:url('{{ $collageUrl }}')"></span>
                        @endforeach
                    </span>
                    <span class="app-login-bg-tile-random-ico"><i class="fa-solid fa-shuffle"></i></span>
                    <span class="app-login-bg-tile-label">تصادفی</span>
                </button>
                @foreach($state['bundled'] ?? [] as $image)
                    <button
                        type="button"
                        class="app-login-bg-tile @if(!$isRandom && ($image['id'] ?? '') === $selectedPath) is-selected @endif"
                        data-login-bg-select
                        data-mode="fixed"
                        data-path="{{ $image['id'] }}"
                        aria-pressed="{{ ! $isRandom && ($image['id'] ?? '') === $selectedPath ? 'true' : 'false' }}"
                        style="background-image:url('{{ $image['url'] }}')"
                        title="انتخاب این تصویر"
                    ></button>
                @endforeach
            </div>
        </div>

        <div class="app-login-bg-section" data-login-bg-custom-section @if(empty($state['custom'])) hidden @endif>
            <div class="app-login-bg-section-head">
                <span class="app-login-bg-section-title">بارگذاری‌شده توسط شما</span>
            </div>
            <div class="app-login-bg-grid app-login-bg-grid--custom" data-login-bg-custom-grid>
                @foreach($state['custom'] ?? [] as $image)
                    <div class="app-login-bg-tile-wrap">
                        <button
                            type="button"
                            class="app-login-bg-tile @if(!$isRandom && ($image['id'] ?? '') === $selectedPath) is-selected @endif"
                            data-login-bg-select
                            data-mode="fixed"
                            data-path="{{ $image['id'] }}"
                            aria-pressed="{{ ! $isRandom && ($image['id'] ?? '') === $selectedPath ? 'true' : 'false' }}"
                            style="background-image:url('{{ $image['url'] }}')"
                            title="انتخاب این تصویر"
                        ></button>
                        <button
                            type="button"
                            class="app-login-bg-delete"
                            data-login-bg-delete
                            data-path="{{ $image['id'] }}"
                            aria-label="حذف تصویر"
                            title="حذف"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="app-login-bg-custom-empty" data-login-bg-custom-empty @if(!empty($state['custom'])) hidden @endif>
            هنوز تصویری بارگذاری نکرده‌اید.
        </p>

        <div class="app-settings-actions app-login-bg-actions">
            <button type="button" class="app-settings-btn app-settings-btn--primary" data-login-bg-save>
                ذخیره پیش‌زمینه
            </button>
        </div>
    </div>
</div>
