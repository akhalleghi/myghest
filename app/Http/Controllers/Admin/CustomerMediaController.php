<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerMedia;
use App\Services\Customers\CustomerMediaService;
use App\Support\PrivateStoragePaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CustomerMediaController extends Controller
{
    public function index(Customer $customer, CustomerMediaService $media): JsonResponse
    {
        return response()->json(['available' => $media->available(), 'items' => $media->items($customer)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Customer $customer, CustomerMedia $media): BinaryFileResponse
    {
        abort_unless((int) $media->customer_id === (int) $customer->id, 404);
        $location = PrivateStoragePaths::readableLocation($media->path);
        abort_if($location === null || $location['disk'] !== 'local', 404);

        return response()->file(Storage::disk('local')->path($location['path']), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
