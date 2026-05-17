@once
    @include('partials.list-pagination-styles')
@endonce

@php
    use App\Support\ListPerPage;
    use Hekmatinasser\Jalali\Jalali;

    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Pagination\LengthAwarePaginator $paginator */
    $standalone = $standalone ?? false;
    $ajax = $ajax ?? false;
    $allowed = ListPerPage::allowedOptions();
    $currentPer = (int) $paginator->perPage();
    $total = (int) $paginator->total();
    $from = $total > 0 ? (int) $paginator->firstItem() : 0;
    $to = $total > 0 ? (int) $paginator->lastItem() : 0;
    $fa = static fn (int|string $n): string => Jalali::enToFaNumbers((string) $n);
@endphp

@if ($total > 0)
    <div class="mg-pagination-bar @if($standalone) mg-pagination-bar--standalone @endif">
        <div class="mg-pagination-bar__start">
            <form method="get" action="{{ url()->current() }}" class="mg-per-page-form">
                @foreach (request()->except(['per_page', 'page']) as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="mg-per-page-{{ $paginator->getPageName() }}">تعداد در هر صفحه</label>
                <select id="mg-per-page-{{ $paginator->getPageName() }}" name="per_page" @unless($ajax) onchange="this.form.submit()" @endunless>
                    @foreach ($allowed as $option)
                        <option value="{{ $option }}" @selected($currentPer === $option)>{{ $fa($option) }}</option>
                    @endforeach
                </select>
            </form>
            <p class="mg-pagination-bar__summary">
                نمایش {{ $fa($from) }} تا {{ $fa($to) }} از {{ $fa($total) }} مورد
            </p>
        </div>
        {{ $paginator->withQueryString()->onEachSide(1)->links('vendor.pagination.myghest') }}
    </div>
@endif
