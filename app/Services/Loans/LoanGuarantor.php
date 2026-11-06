<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Customer;
use App\Models\CustomerLoanFile;
use App\Rules\IranNationalId;
use App\Support\JalaliInputParser;
use Illuminate\Http\Request;

final class LoanGuarantor
{
    public function fromRequest(Request $request): ?array
    {
        $request->validate(['guarantee_by_self' => ['sometimes', 'boolean'], 'guarantor_details' => ['nullable', 'array']]);
        if ($request->boolean('guarantee_by_self', true)) {
            return null;
        }
        $details = (array) $request->input('guarantor_details', []);
        foreach (['first_name', 'last_name', 'father_name', 'address', 'mobile', 'national_id'] as $key) {
            $details[$key] = is_string($details[$key] ?? null) ? trim($details[$key]) : '';
        }
        $details['mobile'] = JalaliInputParser::toEnglishDigits($details['mobile']);
        $nationalInput = $details['national_id'];
        $details['national_id'] = IranNationalId::normalizeNationalInput($nationalInput);
        if ($nationalInput !== '' && $details['national_id'] === '') {
            $details['national_id'] = $nationalInput;
        }
        $request->merge(['guarantor_details' => $details]);
        $data = $request->validate([
            'guarantor_details.first_name' => ['required', 'string', 'max:120'],
            'guarantor_details.last_name' => ['required', 'string', 'max:120'],
            'guarantor_details.father_name' => ['nullable', 'string', 'max:120'],
            'guarantor_details.national_id' => ['nullable', 'digits:10', new IranNationalId],
            'guarantor_details.mobile' => ['required', 'regex:/^09[0-9]{9}$/D'],
            'guarantor_details.address' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'guarantor_details.first_name' => 'نام ضامن',
            'guarantor_details.last_name' => 'نام خانوادگی ضامن',
            'guarantor_details.father_name' => 'نام پدر ضامن',
            'guarantor_details.national_id' => 'کد ملی ضامن',
            'guarantor_details.mobile' => 'موبایل ضامن',
            'guarantor_details.address' => 'آدرس ضامن',
        ]);

        return $data['guarantor_details'];
    }

    public function recipient(Customer $customer, ?array $details): array
    {
        return $details ?? ['first_name' => (string) $customer->first_name, 'last_name' => (string) $customer->last_name,
            'father_name' => (string) $customer->father_name, 'national_id' => (string) $customer->national_id,
            'address' => (string) $customer->address, 'mobile' => (string) $customer->mobile];
    }

    public function fingerprint(Customer $customer, ?array $details): string
    {
        $recipient = $this->recipient($customer, $details);
        ksort($recipient);

        return hash('sha256', json_encode($recipient, JSON_UNESCAPED_UNICODE));
    }

    public function chequeOwnerFields(CustomerLoanFile $file): array
    {
        $party = $file->guarantor_details;

        return $party === null ? [] : [
            'cheque_owner_name' => trim($party['first_name'].' '.$party['last_name']),
            ...(! empty($party['national_id']) ? ['cheque_owner_national_id' => $party['national_id']] : []),
            'cheque_owner_mobile' => $party['mobile'],
        ];
    }

    public function forFile(Customer $customer, CustomerLoanFile $file): array
    {
        return $this->recipient($customer, $file->guarantor_details);
    }
}
