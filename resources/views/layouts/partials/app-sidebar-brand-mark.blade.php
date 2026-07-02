@if(!empty($appLogoUrl))
    <div class="sidebar-system-logo" aria-hidden="true">
        <img src="{{ $appLogoUrl }}" alt="" loading="lazy" decoding="async">
    </div>
@else
    <div class="sidebar-logo" aria-hidden="true">
        @if(!empty($appIconUrl))
            <img src="{{ $appIconUrl }}" alt="">
        @else
            <i class="{{ $appIconFaClass }}"></i>
        @endif
    </div>
@endif
