<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChequeCustomer;
use App\Models\ChequeCustomerMedia;
use App\Services\Cheques\ChequeCustomerMediaService;
use Illuminate\Support\Facades\Storage;

final class ChequeCustomerMediaController extends Controller
{
    public function index(ChequeCustomer $chequeCustomer, ChequeCustomerMediaService $service)
    {
        return response()->json(['available' => $service->available(), 'items' => $service->items($chequeCustomer)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(ChequeCustomer $chequeCustomer, ChequeCustomerMedia $media)
    {
        abort_unless((int) $media->cheque_customer_id === (int) $chequeCustomer->id
            && str_starts_with($media->path, 'private/cheque-customer-media/'), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), [
            'Content-Type' => $media->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
