@if ($paginator->hasPages())
    <nav class="mg-pager-nav" role="navigation" aria-label="صفحه‌بندی">
        <ul class="mg-pager-list">
            @if ($paginator->onFirstPage())
                <li class="mg-pager-item is-disabled" aria-disabled="true">
                    <span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i> قبلی</span>
                </li>
            @else
                <li class="mg-pager-item">
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        قبلی
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="mg-pager-item is-disabled" aria-disabled="true"><span>{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="mg-pager-item is-active" aria-current="page">
                                <span>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $page) }}</span>
                            </li>
                        @else
                            <li class="mg-pager-item">
                                <a href="{{ $url }}">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string) $page) }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="mg-pager-item">
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">
                        بعدی
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </a>
                </li>
            @else
                <li class="mg-pager-item is-disabled" aria-disabled="true">
                    <span>بعدی <i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
