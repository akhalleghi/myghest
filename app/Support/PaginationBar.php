<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;

final class PaginationBar
{
    /**
     * @param  LengthAwarePaginator<int, mixed>|ConcretePaginator<int, mixed>  $paginator
     */
    public static function html(LengthAwarePaginator $paginator, bool $standalone = false, bool $ajax = false): string
    {
        return view('partials.list-pagination', [
            'paginator' => $paginator,
            'standalone' => $standalone,
            'ajax' => $ajax,
        ])->render();
    }
}
