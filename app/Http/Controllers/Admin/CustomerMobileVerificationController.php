<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerMobileVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerMobileVerificationController extends Controller
{
    public function __construct(private readonly CustomerMobileVerification $verification) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20']]);
        $mobile = $this->verification->mobile($data['mobile']);

        return response()->json(['body' => $this->verification->preview(), 'mobile' => $mobile]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20'], 'sms_text' => ['required', 'string', 'max:1000']]);

        return response()->json($this->verification->send((int) auth('admin')->id(), $this->verification->mobile($data['mobile']), trim($data['sms_text'])));
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20'], 'otp_session' => ['required', 'uuid'], 'code' => ['required', 'string', 'max:12']]);

        return response()->json($this->verification->verify((int) auth('admin')->id(), $this->verification->mobile($data['mobile']), $data['otp_session'], $data['code']));
    }
}
