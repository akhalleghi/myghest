<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Support\GuaranteeReturnOtpSettings;
use App\Support\ListPerPage;
use App\Support\LoanCreationOtpSettings;
use App\Support\InstallmentBookletPrintSettings;
use App\Support\LoanInstallmentRoundingSettings;
use App\Support\PrivateStoragePaths;
use App\Models\CustomerBankAccount;
use App\Models\CustomerLoanFile;
use App\Models\CustomerLoanGuarantee;
use App\Models\CustomerLoanInstallment;
use App\Models\CustomerLoanInstallmentPayment;
use App\Models\CustomerReferrer;
use App\Models\CustomerWallet;
use App\Models\LoanType;
use App\Models\Organization;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Rules\IranNationalId;
use App\Services\Admin\RawSmsDispatcher;
use App\Services\Loans\LoanFileFinanceCalculator;
use App\Services\Loans\LoanInstallmentAmountAllocator;
use App\Services\Loans\LoanInstallmentScheduleService;
use App\Services\Sms\SmsPanelManager;
use App\Services\Sms\SmsSettingsService;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly SmsPanelManager $panelManager,
        private readonly RawSmsDispatcher $rawSms,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $listFilter = $this->customerListFilterFromRequest($request);
        $listSort = $this->customerListSortFromRequest($request);

        $customers = Customer::query()
            ->with(['loanFiles.loanType', 'loanFiles.installments', 'wallet'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($w) use ($q): void {
                    $w->where('customer_code', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('mobile', 'like', '%'.$q.'%')
                        ->orWhere('national_id', 'like', '%'.$q.'%');
                });
            });
        $this->applyCustomerListFilters($customers, $listFilter);
        $this->applyCustomerListSort($customers, $listSort);
        $customers = $customers
            ->paginate(ListPerPage::resolve($request))
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $q,
            'listScope' => $listFilter['list_scope'],
            'listSort' => $listSort['sort'],
            'listSortDir' => $listSort['dir'],
            'listFilterLabel' => $this->customerListFilterLabel($listFilter),
            'listFilterQuery' => $this->customerListFilterQueryParams($listFilter, $listSort),
            'appDisplayName' => $this->appDisplayName(),
            'loanTypes' => LoanType::query()
                ->latest('id')
                ->get(['id', 'title', 'interest_rate', 'profit_calculation_method', 'max_loan_amount', 'max_installment_gap', 'installment_gap_unit', 'repayment_periods'])
                ->values(),
            'loanManageMap' => $customers->getCollection()->mapWithKeys(function (Customer $customer): array {
                $loanFiles = $customer->loanFiles->map(fn (CustomerLoanFile $file): array => $this->mapLoanFile($file))->values();
                $loanTotalWithProfit = $loanFiles->sum(static fn (array $row): int => (int) ($row['total_repayable_toman'] ?? 0));
                $remainingInstallments = $loanFiles->sum(static fn (array $row): int => (int) ($row['remaining_amount_toman'] ?? 0));
                $primaryOverdue = $this->resolvePrimaryOverdueInstallment($customer);

                return [
                    (string) $customer->id => [
                        'loan_files' => $loanFiles->all(),
                        'loan_count' => $loanFiles->count(),
                        'loan_total_with_profit' => $loanTotalWithProfit,
                        'loan_remaining_installments' => $remainingInstallments,
                        'primary_overdue_installment_id' => $primaryOverdue['installment_id'] ?? null,
                        'primary_overdue_loan_file_id' => $primaryOverdue['loan_file_id'] ?? null,
                        'overdue_installment_count' => $primaryOverdue['count'] ?? 0,
                    ],
                ];
            }),
            'smsTemplates' => SmsTemplate::query()
                ->latest('id')
                ->get(['id', 'title', 'category', 'body', 'template_key'])
                ->map(static fn (SmsTemplate $tpl): array => [
                    'id' => $tpl->id,
                    'title' => $tpl->title,
                    'category' => $tpl->category,
                    'body' => $tpl->body,
                    'template_key' => (string) ($tpl->template_key ?? ''),
                ])
                ->values(),
            'loanManageLrqEmbedUrlTemplate' => $this->loanManageLoanRequestEmbedUrlTemplate(),
            'loanManageCtxEmbedUrlTemplate' => $this->loanManageCustomerTransactionsEmbedUrlTemplate(),
            'loanManageTicketsEmbedUrlTemplate' => $this->loanManageTicketsEmbedUrlTemplate(),
            'loanCreationOtpEnabled' => LoanCreationOtpSettings::isEnabled(),
            'guaranteeReturnOtpEnabled' => GuaranteeReturnOtpSettings::isEnabled(),
            'loanInstallmentRounding' => LoanInstallmentRoundingSettings::clientConfig(),
        ]);
    }

    private function loanManageCustomerTransactionsEmbedUrlTemplate(): string
    {
        $id = (int) Customer::query()->orderBy('id')->value('id');
        if ($id < 1) {
            return '';
        }
        $u = route('admin.customers.customer-transactions.embed', ['customer' => $id]);

        return preg_replace('#/'.preg_quote((string) $id, '#').'/#', '/__CUSTOMER_ID__/', $u, 1);
    }

    private function loanManageLoanRequestEmbedUrlTemplate(): string
    {
        $id = (int) Customer::query()->orderBy('id')->value('id');
        if ($id < 1) {
            return '';
        }
        $u = route('admin.customers.loan-requests.embed', ['customer' => $id]);

        return preg_replace('#/'.preg_quote((string) $id, '#').'/#', '/__CUSTOMER_ID__/', $u, 1);
    }

    private function loanManageTicketsEmbedUrlTemplate(): string
    {
        $id = (int) Customer::query()->orderBy('id')->value('id');
        if ($id < 1) {
            return '';
        }
        $u = route('admin.customers.tickets.embed', ['customer' => $id]);

        return preg_replace('#/'.preg_quote((string) $id, '#').'/#', '/__CUSTOMER_ID__/', $u, 1);
    }

    public function exportCustomersListExcel(Request $request): StreamedResponse
    {
        $queryText = trim((string) $request->query('q', ''));
        $listFilter = $this->customerListFilterFromRequest($request);

        $filename = 'customers-list-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($queryText, $listFilter): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            fwrite($out, "\xFF\xFE");

            $this->writeExcelUnicodeRow($out, [
                'نام',
                'نام خانوادگی',
                'کد ملی',
                'موبایل',
                'تعداد وام',
                'مجموع مبلغ وام‌های دریافتی با بهره',
                'مانده اقساط',
                'مبلغ قسط هر وام (با کاما)',
                'کد وام‌ها',
                'کد مشتری',
                'تاریخ عضویت',
            ]);

            $customerExportQuery = Customer::query()
                ->with(['loanFiles.loanType', 'loanFiles.installments'])
                ->when($queryText !== '', function ($query) use ($queryText): void {
                    $query->where(function ($w) use ($queryText): void {
                        $w->where('customer_code', 'like', '%'.$queryText.'%')
                            ->orWhere('first_name', 'like', '%'.$queryText.'%')
                            ->orWhere('last_name', 'like', '%'.$queryText.'%')
                            ->orWhere('mobile', 'like', '%'.$queryText.'%')
                            ->orWhere('national_id', 'like', '%'.$queryText.'%');
                    });
                });
            $this->applyCustomerListFilters($customerExportQuery, $listFilter);
            $customerExportQuery
                ->latest('id')
                ->chunkById(150, function ($chunk) use ($out): void {
                    foreach ($chunk as $customer) {
                        if ($customer instanceof Customer) {
                            $this->writeExcelUnicodeRow($out, $this->buildCustomerListExportCells($customer));
                        }
                    }
                });

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * @return array<int, string>
     */
    private function buildCustomerListExportCells(Customer $customer): array
    {
        /** @var Collection<int, array<string, mixed>> $loanRows */
        $loanRows = $customer->loanFiles->map(fn (CustomerLoanFile $file): array => $this->mapLoanFile($file))->values();
        $loanCount = $loanRows->count();
        $loanTotalWithProfit = (int) $loanRows->sum(static fn (array $row): int => (int) ($row['total_repayable_toman'] ?? 0));
        $remainingInstallments = (int) $loanRows->sum(static fn (array $row): int => (int) ($row['remaining_amount_toman'] ?? 0));

        $instParts = $loanRows->map(function (array $row): string {
            return Jalali::enToFaNumbers(number_format((int) ($row['installment_amount_toman'] ?? 0), 0, '.', ','));
        })->all();
        $instJoined = $instParts !== [] ? implode(', ', $instParts) : '—';

        $codeParts = $loanRows->map(static function (array $row): string {
            return (string) ($row['loan_code'] ?? '');
        })->all();
        $codesJoined = $codeParts !== [] ? implode(', ', $codeParts) : '—';

        $nid = trim((string) ($customer->national_id ?? ''));
        $mobile = trim((string) ($customer->mobile ?? ''));

        $membershipFa = '—';
        if ($customer->membership_at !== null) {
            $membershipFa = Jalali::enToFaNumbers(
                Jalali::instance(Carbon::parse($customer->membership_at))->format('Y/m/d')
            );
        }

        return [
            trim((string) ($customer->first_name ?? '')),
            trim((string) ($customer->last_name ?? '')),
            $nid !== '' ? Jalali::enToFaNumbers($nid) : '—',
            $mobile !== '' ? Jalali::enToFaNumbers($mobile) : '—',
            Jalali::enToFaNumbers((string) $loanCount),
            Jalali::enToFaNumbers(number_format(max(0, $loanTotalWithProfit), 0, '.', ',')),
            Jalali::enToFaNumbers(number_format(max(0, $remainingInstallments), 0, '.', ',')),
            $instJoined,
            $codesJoined,
            trim((string) ($customer->customer_code ?? '')),
            $membershipFa,
        ];
    }

    public function downloadCustomersImportSampleExcel(): StreamedResponse
    {
        $sampleNid = $this->generateSampleNationalIdDigits();
        $todayJ = Jalali::instance(Carbon::now())->format('Y/m/d');

        $filename = 'customers-import-sample.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($sampleNid, $todayJ): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }
            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, [
                'کد مشتری',
                'نام',
                'نام خانوادگی',
                'نام پدر',
                'کد ملی',
                'موبایل',
                'رمز عبور',
                'شهر',
                'آدرس',
                'کد پستی',
                'تلفن ثابت',
                'تاریخ عضویت',
                'تاریخ تولد',
                'ایمیل',
            ]);
            $this->writeExcelUnicodeRow($out, [
                '',
                'علی',
                'نمونه‌زاده',
                'محمد',
                $sampleNid,
                '09120000007',
                'SamplePass123',
                'تهران',
                'خیابان نمونه، پلاک ۱۰، واحد ۲، کد پستی ده رقمی در ستون کناری را واقعی کنید',
                '1234567890',
                '',
                $todayJ,
                '',
                '',
            ]);
            fclose($out);
        }, $filename, $headers);
    }

    public function importCustomersFromExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'excel_file' => ['required', 'file', 'max:5120'],
            ], [], [
                'excel_file' => 'فایل اکسل',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'بارگذاری فایل معتبر نیست.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        $uploaded = $request->file('excel_file');
        if ($uploaded === null || ! $uploaded->isValid()) {
            return response()->json([
                'message' => 'فایل بارگذاری‌شده نامعتبر است.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        $binary = (string) file_get_contents($uploaded->getRealPath() ?: '');
        $utf8 = $this->customersImportSpreadsheetBinaryToUtf8($binary);
        if ($utf8 === '') {
            return response()->json([
                'message' => 'فایل خالی یا غیرخواناست.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        if (strlen($binary) >= 2 && str_starts_with($binary, 'PK')) {
            return response()->json([
                'message' => 'فایل‌های اکسل واقعی ‎(.xlsx)‎ در این بخش به‌صورت متنی خوانده نمی‌شوند. یا همان فایل نمونه را بدون باز کردن در اکسل بارگذاری کنید، یا پس از ویرایش از منوی «ذخیرهٔ با نام» گزینهٔ «CSV UTF-8» یا «متن یونیکد ‎(Tab delimited)‎» را انتخاب کنید.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        $lines = preg_split("/\r\n|\n|\r/", $utf8) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
        if ($lines === []) {
            return response()->json([
                'message' => 'هیچ ردیفی در فایل یافت نشد.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        $delimiter = $this->customersImportDetectDelimiter($lines);
        $dataLines = array_slice($lines, 1);
        if ($dataLines === []) {
            return response()->json([
                'message' => 'فقط ردیف عنوان وجود دارد؛ ردیف داده اضافه کنید.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        if (count($dataLines) > 500) {
            return response()->json([
                'message' => 'حداکثر ۵۰۰ ردیف داده در هر بار بارگذاری مجاز است.',
                'created_count' => 0,
                'failures' => [],
            ], 422);
        }

        $created = 0;
        $failures = [];
        $seenNational = [];
        $seenMobile = [];
        $seenCode = [];

        foreach ($dataLines as $offset => $line) {
            $sheetRowIndex = $offset + 2;
            $cells = $this->customersImportAlignRowCells($this->customersImportExplodeLine($line, $delimiter));
            if ($this->customersImportRowIsAllEmpty($cells)) {
                continue;
            }

            $raw = [
                'customer_code' => trim((string) ($cells[0] ?? '')),
                'first_name' => trim((string) ($cells[1] ?? '')),
                'last_name' => trim((string) ($cells[2] ?? '')),
                'father_name' => trim((string) ($cells[3] ?? '')),
                'national_id' => IranNationalId::normalizeNationalInput($cells[4] ?? ''),
                'mobile' => $this->normalizeIranMobileForImport(trim((string) ($cells[5] ?? ''))),
                'password' => trim((string) ($cells[6] ?? '')),
                'city' => trim((string) ($cells[7] ?? '')),
                'address' => trim((string) ($cells[8] ?? '')),
                'postal_code' => preg_replace('/\D/', '', $this->toEnglishDigits(trim((string) ($cells[9] ?? '')))) ?? '',
                'phone_landline' => trim((string) ($cells[10] ?? '')),
                'membership_jdate' => trim((string) ($cells[11] ?? '')),
                'birth_jdate' => trim((string) ($cells[12] ?? '')),
                'email' => strtolower(trim((string) ($cells[13] ?? ''))),
            ];

            if ($raw['email'] === '') {
                $raw['email'] = '';
            }

            if (isset($seenNational[$raw['national_id']]) && $raw['national_id'] !== '') {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['کد ملی در چند ردیف از همین فایل تکراری است.'],
                ];

                continue;
            }
            if (isset($seenMobile[$raw['mobile']]) && $raw['mobile'] !== '') {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['موبایل در چند ردیف از همین فایل تکراری است.'],
                ];

                continue;
            }
            $codeKey = $raw['customer_code'] !== '' ? $raw['customer_code'] : null;
            if ($codeKey !== null && isset($seenCode[$codeKey])) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['کد مشتری در چند ردیف از همین فایل تکراری است.'],
                ];

                continue;
            }

            $membershipAt = $raw['membership_jdate'] !== '' ? $this->parseJalaliDate($raw['membership_jdate']) : null;
            if ($raw['membership_jdate'] !== '' && $membershipAt === null) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['تاریخ عضویت شمسی معتبر نیست (مثال: ۱۴۰۴/۰۱/۱۵).'],
                ];

                continue;
            }
            $birthDate = $raw['birth_jdate'] !== '' ? $this->parseJalaliDate($raw['birth_jdate']) : null;
            if ($raw['birth_jdate'] !== '' && $birthDate === null) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['تاریخ تولد شمسی معتبر نیست.'],
                ];

                continue;
            }

            $plainPassword = trim((string) $raw['password']);
            if ($plainPassword === '' || mb_strlen($plainPassword) < 8) {
                $plainPassword = 'Pw'.substr(bin2hex(random_bytes(7)), 0, 13);
            }

            $customerCodeForRules = $raw['customer_code'] !== '' ? $raw['customer_code'] : null;
            $emailForRules = $raw['email'] !== '' ? $raw['email'] : null;

            $rules = [
                'first_name' => ['required', 'string', 'max:120'],
                'last_name' => ['required', 'string', 'max:120'],
                'father_name' => ['required', 'string', 'max:120'],
                'national_id' => ['required', 'digits:10', new IranNationalId, Rule::unique('customers', 'national_id')],
                'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')],
                'password' => ['required', 'string', 'min:8', 'max:255'],
                'city' => ['required', 'string', 'max:120'],
                'address' => ['required', 'string', 'max:2000'],
                'postal_code' => ['required', 'string', 'max:16', 'regex:/^[0-9]{10}$/'],
                'phone_landline' => ['nullable', 'string', 'max:32'],
                'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191'],
            ];
            if ($customerCodeForRules !== null) {
                $rules['customer_code'] = ['required', 'string', 'max:40', Rule::unique('customers', 'customer_code')];
            }

            if ($emailForRules !== null) {
                $rules['email'] = ['required', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')];
            }

            $payload = [
                'first_name' => $raw['first_name'],
                'last_name' => $raw['last_name'],
                'father_name' => $raw['father_name'],
                'national_id' => $raw['national_id'],
                'mobile' => $raw['mobile'],
                'password' => $plainPassword,
                'city' => $raw['city'],
                'address' => $raw['address'],
                'postal_code' => $raw['postal_code'],
                'phone_landline' => $raw['phone_landline'] !== '' ? $raw['phone_landline'] : null,
                'email' => $emailForRules,
            ];
            if ($customerCodeForRules !== null) {
                $payload['customer_code'] = $customerCodeForRules;
            }

            $validator = Validator::make($payload, $rules, [], [
                'customer_code' => 'کد مشتری',
                'first_name' => 'نام',
                'last_name' => 'نام خانوادگی',
                'father_name' => 'نام پدر',
                'national_id' => 'کد ملی',
                'mobile' => 'موبایل',
                'password' => 'رمز عبور',
                'city' => 'شهر',
                'address' => 'آدرس',
                'postal_code' => 'کد پستی',
                'phone_landline' => 'تلفن ثابت',
                'email' => 'ایمیل',
            ]);

            if ($validator->fails()) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => array_values(array_unique($validator->errors()->all())),
                ];

                continue;
            }

            $validated = $validator->validated();
            $username = $this->usernameFromMobile((string) $validated['mobile']);
            if (Customer::query()->where('username', $username)->exists()) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.'],
                ];

                continue;
            }

            try {
                $finalCode = trim((string) ($validated['customer_code'] ?? ''));
                if ($finalCode === '') {
                    $finalCode = $this->generateUniqueCustomerCode();
                }
                $this->persistImportedCustomerCore(
                    $finalCode,
                    $username,
                    (string) $validated['first_name'],
                    (string) $validated['last_name'],
                    (string) $validated['father_name'],
                    (string) $validated['national_id'],
                    (string) $validated['mobile'],
                    $validated['phone_landline'] ?? null,
                    $membershipAt,
                    $birthDate,
                    $validated['email'] ?? null,
                    (string) $validated['password'],
                    (string) $validated['city'],
                    (string) $validated['address'],
                    (string) $validated['postal_code'],
                );
            } catch (\Throwable $ex) {
                $failures[] = [
                    'row' => $sheetRowIndex,
                    'errors' => ['خطای غیرمنتظره هنگام ذخیره: '.$ex->getMessage()],
                ];

                continue;
            }

            $seenNational[$raw['national_id']] = true;
            $seenMobile[$raw['mobile']] = true;
            if ($codeKey !== null) {
                $seenCode[$codeKey] = true;
            }
            $created++;
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = Jalali::enToFaNumbers((string) $created).' مشتری جدید ثبت شد.';
        }
        if ($failures !== []) {
            $parts[] = Jalali::enToFaNumbers((string) count($failures)).' ردیف دارای اشکال است.';
        }
        if ($created === 0 && $failures === []) {
            $parts[] = 'هیچ ردیف دادهٔ معتبری پردازش نشد.';
        }

        return response()->json([
            'message' => implode(' ', $parts),
            'created_count' => $created,
            'failures' => $failures,
        ]);
    }

    private function generateSampleNationalIdDigits(): string
    {
        for ($i = 100_000_000; $i <= 999_999_999; $i++) {
            $nine = sprintf('%09d', $i);
            $sum = 0;
            for ($j = 0; $j < 9; $j++) {
                $sum += (int) $nine[$j] * (10 - $j);
            }
            $remainder = $sum % 11;
            $check = $remainder < 2 ? $remainder : 11 - $remainder;
            $full = $nine.$check;
            if (preg_match('/^(\d)\1{9}$/', $full)) {
                continue;
            }

            return $full;
        }

        return '2283142978';
    }

    private function customersImportSpreadsheetBinaryToUtf8(string $binary): string
    {
        if ($binary === '') {
            return '';
        }
        if (str_starts_with($binary, "\xFF\xFE")) {
            $body = substr($binary, 2);

            return mb_convert_encoding($body, 'UTF-8', 'UTF-16LE') ?: '';
        }
        if (str_starts_with($binary, "\xFE\xFF")) {
            $body = substr($binary, 2);

            return mb_convert_encoding($body, 'UTF-8', 'UTF-16BE') ?: '';
        }
        if (str_starts_with($binary, "\xEF\xBB\xBF")) {
            $binary = substr($binary, 3);
        }

        return $binary;
    }

    /**
     * بین ردیف‌های نمونه بیشترین «تعداد ستون پایدار» را با تب، ویرگول یا نقطه‌ویرگول می‌سنجیم (CSV ناحیه‌ای اکسل با ‎;‎).
     *
     * @param  array<int, string>  $lines
     */
    private function customersImportDetectDelimiter(array $lines): string
    {
        $sample = [];
        foreach (array_slice($lines, 0, 25) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $sample[] = $trimmed;
            }
        }
        if ($sample === []) {
            return "\t";
        }

        $splitters = [
            "\t" => fn (string $l): array => explode("\t", $l),
            ',' => fn (string $l): array => str_getcsv($l),
            ';' => fn (string $l): array => str_getcsv($l, ';', '"', '\\'),
        ];

        $bestDelimiter = "\t";
        $bestMedian = -1;

        foreach ($splitters as $delimiter => $splitFn) {
            $counts = [];
            foreach ($sample as $sampleLine) {
                $counts[] = count($splitFn($sampleLine));
            }

            sort($counts);
            $mid = $counts[(int) floor((count($counts) - 1) / 2)];
            if ($mid > $bestMedian) {
                $bestMedian = $mid;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @return array<int, string>
     */
    private function customersImportExplodeLine(string $line, string $delimiter): array
    {
        return match ($delimiter) {
            ',' => str_getcsv($line),
            ';' => str_getcsv($line, ';', '"', '\\'),
            default => explode("\t", $line),
        };
    }

    /**
     * اگر ستون اول (کد مشتری خالی) هنگام ذخیرهٔ CSV حذف شده باشد، اینجا قبل از ستون‌ها یک سلول خالی اضافه می‌کنیم تا نگاشت ستون‌ها حفظ شود.
     *
     * @param  array<int, string>  $rawCells
     * @return array<int, string>
     */
    private function customersImportAlignRowCells(array $rawCells): array
    {
        $rawCells = array_values(array_map(static function (mixed $c): string {
            return trim(ltrim((string) $c, "\xEF\xBB\xBF"));
        }, $rawCells));

        $chosen = null;
        foreach ([0, 1, 2] as $prependLen) {
            $merged = array_merge(array_fill(0, $prependLen, ''), $rawCells);
            $cells = array_slice(array_pad($merged, 14, ''), 0, 14);
            if ($this->customersImportRowLooksAlignedForTemplate($cells)) {
                $chosen = $cells;

                break;
            }
        }

        if ($chosen !== null) {
            return $chosen;
        }

        if (count($rawCells) === 13) {
            return array_slice(array_pad(array_merge([''], $rawCells), 14, ''), 0, 14);
        }

        return array_slice(array_pad($rawCells, 14, ''), 0, 14);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function customersImportRowLooksAlignedForTemplate(array $cells): bool
    {
        $digits = preg_replace('/\D/', '', $this->normalizeIranMobileForImport(trim((string) ($cells[5] ?? '')))) ?? '';

        return preg_match('/^09\d{9}$/', $digits) === 1;
    }

    /**
     * اگر اکسل صفر اول موبایل را حذف کرده باشد (‎912…‎)، برای اعتبارسنجی ‎0912…‎ بازمی‌گردانیم.
     */
    private function normalizeIranMobileForImport(string $value): string
    {
        $s = trim($this->toEnglishDigits($value));
        $digits = preg_replace('/\D/', '', $s) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }

        return $s;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function customersImportRowIsAllEmpty(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    private function persistImportedCustomerCore(
        string $customerCode,
        string $username,
        string $firstName,
        string $lastName,
        string $fatherName,
        string $nationalId,
        string $mobile,
        ?string $phoneLandline,
        ?Carbon $membershipAt,
        ?Carbon $birthDate,
        ?string $email,
        string $plainPassword,
        string $city,
        string $address,
        string $postalCode,
    ): Customer {
        return DB::transaction(function () use (
            $customerCode,
            $username,
            $firstName,
            $lastName,
            $fatherName,
            $nationalId,
            $mobile,
            $phoneLandline,
            $membershipAt,
            $birthDate,
            $email,
            $plainPassword,
            $city,
            $address,
            $postalCode,
        ): Customer {
            $c = Customer::query()->create([
                'customer_code' => $customerCode,
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'father_name' => $fatherName,
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'phone_landline' => $phoneLandline,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $email,
                'password' => $plainPassword,
                'city' => $city,
                'address' => $address,
                'postal_code' => $postalCode,
            ]);
            CustomerWallet::query()->create([
                'customer_id' => $c->id,
                'balance_toman' => 0,
                'is_locked' => false,
            ]);

            return $c;
        });
    }

    public function storeLoan(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'loan_start_jdate' => ['required', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1200'],
            'installment_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_interval_unit' => ['required', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'installment_amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'down_payment_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'sub_file_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_settled' => ['nullable', 'boolean'],
            'settled_jdate' => ['nullable', 'string', 'max:20'],
            'has_custom_interest_rate' => ['nullable', 'boolean'],
            'custom_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'customer_verification_token' => ['nullable', 'string', 'max:128'],
        ]);

        if (LoanCreationOtpSettings::isEnabled()) {
            $mobile = $this->normalizeCustomerMobileForSmsFilter((string) $customer->mobile);
            if ($mobile === '') {
                return response()->json(['message' => 'شماره موبایل معتبر برای این مشتری ثبت نشده است؛ ثبت وام با تایید پیامکی ممکن نیست.'], 422);
            }
            if (! $this->consumeLoanCreationVerificationToken($request, $customer)) {
                return response()->json(['message' => 'تایید پیامکی مشتری الزامی است. ابتدا کد ارسال‌شده به موبایل مشتری را تایید کنید.'], 422);
            }
        }

        $startDate = $this->parseJalaliDate((string) $validated['loan_start_jdate']);
        if ($startDate === null) {
            return response()->json(['message' => 'تاریخ شروع وام معتبر نیست.'], 422);
        }

        $disbursementDueDate = null;
        if (($validated['disbursement_due_jdate'] ?? '') !== '') {
            $disbursementDueDate = $this->parseJalaliDate((string) $validated['disbursement_due_jdate']);
            if ($disbursementDueDate === null) {
                return response()->json(['message' => 'سررسید واریز معتبر نیست.'], 422);
            }
        }

        $isSettled = false;
        $settledAt = null;

        $amount = (int) $validated['amount_toman'];
        $installmentsCount = (int) $validated['installments_count'];
        $installmentAmount = (int) $validated['installment_amount_toman'];
        $downPayment = (int) ($validated['down_payment_toman'] ?? 0);
        if ($downPayment > $amount) {
            return response()->json(['message' => 'مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ وام باشد.'], 422);
        }

        $loanType = LoanType::query()->findOrFail((int) $validated['loan_type_id']);
        $intervalCount = (int) $validated['installment_interval_count'];
        $intervalUnit = (string) $validated['installment_interval_unit'];
        if ($intervalUnit !== (string) $loanType->installment_gap_unit) {
            return response()->json(['message' => 'محدوده زمانی اقساط باید مطابق تنظیمات نوع وام باشد.'], 422);
        }
        if ($loanType->max_loan_amount !== null && $amount > (int) $loanType->max_loan_amount) {
            return response()->json(['message' => 'مبلغ وام از سقف مجاز نوع وام بیشتر است.'], 422);
        }
        if ($loanType->max_installment_gap !== null && $intervalCount > (int) $loanType->max_installment_gap) {
            return response()->json(['message' => 'فاصله اقساط از مقدار مجاز نوع وام بیشتر است.'], 422);
        }
        if (! $this->isRepaymentPeriodAllowed($loanType, $installmentsCount, $intervalCount, $intervalUnit, $amount)) {
            return response()->json(['message' => 'دوره بازپرداخت واردشده با محدودیت‌های نوع وام سازگار نیست.'], 422);
        }

        $baseInterestRate = (float) $loanType->interest_rate;
        $profitMethod = (string) $loanType->profit_calculation_method;
        $hasCustomInterestRate = (bool) ($validated['has_custom_interest_rate'] ?? false);
        $customInterestRate = null;
        if ($hasCustomInterestRate) {
            if (($validated['custom_interest_rate'] ?? null) === null || $validated['custom_interest_rate'] === '') {
                return response()->json(['message' => 'درصد بهره جدید را وارد کنید.'], 422);
            }
            $customInterestRate = round((float) $validated['custom_interest_rate'], 2);
        }
        $effectiveInterestRate = $hasCustomInterestRate ? (float) $customInterestRate : $baseInterestRate;
        $calculatedProfit = $this->calculateLoanProfitToman(
            $amount,
            $effectiveInterestRate,
            $profitMethod,
            $installmentsCount,
            $intervalCount,
            $intervalUnit
        );
        $allocation = app(LoanInstallmentAmountAllocator::class)->allocateForLoanFile(
            $amount,
            $calculatedProfit,
            $downPayment,
            $installmentsCount,
        );
        $downPayment = (int) $allocation['adjusted_down_payment_toman'];
        $installmentAmount = (int) $allocation['base_amount_toman'];
        $payableAfterDownPayment = (int) $allocation['payable_after_down_payment_toman'];
        $sumInstallments = array_sum($allocation['amounts_toman']);
        if ($sumInstallments > $payableAfterDownPayment) {
            return response()->json(['message' => 'مجموع مبلغ اقساط از مبلغ قابل بازپرداخت (با احتساب بهره نوع وام) بیشتر است.'], 422);
        }
        if ($installmentAmount < 1) {
            return response()->json(['message' => 'مبلغ هر قسط پس از رندسازی معتبر نیست.'], 422);
        }

        $loanFile = DB::transaction(function () use (
            $customer,
            $loanType,
            $startDate,
            $disbursementDueDate,
            $amount,
            $validated,
            $installmentsCount,
            $installmentAmount,
            $downPayment,
            $isSettled,
            $settledAt,
            $baseInterestRate,
            $profitMethod,
            $hasCustomInterestRate,
            $customInterestRate,
            $effectiveInterestRate
        ): CustomerLoanFile {
            $file = CustomerLoanFile::query()->create([
                'customer_id' => $customer->id,
                'loan_type_id' => $loanType->id,
                'loan_code' => 'TMP',
                'loan_start_date' => $startDate,
                'disbursement_due_date' => $disbursementDueDate,
                'amount_toman' => $amount,
                'installments_count' => $installmentsCount,
                'installment_interval_count' => (int) $validated['installment_interval_count'],
                'installment_interval_unit' => (string) $validated['installment_interval_unit'],
                'installment_amount_toman' => $installmentAmount,
                'down_payment_toman' => $downPayment,
                'profit_calculation_method' => $profitMethod,
                'sub_file_number' => trim((string) ($validated['sub_file_number'] ?? '')) ?: null,
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'is_settled' => $isSettled,
                'settled_at' => $settledAt,
                'base_interest_rate' => $baseInterestRate,
                'has_custom_interest_rate' => $hasCustomInterestRate,
                'custom_interest_rate' => $customInterestRate,
                'effective_interest_rate' => $effectiveInterestRate,
                'created_by_admin_id' => auth('admin')->id(),
            ]);
            $file->loan_code = 'LF-'.str_pad((string) $file->id, 7, '0', STR_PAD_LEFT);
            $file->save();
            app(LoanInstallmentScheduleService::class)->ensureSchedule($file);

            return $file->fresh(['loanType']) ?? $file;
        });

        $smsFeedback = '';
        if ((bool) ($validated['send_sms'] ?? false)) {
            $smsText = trim((string) ($validated['sms_text'] ?? ''));
            if ($smsText === '' && isset($validated['sms_template_id'])) {
                $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
                if ($template !== null) {
                    $smsText = $this->renderTemplate($template->body, $this->loanSmsTemplateVars($customer, $loanFile));
                }
            }
            if ($smsText === '') {
                $smsText = $this->defaultLoanCreatedSmsText($customer, $loanFile);
            }
            $smsResult = $this->rawSms->send($customer->mobile, $smsText, 'loan-file-created');
            $smsFeedback = ' '.$smsResult['message'];
        }

        return response()->json([
            'message' => 'پرونده وام با موفقیت ثبت شد.'.$smsFeedback,
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function updateLoan(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'این قرارداد فسخ شده است و قابل ویرایش نیست.'], 422);
        }

        $validated = $request->validate([
            'loan_start_jdate' => ['required', 'string', 'max:20'],
            'disbursement_due_jdate' => ['nullable', 'string', 'max:20'],
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1200'],
            'installment_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_interval_unit' => ['required', Rule::in([LoanType::GAP_MONTHLY, LoanType::GAP_WEEKLY])],
            'installment_amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'down_payment_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'sub_file_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_settled' => ['nullable', 'boolean'],
            'settled_jdate' => ['nullable', 'string', 'max:20'],
            'has_custom_interest_rate' => ['nullable', 'boolean'],
            'custom_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $startDate = $this->parseJalaliDate((string) $validated['loan_start_jdate']);
        if ($startDate === null) {
            return response()->json(['message' => 'تاریخ شروع وام معتبر نیست.'], 422);
        }
        $disbursementDueDate = null;
        if (($validated['disbursement_due_jdate'] ?? '') !== '') {
            $disbursementDueDate = $this->parseJalaliDate((string) $validated['disbursement_due_jdate']);
            if ($disbursementDueDate === null) {
                return response()->json(['message' => 'سررسید واریز معتبر نیست.'], 422);
            }
        }

        $isSettled = (bool) ($validated['is_settled'] ?? false);
        $settledAt = null;
        if ($isSettled) {
            if (($validated['settled_jdate'] ?? '') === '') {
                return response()->json(['message' => 'تاریخ تسویه الزامی است.'], 422);
            }
            $settledAt = $this->parseJalaliDate((string) $validated['settled_jdate']);
            if ($settledAt === null) {
                return response()->json(['message' => 'تاریخ تسویه معتبر نیست.'], 422);
            }
            if ($settledAt->lt($startDate)) {
                return response()->json(['message' => 'تاریخ تسویه نمی‌تواند قبل از تاریخ شروع وام باشد.'], 422);
            }
        }

        $amount = (int) $validated['amount_toman'];
        $installmentsCount = (int) $validated['installments_count'];
        $installmentAmount = (int) $validated['installment_amount_toman'];
        $downPayment = (int) ($validated['down_payment_toman'] ?? 0);
        if ($downPayment > $amount) {
            return response()->json(['message' => 'مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ وام باشد.'], 422);
        }

        $loanType = LoanType::query()->findOrFail((int) $validated['loan_type_id']);
        $intervalCount = (int) $validated['installment_interval_count'];
        $intervalUnit = (string) $validated['installment_interval_unit'];
        if ($intervalUnit !== (string) $loanType->installment_gap_unit) {
            return response()->json(['message' => 'محدوده زمانی اقساط باید مطابق تنظیمات نوع وام باشد.'], 422);
        }
        if ($loanType->max_loan_amount !== null && $amount > (int) $loanType->max_loan_amount) {
            return response()->json(['message' => 'مبلغ وام از سقف مجاز نوع وام بیشتر است.'], 422);
        }
        if ($loanType->max_installment_gap !== null && $intervalCount > (int) $loanType->max_installment_gap) {
            return response()->json(['message' => 'فاصله اقساط از مقدار مجاز نوع وام بیشتر است.'], 422);
        }
        if (! $this->isRepaymentPeriodAllowed($loanType, $installmentsCount, $intervalCount, $intervalUnit, $amount)) {
            return response()->json(['message' => 'دوره بازپرداخت واردشده با محدودیت‌های نوع وام سازگار نیست.'], 422);
        }

        $baseInterestRate = (float) $loanType->interest_rate;
        $profitMethod = (string) $loanType->profit_calculation_method;
        $hasCustomInterestRate = (bool) ($validated['has_custom_interest_rate'] ?? false);
        $customInterestRate = null;
        if ($hasCustomInterestRate) {
            if (($validated['custom_interest_rate'] ?? null) === null || $validated['custom_interest_rate'] === '') {
                return response()->json(['message' => 'درصد بهره جدید را وارد کنید.'], 422);
            }
            $customInterestRate = round((float) $validated['custom_interest_rate'], 2);
        }
        $effectiveInterestRate = $hasCustomInterestRate ? (float) $customInterestRate : $baseInterestRate;
        $calculatedProfit = $this->calculateLoanProfitToman(
            $amount,
            $effectiveInterestRate,
            $profitMethod,
            $installmentsCount,
            $intervalCount,
            $intervalUnit
        );
        $allocation = app(LoanInstallmentAmountAllocator::class)->allocateForLoanFile(
            $amount,
            $calculatedProfit,
            $downPayment,
            $installmentsCount,
        );
        $downPayment = (int) $allocation['adjusted_down_payment_toman'];
        $installmentAmount = (int) $allocation['base_amount_toman'];
        $payableAfterDownPayment = (int) $allocation['payable_after_down_payment_toman'];
        $sumInstallments = array_sum($allocation['amounts_toman']);
        if ($sumInstallments > $payableAfterDownPayment) {
            return response()->json(['message' => 'مجموع مبلغ اقساط از مبلغ قابل بازپرداخت (با احتساب بهره نوع وام) بیشتر است.'], 422);
        }
        if ($installmentAmount < 1) {
            return response()->json(['message' => 'مبلغ هر قسط پس از رندسازی معتبر نیست.'], 422);
        }

        try {
            DB::transaction(function () use (
                $loanFile,
                $loanType,
                $startDate,
                $disbursementDueDate,
                $amount,
                $installmentsCount,
                $installmentAmount,
                $downPayment,
                $intervalCount,
                $intervalUnit,
                $profitMethod,
                $validated,
                $isSettled,
                $settledAt,
                $baseInterestRate,
                $hasCustomInterestRate,
                $customInterestRate,
                $effectiveInterestRate
            ): void {
                $loanFile->update([
                    'loan_type_id' => $loanType->id,
                    'loan_start_date' => $startDate,
                    'disbursement_due_date' => $disbursementDueDate,
                    'amount_toman' => $amount,
                    'installments_count' => $installmentsCount,
                    'installment_interval_count' => $intervalCount,
                    'installment_interval_unit' => $intervalUnit,
                    'installment_amount_toman' => $installmentAmount,
                    'down_payment_toman' => $downPayment,
                    'profit_calculation_method' => $profitMethod,
                    'sub_file_number' => trim((string) ($validated['sub_file_number'] ?? '')) ?: null,
                    'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                    'is_settled' => $isSettled,
                    'settled_at' => $settledAt,
                    'base_interest_rate' => $baseInterestRate,
                    'has_custom_interest_rate' => $hasCustomInterestRate,
                    'custom_interest_rate' => $customInterestRate,
                    'effective_interest_rate' => $effectiveInterestRate,
                ]);

                $loanFile->refresh();
                $loanFile->load('loanType');
                app(LoanInstallmentScheduleService::class)->syncScheduleFromLoanFile($loanFile);
            });
        } catch (ValidationException $e) {
            $firstMessage = collect($e->errors())->flatten()->first();

            return response()->json([
                'message' => is_string($firstMessage) && $firstMessage !== ''
                    ? $firstMessage
                    : 'ویرایش پرونده وام به‌خاطر ناسازگاری با اقساط ثبت‌شده ممکن نیست.',
                'errors' => $e->errors(),
            ], 422);
        }
        $loanFile->refresh();
        $loanFile->load(['loanType', 'installments']);

        return response()->json([
            'message' => 'پرونده وام با موفقیت ویرایش شد.',
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function destroyLoan(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $loanCode = (string) $loanFile->loan_code;
        $loanFile->delete();

        return response()->json([
            'message' => 'پرونده وام '.$loanCode.' حذف شد.',
        ]);
    }

    public function revokeLoanContract(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'این قرارداد قبلاً فسخ شده است.'], 422);
        }

        $adminId = auth('admin')->id();
        if ($adminId === null) {
            return response()->json(['message' => 'احراز هویت مدیر الزامی است.'], 401);
        }

        DB::transaction(function () use ($loanFile, $adminId): void {
            CustomerLoanInstallment::query()->where('customer_loan_file_id', $loanFile->id)->delete();
            $loanFile->update([
                'revoked_at' => now(),
                'revoked_by_admin_id' => (int) $adminId,
                'installments_count' => 0,
                'installment_amount_toman' => 0,
                'is_settled' => false,
                'settled_at' => null,
                'discount_amount_toman' => 0,
                'discount_updated_at' => null,
                'discount_updated_by_admin_id' => null,
            ]);
        });

        $loanFile->refresh();
        $loanFile->load('loanType');

        return response()->json([
            'message' => 'قرارداد با موفقیت فسخ شد. تمام اقساط این پرونده حذف شدند.',
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function sendLoanFileSms(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'این قرارداد فسخ شده است؛ ارسال پیامک برای این پرونده مجاز نیست.'], 422);
        }

        $validated = $request->validate([
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ]);

        $smsText = trim((string) ($validated['sms_text'] ?? ''));
        if ($smsText === '' && isset($validated['sms_template_id'])) {
            $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
            if ($template !== null) {
                $smsText = $this->renderTemplate($template->body, $this->loanSmsTemplateVars($customer, $loanFile));
            }
        }
        if ($smsText === '') {
            $smsText = $this->defaultLoanCreatedSmsText($customer, $loanFile);
        }

        $smsResult = $this->rawSms->send($customer->mobile, $smsText, 'loan-file-created');

        return response()->json([
            'ok' => $smsResult['ok'],
            'message' => $smsResult['message'],
        ], $smsResult['ok'] ? 200 : 422);
    }

    public function loanInstallments(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $loanFile->load('loanType');

        if ($loanFile->revoked_at !== null) {
            return response()->json([
                'loan' => array_merge([
                    'id' => (int) $loanFile->id,
                    'loan_code' => (string) $loanFile->loan_code,
                    'loan_type_title' => (string) ($loanFile->loanType?->title ?? '—'),
                    'amount_toman' => (int) $loanFile->amount_toman,
                    'loan_start_jdate' => $loanFile->loan_start_date
                        ? Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d')
                        : '',
                    'loan_start_jdate_fa' => $loanFile->loan_start_date
                        ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d'))
                        : '',
                    'installment_amount_toman' => (int) $loanFile->installment_amount_toman,
                    'installments_count' => (int) $loanFile->installments_count,
                    'is_settled' => (bool) $loanFile->is_settled,
                    'schedule_remaining_toman' => 0,
                    'is_revoked' => true,
                    'revoked_notice' => 'این قرارداد فسخ شده است؛ اقساطی برای نمایش وجود ندارد.',
                ], $this->loanInstallmentsModalSummary($loanFile, null)),
                'installments' => [],
            ]);
        }

        $this->ensureLoanInstallmentSchedule($loanFile);
        $loanFile->load(['installments.recordedByAdmin', 'installments.payments.recordedByAdmin']);

        $profit = $this->calculateLoanProfitToman(
            (int) $loanFile->amount_toman,
            (float) $loanFile->effective_interest_rate,
            (string) ($loanFile->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $loanFile->installments_count,
            (int) $loanFile->installment_interval_count,
            (string) $loanFile->installment_interval_unit
        );
        $totalRepayable = max(0, ((int) $loanFile->amount_toman + $profit) - (int) $loanFile->down_payment_toman);
        $snap = $this->loanInstallmentFinancialSnapshot($loanFile, $totalRepayable);

        $installmentIds = $loanFile->installments
            ->map(static fn (CustomerLoanInstallment $i): int => (int) $i->id)
            ->all();
        $smsStatsByInstallment = $this->installmentSmsStatsForInstallmentIds($installmentIds);

        $rows = $loanFile->installments->map(
            fn (CustomerLoanInstallment $i): array => array_merge(
                $this->mapLoanInstallmentRow($i, $customer, $loanFile),
                [
                    'sms_stats' => $smsStatsByInstallment[(int) $i->id] ?? $this->emptyInstallmentSmsStats(),
                ],
            )
        )->values();

        return response()->json([
            'loan' => array_merge([
                'id' => (int) $loanFile->id,
                'loan_code' => (string) $loanFile->loan_code,
                'loan_type_title' => (string) ($loanFile->loanType?->title ?? '—'),
                'amount_toman' => (int) $loanFile->amount_toman,
                'loan_start_jdate' => $loanFile->loan_start_date
                    ? Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d')
                    : '',
                'loan_start_jdate_fa' => $loanFile->loan_start_date
                    ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d'))
                    : '',
                'installment_amount_toman' => (int) $loanFile->installment_amount_toman,
                'installments_count' => (int) $loanFile->installments_count,
                'is_settled' => (bool) $loanFile->is_settled,
                'schedule_remaining_toman' => (int) $snap['schedule_remaining_toman'],
            ], $this->loanInstallmentsModalSummary($loanFile, $snap)),
            'installments' => $rows,
        ]);
    }

    /**
     * نمایش یک‌صفحهٔ قابل چاپ (A4، برای باز شدن در پنجرهٔ جدید) برای دفترچهٔ اقساط پرونده.
     */
    public function loanInstallmentBookletPrint(Customer $customer, CustomerLoanFile $loanFile): View
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $this->ensureLoanInstallmentSchedule($loanFile);
        $loanFile->load([
            'loanType',
            'installments' => static function ($q): void {
                $q->orderBy('sequence');
            },
            'installments.payments' => static function ($q): void {
                $q->orderBy('id');
            },
        ]);

        $lateCoef = (float) ($loanFile->loanType?->daily_late_coefficient ?? 0);

        $bookletRows = [];
        foreach ($loanFile->installments as $inst) {
            $bookletRows[] = $this->buildInstallmentBookletPrintRow($inst, $lateCoef);
        }

        $loanTypeTitle = (string) ($loanFile->loanType?->title ?? '—');
        $fileSummaryLine = Jalali::enToFaNumbers((string) $loanFile->loan_code).' — '.$loanTypeTitle;

        $contractStartFa = '—';
        if ($loanFile->loan_start_date !== null) {
            $contractStartFa = Jalali::enToFaNumbers(
                Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d')
            );
        }

        $uiFontRaw = AppSetting::query()->where('key', 'app_ui_font')->value('value');
        $appUiFont = is_string($uiFontRaw) && in_array($uiFontRaw, ['iransans', 'iranyekan', 'anjoman', 'estedad'], true)
            ? $uiFontRaw
            : 'iransans';

        $printSettings = InstallmentBookletPrintSettings::resolved();
        $appLogoPath = AppSetting::query()->where('key', 'app_logo_path')->value('value');
        $appLogoPath = is_string($appLogoPath) ? trim($appLogoPath) : '';

        $visibleColumns = [];
        foreach (InstallmentBookletPrintSettings::orderedColumnKeys() as $columnKey) {
            $column = $printSettings['columns'][$columnKey] ?? null;
            if (! is_array($column) || ($column['show'] ?? '0') !== '1') {
                continue;
            }
            $visibleColumns[] = [
                'key' => $columnKey,
                'label' => (string) ($column['label'] ?? $columnKey),
            ];
        }

        return view('admin.customers.loan_installment_booklet_print', [
            'titleMain' => (string) ($printSettings['title_main'] ?? 'دفترچه اقساط'),
            'subtitleSales' => (string) ($printSettings['subtitle'] ?? 'فروش اقساطی'),
            'loanAmountLabel' => (string) ($printSettings['loan_amount_label'] ?? 'مبلغ وام'),
            'loanAmountDisplay' => $this->formatBookletMoneyFa((int) $loanFile->amount_toman),
            'showLoanAmount' => ($printSettings['show_loan_amount'] ?? '1') === '1',
            'showSummaryTable' => ($printSettings['show_summary_table'] ?? '1') === '1',
            'showDetailTable' => ($printSettings['show_detail_table'] ?? '1') === '1',
            'showPortalBlock' => ($printSettings['show_portal_block'] ?? '1') === '1',
            'showUsername' => ($printSettings['show_username'] ?? '1') === '1',
            'showPassword' => ($printSettings['show_password'] ?? '1') === '1',
            'showSignatures' => ($printSettings['show_signatures'] ?? '1') === '1',
            'portalIntroText' => (string) ($printSettings['portal_intro_text'] ?? ''),
            'usernameLabel' => (string) ($printSettings['username_label'] ?? 'نام کاربری:'),
            'passwordLabel' => (string) ($printSettings['password_label'] ?? 'رمز عبور:'),
            'sellerSignatureLabel' => (string) ($printSettings['seller_signature_label'] ?? 'امضا و اثر انگشت فروشنده'),
            'buyerSignatureLabel' => (string) ($printSettings['buyer_signature_label'] ?? 'امضا و اثر انگشت خریدار'),
            'borrowerFullName' => trim((string) $customer->fullName()),
            'borrowerTitleLine' => 'آقای / خانم '.trim((string) $customer->fullName()),
            'borrowerUsernameDisplay' => trim((string) ($customer->username ?? '')) !== ''
                ? Jalali::enToFaNumbers(trim((string) $customer->username))
                : '—',
            'borrowerPasswordDisplay' => $this->customerPrintPasswordDisplay($customer, $printSettings),
            'loanFileSummary' => $fileSummaryLine,
            'contractDateFa' => $contractStartFa,
            'installmentsCountDisplay' => Jalali::enToFaNumbers((string) (int) $loanFile->installments_count),
            'installmentAmountDisplay' => $this->formatBookletMoneyFa((int) $loanFile->installment_amount_toman),
            'bookletRows' => $bookletRows,
            'visibleColumns' => $visibleColumns,
            'portalUrl' => rtrim((string) url('/'), '/'),
            'printLogoUrl' => InstallmentBookletPrintSettings::logoUrl($printSettings, $appLogoPath !== '' ? $appLogoPath : null),
            'bodyFontSize' => InstallmentBookletPrintSettings::bodyFontSize($printSettings),
            'appUiFont' => $appUiFont,
            'loanRevoked' => $loanFile->revoked_at !== null,
        ]);
    }

    public function updateLoanInstallment(
        Request $request,
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ ویرایش قسط ممکن نیست.'], 422);
        }
        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ ویرایش قسط مجاز نیست.'], 422);
        }

        $validated = $request->validate([
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'due_jdate' => ['required', 'string', 'max:20'],
        ], [], [
            'amount_toman' => 'مبلغ قسط',
            'due_jdate' => 'تاریخ سررسید',
        ]);

        $amount = (int) $validated['amount_toman'];
        $paid = (int) $installment->paid_amount_toman;
        if ($amount < $paid) {
            return response()->json([
                'message' => 'مبلغ قسط نمی‌تواند از مجموع پرداخت‌های ثبت‌شده ('.number_format($paid, 0, '.', ',').' تومان) کمتر باشد.',
            ], 422);
        }

        $dueCarbon = $this->parseJalaliDate(trim((string) $validated['due_jdate']));
        if ($dueCarbon === null) {
            return response()->json(['message' => 'تاریخ سررسید معتبر نیست. فرمت صحیح: ۱۴۰۳/۰۶/۱۵'], 422);
        }

        $installment->amount_toman = $amount;
        $installment->due_date = $dueCarbon->startOfDay()->format('Y-m-d');
        $installment->recorded_by_label = 'مدیر صندوق';
        $installment->save();

        $installment->refresh();
        $installment->load('recordedByAdmin');

        return response()->json([
            'message' => 'قسط با موفقیت به‌روزرسانی شد.',
            'installment' => $this->mapLoanInstallmentRow($installment, $customer, $loanFile),
        ]);
    }

    public function loanInstallmentPayments(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است.'], 422);
        }

        $loanFile->load('loanType');
        $this->ensureLoanInstallmentSchedule($loanFile);
        $installment->refresh();
        $this->maybeBackfillLegacyInstallmentPayments($installment);
        $installment->load(['payments.recordedByAdmin']);

        $profit = $this->calculateLoanProfitToman(
            (int) $loanFile->amount_toman,
            (float) $loanFile->effective_interest_rate,
            (string) ($loanFile->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $loanFile->installments_count,
            (int) $loanFile->installment_interval_count,
            (string) $loanFile->installment_interval_unit
        );
        $totalRepayable = max(0, ((int) $loanFile->amount_toman + $profit) - (int) $loanFile->down_payment_toman);
        $loanFile->load('installments');
        $snap = $this->loanInstallmentFinancialSnapshot($loanFile, $totalRepayable);

        $labels = CustomerLoanInstallmentPayment::methodLabels();
        $options = [];
        foreach (CustomerLoanInstallmentPayment::creatablePaymentMethodKeys() as $key) {
            $options[] = ['value' => (string) $key, 'label' => $labels[$key]];
        }
        $editMethodOptions = [];
        foreach (CustomerLoanInstallmentPayment::methodKeys() as $key) {
            $editMethodOptions[] = ['value' => (string) $key, 'label' => $labels[$key]];
        }

        return response()->json([
            'loan' => [
                'id' => (int) $loanFile->id,
                'loan_code' => (string) $loanFile->loan_code,
                'loan_type_title' => (string) ($loanFile->loanType?->title ?? '—'),
                'amount_toman' => (int) $loanFile->amount_toman,
                'loan_start_jdate' => $loanFile->loan_start_date
                    ? Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d')
                    : '',
                'loan_start_jdate_fa' => $loanFile->loan_start_date
                    ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($loanFile->loan_start_date))->format('Y/m/d'))
                    : '',
                'installment_amount_toman' => (int) $loanFile->installment_amount_toman,
                'installments_count' => (int) $loanFile->installments_count,
                'is_settled' => (bool) $loanFile->is_settled,
                'schedule_remaining_toman' => (int) $snap['schedule_remaining_toman'],
            ],
            'installment' => $this->mapLoanInstallmentPayContext($installment, $customer, $loanFile),
            'payment_method_options' => $options,
            /** همهٔ کلیدها شامل «ثبت قبلی» برای ویرایش ردیف‌های مهاجرت‌یافته */
            'payment_method_edit_options' => $editMethodOptions,
            'payments' => $installment->payments
                ->map(fn (CustomerLoanInstallmentPayment $p): array => $this->mapInstallmentPaymentRow($p))
                ->values(),
        ]);
    }

    public function storeLoanInstallmentPayment(
        Request $request,
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ ثبت پرداخت ممکن نیست.'], 422);
        }
        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ ثبت پرداخت مجاز نیست.'], 422);
        }

        $adminId = auth('admin')->id();
        if ($adminId === null) {
            return response()->json(['message' => 'احراز هویت مدیر الزامی است.'], 401);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(CustomerLoanInstallmentPayment::creatablePaymentMethodKeys())],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reference_due_jdate' => ['nullable', 'string', 'max:20'],
            'deposited_jdate' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:5000'],
            'send_sms' => ['nullable', 'boolean'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
        ], [], [
            'payment_method' => 'نحوه پرداخت',
            'amount_toman' => 'مبلغ پرداختی',
            'reference_due_jdate' => 'تاریخ سررسید',
            'deposited_jdate' => 'تاریخ واریز',
            'note' => 'توضیحات',
        ]);

        $amountNew = (int) $validated['amount_toman'];
        $loanFile->loadMissing('loanType');
        $paymentCeiling = $this->loanInstallmentPaymentCeilingToman($loanFile);
        if ($amountNew > $paymentCeiling) {
            return response()->json([
                'message' => $paymentCeiling <= 0
                    ? 'طبق ماندهٔ وام و تخفیف ثبت‌شده، مبلغ دیگری قابل ثبت نیست.'
                    : ('جمع پرداخت‌ها نمی‌تواند از ماندهٔ وام بیشتر شود؛ حداکثر قابل ثبت در این مرحله: '
                        .number_format($paymentCeiling, 0, '.', ',').' تومان.'),
            ], 422);
        }

        $refDueCarbon = null;
        $rawRefDue = isset($validated['reference_due_jdate']) ? trim((string) $validated['reference_due_jdate']) : '';
        if ($rawRefDue !== '') {
            $refDueCarbon = $this->parseJalaliDate($rawRefDue);
            if ($refDueCarbon === null) {
                return response()->json(['message' => 'تاریخ سررسید معتبر نیست. فرمت: ۱۴۰۳/۰۶/۱۵'], 422);
            }
        }

        $depCarbon = $this->parseJalaliDate(trim((string) $validated['deposited_jdate']));
        if ($depCarbon === null) {
            return response()->json(['message' => 'تاریخ واریز معتبر نیست. فرمت: ۱۴۰۳/۰۶/۱۵'], 422);
        }

        $noteTrim = isset($validated['note']) ? trim((string) $validated['note']) : '';
        $noteStored = $noteTrim !== '' ? $noteTrim : null;

        $payment = DB::transaction(function () use ($installment, $validated, $refDueCarbon, $depCarbon, $adminId, $noteStored): CustomerLoanInstallmentPayment {
            $row = CustomerLoanInstallmentPayment::query()->create([
                'customer_loan_installment_id' => (int) $installment->id,
                'payment_method' => (string) $validated['payment_method'],
                'amount_toman' => (int) $validated['amount_toman'],
                'reference_due_date' => $refDueCarbon?->startOfDay()->format('Y-m-d'),
                'deposited_at' => $depCarbon->startOfDay()->format('Y-m-d'),
                'note' => $noteStored,
                'recorded_by_admin_id' => (int) $adminId,
            ]);
            $installment->refresh();
            $this->resyncInstallmentPaidTotalsFromPayments($installment);

            return $row->fresh(['recordedByAdmin']);
        });

        $installment->refresh();
        $installment->load(['recordedByAdmin']);
        $loanFile->refresh();
        $loanFile->load(['loanType', 'installments']);

        $smsFeedback = '';
        if ((bool) ($validated['send_sms'] ?? false)) {
            $smsText = trim((string) ($validated['sms_text'] ?? ''));
            if ($smsText === '' && isset($validated['sms_template_id'])) {
                $template = SmsTemplate::query()->find((int) $validated['sms_template_id']);
                if ($template !== null) {
                    $smsText = $this->renderTemplate(
                        $template->body,
                        $this->installmentPaymentRegisteredSmsTemplateVars($customer, $loanFile, $installment, $amountNew)
                    );
                }
            }
            if ($smsText === '') {
                $smsText = $this->defaultAdminInstallmentPaymentRegisteredSmsText($customer, $loanFile, $installment, $amountNew);
            }
            $smsResult = $this->rawSms->send($customer->mobile, $smsText, 'installment-payment-registered', [
                'installment_id' => (int) $installment->id,
                'loan_file_id' => (int) $loanFile->id,
                'customer_id' => (int) $customer->id,
                'payment_id' => (int) $payment->id,
                'automated' => false,
                'manual' => true,
            ]);
            $smsFeedback = ' '.$smsResult['message'];
        }

        return response()->json([
            'message' => 'پرداخت با موفقیت ثبت شد.'.$smsFeedback,
            'payment' => $this->mapInstallmentPaymentRow($payment),
            'installment' => $this->mapLoanInstallmentRow($installment->fresh(['recordedByAdmin']), $customer, $loanFile),
            'payments' => CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', (int) $installment->id)
                ->with('recordedByAdmin')
                ->orderBy('id')
                ->get()
                ->map(fn (CustomerLoanInstallmentPayment $p): array => $this->mapInstallmentPaymentRow($p))
                ->values(),
        ]);
    }

    public function updateLoanInstallmentPayment(
        Request $request,
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
        CustomerLoanInstallmentPayment $payment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ((int) $payment->customer_loan_installment_id !== (int) $installment->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ ویرایش پرداخت ممکن نیست.'], 422);
        }
        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ ویرایش پرداخت مجاز نیست.'], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(CustomerLoanInstallmentPayment::methodKeys())],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reference_due_jdate' => ['nullable', 'string', 'max:20'],
            'deposited_jdate' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'payment_method' => 'نحوه پرداخت',
            'amount_toman' => 'مبلغ پرداختی',
            'reference_due_jdate' => 'تاریخ سررسید',
            'deposited_jdate' => 'تاریخ واریز',
            'note' => 'توضیحات',
        ]);

        $amountNew = (int) $validated['amount_toman'];
        $loanFile->loadMissing('loanType');
        $paymentCeiling = $this->loanInstallmentPaymentCeilingToman($loanFile) + (int) $payment->amount_toman;
        if ($amountNew > $paymentCeiling) {
            return response()->json([
                'message' => $paymentCeiling <= 0
                    ? 'طبق ماندهٔ وام و تخفیف ثبت‌شده، مبلغ مجاز نیست.'
                    : ('جمع پرداخت‌ها نمی‌تواند از ماندهٔ وام بیشتر شود؛ حداکثر قابل ثبت برای این ردیف: '
                        .number_format($paymentCeiling, 0, '.', ',').' تومان.'),
            ], 422);
        }

        $refDueCarbon = null;
        $rawRefDue = isset($validated['reference_due_jdate']) ? trim((string) $validated['reference_due_jdate']) : '';
        if ($rawRefDue !== '') {
            $refDueCarbon = $this->parseJalaliDate($rawRefDue);
            if ($refDueCarbon === null) {
                return response()->json(['message' => 'تاریخ سررسید معتبر نیست. فرمت: ۱۴۰۳/۰۶/۱۵'], 422);
            }
        }

        $depCarbon = $this->parseJalaliDate(trim((string) $validated['deposited_jdate']));
        if ($depCarbon === null) {
            return response()->json(['message' => 'تاریخ واریز معتبر نیست. فرمت: ۱۴۰۳/۰۶/۱۵'], 422);
        }

        $noteTrim = isset($validated['note']) ? trim((string) $validated['note']) : '';
        $noteStored = $noteTrim !== '' ? $noteTrim : null;

        DB::transaction(function () use ($payment, $validated, $refDueCarbon, $depCarbon, $noteStored, $installment): void {
            $payment->payment_method = (string) $validated['payment_method'];
            $payment->amount_toman = (int) $validated['amount_toman'];
            $payment->reference_due_date = $refDueCarbon?->startOfDay()->format('Y-m-d');
            $payment->deposited_at = $depCarbon->startOfDay()->format('Y-m-d');
            $payment->note = $noteStored;
            $payment->save();
            $installment->refresh();
            $this->resyncInstallmentPaidTotalsFromPayments($installment);
        });

        $payment->refresh(['recordedByAdmin']);

        return response()->json([
            'message' => 'پرداخت با موفقیت به‌روزرسانی شد.',
            'payment' => $this->mapInstallmentPaymentRow($payment),
            'installment' => $this->mapLoanInstallmentRow($installment->fresh(['recordedByAdmin']), $customer, $loanFile),
            'payments' => CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', (int) $installment->id)
                ->with('recordedByAdmin')
                ->orderBy('id')
                ->get()
                ->map(fn (CustomerLoanInstallmentPayment $p): array => $this->mapInstallmentPaymentRow($p))
                ->values(),
        ]);
    }

    public function destroyLoanInstallmentPayment(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
        CustomerLoanInstallmentPayment $payment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ((int) $payment->customer_loan_installment_id !== (int) $installment->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ حذف پرداخت ممکن نیست.'], 422);
        }
        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ حذف پرداخت مجاز نیست.'], 422);
        }

        DB::transaction(function () use ($payment, $installment): void {
            $payment->delete();
            $installment->refresh();
            $this->resyncInstallmentPaidTotalsFromPayments($installment);
        });

        return response()->json([
            'message' => 'پرداخت حذف شد.',
            'installment' => $this->mapLoanInstallmentRow($installment->fresh(['recordedByAdmin']), $customer, $loanFile),
            'payments' => CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', (int) $installment->id)
                ->with('recordedByAdmin')
                ->orderBy('id')
                ->get()
                ->map(fn (CustomerLoanInstallmentPayment $p): array => $this->mapInstallmentPaymentRow($p))
                ->values(),
        ]);
    }

    /**
     * حذف تمام ردیف‌های واریزی ثبت‌شده برای این قسط؛ خود ردیف قسط در برنامهٔ اقساط باقی می‌ماند.
     */
    public function destroyAllLoanInstallmentPayments(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $installment,
    ): JsonResponse {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }
        if ((int) $installment->customer_loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ حذف پرداخت ممکن نیست.'], 422);
        }
        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ حذف پرداخت مجاز نیست.'], 422);
        }

        $deleted = 0;
        DB::transaction(function () use ($installment, &$deleted): void {
            $deleted = (int) CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', (int) $installment->id)
                ->delete();
            $installment->refresh();
            $this->resyncInstallmentPaidTotalsFromPayments($installment);
        });

        return response()->json([
            'message' => $deleted > 0
                ? 'تمام واریزی‌های ثبت‌شده برای این قسط حذف شد؛ خود قسط حذف نشده است.'
                : 'واریزی‌ای برای این قسط ثبت نشده بود.',
            'deleted_count' => $deleted,
            'installment' => $this->mapLoanInstallmentRow($installment->fresh(['recordedByAdmin']), $customer, $loanFile),
            'payments' => CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', (int) $installment->id)
                ->with('recordedByAdmin')
                ->orderBy('id')
                ->get()
                ->map(fn (CustomerLoanInstallmentPayment $p): array => $this->mapInstallmentPaymentRow($p))
                ->values(),
        ]);
    }

    /**
     * پیش‌نمایش «تسویه آنی» بر اساس مانده اقساط، پرداخت‌ها، ضرایب نوع وام و روش محاسبه بهره.
     */
    public function loanInstantSettlementPreview(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $loanFile->load('loanType');

        return response()->json($this->buildInstantSettlementPreview($loanFile));
    }

    public function loanDiscountPreview(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است؛ ثبت تخفیف ممکن نیست.'], 422);
        }

        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است؛ ثبت تخفیف مجاز نیست.'], 422);
        }

        $loanFile->load('loanType');
        $this->ensureLoanInstallmentSchedule($loanFile);
        $loanFile->load('installments');

        $profit = $this->calculateLoanProfitToman(
            (int) $loanFile->amount_toman,
            (float) $loanFile->effective_interest_rate,
            (string) ($loanFile->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $loanFile->installments_count,
            (int) $loanFile->installment_interval_count,
            (string) $loanFile->installment_interval_unit
        );
        $totalRepayable = max(0, ((int) $loanFile->amount_toman + $profit) - (int) $loanFile->down_payment_toman);
        $snap = $this->loanInstallmentFinancialSnapshot($loanFile, $totalRepayable);
        $discount = (int) ($loanFile->discount_amount_toman ?? 0);
        $scheduleRemaining = $snap['schedule_remaining_toman'];

        return response()->json([
            'late_fee_so_far_toman' => $snap['late_fee_so_far_toman'],
            'schedule_remaining_toman' => $scheduleRemaining,
            'discount_registered_toman' => $discount,
            'remaining_after_discount_toman' => max(0, $scheduleRemaining - $discount),
            /** سقف مبلغ کل تخفیف قابل ثبت = مانده قسطی (قبل از اعمال تخفیف). */
            'max_discount_toman' => $scheduleRemaining,
            'loan_code' => (string) $loanFile->loan_code,
            'borrower_name' => $customer->fullName(),
        ]);
    }

    public function storeLoanDiscount(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($loanFile->revoked_at !== null) {
            return response()->json(['message' => 'قرارداد فسخ شده است.'], 422);
        }

        if ($loanFile->is_settled) {
            return response()->json(['message' => 'پرونده تسویه‌شده است.'], 422);
        }

        $validated = $request->validate([
            'discount_toman' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $newTotalDiscount = (int) $validated['discount_toman'];
        $adminId = auth('admin')->id();
        if ($adminId === null) {
            return response()->json(['message' => 'احراز هویت مدیر الزامی است.'], 401);
        }

        try {
            DB::transaction(function () use ($loanFile, $newTotalDiscount, $adminId): void {
                $loanFile->refresh();
                $loanFile->load('loanType');
                $this->ensureLoanInstallmentSchedule($loanFile);
                $loanFile->load('installments');

                $profit = $this->calculateLoanProfitToman(
                    (int) $loanFile->amount_toman,
                    (float) $loanFile->effective_interest_rate,
                    (string) ($loanFile->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
                    (int) $loanFile->installments_count,
                    (int) $loanFile->installment_interval_count,
                    (string) $loanFile->installment_interval_unit
                );
                $totalRepayable = max(0, ((int) $loanFile->amount_toman + $profit) - (int) $loanFile->down_payment_toman);
                $snap = $this->loanInstallmentFinancialSnapshot($loanFile, $totalRepayable);
                $scheduleRemaining = $snap['schedule_remaining_toman'];

                if ($newTotalDiscount > $scheduleRemaining) {
                    throw ValidationException::withMessages([
                        'discount_toman' => [
                            'مبلغ کل تخفیف نمی‌تواند از مانده قسطی بیشتر باشد. حداکثر: '.number_format($scheduleRemaining, 0, '.', ',').' تومان',
                        ],
                    ]);
                }

                $loanFile->update([
                    'discount_amount_toman' => $newTotalDiscount,
                    'discount_updated_at' => now(),
                    'discount_updated_by_admin_id' => (int) $adminId,
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => (string) (collect($e->errors())->flatten()->first() ?? 'اعتبارسنجی ناموفق بود.'),
                'errors' => $e->errors(),
            ], 422);
        }

        $loanFile->refresh()->load(['loanType', 'installments']);

        return response()->json([
            'message' => 'مبلغ تخفیف با موفقیت ذخیره شد.',
            'loan_file' => $this->mapLoanFile($loanFile),
        ]);
    }

    public function loanGuarantees(Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $rows = CustomerLoanGuarantee::query()
            ->where('loan_file_id', $loanFile->id)
            ->latest('id')
            ->get()
            ->map(fn (CustomerLoanGuarantee $g): array => $this->mapLoanGuarantee($g))
            ->values();

        return response()->json([
            'guarantees' => $rows,
        ]);
    }

    public function loanGuaranteesReport(Customer $customer): JsonResponse
    {
        $customer->load([
            'loanFiles' => function ($q): void {
                $q->with(['loanType', 'guarantees' => function ($gq): void {
                    $gq->latest('id');
                }])->latest('id');
            },
        ]);

        $summary = [
            'total' => 0,
            'org_self' => 0,
            'org_other' => 0,
            'cheque' => 0,
            'gold' => 0,
            'other' => 0,
            'cheque_returned' => 0,
            'cheque_collected' => 0,
        ];

        $rows = [];
        foreach ($customer->loanFiles as $file) {
            foreach ($file->guarantees as $g) {
                $summary['total']++;
                $type = (string) $g->type;
                match ($type) {
                    CustomerLoanGuarantee::TYPE_ORG_SELF => $summary['org_self']++,
                    CustomerLoanGuarantee::TYPE_ORG_OTHER => $summary['org_other']++,
                    CustomerLoanGuarantee::TYPE_CHEQUE => $summary['cheque']++,
                    CustomerLoanGuarantee::TYPE_GOLD => $summary['gold']++,
                    CustomerLoanGuarantee::TYPE_OTHER => $summary['other']++,
                    default => null,
                };
                if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
                    $meta = is_array($g->meta) ? $g->meta : [];
                    if (! empty($meta['cheque_returned'])) {
                        $summary['cheque_returned']++;
                    }
                    if (! empty($meta['cheque_collected'])) {
                        $summary['cheque_collected']++;
                    }
                }

                $mapped = $this->mapLoanGuarantee($g);
                $rows[] = [
                    'loan_file_id' => (int) $file->id,
                    'loan_code' => (string) $file->loan_code,
                    'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
                    'customer_full_name' => $customer->fullName(),
                    'customer_national_id' => trim((string) ($customer->national_id ?? '')),
                    'customer_mobile' => trim((string) ($customer->mobile ?? '')),
                    'amount_toman' => (int) $file->amount_toman,
                    'installment_amount_toman' => (int) $file->installment_amount_toman,
                    'guarantee_type' => $type,
                    'guarantee_type_label' => (string) ($mapped['type_label'] ?? $g->type),
                    'guarantee_detail_lines' => $this->guaranteeReportMetaLines($g),
                ];
            }
        }

        return response()->json([
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function exportLoanGuaranteesReportExcel(Customer $customer): StreamedResponse
    {
        $customer->load([
            'loanFiles' => function ($q): void {
                $q->with(['loanType', 'guarantees' => function ($gq): void {
                    $gq->latest('id');
                }])->latest('id');
            },
        ]);

        $filename = 'guarantees-customer-'.$customer->id.'-'.now()->format('Ymd-His').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($customer): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }
            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, [
                'شماره وام و نوع وام',
                'نام و نام خانوادگی',
                'کد ملی',
                'موبایل',
                'مبلغ وام (تومان)',
                'مبلغ قسط (تومان)',
                'نوع ضمانت',
                'اطلاعات ضمانت',
            ]);
            foreach ($customer->loanFiles as $file) {
                foreach ($file->guarantees as $g) {
                    $mapped = $this->mapLoanGuarantee($g);
                    $loanInfo = (string) $file->loan_code.' — '.($file->loanType?->title ?? '—');
                    $detail = implode(' | ', $this->guaranteeReportMetaLines($g));
                    $this->writeExcelUnicodeRow($out, [
                        $loanInfo,
                        $customer->fullName(),
                        trim((string) ($customer->national_id ?? '')),
                        trim((string) ($customer->mobile ?? '')),
                        number_format((int) $file->amount_toman, 0, '.', ''),
                        number_format((int) $file->installment_amount_toman, 0, '.', ''),
                        (string) ($mapped['type_label'] ?? $g->type),
                        $detail,
                    ]);
                }
            }
            fclose($out);
        }, $filename, $headers);
    }

    public function exportCustomerSmsLogsExcel(Request $request, Customer $customer): StreamedResponse
    {
        $rawDate = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
        $parsed = Carbon::createFromFormat('Y-m-d', $rawDate);
        $selectedDate = $parsed !== false ? $parsed->startOfDay() : Carbon::today()->startOfDay();

        $filename = 'sms-customer-'.$customer->id.'-'.$selectedDate->format('Y-m-d').'.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($customer, $selectedDate): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }
            fwrite($out, "\xFF\xFE");
            $this->writeExcelUnicodeRow($out, [
                'پنل پیامک',
                'وضعیت',
                'زمان ارسال',
                'متن پیام',
                'دریافت کننده',
                'نوع پیامک',
                'هزینه',
            ]);
            $rows = $this->customerSmsLogsCollection($customer, $selectedDate);
            foreach ($rows as $log) {
                $sentAt = $log->sent_at ? Jalali::instance($log->sent_at)->format('Y/m/d H:i') : '';
                $sentAtFa = $sentAt !== '' ? Jalali::enToFaNumbers($sentAt) : '—';
                $this->writeExcelUnicodeRow($out, [
                    (string) ($log->sms_panel ?? ''),
                    $log->statusLabel(),
                    $sentAtFa,
                    (string) ($log->message_text ?? ''),
                    Jalali::enToFaNumbers((string) ($log->recipient ?? '')),
                    $this->smsTypeLabelFa((string) ($log->type ?? '')),
                    number_format((float) $log->cost, 0, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }

    public function customerSmsLogs(Request $request, Customer $customer): JsonResponse
    {
        $rawDate = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
        $parsed = Carbon::createFromFormat('Y-m-d', $rawDate);
        $selectedDate = $parsed !== false ? $parsed->startOfDay() : Carbon::today()->startOfDay();

        $canonicalMobile = $this->normalizeCustomerMobileForSmsFilter((string) ($customer->mobile ?? ''));
        if ($canonicalMobile === '') {
            return response()->json([
                'date' => $selectedDate->format('Y-m-d'),
                'date_jalali' => Jalali::instance($selectedDate)->format('Y/m/d'),
                'date_jalali_fa' => Jalali::enToFaNumbers(Jalali::instance($selectedDate)->format('Y/m/d')),
                'prev_date' => $selectedDate->copy()->subDay()->format('Y-m-d'),
                'next_date' => $selectedDate->copy()->addDay()->format('Y-m-d'),
                'has_mobile' => false,
                'logs' => [],
            ]);
        }

        $logs = $this->customerSmsLogsCollection($customer, $selectedDate)
            ->map(fn (SmsLog $log): array => $this->mapSmsLogRow($log))
            ->values();

        return response()->json([
            'date' => $selectedDate->format('Y-m-d'),
            'date_jalali' => Jalali::instance($selectedDate)->format('Y/m/d'),
            'date_jalali_fa' => Jalali::enToFaNumbers(Jalali::instance($selectedDate)->format('Y/m/d')),
            'prev_date' => $selectedDate->copy()->subDay()->format('Y-m-d'),
            'next_date' => $selectedDate->copy()->addDay()->format('Y-m-d'),
            'has_mobile' => true,
            'logs' => $logs->all(),
        ]);
    }

    /**
     * @return Collection<int, SmsLog>
     */
    private function customerSmsLogsCollection(Customer $customer, Carbon $day): Collection
    {
        $canonicalMobile = $this->normalizeCustomerMobileForSmsFilter((string) ($customer->mobile ?? ''));
        if ($canonicalMobile === '') {
            return collect();
        }
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $variants = $this->smsRecipientFilterVariants($canonicalMobile);

        return SmsLog::query()
            ->whereBetween('sent_at', [$from, $to])
            ->whereIn('recipient', $variants)
            ->latest('sent_at')
            ->get();
    }

    private function smsTypeLabelFa(string $type): string
    {
        return match ($type) {
            'customer-credentials' => 'ارسال نام کاربری و رمز عبور',
            'loan-file-created' => 'ثبت پرونده وام',
            'installment-payment-registered' => 'ثبت واریز قسط',
            'wallet-charge-link' => 'لینک شارژ کیف پول',
            'welcome-message' => 'پیام خوش‌آمدگویی',
            'guarantor-otp' => 'کد تأیید ضامن',
            'panel-test' => 'تست پنل پیامک',
            'wallet-transaction' => 'ارتباط با تراکنش کیف پول',
            default => $type !== '' ? $type : 'نامشخص',
        };
    }

    /**
     * @param  resource  $out
     * @param  array<int, string>  $cells
     */
    private function writeExcelUnicodeRow($out, array $cells): void
    {
        $cleanCells = array_map(static function (string $value): string {
            return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], $value);
        }, $cells);

        $line = implode("\t", $cleanCells)."\r\n";
        fwrite($out, mb_convert_encoding($line, 'UTF-16LE', 'UTF-8'));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSmsLogRow(SmsLog $log): array
    {
        $sentAt = $log->sent_at ? Jalali::instance($log->sent_at)->format('Y/m/d H:i') : '';

        $typeRaw = (string) ($log->type ?? '');

        return [
            'id' => (int) $log->id,
            'sms_panel' => (string) ($log->sms_panel ?? ''),
            'status' => (string) $log->status,
            'status_label' => $log->statusLabel(),
            'sent_at_jalali_fa' => $sentAt !== '' ? Jalali::enToFaNumbers($sentAt) : '—',
            'message_text' => (string) ($log->message_text ?? ''),
            'recipient_fa' => Jalali::enToFaNumbers((string) ($log->recipient ?? '')),
            'type' => $typeRaw,
            'type_label_fa' => $this->smsTypeLabelFa($typeRaw),
            'cost_fa' => Jalali::enToFaNumbers(number_format((float) $log->cost, 0, '.', '')),
        ];
    }

    private function normalizeCustomerMobileForSmsFilter(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $this->toEnglishDigits(trim($raw))) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }
        if (! preg_match('/^09\d{9}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    private function smsRecipientFilterVariants(string $canonical11): array
    {
        if (! preg_match('/^09\d{9}$/', $canonical11)) {
            return [];
        }
        $wo = substr($canonical11, 1);

        return array_values(array_unique([
            $canonical11,
            $wo,
            '98'.$wo,
            '+98'.$wo,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function guaranteeReportMetaLines(CustomerLoanGuarantee $g): array
    {
        $meta = is_array($g->meta) ? $g->meta : [];
        $type = (string) $g->type;
        $lines = [];

        $desc = trim((string) ($g->description ?? ''));
        if ($desc !== '') {
            $lines[] = 'توضیح: '.$desc;
        }

        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $orgSelfLbl = (string) ($meta['organization_name'] ?? $meta['org_name'] ?? '');
            if ($orgSelfLbl !== '') {
                $lines[] = 'سازمان: '.$orgSelfLbl;
            }
            if (! empty($meta['employee_no'])) {
                $lines[] = 'شماره پرسنلی: '.trim((string) $meta['employee_no']);
            }
        }

        if ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $orgLbl = (string) ($meta['organization_name'] ?? $meta['org_name'] ?? '');
            if ($orgLbl !== '') {
                $lines[] = 'سازمان: '.$orgLbl;
            }
            if (! empty($meta['guarantor_name'])) {
                $lines[] = 'ضامن: '.trim((string) $meta['guarantor_name']);
            }
            if (! empty($meta['guarantor_employee_no'])) {
                $lines[] = 'شماره پرسنلی: '.trim((string) $meta['guarantor_employee_no']);
            }
            if (! empty($meta['guarantor_phone'])) {
                $lines[] = 'موبایل ضامن: '.trim((string) $meta['guarantor_phone']);
            }
            $lines[] = 'موبایل ضامن احراز شده: '.((bool) ($meta['guarantor_mobile_verified'] ?? false) ? 'بله' : 'خیر');
        }

        if (! empty($meta['org_name']) && $type !== CustomerLoanGuarantee::TYPE_ORG_OTHER && $type !== CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $lines[] = 'سازمان: '.trim((string) $meta['org_name']);
        }
        if (! empty($meta['guarantor_name']) && $type !== CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $lines[] = 'ضامن: '.trim((string) $meta['guarantor_name']);
        }

        if (! empty($meta['cheque_owner_name'])) {
            $lines[] = 'صاحب چک: '.trim((string) $meta['cheque_owner_name']);
        }
        if (! empty($meta['cheque_owner_mobile'])) {
            $lines[] = 'موبایل: '.trim((string) $meta['cheque_owner_mobile']);
        }
        if (! empty($meta['cheque_serial'])) {
            $lines[] = 'شماره چک: '.trim((string) $meta['cheque_serial']);
        }
        if (! empty($meta['cheque_sayadi'])) {
            $lines[] = 'صیادی: '.trim((string) $meta['cheque_sayadi']);
        }
        if (! empty($meta['cheque_due_jdate'])) {
            $lines[] = 'تاریخ چک: '.trim((string) $meta['cheque_due_jdate']);
        }
        if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            $lines[] = 'وصول شده؟ '.(! empty($meta['cheque_collected']) ? 'بله' : 'خیر');
            $lines[] = 'عودت شده؟ '.(! empty($meta['cheque_returned']) ? 'بله' : 'خیر');
        }
        if (in_array($type, [CustomerLoanGuarantee::TYPE_GOLD, CustomerLoanGuarantee::TYPE_OTHER], true)) {
            $lines[] = 'عودت شده؟ '.(! empty($meta['returned']) ? 'بله' : 'خیر');
        }

        $goldLabel = (string) ($meta['gold_item_label'] ?? $meta['gold_item_type'] ?? '');
        if ($goldLabel !== '') {
            $lines[] = 'نوع طلا: '.$goldLabel;
        }
        if (isset($meta['gold_weight_gram']) && $meta['gold_weight_gram'] !== '' && $meta['gold_weight_gram'] !== null) {
            $lines[] = 'وزن: '.trim((string) $meta['gold_weight_gram']).' گرم';
        }
        if (isset($meta['gold_quantity']) && $meta['gold_quantity'] !== '' && $meta['gold_quantity'] !== null) {
            $lines[] = 'تعداد: '.trim((string) $meta['gold_quantity']);
        }
        if (! empty($meta['gold_rate_toman'])) {
            $lines[] = 'نرخ: '.number_format((int) $meta['gold_rate_toman'], 0, '.', ',').' تومان';
        }

        $amt = isset($meta['amount_toman']) ? (int) $meta['amount_toman'] : 0;
        if ($amt > 0 && ($type === CustomerLoanGuarantee::TYPE_GOLD || $type === CustomerLoanGuarantee::TYPE_OTHER)) {
            $lines[] = 'مبلغ: '.number_format($amt, 0, '.', ',').' تومان';
        }

        return $lines;
    }

    /**
     * قوانین اعتبارسنجی ضمانت؛ فیلدهای مخصوص طلا فقط وقتی نوع «طلا» است اعمال می‌شوند
     * تا مقادیر ارسالی/پیش‌فرض از تب‌های دیگر باعث خطا نشود.
     *
     * @return array<string, array<int, mixed>>
     */
    private function loanGuaranteeValidationRules(Request $request, bool $forUpdate): array
    {
        $rules = [
            'type' => ['required', Rule::in([
                CustomerLoanGuarantee::TYPE_ORG_SELF,
                CustomerLoanGuarantee::TYPE_ORG_OTHER,
                CustomerLoanGuarantee::TYPE_CHEQUE,
                CustomerLoanGuarantee::TYPE_GOLD,
                CustomerLoanGuarantee::TYPE_OTHER,
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'org_name' => ['nullable', 'string', 'max:255'],
            'employee_no' => ['nullable', 'string', 'max:120'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_national_id' => ['nullable', 'string', 'max:20'],
            'guarantor_phone' => ['nullable', 'string', 'max:20'],
            'cheque_owner_name' => ['nullable', 'string', 'max:255'],
            'cheque_owner_national_id' => ['nullable', 'string', 'max:20'],
            'cheque_owner_mobile' => ['nullable', 'string', 'max:20'],
            'cheque_serial' => ['nullable', 'string', 'max:120'],
            'cheque_sayadi' => ['nullable', 'string', 'max:120'],
            'cheque_due_jdate' => ['nullable', 'string', 'max:20'],
            'cheque_collected' => ['nullable', 'boolean'],
            'cheque_returned' => ['nullable', 'boolean'],
            'guarantee_returned' => ['nullable', 'boolean'],
            'guarantee_return_verification_token' => ['nullable', 'string', 'max:128'],
            'return_document' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
            'amount_toman' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'attachment' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
        ];

        if ($forUpdate) {
            $rules['remove_attachment'] = ['nullable', 'boolean'];
        }

        if ((string) $request->input('type', '') === CustomerLoanGuarantee::TYPE_GOLD) {
            $rules['gold_item_code'] = ['required', Rule::in(CustomerLoanGuarantee::goldItemCodes())];
            $rules['gold_weight_gram'] = ['nullable', 'numeric', 'gt:0'];
            $rules['gold_quantity'] = ['nullable', 'integer', 'min:1'];
            $rules['gold_rate_toman'] = ['required', 'integer', 'min:1', 'max:999999999999'];
        }

        if ((string) $request->input('type', '') === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $rules['organization_id'] = ['required', 'integer', 'exists:organizations,id'];
        }

        if ((string) $request->input('type', '') === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $rules['organization_id'] = ['required', 'integer', 'exists:organizations,id'];
            $rules['guarantor_name'] = ['required', 'string', 'max:255'];
            $rules['guarantor_employee_no'] = ['nullable', 'string', 'max:120'];
            $rules['guarantor_verification_token'] = ['nullable', 'string', 'max:128'];
        }

        return $rules;
    }

    public function storeLoanGuarantee(Request $request, Customer $customer, CustomerLoanFile $loanFile): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $validated = $request->validate($this->loanGuaranteeValidationRules($request, false));

        $type = (string) $validated['type'];
        $description = trim((string) ($validated['description'] ?? ''));
        $amountToman = (int) ($validated['amount_toman'] ?? 0);

        $meta = [];
        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $built = $this->buildOrgSelfGuaranteeMeta($validated);
            if ($built instanceof JsonResponse) {
                return $built;
            }
            $meta = $built;
        } elseif ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $built = $this->buildOrgOtherGuaranteeMeta($request, $validated);
            if ($built instanceof JsonResponse) {
                return $built;
            }
            $meta = $built;
        } elseif ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            $chequeOwnerName = trim((string) ($validated['cheque_owner_name'] ?? ''));
            $chequeOwnerNationalId = IranNationalId::normalizeToDigits(trim((string) ($validated['cheque_owner_national_id'] ?? '')));
            $chequeOwnerMobile = $this->toEnglishDigits(trim((string) ($validated['cheque_owner_mobile'] ?? '')));
            $chequeSerial = trim((string) ($validated['cheque_serial'] ?? ''));
            $chequeSayadi = trim((string) ($validated['cheque_sayadi'] ?? ''));
            if ($chequeOwnerName === '') {
                return response()->json(['message' => 'نام و نام خانوادگی صاحب چک الزامی است.'], 422);
            }
            if (! preg_match('/^\d{10}$/', $chequeOwnerNationalId)) {
                return response()->json(['message' => 'کد ملی صاحب چک باید ۱۰ رقم باشد.'], 422);
            }
            if ($chequeOwnerMobile !== '' && ! preg_match('/^09\d{9}$/', $chequeOwnerMobile)) {
                return response()->json(['message' => 'شماره موبایل صاحب چک معتبر نیست.'], 422);
            }
            if ($chequeSerial === '') {
                return response()->json(['message' => 'شماره چک الزامی است.'], 422);
            }
            if ($chequeSayadi === '') {
                return response()->json(['message' => 'شماره صیادی الزامی است.'], 422);
            }
            $chequeDueDate = null;
            if (($validated['cheque_due_jdate'] ?? '') === '') {
                return response()->json(['message' => 'تاریخ چک الزامی است.'], 422);
            }
            $chequeDueDate = $this->parseJalaliDate((string) $validated['cheque_due_jdate']);
            if ($chequeDueDate === null) {
                return response()->json(['message' => 'تاریخ چک معتبر نیست.'], 422);
            }
            $chequeCollected = $request->boolean('cheque_collected');
            $meta = [
                'cheque_owner_name' => $chequeOwnerName,
                'cheque_owner_national_id' => $chequeOwnerNationalId,
                'cheque_owner_mobile' => $chequeOwnerMobile,
                'cheque_serial' => $chequeSerial,
                'cheque_sayadi' => $chequeSayadi,
                'cheque_due_jdate' => $chequeDueDate ? Jalali::instance($chequeDueDate)->format('Y/m/d') : '',
                'cheque_collected' => $chequeCollected,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_GOLD) {
            $meta = $this->buildGoldGuaranteeMeta($validated);
        } else {
            // سایر
            if ($description === '') {
                return response()->json(['message' => 'برای ضمانت نوع سایر، توضیحات الزامی است.'], 422);
            }
            $meta = [
                'amount_toman' => $amountToman,
            ];
        }

        $returnDocumentPath = null;
        $returnedAt = null;
        $returnedByAdminId = null;
        $returnError = $this->processGuaranteeReturnState(
            $request,
            $customer,
            $loanFile,
            $type,
            $meta,
            null,
            $returnDocumentPath,
            $returnedAt,
            $returnedByAdminId,
        );
        if ($returnError instanceof JsonResponse) {
            return $returnError;
        }

        $attachmentPath = null;
        $attachment = $request->file('attachment');
        if ($attachment instanceof UploadedFile && $attachment->isValid()) {
            $attachmentPath = $this->storeGuaranteeAttachment($attachment);
        }

        $guarantee = CustomerLoanGuarantee::query()->create([
            'customer_id' => $customer->id,
            'loan_file_id' => $loanFile->id,
            'type' => $type,
            'description' => $description !== '' ? $description : null,
            'meta' => $meta,
            'attachment_path' => $attachmentPath,
            'return_document_path' => $returnDocumentPath,
            'returned_at' => $returnedAt,
            'returned_by_admin_id' => $returnedByAdminId,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return response()->json([
            'message' => 'ضمانت با موفقیت ثبت شد.',
            'guarantee' => $this->mapLoanGuarantee($guarantee),
        ]);
    }

    public function updateLoanGuarantee(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }

        $validated = $request->validate($this->loanGuaranteeValidationRules($request, true));

        $type = (string) $validated['type'];
        $description = trim((string) ($validated['description'] ?? ''));
        $amountToman = (int) ($validated['amount_toman'] ?? 0);
        $meta = [];

        if ($type === CustomerLoanGuarantee::TYPE_ORG_SELF) {
            $built = $this->buildOrgSelfGuaranteeMeta($validated);
            if ($built instanceof JsonResponse) {
                return $built;
            }
            $meta = $built;
        } elseif ($type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $built = $this->buildOrgOtherGuaranteeMeta($request, $validated, $guarantee);
            if ($built instanceof JsonResponse) {
                return $built;
            }
            $meta = $built;
        } elseif ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            $chequeOwnerName = trim((string) ($validated['cheque_owner_name'] ?? ''));
            $chequeOwnerNationalId = IranNationalId::normalizeToDigits(trim((string) ($validated['cheque_owner_national_id'] ?? '')));
            $chequeOwnerMobile = $this->toEnglishDigits(trim((string) ($validated['cheque_owner_mobile'] ?? '')));
            $chequeSerial = trim((string) ($validated['cheque_serial'] ?? ''));
            $chequeSayadi = trim((string) ($validated['cheque_sayadi'] ?? ''));
            if ($chequeOwnerName === '') {
                return response()->json(['message' => 'نام و نام خانوادگی صاحب چک الزامی است.'], 422);
            }
            if (! preg_match('/^\d{10}$/', $chequeOwnerNationalId)) {
                return response()->json(['message' => 'کد ملی صاحب چک باید ۱۰ رقم باشد.'], 422);
            }
            if ($chequeOwnerMobile !== '' && ! preg_match('/^09\d{9}$/', $chequeOwnerMobile)) {
                return response()->json(['message' => 'شماره موبایل صاحب چک معتبر نیست.'], 422);
            }
            if ($chequeSerial === '') {
                return response()->json(['message' => 'شماره چک الزامی است.'], 422);
            }
            if ($chequeSayadi === '') {
                return response()->json(['message' => 'شماره صیادی الزامی است.'], 422);
            }
            $chequeDueDate = null;
            if (($validated['cheque_due_jdate'] ?? '') === '') {
                return response()->json(['message' => 'تاریخ چک الزامی است.'], 422);
            }
            $chequeDueDate = $this->parseJalaliDate((string) $validated['cheque_due_jdate']);
            if ($chequeDueDate === null) {
                return response()->json(['message' => 'تاریخ چک معتبر نیست.'], 422);
            }
            $chequeCollected = $request->boolean('cheque_collected');
            $meta = [
                'cheque_owner_name' => $chequeOwnerName,
                'cheque_owner_national_id' => $chequeOwnerNationalId,
                'cheque_owner_mobile' => $chequeOwnerMobile,
                'cheque_serial' => $chequeSerial,
                'cheque_sayadi' => $chequeSayadi,
                'cheque_due_jdate' => $chequeDueDate ? Jalali::instance($chequeDueDate)->format('Y/m/d') : '',
                'cheque_collected' => $chequeCollected,
            ];
        } elseif ($type === CustomerLoanGuarantee::TYPE_GOLD) {
            $meta = $this->buildGoldGuaranteeMeta($validated);
        } else {
            if ($description === '') {
                return response()->json(['message' => 'برای ضمانت نوع سایر، توضیحات الزامی است.'], 422);
            }
            $meta = [
                'amount_toman' => $amountToman,
            ];
        }

        $returnDocumentPath = $guarantee->return_document_path;
        $returnedAt = $guarantee->returned_at;
        $returnedByAdminId = $guarantee->returned_by_admin_id;
        $returnError = $this->processGuaranteeReturnState(
            $request,
            $customer,
            $loanFile,
            $type,
            $meta,
            $guarantee,
            $returnDocumentPath,
            $returnedAt,
            $returnedByAdminId,
        );
        if ($returnError instanceof JsonResponse) {
            return $returnError;
        }

        $removeAttachment = (bool) ($validated['remove_attachment'] ?? false);
        $newAttachment = $request->file('attachment');
        $attachmentPath = $guarantee->attachment_path;
        if ($removeAttachment && is_string($attachmentPath) && $attachmentPath !== '') {
            PrivateStoragePaths::delete($attachmentPath);
            $attachmentPath = null;
        }
        if ($newAttachment instanceof UploadedFile && $newAttachment->isValid()) {
            if (is_string($attachmentPath) && $attachmentPath !== '') {
                PrivateStoragePaths::delete($attachmentPath);
            }
            $attachmentPath = $this->storeGuaranteeAttachment($newAttachment);
        }

        $guarantee->update([
            'type' => $type,
            'description' => $description !== '' ? $description : null,
            'meta' => $meta,
            'attachment_path' => $attachmentPath,
            'return_document_path' => $returnDocumentPath,
            'returned_at' => $returnedAt,
            'returned_by_admin_id' => $returnedByAdminId,
        ]);
        $guarantee->refresh();

        return response()->json([
            'message' => 'ضمانت با موفقیت ویرایش شد.',
            'guarantee' => $this->mapLoanGuarantee($guarantee),
        ]);
    }

    public function destroyLoanGuarantee(Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee): JsonResponse
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }

        if (is_string($guarantee->attachment_path) && $guarantee->attachment_path !== '') {
            PrivateStoragePaths::delete($guarantee->attachment_path);
        }
        PrivateStoragePaths::delete((string) ($guarantee->return_document_path ?? ''));
        $guarantee->delete();

        return response()->json([
            'message' => 'ضمانت حذف شد.',
        ]);
    }

    public function loanGuaranteeAttachment(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee)
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        $attachmentPath = is_string($guarantee->attachment_path) ? trim($guarantee->attachment_path) : '';
        if ($attachmentPath === '') {
            abort(404);
        }

        $download = $request->boolean('download');
        $fileName = basename($attachmentPath);

        return $this->serveStoredPrivateFile($attachmentPath, $fileName, $download);
    }

    public function loanGuaranteeReturnDocument(Request $request, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanGuarantee $guarantee)
    {
        if ((int) $loanFile->customer_id !== (int) $customer->id || (int) $guarantee->loan_file_id !== (int) $loanFile->id) {
            abort(404);
        }
        $documentPath = is_string($guarantee->return_document_path) ? trim($guarantee->return_document_path) : '';
        if ($documentPath === '') {
            abort(404);
        }

        $download = $request->boolean('download');
        $fileName = basename($documentPath);

        return $this->serveStoredPrivateFile($documentPath, $fileName, $download);
    }

    public function sendQuickSms(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'sms_type' => ['required', 'in:wallet_link,welcome,installment_pre_due,installment_due,installment_overdue,installment_thanks'],
            'sms_text' => ['nullable', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'installment_id' => ['nullable', 'integer'],
        ]);

        $smsType = (string) $validated['sms_type'];
        $messageText = trim((string) ($validated['sms_text'] ?? ''));
        $templateId = $validated['sms_template_id'] ?? null;

        $installment = null;
        $loanFile = null;
        if (in_array($smsType, ['installment_pre_due', 'installment_due', 'installment_overdue', 'installment_thanks'], true)) {
            $iid = (int) ($validated['installment_id'] ?? 0);
            if ($iid < 1) {
                return response()->json(['message' => 'قسط معتبر را مشخص کنید.'], 422);
            }
            $installment = CustomerLoanInstallment::query()
                ->whereKey($iid)
                ->whereHas('loanFile', static fn ($q) => $q->where('customer_id', $customer->id))
                ->with('loanFile.loanType')
                ->first();
            if ($installment === null) {
                return response()->json(['message' => 'قسط یافت نشد.'], 422);
            }
            $loanFile = $installment->loanFile;
            if ($loanFile === null || (int) $loanFile->customer_id !== (int) $customer->id) {
                return response()->json(['message' => 'پرونده وام معتبر نیست.'], 422);
            }
        }

        if ($messageText === '' && $templateId !== null) {
            $tpl = SmsTemplate::query()->find((int) $templateId);
            if ($tpl !== null) {
                $vars = $installment !== null && $loanFile !== null
                    ? $this->installmentSmsTemplateVarsExtended($customer, $loanFile, $installment)
                    : [
                        'store_name' => $this->appDisplayName(),
                        'customer_name' => $customer->fullName(),
                        'payment_link' => '—',
                        'payment_link_variable' => '—',
                    ];
                $messageText = $this->renderTemplate($tpl->body, $vars);
            }
        }
        if ($messageText === '') {
            if ($installment !== null && $loanFile !== null) {
                $messageText = $this->defaultInstallmentSmsBody($smsType, $customer, $loanFile, $installment);
            } elseif ($smsType === 'wallet_link') {
                $messageText = 'سلام '.$customer->fullName().'، لینک شارژ کیف پول شما: —';
            } else {
                $messageText = 'سلام '.$customer->fullName().'، به سامانه '.$this->appDisplayName().' خوش آمدید.';
            }
        }

        $logType = match ($smsType) {
            'wallet_link' => 'wallet-charge-link',
            'welcome' => 'welcome-message',
            'installment_pre_due' => 'installment-pre-due',
            'installment_due' => 'installment-due',
            'installment_overdue' => 'installment-overdue',
            'installment_thanks' => 'installment-thanks',
            default => 'welcome-message',
        };

        $extraMeta = [];
        if ($installment !== null && $loanFile !== null) {
            $extraMeta = [
                'installment_id' => (int) $installment->id,
                'loan_file_id' => (int) $loanFile->id,
                'customer_id' => (int) $customer->id,
                'automated' => false,
                'manual' => true,
            ];
        }

        $result = $this->rawSms->send($customer->mobile, $messageText, $logType, $extraMeta);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * متن پیش‌فرض مدال ارسال سریع پیامک از قالب‌های انتخاب‌شده در «مدیریت پیامک» و قالب‌های سیستمی اقساط.
     *
     * Query: sms_type (اجباری)، installment_id برای انواع installment_*.
     */
    public function quickSmsModalPreview(Request $request, Customer $customer): JsonResponse
    {
        $smsType = (string) $request->query('sms_type', '');
        $allowed = [
            'wallet_link',
            'welcome',
            'installment_pre_due',
            'installment_due',
            'installment_overdue',
            'installment_thanks',
        ];
        if (! in_array($smsType, $allowed, true)) {
            return response()->json(['message' => 'نوع پیامک نامعتبر است.'], 422);
        }

        $installment = null;
        $loanFile = null;
        if (in_array($smsType, ['installment_pre_due', 'installment_due', 'installment_overdue', 'installment_thanks'], true)) {
            $iid = (int) $request->query('installment_id', 0);
            if ($iid < 1) {
                return response()->json(['message' => 'شناسه قسط الزامی است.'], 422);
            }
            $installment = CustomerLoanInstallment::query()
                ->whereKey($iid)
                ->whereHas('loanFile', static fn ($q) => $q->where('customer_id', $customer->id))
                ->with(['loanFile.loanType'])
                ->first();
            if ($installment === null) {
                return response()->json(['message' => 'قسط یافت نشد.'], 404);
            }
            $loanFile = $installment->loanFile;
            if ($loanFile === null) {
                abort(404);
            }
        }

        $payload = $this->composeQuickSmsModalContent($customer, $smsType, $installment, $loanFile);

        return response()->json($payload);
    }

    public function store(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $nationalId = trim((string) $request->input('national_id', ''));
        $postalCode = trim((string) $request->input('postal_code', ''));

        $request->merge([
            'father_name' => trim((string) $request->input('father_name', '')) !== ''
                ? trim((string) $request->input('father_name', ''))
                : null,
            'national_id' => $nationalId !== '' ? $nationalId : null,
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'mobile2' => $this->normalizeOptionalIranMobile(trim((string) $request->input('mobile2', ''))),
            'city' => trim((string) $request->input('city', '')) !== ''
                ? trim((string) $request->input('city', ''))
                : null,
            'address' => trim((string) $request->input('address', '')) !== ''
                ? trim((string) $request->input('address', ''))
                : null,
            'postal_code' => $postalCode !== '' ? $postalCode : null,
        ]);

        $validated = $request->validate([
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:10', Rule::unique('customers', 'national_id')],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')],
            'mobile2' => $this->mobile2ValidationRules(),
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'mobile2' => 'موبایل دوم',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.',
            ]);
        }

        $accounts = $this->validatedBankAccounts($request->input('accounts', []));
        $referrers = $this->validatedReferrers($request->input('referrers', []));

        $sendCredentials = $request->boolean('send_credentials');

        $customerCode = trim((string) ($validated['customer_code'] ?? ''));
        if ($customerCode === '') {
            $customerCode = $this->generateUniqueCustomerCode();
        }

        $plainPassword = (string) $validated['password'];

        $customer = DB::transaction(function () use (
            $validated,
            $customerCode,
            $username,
            $plainPassword,
            $membershipAt,
            $birthDate,
            $accounts,
            $referrers
        ): Customer {
            /** @var Customer $c */
            $c = Customer::query()->create([
                'customer_code' => $customerCode,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'] !== null && (string) $validated['father_name'] !== ''
                    ? (string) $validated['father_name']
                    : null,
                'national_id' => $validated['national_id'] !== null && (string) $validated['national_id'] !== ''
                    ? (string) $validated['national_id']
                    : null,
                'mobile' => $validated['mobile'],
                'mobile2' => $validated['mobile2'] !== null && (string) $validated['mobile2'] !== ''
                    ? (string) $validated['mobile2']
                    : null,
                'phone_landline' => $validated['phone_landline'] !== null && $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'password' => $plainPassword,
                'password_print_encrypted' => $this->customerPasswordPrintEncrypted($plainPassword),
                'city' => $validated['city'] !== null && (string) $validated['city'] !== ''
                    ? (string) $validated['city']
                    : null,
                'address' => $validated['address'] !== null && (string) $validated['address'] !== ''
                    ? (string) $validated['address']
                    : null,
                'postal_code' => $validated['postal_code'] !== null && (string) $validated['postal_code'] !== ''
                    ? (string) $validated['postal_code']
                    : null,
            ]);

            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $c->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $c->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }

            CustomerWallet::query()->create([
                'customer_id' => $c->id,
                'balance_toman' => 0,
                'is_locked' => false,
            ]);

            return $c;
        });

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10)
                .'رمز عبور: '.$plainPassword;
            $smsResult = $this->rawSms->send($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'مشتری با موفقیت ثبت شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', $flash);
    }

    /**
     * دادهٔ حداقلی برای باز کردن مودال «مدیریت وام‌ها» از صفحات دیگر (مثلاً درخواست وام).
     */
    public function loanManageModalContext(Customer $customer): JsonResponse
    {
        return response()->json([
            'id' => $customer->id,
            'name' => $customer->fullName(),
            'mobile' => (string) ($customer->mobile ?? ''),
        ]);
    }

    public function editData(Customer $customer): JsonResponse
    {
        $customer->load(['bankAccounts', 'referrers']);

        $membershipJ = '';
        if ($customer->membership_at !== null) {
            $membershipJ = Jalali::instance(Carbon::parse($customer->membership_at))->format('Y/m/d');
        }
        $birthJ = '';
        if ($customer->birth_date !== null) {
            $birthJ = Jalali::instance(Carbon::parse($customer->birth_date))->format('Y/m/d');
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'father_name' => $customer->father_name,
                'national_id' => $customer->national_id,
                'mobile' => $customer->mobile,
                'mobile2' => (string) ($customer->mobile2 ?? ''),
                'phone_landline' => (string) ($customer->phone_landline ?? ''),
                'membership_jdate' => $membershipJ,
                'birth_jdate' => $birthJ,
                'email' => (string) ($customer->email ?? ''),
                'city' => $customer->city,
                'address' => $customer->address,
                'postal_code' => $customer->postal_code,
            ],
            'bank_accounts' => $customer->bankAccounts->map(static function (CustomerBankAccount $b): array {
                return [
                    'account_identifier' => $b->account_identifier,
                    'bank_name' => (string) ($b->bank_name ?? ''),
                    'branch_name' => (string) ($b->branch_name ?? ''),
                ];
            })->values(),
            'referrers' => $customer->referrers->map(static function (CustomerReferrer $r): array {
                return [
                    'first_name' => $r->first_name,
                    'last_name' => $r->last_name,
                    'phone' => $r->phone,
                ];
            })->values(),
        ]);
    }

    /**
     * همان شکل آرایٔهٔ پرونده‌های نقشهٔ مدیریت وام در صفحهٔ لیست مشتریان؛ برای به‌روز کردن کارت‌ها بدون رفرش کامل صفحه.
     */
    public function loanBoardSummary(Customer $customer): JsonResponse
    {
        $customer->load(['loanFiles.loanType', 'loanFiles.installments']);
        $loanFiles = $customer->loanFiles->map(fn (CustomerLoanFile $file): array => $this->mapLoanFile($file))->values();
        $loanTotalWithProfit = (int) $loanFiles->sum(static fn (array $row): int => (int) ($row['total_repayable_toman'] ?? 0));
        $remainingInstallments = (int) $loanFiles->sum(static fn (array $row): int => (int) ($row['remaining_amount_toman'] ?? 0));

        return response()->json([
            'loan_files' => $loanFiles->all(),
            'loan_count' => $loanFiles->count(),
            'loan_total_with_profit' => $loanTotalWithProfit,
            'loan_remaining_installments' => $remainingInstallments,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        if (trim((string) $request->input('email', '')) === '') {
            $request->merge(['email' => null]);
        }

        $nationalId = trim((string) $request->input('national_id', ''));
        $postalCode = trim((string) $request->input('postal_code', ''));

        $request->merge([
            'father_name' => trim((string) $request->input('father_name', '')) !== ''
                ? trim((string) $request->input('father_name', ''))
                : null,
            'national_id' => $nationalId !== '' ? $nationalId : null,
            'mobile' => $this->toEnglishDigits(trim((string) $request->input('mobile', ''))),
            'mobile2' => $this->normalizeOptionalIranMobile(trim((string) $request->input('mobile2', ''))),
            'city' => trim((string) $request->input('city', '')) !== ''
                ? trim((string) $request->input('city', ''))
                : null,
            'address' => trim((string) $request->input('address', '')) !== ''
                ? trim((string) $request->input('address', ''))
                : null,
            'postal_code' => $postalCode !== '' ? $postalCode : null,
        ]);

        $validator = Validator::make($request->all(), [
            'customer_code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'customer_code')->ignore($customer->id)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:10', Rule::unique('customers', 'national_id')->ignore($customer->id)],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')->ignore($customer->id)],
            'mobile2' => $this->mobile2ValidationRules($customer->id),
            'phone_landline' => ['nullable', 'string', 'max:32'],
            'membership_jdate' => ['nullable', 'string', 'max:20'],
            'birth_jdate' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ], [], [
            'customer_code' => 'کد مشتری',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'national_id' => 'کد ملی',
            'mobile' => 'موبایل',
            'mobile2' => 'موبایل دوم',
            'phone_landline' => 'تلفن ثابت',
            'membership_jdate' => 'تاریخ عضویت',
            'birth_jdate' => 'تاریخ تولد',
            'email' => 'ایمیل',
            'password' => 'کلمه عبور',
            'city' => 'شهر',
            'address' => 'آدرس',
            'postal_code' => 'کدپستی',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($validator)
                ->with('open_edit_customer_id', $customer->id);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $membershipAt = $this->parseJalaliDate($validated['membership_jdate'] ?? null);
        $birthDate = $this->parseJalaliDate($validated['birth_jdate'] ?? null);

        $username = $this->usernameFromMobile($validated['mobile']);
        if (Customer::query()->where('username', $username)->where('id', '!=', $customer->id)->exists()) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors(['mobile' => 'این شماره موبایل قبلاً به‌عنوان نام کاربری ثبت شده است.'])
                ->with('open_edit_customer_id', $customer->id);
        }

        try {
            $accounts = $this->validatedBankAccounts($request->input('accounts', []));
            $referrers = $this->validatedReferrers($request->input('referrers', []));
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.customers.index', $request->only('q'))
                ->withInput($request->except('password'))
                ->withErrors($e->errors())
                ->with('open_edit_customer_id', $customer->id);
        }

        $sendCredentials = $request->boolean('send_credentials');
        $plainPasswordInput = trim((string) ($validated['password'] ?? ''));

        DB::transaction(function () use ($validated, $customer, $username, $membershipAt, $birthDate, $accounts, $referrers, $plainPasswordInput): void {
            $data = [
                'customer_code' => trim((string) ($validated['customer_code'] ?? '')) !== ''
                    ? (string) $validated['customer_code']
                    : $customer->customer_code,
                'username' => $username,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'father_name' => $validated['father_name'] !== null && (string) $validated['father_name'] !== ''
                    ? (string) $validated['father_name']
                    : null,
                'national_id' => $validated['national_id'] !== null && (string) $validated['national_id'] !== ''
                    ? (string) $validated['national_id']
                    : null,
                'mobile' => $validated['mobile'],
                'mobile2' => $validated['mobile2'] !== null && (string) $validated['mobile2'] !== ''
                    ? (string) $validated['mobile2']
                    : null,
                'phone_landline' => $validated['phone_landline'] !== null && (string) $validated['phone_landline'] !== ''
                    ? trim((string) $validated['phone_landline'])
                    : null,
                'membership_at' => $membershipAt,
                'birth_date' => $birthDate,
                'email' => $validated['email'] !== null && $validated['email'] !== ''
                    ? (string) $validated['email']
                    : null,
                'city' => $validated['city'] !== null && (string) $validated['city'] !== ''
                    ? (string) $validated['city']
                    : null,
                'address' => $validated['address'] !== null && (string) $validated['address'] !== ''
                    ? (string) $validated['address']
                    : null,
                'postal_code' => $validated['postal_code'] !== null && (string) $validated['postal_code'] !== ''
                    ? (string) $validated['postal_code']
                    : null,
            ];

            if ($plainPasswordInput !== '') {
                $data['password'] = $plainPasswordInput;
                $data['password_print_encrypted'] = $this->customerPasswordPrintEncrypted($plainPasswordInput);
            }

            $customer->update($data);

            $customer->bankAccounts()->delete();
            foreach ($accounts as $i => $row) {
                CustomerBankAccount::query()->create([
                    'customer_id' => $customer->id,
                    'account_identifier' => $row['account_identifier'],
                    'bank_name' => $row['bank_name'],
                    'branch_name' => $row['branch_name'],
                    'sort_order' => $i,
                ]);
            }

            $customer->referrers()->delete();
            foreach ($referrers as $i => $row) {
                CustomerReferrer::query()->create([
                    'customer_id' => $customer->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'sort_order' => $i,
                ]);
            }
        });

        $customer->refresh();

        $smsMessage = '';
        if ($sendCredentials) {
            $msg = 'سامانه '.$this->appDisplayName().chr(10)
                .'نام کاربری: '.$customer->username.chr(10);
            if ($plainPasswordInput !== '') {
                $msg .= 'رمز عبور: '.$plainPasswordInput;
            } else {
                $msg .= 'رمز عبور تغییر نکرده است.';
            }
            $smsResult = $this->rawSms->send($customer->mobile, $msg);
            $smsMessage = $smsResult['message'];
            if ($smsResult['ok']) {
                $customer->credentials_sms_sent_at = now();
                $customer->save();
            }
        }

        $flash = 'اطلاعات مشتری با موفقیت به‌روزرسانی شد.';
        if ($sendCredentials) {
            $flash .= ' '.$smsMessage;
        }

        return redirect()
            ->route('admin.customers.index', $request->only('q'))
            ->with('flash_success', $flash);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('flash_success', 'مشتری با موفقیت حذف شد.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{account_identifier: string, bank_name: string|null, branch_name: string|null}>
     */
    private function validatedBankAccounts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $acc = $this->toEnglishDigits(trim((string) ($row['account_identifier'] ?? '')));
            $bank = trim((string) ($row['bank_name'] ?? ''));
            $branch = trim((string) ($row['branch_name'] ?? ''));
            if ($acc === '' && $bank === '' && $branch === '') {
                continue;
            }
            if ($acc === '') {
                throw ValidationException::withMessages([
                    'accounts' => 'برای هر ردیف شماره حساب، شماره کارت یا شبا را کامل کنید.',
                ]);
            }
            $out[] = [
                'account_identifier' => $acc,
                'bank_name' => $bank !== '' ? $bank : null,
                'branch_name' => $branch !== '' ? $branch : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{first_name: string, last_name: string, phone: string}>
     */
    private function validatedReferrers(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fn = trim((string) ($row['first_name'] ?? ''));
            $ln = trim((string) ($row['last_name'] ?? ''));
            $ph = $this->toEnglishDigits(trim((string) ($row['phone'] ?? '')));
            if ($fn === '' && $ln === '' && $ph === '') {
                continue;
            }
            if ($fn === '' || $ln === '' || $ph === '') {
                throw ValidationException::withMessages([
                    'referrers' => 'برای هر معرف، نام، نام خانوادگی و شماره تماس الزامی است.',
                ]);
            }
            if (! preg_match('/^09\d{9}$/', $ph)) {
                throw ValidationException::withMessages([
                    'referrers' => 'شماره تماس معرف باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
                ]);
            }
            $out[] = ['first_name' => $fn, 'last_name' => $ln, 'phone' => $ph];
        }

        return $out;
    }

    private function renderTemplate(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = preg_replace('/\{\{\s*'.preg_quote((string) $k, '/').'\s*\}\}/i', (string) $v, $out) ?? $out;
        }

        return trim($out);
    }

    private function appDisplayName(): string
    {
        $v = AppSetting::query()->where('key', 'app_display_name')->value('value');

        return is_string($v) && $v !== '' ? $v : (string) config('app.name');
    }

    private function usernameFromMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    private function generateUniqueCustomerCode(): string
    {
        do {
            $code = 'CUS-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (Customer::query()->where('customer_code', $code)->exists());

        return $code;
    }

    private function parseJalaliDate(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = $this->toEnglishDigits($value);

        try {
            $j = Jalali::parseFormat('Y/m/d', $value);
            $j->startDay();

            return Carbon::createFromTimestamp($j->getTimestamp(), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function toEnglishDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($ar, $en, str_replace($fa, $en, $value));
    }

    private function normalizeOptionalIranMobile(string $value): ?string
    {
        $value = $this->toEnglishDigits(trim($value));
        if ($value === '') {
            return null;
        }

        if (strlen($value) === 10 && str_starts_with($value, '9')) {
            $value = '0'.$value;
        }

        return $value;
    }

    /**
     * @return list<\Closure|string>
     */
    private function mobile2ValidationRules(?int $ignoreCustomerId = null): array
    {
        return [
            'nullable',
            'string',
            'max:20',
            'regex:/^09\d{9}$/',
            'different:mobile',
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreCustomerId): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                $query = Customer::query()
                    ->where(function ($q) use ($value): void {
                        $q->where('mobile', $value)->orWhere('mobile2', $value);
                    });

                if ($ignoreCustomerId !== null) {
                    $query->where('id', '!=', $ignoreCustomerId);
                }

                if ($query->exists()) {
                    $fail('این شماره موبایل قبلاً ثبت شده است.');
                }
            },
        ];
    }

    /** @var list<string> */
    private const CUSTOMER_LIST_SCOPES = ['all', 'active_loan', 'overdue_installment'];

    /** @var list<string> */
    private const CUSTOMER_LIST_SORT_COLUMNS = [
        'customer_code',
        'name',
        'loan_count',
        'loan_total',
        'loan_remaining',
        'wallet',
        'membership_at',
    ];

    /**
     * @return array{list_scope: string, disbursement_due_overdue: bool}
     */
    private function customerListFilterFromRequest(Request $request): array
    {
        $scope = (string) $request->query('list_scope', 'all');
        if ($request->boolean('has_overdue_installments')) {
            $scope = 'overdue_installment';
        }
        if (! in_array($scope, self::CUSTOMER_LIST_SCOPES, true)) {
            $scope = 'all';
        }

        return [
            'list_scope' => $scope,
            'disbursement_due_overdue' => $request->boolean('disbursement_due_overdue'),
        ];
    }

    /**
     * @return array{sort: ?string, dir: 'asc'|'desc'}
     */
    private function customerListSortFromRequest(Request $request): array
    {
        $sort = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, self::CUSTOMER_LIST_SORT_COLUMNS, true)) {
            return ['sort' => null, 'dir' => 'asc'];
        }

        return ['sort' => $sort, 'dir' => $dir];
    }

    /**
     * @param  array{list_scope: string, disbursement_due_overdue: bool}  $filters
     */
    private function applyCustomerListFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $today = now()->toDateString();

        if ($filters['list_scope'] === 'active_loan') {
            $query->whereHas('loanFiles', static function ($loanFileQuery): void {
                $loanFileQuery
                    ->whereNull('revoked_at')
                    ->where('is_settled', false);
            });
        }

        if ($filters['list_scope'] === 'overdue_installment') {
            $query->whereHas('loanFiles', static function ($loanFileQuery) use ($today): void {
                $loanFileQuery
                    ->whereNull('revoked_at')
                    ->where('is_settled', false)
                    ->whereHas('installments', static function ($installmentQuery) use ($today): void {
                        $installmentQuery
                            ->whereColumn('paid_amount_toman', '<', 'amount_toman')
                            ->whereDate('due_date', '<', $today);
                    });
            });
        }

        if ($filters['disbursement_due_overdue']) {
            $query->whereHas('loanFiles', static function ($loanFileQuery) use ($today): void {
                $loanFileQuery
                    ->whereNull('revoked_at')
                    ->where('is_settled', false)
                    ->whereNotNull('disbursement_due_date')
                    ->whereDate('disbursement_due_date', '<', $today);
            });
        }
    }

    /**
     * @param  array{sort: ?string, dir: 'asc'|'desc'}  $sort
     */
    private function applyCustomerListSort(\Illuminate\Database\Eloquent\Builder $query, array $sort): void
    {
        $column = $sort['sort'];
        $direction = $sort['dir'] === 'desc' ? 'desc' : 'asc';

        if ($column === null) {
            $query->latest('customers.id');

            return;
        }

        match ($column) {
            'customer_code' => $query->orderBy('customers.customer_code', $direction),
            'name' => $query
                ->orderBy('customers.first_name', $direction)
                ->orderBy('customers.last_name', $direction),
            'membership_at' => $query->orderBy('customers.membership_at', $direction),
            'loan_count' => $query
                ->withCount('loanFiles')
                ->orderBy('loan_files_count', $direction),
            'wallet' => $query->orderBy(
                CustomerWallet::query()
                    ->selectRaw('COALESCE(balance_toman, 0)')
                    ->whereColumn('customer_wallets.customer_id', 'customers.id')
                    ->limit(1),
                $direction
            ),
            'loan_total' => $query->orderBy(
                CustomerLoanFile::query()
                    ->selectRaw('COALESCE(SUM(amount_toman), 0)')
                    ->whereColumn('customer_loan_files.customer_id', 'customers.id'),
                $direction
            ),
            'loan_remaining' => $query->orderBy(
                CustomerLoanInstallment::query()
                    ->selectRaw('COALESCE(SUM(GREATEST(0, customer_loan_installments.amount_toman - customer_loan_installments.paid_amount_toman)), 0)')
                    ->join('customer_loan_files', 'customer_loan_files.id', '=', 'customer_loan_installments.customer_loan_file_id')
                    ->whereColumn('customer_loan_files.customer_id', 'customers.id')
                    ->whereNull('customer_loan_files.revoked_at')
                    ->where('customer_loan_files.is_settled', false),
                $direction
            ),
            default => $query->latest('customers.id'),
        };

        $query->orderBy('customers.id', 'desc');
    }

    /**
     * @param  array{list_scope: string, disbursement_due_overdue: bool}  $filters
     */
    private function customerListFilterLabel(array $filters): ?string
    {
        if ($filters['list_scope'] === 'overdue_installment') {
            return 'مشتریان دارای قسط سررسید گذشته / معوق';
        }
        if ($filters['list_scope'] === 'active_loan') {
            return 'مشتریان دارای وام فعال';
        }
        if ($filters['disbursement_due_overdue']) {
            return 'مشتریان دارای سررسید واریز به طرف حساب (گذشته از موعد)';
        }

        return null;
    }

    /**
     * @param  array{list_scope: string, disbursement_due_overdue: bool}  $filters
     * @param  array{sort: ?string, dir: 'asc'|'desc'}  $sort
     * @return array<string, int|string>
     */
    private function customerListFilterQueryParams(array $filters, array $sort = ['sort' => null, 'dir' => 'asc']): array
    {
        $params = [];
        if ($filters['list_scope'] !== 'all') {
            $params['list_scope'] = $filters['list_scope'];
        }
        if ($filters['disbursement_due_overdue']) {
            $params['disbursement_due_overdue'] = 1;
        }
        if ($sort['sort'] !== null) {
            $params['sort'] = $sort['sort'];
            $params['dir'] = $sort['dir'];
        }

        return $params;
    }

    /**
     * @return array{installment_id: int, loan_file_id: int, count: int}|null
     */
    private function resolvePrimaryOverdueInstallment(Customer $customer): ?array
    {
        $today = Carbon::today();
        $best = null;
        $count = 0;

        foreach ($customer->loanFiles as $file) {
            if ($file->revoked_at !== null || $file->is_settled) {
                continue;
            }

            foreach ($file->installments as $installment) {
                if ((int) $installment->paid_amount_toman >= (int) $installment->amount_toman) {
                    continue;
                }

                $due = Carbon::parse($installment->due_date)->startOfDay();
                if ($due->gte($today)) {
                    continue;
                }

                $count++;
                if ($best === null || $due->lt($best['due'])) {
                    $best = [
                        'installment_id' => (int) $installment->id,
                        'loan_file_id' => (int) $file->id,
                        'due' => $due,
                    ];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'installment_id' => $best['installment_id'],
            'loan_file_id' => $best['loan_file_id'],
            'count' => $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanFile(CustomerLoanFile $file): array
    {
        $file->loadMissing(['loanType', 'installments']);

        $profit = $this->calculateLoanProfitToman(
            (int) $file->amount_toman,
            (float) $file->effective_interest_rate,
            (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            (int) $file->installments_count,
            (int) $file->installment_interval_count,
            (string) $file->installment_interval_unit
        );
        $totalRepayable = ((int) $file->amount_toman + $profit) - (int) $file->down_payment_toman;
        $totalRepayable = max(0, $totalRepayable);
        $isRevoked = $file->revoked_at !== null;
        $discount = (int) ($file->discount_amount_toman ?? 0);

        if ($isRevoked) {
            return [
                'id' => $file->id,
                'loan_code' => (string) $file->loan_code,
                'loan_type_id' => (int) $file->loan_type_id,
                'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
                'loan_start_jdate' => $file->loan_start_date ? Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d') : '',
                'disbursement_due_jdate' => $file->disbursement_due_date ? Jalali::instance(Carbon::parse($file->disbursement_due_date))->format('Y/m/d') : '',
                'amount_toman' => (int) $file->amount_toman,
                'installments_count' => (int) $file->installments_count,
                'installment_interval_count' => (int) $file->installment_interval_count,
                'installment_interval_unit' => (string) $file->installment_interval_unit,
                'installment_amount_toman' => (int) $file->installment_amount_toman,
                'down_payment_toman' => (int) $file->down_payment_toman,
                'profit_calculation_method' => (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
                'sub_file_number' => (string) ($file->sub_file_number ?? ''),
                'description' => (string) ($file->description ?? ''),
                'is_settled' => (bool) $file->is_settled,
                'settled_jdate' => $file->settled_at ? Jalali::instance(Carbon::parse($file->settled_at))->format('Y/m/d') : '',
                'is_revoked' => true,
                'revoked_jdate' => $file->revoked_at
                    ? Jalali::instance(Carbon::parse($file->revoked_at))->format('Y/m/d')
                    : '',
                'revoked_by_admin_id' => $file->revoked_by_admin_id !== null ? (int) $file->revoked_by_admin_id : null,
                'base_interest_rate' => (float) $file->base_interest_rate,
                'has_custom_interest_rate' => (bool) $file->has_custom_interest_rate,
                'custom_interest_rate' => $file->custom_interest_rate !== null ? (float) $file->custom_interest_rate : null,
                'effective_interest_rate' => (float) $file->effective_interest_rate,
                'calculated_profit_toman' => $profit,
                'total_repayable_toman' => $totalRepayable,
                'remaining_amount_toman' => 0,
                'paid_installments_count' => 0,
                'paid_installments_slot_count' => 0,
                'paid_installments_amount_toman' => 0,
                'discount_amount_toman' => 0,
                'late_fee_so_far_toman' => 0,
                'schedule_remaining_before_discount_toman' => 0,
            ];
        }

        $snap = $this->loanInstallmentFinancialSnapshot($file, $totalRepayable);
        $scheduleRemaining = $snap['schedule_remaining_toman'];
        $remainingAmount = $file->is_settled
            ? 0
            : max(0, $scheduleRemaining - $discount);

        return [
            'id' => $file->id,
            'loan_code' => (string) $file->loan_code,
            'loan_type_id' => (int) $file->loan_type_id,
            'loan_type_title' => (string) ($file->loanType?->title ?? '—'),
            'loan_start_jdate' => $file->loan_start_date ? Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d') : '',
            'disbursement_due_jdate' => $file->disbursement_due_date ? Jalali::instance(Carbon::parse($file->disbursement_due_date))->format('Y/m/d') : '',
            'amount_toman' => (int) $file->amount_toman,
            'installments_count' => (int) $file->installments_count,
            'installment_interval_count' => (int) $file->installment_interval_count,
            'installment_interval_unit' => (string) $file->installment_interval_unit,
            'installment_amount_toman' => (int) $file->installment_amount_toman,
            'down_payment_toman' => (int) $file->down_payment_toman,
            'profit_calculation_method' => (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY),
            'sub_file_number' => (string) ($file->sub_file_number ?? ''),
            'description' => (string) ($file->description ?? ''),
            'is_settled' => (bool) $file->is_settled,
            'settled_jdate' => $file->settled_at ? Jalali::instance(Carbon::parse($file->settled_at))->format('Y/m/d') : '',
            'is_revoked' => false,
            'revoked_jdate' => '',
            'revoked_by_admin_id' => null,
            'base_interest_rate' => (float) $file->base_interest_rate,
            'has_custom_interest_rate' => (bool) $file->has_custom_interest_rate,
            'custom_interest_rate' => $file->custom_interest_rate !== null ? (float) $file->custom_interest_rate : null,
            'effective_interest_rate' => (float) $file->effective_interest_rate,
            'calculated_profit_toman' => $profit,
            'total_repayable_toman' => $totalRepayable,
            'remaining_amount_toman' => $remainingAmount,
            'paid_installments_count' => $snap['paid_installments_count'],
            'paid_installments_slot_count' => $snap['paid_installments_slot_count'],
            'paid_installments_amount_toman' => $snap['total_paid_toman'],
            'discount_amount_toman' => $discount,
            'late_fee_so_far_toman' => $snap['late_fee_so_far_toman'],
            'schedule_remaining_before_discount_toman' => $scheduleRemaining,
        ];
    }

    /**
     * حداکثر مبلغی که هنوز می‌توان به‌صورت مجموع پرداخت‌های اقساط ثبت کرد (پس از کسر تخفیف)، بدون توجه به سهم نامی هر قسط.
     */
    private function loanInstallmentPaymentCeilingToman(CustomerLoanFile $file): int
    {
        return app(LoanFileFinanceCalculator::class)->installmentPaymentCeilingToman($file);
    }

    /**
     * ماندهٔ بازپرداخت قرارداد، پرداخت‌ها و برآورد دیرکرد تا امروز بر اساس ضریب دیرکرد روزانه نوع وام.
     * «مانده قسطی» بر مبنای مبلغ قابل بازپرداخت کل منهای جمع پرداخت‌ها است (پرداخت اضافه در یک قسط، ماندهٔ کل را کاهش می‌دهد).
     *
     * @return array{schedule_remaining_toman: int, total_paid_toman: int, paid_installments_count: int, paid_installments_slot_count: int, late_fee_so_far_toman: int}
     */
    private function loanInstallmentFinancialSnapshot(CustomerLoanFile $file, int $totalRepayableContract): array
    {
        $installments = $file->installments;
        $lateCoef = (float) ($file->loanType?->daily_late_coefficient ?? 0);
        $discount = (int) ($file->discount_amount_toman ?? 0);

        if ($installments->isEmpty()) {
            return [
                'schedule_remaining_toman' => $totalRepayableContract,
                'total_paid_toman' => 0,
                'paid_installments_count' => 0,
                'paid_installments_slot_count' => 0,
                'late_fee_so_far_toman' => 0,
            ];
        }

        $totalPaid = (int) $installments->sum(static fn (CustomerLoanInstallment $i): int => (int) $i->paid_amount_toman);
        $scheduleRemaining = max(0, $totalRepayableContract - $totalPaid);
        $remainingAfterDiscount = $file->is_settled
            ? 0
            : max(0, $scheduleRemaining - $discount);
        $slotFullyPaidCount = (int) $installments->filter(static function (CustomerLoanInstallment $i): bool {
            return (int) $i->amount_toman > 0 && (int) $i->paid_amount_toman >= (int) $i->amount_toman;
        })->count();
        $periodCount = $installments->count();
        /**
         * برای کارت پرونده و گزارش‌ها: اگر ماندهٔ واقعی تعهد (با تخفیف) صفر است، همهٔ دوره‌های قرارداد از نظر تعهد پوشش داده شده‌اند
         * حتی اگر مبلغ نامی بعضی اقساط هنوز کمتر پرداخت شده باشد (جابه‌جایی پرداخت بین اقساط).
         */
        $paidInstallmentsCountReport = $remainingAfterDiscount <= 0 && $periodCount > 0 ? $periodCount : $slotFullyPaidCount;

        return [
            'schedule_remaining_toman' => $scheduleRemaining,
            'total_paid_toman' => $totalPaid,
            'paid_installments_count' => $paidInstallmentsCountReport,
            'paid_installments_slot_count' => $slotFullyPaidCount,
            'late_fee_so_far_toman' => $this->estimateLateFeeSoFarToman($installments, $lateCoef, $remainingAfterDiscount),
        ];
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    private function estimateLateFeeSoFarToman(Collection $installments, float $dailyLateCoef, int $contractDebtRemainingAfterDiscount): int
    {
        if ($dailyLateCoef <= 0 || $contractDebtRemainingAfterDiscount <= 0) {
            return 0;
        }

        $now = Carbon::now()->startOfDay();
        $sum = 0;

        foreach ($installments as $i) {
            $unpaid = max(0, (int) $i->amount_toman - (int) $i->paid_amount_toman);
            if ($unpaid <= 0) {
                continue;
            }
            $due = Carbon::parse($i->due_date)->startOfDay();
            if ($due->gte($now)) {
                continue;
            }
            $days = (int) $due->diffInDays($now);
            if ($days < 1) {
                continue;
            }
            $sum += (int) round($unpaid * $dailyLateCoef * $days);
        }

        return max(0, $sum);
    }

    /**
     * خلاصهٔ مالی پرونده برای بالای مدال «اقساط و پرداخت».
     *
     * @param  array{schedule_remaining_toman: int, total_paid_toman: int, paid_installments_count: int, paid_installments_slot_count: int, late_fee_so_far_toman: int}|null  $snap
     * @return array{
     *     paid_installments_count: int,
     *     remaining_installments_count: int,
     *     remaining_amount_toman: int,
     *     paid_installments_amount_toman: int,
     *     late_penalty_toman: int,
     *     early_benefit_toman: int
     * }
     */
    private function loanInstallmentsModalSummary(CustomerLoanFile $loanFile, ?array $snap): array
    {
        if ($snap === null) {
            return [
                'paid_installments_count' => 0,
                'remaining_installments_count' => 0,
                'remaining_amount_toman' => 0,
                'paid_installments_amount_toman' => 0,
                'late_penalty_toman' => 0,
                'early_benefit_toman' => 0,
            ];
        }

        $discount = (int) ($loanFile->discount_amount_toman ?? 0);
        $remainingAmount = $loanFile->is_settled
            ? 0
            : max(0, (int) $snap['schedule_remaining_toman'] - $discount);
        $unpaidCount = (int) $loanFile->installments->filter(static function (CustomerLoanInstallment $i): bool {
            return (int) $i->paid_amount_toman < (int) $i->amount_toman;
        })->count();
        $lateCoef = (float) ($loanFile->loanType?->daily_late_coefficient ?? 0);
        $earlyCoef = (float) ($loanFile->loanType?->daily_early_coefficient ?? 0);

        return [
            'paid_installments_count' => (int) $snap['paid_installments_count'],
            'remaining_installments_count' => $unpaidCount,
            'remaining_amount_toman' => $remainingAmount,
            'paid_installments_amount_toman' => (int) $snap['total_paid_toman'],
            'late_penalty_toman' => $this->aggregateLatePenaltyToman($loanFile->installments, $lateCoef, $remainingAmount),
            'early_benefit_toman' => $this->aggregateEarlyBenefitToman($loanFile->installments, $earlyCoef),
        ];
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    private function aggregateLatePenaltyToman(Collection $installments, float $lateCoef, int $remainingAfterDiscount): int
    {
        if ($lateCoef <= 0) {
            return 0;
        }

        $finance = app(LoanFileFinanceCalculator::class);
        $now = Carbon::now()->startOfDay();
        $sum = 0;

        foreach ($installments as $inst) {
            $amount = (int) $inst->amount_toman;
            $paid = (int) $inst->paid_amount_toman;
            if ($amount <= 0) {
                continue;
            }

            $due = Carbon::parse($inst->due_date)->startOfDay();
            $paidAt = $inst->paid_at !== null ? Carbon::parse($inst->paid_at)->startOfDay() : null;
            $slotFullyPaid = $paid >= $amount;

            if ($slotFullyPaid && $paidAt !== null && $paidAt->gt($due)) {
                $sum += $finance->estimatePenaltyAtDateToman($inst, $lateCoef, $paidAt);
            } elseif (! $slotFullyPaid && $due->lt($now) && $remainingAfterDiscount > 0) {
                $sum += $finance->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);
            }
        }

        return max(0, $sum);
    }

    /**
     * @param  Collection<int, CustomerLoanInstallment>  $installments
     */
    private function aggregateEarlyBenefitToman(Collection $installments, float $earlyCoef): int
    {
        if ($earlyCoef <= 0) {
            return 0;
        }

        $sum = 0;

        foreach ($installments as $inst) {
            $amount = (int) $inst->amount_toman;
            $paid = (int) $inst->paid_amount_toman;
            if ($amount <= 0 || $paid < $amount || $inst->paid_at === null) {
                continue;
            }

            $due = Carbon::parse($inst->due_date)->startOfDay();
            $paidAt = Carbon::parse($inst->paid_at)->startOfDay();
            if ($paidAt->gte($due)) {
                continue;
            }

            $days = (int) $paidAt->diffInDays($due);
            if ($days < 1) {
                continue;
            }

            $sum += (int) round($amount * $earlyCoef * $days);
        }

        return max(0, $sum);
    }

    /** برآورد حق‌الزحمهٔ دیرکرد روزانه فقط برای بخش پرداخت‌نشدهٔ این قسط تا امروز (همان منطق فایل). */
    private function estimateBookletPenaltyTomanForInstallment(CustomerLoanInstallment $inst, float $dailyLateCoef): int
    {
        if ($dailyLateCoef <= 0) {
            return 0;
        }
        $unpaid = max(0, (int) $inst->amount_toman - (int) $inst->paid_amount_toman);
        if ($unpaid <= 0) {
            return 0;
        }
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $now = Carbon::now()->startOfDay();
        if ($due->gte($now)) {
            return 0;
        }
        $days = (int) $due->diffInDays($now);
        if ($days < 1) {
            return 0;
        }

        return max(0, (int) round($unpaid * $dailyLateCoef * $days));
    }

    /**
     * @return array<string, string>
     */
    private function buildInstallmentBookletPrintRow(CustomerLoanInstallment $inst, float $lateCoef): array
    {
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $dueFa = Jalali::enToFaNumbers(Jalali::instance($due)->format('Y/m/d'));
        $paidAtRoot = $inst->paid_at !== null ? Carbon::parse($inst->paid_at)->startOfDay() : null;

        $paySlices = [];
        foreach ($inst->payments as $pay) {
            $paySlices[] = [
                'amount' => (int) $pay->amount_toman,
                'method' => (string) $pay->payment_method,
                'deposited' => Carbon::parse($pay->deposited_at)->startOfDay(),
                'note' => trim((string) ($pay->note ?? '')),
            ];
        }
        if ($paySlices === [] && (int) $inst->paid_amount_toman > 0) {
            $paySlices[] = [
                'amount' => (int) $inst->paid_amount_toman,
                'method' => CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED,
                'deposited' => $inst->paid_at !== null
                    ? Carbon::parse($inst->paid_at)->startOfDay()
                    : Carbon::now()->startOfDay(),
                'note' => '',
            ];
        }

        $methodLabels = CustomerLoanInstallmentPayment::methodLabels();
        $amountLines = [];
        $dateLinesAll = [];
        $sumCash = 0;
        $sumBank = 0;
        $sumTerminal = 0;
        $sumOnline = 0;
        $sumGateway = 0;
        $noteParts = [];

        foreach ($paySlices as $slice) {
            $depFa = Jalali::enToFaNumbers(Jalali::instance($slice['deposited'])->format('Y/m/d'));
            $ml = $methodLabels[$slice['method']] ?? $slice['method'];
            $amt = $slice['amount'];
            $amountLines[] = $this->formatBookletMoneyFa($amt).' — '.$depFa."\n".'('.$ml.')';
            $dateLinesAll[] = $depFa;

            switch ($slice['method']) {
                case CustomerLoanInstallmentPayment::METHOD_CASH:
                    $sumCash += $amt;
                    break;
                case CustomerLoanInstallmentPayment::METHOD_BANK_TRANSFER:
                    $sumBank += $amt;
                    break;
                case CustomerLoanInstallmentPayment::METHOD_CARD_TERMINAL:
                    $sumTerminal += $amt;
                    break;
                case CustomerLoanInstallmentPayment::METHOD_ONLINE:
                case CustomerLoanInstallmentPayment::METHOD_WALLET:
                case CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET:
                    $sumOnline += $amt;
                    break;
                case CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE:
                    $sumGateway += $amt;
                    break;
                case CustomerLoanInstallmentPayment::METHOD_GOLD:
                    $noteParts[] = 'طلا: '.$this->formatBookletMoneyFa($amt).' ('.$depFa.')';
                    break;
                default:
                    $noteParts[] = $ml.': '.$this->formatBookletMoneyFa($amt).' ('.$depFa.')';
                    break;
            }
            if ($slice['note'] !== '') {
                $noteParts[] = $slice['note'];
            }
        }

        $amountsPaidCell = $amountLines !== [] ? implode("\n", $amountLines) : '—';
        $payDatesCell = $dateLinesAll !== [] ? implode("\n", $dateLinesAll) : '—';

        $now = Carbon::now()->startOfDay();
        $earlyFa = '';
        $lateFa = '';
        $hasPositivePaidThisInst = array_sum(array_column($paySlices, 'amount')) > 0;
        if ($hasPositivePaidThisInst && $paidAtRoot !== null) {
            if ($paidAtRoot->lt($due)) {
                $earlyFa = Jalali::enToFaNumbers((string) (int) $paidAtRoot->diffInDays($due)).' روز';
            } elseif ($paidAtRoot->gt($due)) {
                $lateFa = Jalali::enToFaNumbers((string) (int) $paidAtRoot->diffInDays($due)).' روز';
            }
        } elseif (
            ! $hasPositivePaidThisInst &&
            $now->gt($due) &&
            (int) $inst->paid_amount_toman < (int) $inst->amount_toman
        ) {
            $lateFa = Jalali::enToFaNumbers((string) (int) $due->diffInDays($now)).' روز از سررسید (پرداخت‌نشده)';
        }

        $penaltyToman = $this->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);
        $penaltyCell = $penaltyToman > 0 ? $this->formatBookletMoneyFa($penaltyToman) : '—';

        return [
            'sequence_fa' => Jalali::enToFaNumbers((string) (int) $inst->sequence),
            'due_fa' => $dueFa,
            'amount_due_cell' => $this->formatBookletMoneyFa((int) $inst->amount_toman),
            'pay_dates_cell' => $payDatesCell,
            'amounts_paid_cell' => $amountsPaidCell,
            'early_cell' => $earlyFa !== '' ? $earlyFa : '—',
            'late_cell' => $lateFa !== '' ? $lateFa : '—',
            'penalty_cell' => $penaltyCell,
            'online_cell' => $sumOnline > 0 ? $this->formatBookletMoneyFa($sumOnline) : '—',
            'gateway_cell' => $sumGateway > 0 ? $this->formatBookletMoneyFa($sumGateway) : '—',
            'cash_cell' => $sumCash > 0 ? $this->formatBookletMoneyFa($sumCash) : '—',
            'transfer_cell' => $sumBank > 0 ? $this->formatBookletMoneyFa($sumBank) : '—',
            'terminal_cell' => $sumTerminal > 0 ? $this->formatBookletMoneyFa($sumTerminal) : '—',
            'notes_cell' => $noteParts !== [] ? implode("\n", $noteParts) : '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $printSettings
     */
    private function customerPrintPasswordDisplay(Customer $customer, array $printSettings): string
    {
        if (($printSettings['show_password'] ?? '1') !== '1') {
            return '';
        }

        $encrypted = $customer->password_print_encrypted ?? null;
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return (string) ($printSettings['password_unavailable_text'] ?? '—');
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return (string) ($printSettings['password_unavailable_text'] ?? '—');
        }
    }

    private function customerPasswordPrintEncrypted(?string $plainPassword): ?string
    {
        $plainPassword = trim((string) $plainPassword);
        if ($plainPassword === '') {
            return null;
        }

        return Crypt::encryptString($plainPassword);
    }

    private function formatBookletMoneyFa(int $amount): string
    {
        return Jalali::enToFaNumbers(number_format($amount, 0, '.', ',')).' تومان';
    }

    private function formatInstallmentEarlyLateLabel(CustomerLoanInstallment $inst, CustomerLoanFile $file): string
    {
        if ($file->revoked_at !== null) {
            return '—';
        }

        $file->loadMissing('loanType');
        $lateCoef = (float) ($file->loanType?->daily_late_coefficient ?? 0);
        $earlyCoef = (float) ($file->loanType?->daily_early_coefficient ?? 0);
        $finance = app(LoanFileFinanceCalculator::class);

        $due = Carbon::parse($inst->due_date)->startOfDay();
        $today = Carbon::now()->startOfDay();
        $amount = (int) $inst->amount_toman;
        $paid = (int) $inst->paid_amount_toman;
        $slotFullyPaid = $amount > 0 && $paid >= $amount;

        // تا قبل از تسویهٔ کامل قسط، همیشه بر اساس امروز (نه تاریخ واریز) برآورد می‌شود.
        if (! $slotFullyPaid) {
            if ($today->gt($due)) {
                $days = (int) $due->diffInDays($today);
                $pen = $finance->estimateBookletPenaltyTomanForInstallment($inst, $lateCoef);

                return 'دیرکرد: '.Jalali::enToFaNumbers((string) $days).' روز — '.$this->formatBookletMoneyFa($pen);
            }

            if ($today->lt($due) && $earlyCoef > 0 && $amount > 0) {
                $days = (int) $today->diffInDays($due);
                $unpaid = max(0, $amount - $paid);
                if ($days >= 1 && $unpaid > 0) {
                    $benefit = max(0, (int) round($unpaid * $earlyCoef * $days));
                    if ($benefit > 0) {
                        return 'زودکرد احتمالی: '.Jalali::enToFaNumbers((string) $days).' روز — '.$this->formatBookletMoneyFa($benefit);
                    }
                }
            }

            return '—';
        }

        $paidAt = $inst->paid_at !== null ? Carbon::parse($inst->paid_at)->startOfDay() : null;

        if ($paidAt !== null) {
            if ($paidAt->lt($due)) {
                $days = (int) $paidAt->diffInDays($due);
                $label = 'زودکرد: '.Jalali::enToFaNumbers((string) $days).' روز';
                if ($earlyCoef > 0 && $days >= 1) {
                    $benefit = max(0, (int) round($amount * $earlyCoef * $days));
                    if ($benefit > 0) {
                        $label .= ' — '.$this->formatBookletMoneyFa($benefit);
                    }
                }

                return $label;
            }
            if ($paidAt->gt($due)) {
                $days = (int) $due->diffInDays($paidAt);
                $pen = $finance->estimatePenaltyAtDateToman($inst, $lateCoef, $paidAt);

                return 'دیرکرد: '.Jalali::enToFaNumbers((string) $days).' روز — '.$this->formatBookletMoneyFa($pen);
            }

            return 'به‌موقع';
        }

        return 'تسویهٔ قسط';
    }

    private function resolveInstallmentPaymentMethodsLabel(CustomerLoanInstallment $inst): ?string
    {
        $lines = $this->resolveInstallmentPaymentMethodLines($inst);
        if ($lines === []) {
            return null;
        }

        $parts = [];
        foreach ($lines as $line) {
            $method = trim((string) ($line['method_label'] ?? ''));
            $source = trim((string) ($line['source_label'] ?? ''));
            if ($method === '') {
                continue;
            }
            $parts[] = $source !== '' ? $method.' ('.$source.')' : $method;
        }

        return $parts !== [] ? implode('، ', $parts) : null;
    }

    /**
     * @return list<array{method_label: string, source_label: string|null}>
     */
    private function resolveInstallmentPaymentMethodLines(CustomerLoanInstallment $inst): array
    {
        $inst->loadMissing('payments');
        $methodLabels = CustomerLoanInstallmentPayment::methodLabels();
        $lines = [];
        $seen = [];

        foreach ($inst->payments as $payment) {
            $key = (string) $payment->payment_method;
            $methodLabel = $methodLabels[$key] ?? $key;
            $sourceLabel = $this->resolveInstallmentPaymentSourceLabelFa($payment);
            $uniq = $methodLabel.'|'.($sourceLabel ?? '');
            if (isset($seen[$uniq])) {
                continue;
            }
            $seen[$uniq] = true;
            $lines[] = [
                'method_label' => $methodLabel,
                'source_label' => $sourceLabel,
            ];
        }

        if ($lines === [] && (int) $inst->paid_amount_toman > 0) {
            $lines[] = [
                'method_label' => $methodLabels[CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED]
                    ?? CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED,
                'source_label' => null,
            ];
        }

        return $lines;
    }

    private function resolveInstallmentPaymentSourceLabelFa(CustomerLoanInstallmentPayment $payment): ?string
    {
        $note = (string) ($payment->note ?? '');

        if ($payment->recorded_by_admin_id !== null) {
            if (str_contains($note, 'اعلام کاربر #')) {
                return 'اعلام واریز';
            }

            return 'ادمین';
        }

        return match ((string) $payment->payment_method) {
            CustomerLoanInstallmentPayment::METHOD_ONLINE,
            CustomerLoanInstallmentPayment::METHOD_WALLET,
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE,
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET => 'مشتری',
            default => null,
        };
    }

    private function resolveInstallmentRecordedByLabel(CustomerLoanInstallment $inst, Customer $customer): string
    {
        $inst->loadMissing(['payments.recordedByAdmin', 'recordedByAdmin']);

        if ((int) $inst->paid_amount_toman <= 0) {
            return '—';
        }

        $labels = [];
        $seen = [];

        foreach ($inst->payments as $payment) {
            $label = $this->resolvePaymentRecordedByLabelFa($payment, $customer);
            if ($label === '' || isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;
            $labels[] = $label;
        }

        if ($labels !== []) {
            return implode('، ', $labels);
        }

        $storedLabel = trim((string) ($inst->recorded_by_label ?? ''));
        if ($storedLabel !== '') {
            return $storedLabel;
        }

        if ($inst->recorded_by_admin_id !== null) {
            return $this->formatAdminDisplayName($inst->recordedByAdmin);
        }

        return 'سیستم (ثبت قبلی)';
    }

    private function resolvePaymentRecordedByLabelFa(CustomerLoanInstallmentPayment $payment, Customer $customer): string
    {
        $note = (string) ($payment->note ?? '');
        $method = (string) $payment->payment_method;

        if (str_contains($note, 'اعلام کاربر #')) {
            $customerName = trim($customer->fullName());

            return $customerName !== '' ? $customerName.' (اعلام واریز)' : 'مشتری (اعلام واریز)';
        }

        if ($payment->recorded_by_admin_id !== null) {
            return $this->formatAdminDisplayName($payment->recordedByAdmin);
        }

        if (in_array($method, [
            CustomerLoanInstallmentPayment::METHOD_ONLINE,
            CustomerLoanInstallmentPayment::METHOD_WALLET,
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_ONLINE,
            CustomerLoanInstallmentPayment::METHOD_FULL_SETTLEMENT_WALLET,
        ], true)) {
            $customerName = trim($customer->fullName());

            return $customerName !== '' ? $customerName : 'مشتری';
        }

        if ($method === CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED) {
            return 'سیستم (ثبت قبلی)';
        }

        if (
            str_contains($note, 'پرداخت آنلاین')
            || str_contains($note, 'پرداخت از کیف پول')
            || str_contains($note, 'تسویه')
        ) {
            $customerName = trim($customer->fullName());

            return $customerName !== '' ? $customerName : 'مشتری';
        }

        return 'سیستم';
    }

    private function formatAdminDisplayName(?Admin $admin): string
    {
        if ($admin === null) {
            return 'ادمین';
        }

        $name = trim((string) ($admin->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($admin->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        return 'ادمین';
    }

    /**
     * @return array{kind: string, diff_toman: int, label: string|null}
     */
    private function resolveInstallmentAmountMismatch(int $nominalToman, int $paidToman): array
    {
        if ($paidToman <= 0 || $nominalToman <= 0) {
            return ['kind' => 'none', 'diff_toman' => 0, 'label' => null];
        }

        $diff = $paidToman - $nominalToman;
        if ($diff === 0) {
            return ['kind' => 'none', 'diff_toman' => 0, 'label' => null];
        }

        if ($diff > 0) {
            return [
                'kind' => 'over',
                'diff_toman' => $diff,
                'label' => 'اضافه‌پرداخت: '.$this->formatBookletMoneyFa($diff),
            ];
        }

        $shortfall = abs($diff);

        return [
            'kind' => 'under',
            'diff_toman' => $diff,
            'label' => 'کسری: '.$this->formatBookletMoneyFa($shortfall),
        ];
    }

    private function calculateLoanProfitToman(
        int $amountToman,
        float $interestRatePercent,
        string $profitMethod,
        int $installmentsCount,
        int $intervalCount,
        string $intervalUnit
    ): int {
        if ($amountToman <= 0 || $interestRatePercent <= 0 || $installmentsCount <= 0 || $intervalCount <= 0) {
            return 0;
        }
        $months = $this->repaymentDurationInMonths($installmentsCount, $intervalCount, $intervalUnit);
        if ($months <= 0) {
            return 0;
        }
        $rate = $interestRatePercent / 100;
        $profit = $profitMethod === LoanType::PROFIT_BANK
            ? ($amountToman * $rate * ($months / 12))
            : ($amountToman * $rate * $months);

        return max(0, (int) round($profit));
    }

    private function repaymentDurationInMonths(int $installmentsCount, int $intervalCount, string $intervalUnit): float
    {
        $multiplier = $intervalUnit === LoanType::GAP_WEEKLY ? (12 / 52) : 1.0;

        return max(0, $installmentsCount * $intervalCount * $multiplier);
    }

    private function isRepaymentPeriodAllowed(LoanType $loanType, int $installmentsCount, int $intervalCount, string $intervalUnit, int $amount): bool
    {
        $periods = is_array($loanType->repayment_periods) ? $loanType->repayment_periods : [];
        $type = (string) ($periods['type'] ?? LoanType::REPAY_UNLIMITED);
        if ($type === LoanType::REPAY_UNLIMITED) {
            return true;
        }
        $months = (int) ceil($this->repaymentDurationInMonths($installmentsCount, $intervalCount, $intervalUnit));
        if ($type === LoanType::REPAY_MAX_UNTIL) {
            $maxMonths = (int) ($periods['max_months'] ?? 0);

            return $maxMonths < 1 || $months <= $maxMonths;
        }
        if ($type === LoanType::REPAY_ALLOWED_MONTHS) {
            $rows = is_array($periods['allowed_rows'] ?? null) ? $periods['allowed_rows'] : [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $m = (int) ($row['months'] ?? 0);
                $cap = (int) round((float) ($row['cap'] ?? 0));
                if ($m === $months && ($cap < 1 || $amount <= $cap)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function loanSmsTemplateVars(Customer $customer, CustomerLoanFile $loanFile): array
    {
        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'loan_amount' => number_format((int) $loanFile->amount_toman, 0, '.', ',').' تومان',
            'installment_amount' => number_format((int) $loanFile->installment_amount_toman, 0, '.', ',').' تومان',
        ];
    }

    private function defaultLoanCreatedSmsText(Customer $customer, CustomerLoanFile $loanFile): string
    {
        return 'سامانه '.$this->appDisplayName()."\n"
            .'مشتری گرامی '.$customer->fullName()."\n"
            .'ثبت پرونده وام جدید انجام شد.'."\n"
            .'پرونده وام: '.$loanFile->loan_code."\n"
            .'مبلغ وام: '.number_format((int) $loanFile->amount_toman, 0, '.', ',').' تومان'."\n"
            .'مبلغ هر قسط: '.number_format((int) $loanFile->installment_amount_toman, 0, '.', ',').' تومان';
    }

    /**
     * @return array<string, string>
     */
    private function installmentPaymentRegisteredSmsTemplateVars(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $inst,
        int $registeredAmountToman,
    ): array {
        $loanFile->loadMissing('loanType');
        $mapped = $this->mapLoanFile($loanFile);

        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'installment_number' => (string) $inst->sequence,
            'paid_amount' => number_format($registeredAmountToman, 0, '.', ',').' تومان',
            'remaining_loan' => number_format((int) ($mapped['remaining_amount_toman'] ?? 0), 0, '.', ',').' تومان',
        ];
    }

    private function defaultAdminInstallmentPaymentRegisteredSmsText(
        Customer $customer,
        CustomerLoanFile $loanFile,
        CustomerLoanInstallment $inst,
        int $registeredAmountToman,
    ): string {
        $tpl = SmsTemplate::query()
            ->where('template_key', 'default_admin_installment_payment_registered')
            ->first();
        $vars = $this->installmentPaymentRegisteredSmsTemplateVars($customer, $loanFile, $inst, $registeredAmountToman);
        if ($tpl !== null) {
            return trim($this->renderTemplate($tpl->body, $vars));
        }

        return $this->appDisplayName()."\n"
            .'مشتری گرامی '.$customer->fullName().'؛ مبلغ '
            .number_format($registeredAmountToman, 0, '.', ',').' تومان بابت قسط شماره '.(string) $inst->sequence
            .' پرونده '.(string) $loanFile->loan_code.' ثبت گردید.';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGoldGuaranteeMeta(array $validated): array
    {
        $itemCode = (string) ($validated['gold_item_code'] ?? '');
        if ($itemCode === '') {
            throw ValidationException::withMessages([
                'gold_item_code' => 'نوع طلا را انتخاب کنید.',
            ]);
        }

        $labels = CustomerLoanGuarantee::goldItemLabels();
        if (! isset($labels[$itemCode])) {
            throw ValidationException::withMessages([
                'gold_item_code' => 'نوع طلای انتخاب‌شده معتبر نیست.',
            ]);
        }

        $rateToman = (int) ($validated['gold_rate_toman'] ?? 0);
        if ($rateToman < 1) {
            throw ValidationException::withMessages([
                'gold_rate_toman' => 'نرخ طلا الزامی است.',
            ]);
        }

        $weight = null;
        $quantity = null;
        if ($itemCode === CustomerLoanGuarantee::GOLD_ITEM_BROKEN_GOLD) {
            $weight = isset($validated['gold_weight_gram']) ? round((float) $validated['gold_weight_gram'], 3) : null;
            if ($weight === null || $weight <= 0) {
                throw ValidationException::withMessages([
                    'gold_weight_gram' => 'برای طلای شکن، وزن طلا الزامی است.',
                ]);
            }
        } else {
            $quantity = isset($validated['gold_quantity']) ? (int) $validated['gold_quantity'] : 0;
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'gold_quantity' => 'برای این نوع طلا، تعداد الزامی است.',
                ]);
            }
        }

        $estimatedAmount = $itemCode === CustomerLoanGuarantee::GOLD_ITEM_BROKEN_GOLD
            ? (int) round($rateToman * (float) ($weight ?? 0))
            : (int) ($rateToman * (int) ($quantity ?? 0));

        return [
            'gold_item_code' => $itemCode,
            'gold_item_label' => (string) $labels[$itemCode],
            // backward compatibility with older UI consumers
            'gold_item_type' => (string) $labels[$itemCode],
            'gold_weight_gram' => $weight,
            'gold_quantity' => $quantity,
            'gold_rate_toman' => $rateToman,
            'amount_toman' => max(0, $estimatedAmount),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanGuarantee(CustomerLoanGuarantee $g): array
    {
        $attachmentUrl = null;
        $attachmentDownloadUrl = null;
        $attachmentPreviewUrl = null;
        if (is_string($g->attachment_path) && $g->attachment_path !== '') {
            $routeParams = [
                'customer' => (int) $g->customer_id,
                'loanFile' => (int) $g->loan_file_id,
                'guarantee' => (int) $g->id,
            ];
            $attachmentPreviewUrl = route('admin.customers.loan-files.guarantees.attachment', $routeParams);
            $attachmentDownloadUrl = route('admin.customers.loan-files.guarantees.attachment', $routeParams + ['download' => 1]);
            $attachmentUrl = $attachmentDownloadUrl;
        }
        $typeLabels = [
            CustomerLoanGuarantee::TYPE_ORG_SELF => 'سازمانی - خودم',
            CustomerLoanGuarantee::TYPE_ORG_OTHER => 'سازمانی - شخص دیگر',
            CustomerLoanGuarantee::TYPE_CHEQUE => 'چک',
            CustomerLoanGuarantee::TYPE_GOLD => 'طلا',
            CustomerLoanGuarantee::TYPE_OTHER => 'سایر',
        ];
        $returnDocumentPreviewUrl = null;
        $returnDocumentDownloadUrl = null;
        if (is_string($g->return_document_path) && $g->return_document_path !== '') {
            $returnRouteParams = [
                'customer' => (int) $g->customer_id,
                'loanFile' => (int) $g->loan_file_id,
                'guarantee' => (int) $g->id,
            ];
            $returnDocumentPreviewUrl = route('admin.customers.loan-files.guarantees.return-document', $returnRouteParams);
            $returnDocumentDownloadUrl = route('admin.customers.loan-files.guarantees.return-document', $returnRouteParams + ['download' => 1]);
        }

        return [
            'id' => (int) $g->id,
            'type' => (string) $g->type,
            'type_label' => (string) ($typeLabels[$g->type] ?? $g->type),
            'description' => (string) ($g->description ?? ''),
            'meta' => is_array($g->meta) ? $g->meta : [],
            'is_returned' => $g->isMarkedReturned(),
            'returned_at' => $g->returned_at ? Jalali::instance($g->returned_at)->format('Y/m/d H:i') : '',
            'return_document_preview_url' => $returnDocumentPreviewUrl,
            'return_document_download_url' => $returnDocumentDownloadUrl,
            'return_document_name' => is_string($g->return_document_path) && $g->return_document_path !== '' ? basename($g->return_document_path) : '',
            'attachment_url' => $attachmentUrl,
            'attachment_preview_url' => $attachmentPreviewUrl,
            'attachment_download_url' => $attachmentDownloadUrl,
            'attachment_name' => is_string($g->attachment_path) && $g->attachment_path !== '' ? basename($g->attachment_path) : '',
            'created_at' => $g->created_at ? Jalali::instance($g->created_at)->format('Y/m/d H:i') : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|JsonResponse
     */
    private function buildOrgSelfGuaranteeMeta(array $validated): array|JsonResponse
    {
        $orgId = (int) ($validated['organization_id'] ?? 0);
        $organization = Organization::query()->find($orgId);
        if ($organization === null) {
            return response()->json(['message' => 'سازمان معتبر را انتخاب کنید.'], 422);
        }

        return [
            'organization_id' => (int) $organization->id,
            'organization_name' => (string) $organization->name,
            'employee_no' => trim((string) ($validated['employee_no'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|JsonResponse
     */
    private function buildOrgOtherGuaranteeMeta(Request $request, array $validated, ?CustomerLoanGuarantee $existing = null): array|JsonResponse
    {
        $orgId = (int) ($validated['organization_id'] ?? 0);
        $organization = Organization::query()->find($orgId);
        if ($organization === null) {
            return response()->json(['message' => 'سازمان معتبر را انتخاب کنید.'], 422);
        }

        // کد ملی ضامن اختیاری است؛ کنترل‌رقم استاندارد اغلب برای نسخهٔ قدیمی/کپیِ نادرست خطا می‌دهد؛
        // تنها ده رقم انگلیسی (با حذف جداکننده) و غیر ده‌تایی تکراری پذیرفته می‌شود.
        $nationalId = IranNationalId::normalizeNationalInput($validated['guarantor_national_id'] ?? '');
        if ($nationalId !== '') {
            if (! IranNationalId::isTenDigitNationalBody($nationalId)) {
                return response()->json([
                    'message' => 'کد ملی ضامن را دقیقا با ده رقم (فارسی یا انگلیسی) و بدون ده رقم تکراری وارد کنید.',
                ], 422);
            }
        }

        $phone = $this->toEnglishDigits(trim((string) ($validated['guarantor_phone'] ?? '')));
        if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
            return response()->json(['message' => 'شماره موبایل ضامن معتبر نیست.'], 422);
        }

        $verified = false;
        if ($phone !== '') {
            $verified = $this->consumeGuarantorVerificationToken($request, $phone);
        }

        if (! $verified && $existing !== null && (string) $existing->type === CustomerLoanGuarantee::TYPE_ORG_OTHER) {
            $prevMeta = is_array($existing->meta) ? $existing->meta : [];
            $oldPhone = $this->toEnglishDigits(trim((string) ($prevMeta['guarantor_phone'] ?? '')));
            $wasVerified = (bool) ($prevMeta['guarantor_mobile_verified'] ?? false);
            if ($wasVerified && $oldPhone !== '' && $oldPhone === $phone) {
                $verified = true;
            }
        }

        return [
            'organization_id' => (int) $organization->id,
            'organization_name' => (string) $organization->name,
            'guarantor_name' => trim((string) ($validated['guarantor_name'] ?? '')),
            'guarantor_national_id' => $nationalId,
            'guarantor_employee_no' => trim((string) ($validated['guarantor_employee_no'] ?? '')),
            'guarantor_phone' => $phone,
            'guarantor_mobile_verified' => $verified,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function guaranteeTypesSupportingReturn(): array
    {
        return [
            CustomerLoanGuarantee::TYPE_CHEQUE,
            CustomerLoanGuarantee::TYPE_GOLD,
            CustomerLoanGuarantee::TYPE_OTHER,
        ];
    }

    private function isGuaranteeMarkedReturnedFromRequest(string $type, Request $request): bool
    {
        if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            return $request->boolean('cheque_returned');
        }
        if (in_array($type, [CustomerLoanGuarantee::TYPE_GOLD, CustomerLoanGuarantee::TYPE_OTHER], true)) {
            return $request->boolean('guarantee_returned');
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function processGuaranteeReturnState(
        Request $request,
        Customer $customer,
        CustomerLoanFile $loanFile,
        string $type,
        array &$meta,
        ?CustomerLoanGuarantee $existing,
        ?string &$returnDocumentPath,
        ?Carbon &$returnedAt,
        ?int &$returnedByAdminId,
    ): ?JsonResponse {
        if (! in_array($type, $this->guaranteeTypesSupportingReturn(), true)) {
            return null;
        }

        $wasReturned = $existing !== null ? $existing->isMarkedReturned() : false;
        $nowReturned = $this->isGuaranteeMarkedReturnedFromRequest($type, $request);

        if ($type === CustomerLoanGuarantee::TYPE_CHEQUE) {
            $meta['cheque_returned'] = $nowReturned;
        } else {
            $meta['returned'] = $nowReturned;
        }

        if ($nowReturned && ! $wasReturned) {
            if (GuaranteeReturnOtpSettings::isEnabled()) {
                if (! $this->consumeGuaranteeReturnVerificationToken($request, $customer, $loanFile, $existing?->id)) {
                    return response()->json([
                        'message' => 'تایید پیامکی مشتری برای ثبت عودت ضمانت الزامی است.',
                    ], 422);
                }
            }

        }

        $returnDoc = $request->file('return_document');
        if ($returnDoc instanceof UploadedFile && $returnDoc->isValid()) {
            PrivateStoragePaths::delete((string) ($returnDocumentPath ?? ''));
            $returnDocumentPath = $this->storeGuaranteeReturnDocument($returnDoc);
        }

        if ($nowReturned && ! $wasReturned) {
            $returnedAt = now();
            $adminId = auth('admin')->id();
            $returnedByAdminId = is_numeric($adminId) ? (int) $adminId : null;
            $meta['return_record'] = [
                'returned_jdate' => Jalali::now()->format('Y/m/d H:i'),
                'customer_otp_verified' => GuaranteeReturnOtpSettings::isEnabled(),
            ];
        } elseif (! $nowReturned) {
            $returnedAt = null;
            $returnedByAdminId = null;
        }

        return null;
    }

    private function consumeGuaranteeReturnVerificationToken(
        Request $request,
        Customer $customer,
        CustomerLoanFile $loanFile,
        ?int $guaranteeId,
    ): bool {
        $token = trim((string) $request->input('guarantee_return_verification_token', ''));
        if ($token === '') {
            return false;
        }

        $mobile = $this->normalizeCustomerMobileForSmsFilter((string) $customer->mobile);
        if ($mobile === '') {
            return false;
        }

        $payload = Cache::pull('guarantee_return_verified:'.$token);
        if (! is_array($payload)) {
            return false;
        }

        $expectedGuaranteeId = $guaranteeId !== null && $guaranteeId > 0 ? $guaranteeId : null;
        $payloadGuaranteeId = isset($payload['guarantee_id']) ? (int) $payload['guarantee_id'] : null;

        return (int) ($payload['customer_id'] ?? 0) === (int) $customer->id
            && (int) ($payload['loan_file_id'] ?? 0) === (int) $loanFile->id
            && $payloadGuaranteeId === $expectedGuaranteeId
            && (string) ($payload['mobile'] ?? '') === $mobile;
    }

    private function storeGuaranteeReturnDocument(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = 'guarantee-return-'.Str::lower(Str::random(22)).'.'.$extension;

        return $file->storeAs('private/loan-guarantee-returns', $safeName, 'local');
    }

    private function serveStoredPrivateFile(string $storedPath, string $fileName, bool $download)
    {
        $location = PrivateStoragePaths::readableLocation($storedPath);
        if ($location === null) {
            abort(404);
        }

        $disk = Storage::disk($location['disk']);
        if ($download) {
            return $disk->download($location['path'], $fileName);
        }

        return $disk->response($location['path'], $fileName, [], 'inline');
    }

    private function consumeGuarantorVerificationToken(Request $request, string $normalizedMobile): bool
    {
        $token = trim((string) $request->input('guarantor_verification_token', ''));
        if ($token === '') {
            return false;
        }

        $payload = Cache::pull('guarantor_verified:'.$token);

        return is_array($payload) && (string) ($payload['mobile'] ?? '') === $normalizedMobile;
    }

    private function consumeLoanCreationVerificationToken(Request $request, Customer $customer): bool
    {
        $token = trim((string) $request->input('customer_verification_token', ''));
        if ($token === '') {
            return false;
        }

        $mobile = $this->normalizeCustomerMobileForSmsFilter((string) $customer->mobile);
        if ($mobile === '') {
            return false;
        }

        $payload = Cache::pull('loan_creation_verified:'.$token);

        return is_array($payload)
            && (int) ($payload['customer_id'] ?? 0) === (int) $customer->id
            && (string) ($payload['mobile'] ?? '') === $mobile;
    }

    private function storeGuaranteeAttachment(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = 'guarantee-'.Str::lower(Str::random(22)).'.'.$extension;

        return $file->storeAs('private/loan-guarantees', $safeName, 'local');
    }

    private function ensureLoanInstallmentSchedule(CustomerLoanFile $file): void
    {
        app(LoanInstallmentScheduleService::class)->ensureSchedule($file);
    }

    private function resyncInstallmentPaidTotalsFromPayments(CustomerLoanInstallment $installment): void
    {
        $sum = (int) CustomerLoanInstallmentPayment::query()
            ->where('customer_loan_installment_id', (int) $installment->id)
            ->sum('amount_toman');
        $installment->paid_amount_toman = $sum;
        $maxDep = CustomerLoanInstallmentPayment::query()
            ->where('customer_loan_installment_id', (int) $installment->id)
            ->max('deposited_at');
        if ($sum > 0 && $maxDep !== null) {
            $installment->paid_at = Carbon::parse((string) $maxDep)->startOfDay()->format('Y-m-d');
        } else {
            $installment->paid_at = null;
        }

        $installment->save();
    }

    /** اگر قبل از وجود ردیف پرداخت جزئی، مبلغی روی خود قسط مانده باشد یک ردیف واحد برای سازگاری با گزارش‌ها می‌سازد. */
    private function maybeBackfillLegacyInstallmentPayments(CustomerLoanInstallment $installment): void
    {
        DB::transaction(function () use ($installment): void {
            $fresh = CustomerLoanInstallment::query()->whereKey($installment->id)->lockForUpdate()->first();
            if ($fresh === null) {
                return;
            }
            $hasRows = CustomerLoanInstallmentPayment::query()
                ->where('customer_loan_installment_id', $fresh->id)
                ->exists();
            if ($hasRows) {
                return;
            }

            $paid = (int) $fresh->paid_amount_toman;
            if ($paid <= 0) {
                return;
            }

            $deposit = $fresh->paid_at !== null
                ? Carbon::parse($fresh->paid_at)->startOfDay()
                : Carbon::now()->startOfDay();

            CustomerLoanInstallmentPayment::query()->create([
                'customer_loan_installment_id' => (int) $fresh->id,
                'payment_method' => CustomerLoanInstallmentPayment::METHOD_LEGACY_IMPORTED,
                'amount_toman' => $paid,
                'reference_due_date' => null,
                'deposited_at' => $deposit->format('Y-m-d'),
                'note' => 'ثبت خودکار؛ پیش‌تر تنها مجموعِ پرداخت روی این قسط ذخیره شده بود.',
                'recorded_by_admin_id' => null,
            ]);
            $fresh->refresh();
            $this->resyncInstallmentPaidTotalsFromPayments($fresh);
        });
        $installment->refresh();
    }

    /**
     * خلاصه قسط به‌اضافهٔ ماندهٔ قابل پرداخت برای مدال ثبت پرداخت.
     *
     * @return array<string, mixed>
     */
    private function mapLoanInstallmentPayContext(CustomerLoanInstallment $i, Customer $customer, CustomerLoanFile $file): array
    {
        $base = $this->mapLoanInstallmentRow($i, $customer, $file);
        $base['remaining_toman'] = max(0, (int) $i->amount_toman - (int) $i->paid_amount_toman);
        $file->loadMissing('loanType');
        $maxPay = $this->loanInstallmentPaymentCeilingToman($file);
        $base['max_payment_toman'] = $maxPay;
        $base['loan_remaining_payable_toman'] = $maxPay;
        $base['can_add_payment'] = ! $file->is_settled && $maxPay > 0;

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInstallmentPaymentRow(CustomerLoanInstallmentPayment $p): array
    {
        $refDue = $p->reference_due_date !== null
            ? Carbon::parse($p->reference_due_date)->startOfDay()
            : null;
        $dep = Carbon::parse($p->deposited_at)->startOfDay();

        $labels = CustomerLoanInstallmentPayment::methodLabels();
        $methodKey = (string) $p->payment_method;

        $rec = $p->recordedByAdmin;
        $by = '—';
        if ($rec !== null) {
            $nm = trim((string) ($rec->name ?? ''));
            $by = $nm !== '' ? $nm : trim((string) ($rec->username ?? ''));
            if ($by === '') {
                $by = '—';
            }
        }

        $refJ = $refDue !== null ? Jalali::instance($refDue)->format('Y/m/d') : '';
        $refJfa = $refJ !== '' ? Jalali::enToFaNumbers($refJ) : '';
        $depJ = Jalali::instance($dep)->format('Y/m/d');
        $depJfa = Jalali::enToFaNumbers($depJ);

        return [
            'id' => (int) $p->id,
            'payment_method' => $methodKey,
            'payment_method_label' => $labels[$methodKey] ?? $methodKey,
            'amount_toman' => (int) $p->amount_toman,
            'reference_due_date' => $refDue !== null ? $refDue->format('Y-m-d') : null,
            'reference_due_jdate' => $refJ,
            'reference_due_jdate_fa' => $refJfa,
            'deposited_at' => $dep->format('Y-m-d'),
            'deposited_jdate' => $depJ,
            'deposited_jdate_fa' => $depJfa,
            'note' => (string) ($p->note ?? ''),
            'recorded_by' => $by,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLoanInstallmentRow(CustomerLoanInstallment $i, Customer $customer, CustomerLoanFile $file): array
    {
        $due = Carbon::parse($i->due_date)->startOfDay();
        $dueJalali = Jalali::instance($due)->format('Y/m/d');
        $dueJalaliFa = Jalali::enToFaNumbers($dueJalali);
        $paidAt = $i->paid_at ? Carbon::parse($i->paid_at)->startOfDay() : null;
        $paidJalali = $paidAt ? Jalali::instance($paidAt)->format('Y/m/d') : '';
        $paidJalaliFa = $paidJalali !== '' ? Jalali::enToFaNumbers($paidJalali) : '';
        $paidAmount = (int) $i->paid_amount_toman;
        $earlyLate = $this->formatInstallmentEarlyLateLabel($i, $file);
        $paymentMethodsLabel = $this->resolveInstallmentPaymentMethodsLabel($i);
        $paymentMethodLines = $this->resolveInstallmentPaymentMethodLines($i);
        $recLabel = $this->resolveInstallmentRecordedByLabel($i, $customer);
        $mismatch = $this->resolveInstallmentAmountMismatch((int) $i->amount_toman, $paidAmount);

        return [
            'id' => (int) $i->id,
            'sequence' => (int) $i->sequence,
            'amount_toman' => (int) $i->amount_toman,
            'due_date' => $due->format('Y-m-d'),
            'due_jdate' => $dueJalali,
            'due_jdate_fa' => $dueJalaliFa,
            'paid_amount_toman' => $paidAmount,
            'paid_at' => $paidAt ? $paidAt->format('Y-m-d') : null,
            'paid_jdate' => $paidJalali,
            'paid_jdate_fa' => $paidJalaliFa,
            'payment_methods_label' => $paymentMethodsLabel,
            'payment_method_lines' => $paymentMethodLines,
            'early_late_label' => $earlyLate,
            'amount_mismatch_kind' => $mismatch['kind'],
            'amount_mismatch_toman' => $mismatch['diff_toman'],
            'amount_mismatch_label' => $mismatch['label'],
            'recorded_by' => $recLabel,
            'customer_id' => (int) $customer->id,
            'customer_name' => $customer->fullName(),
            'customer_mobile' => trim((string) ($customer->mobile ?? '')),
            'loan_file_id' => (int) $file->id,
        ];
    }

    /**
     * @var array<string, list<string>>
     */
    private const INSTALLMENT_SMS_LOG_TYPE_GROUPS = [
        'installment_pre_due' => ['installment_pre_due', 'installment-pre-due'],
        'installment_due' => ['installment_due', 'installment-due'],
        'installment_overdue' => ['installment_overdue', 'installment-overdue'],
        'installment_thanks' => ['installment_thanks', 'installment-thanks'],
    ];

    /**
     * @return array<string, array{count: int, last_mode: string|null}>
     */
    private function emptyInstallmentSmsStats(): array
    {
        $stats = [];
        foreach (array_keys(self::INSTALLMENT_SMS_LOG_TYPE_GROUPS) as $key) {
            $stats[$key] = ['count' => 0, 'last_mode' => null];
        }

        return $stats;
    }

    /**
     * @param  list<int>  $installmentIds
     * @return array<int, array<string, array{count: int, last_mode: string|null}>>
     */
    private function installmentSmsStatsForInstallmentIds(array $installmentIds): array
    {
        $installmentIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $installmentIds),
            static fn (int $id): bool => $id > 0,
        )));

        $stats = [];
        foreach ($installmentIds as $installmentId) {
            $stats[$installmentId] = $this->emptyInstallmentSmsStats();
        }

        if ($installmentIds === []) {
            return $stats;
        }

        $allTypes = [];
        foreach (self::INSTALLMENT_SMS_LOG_TYPE_GROUPS as $types) {
            foreach ($types as $type) {
                $allTypes[] = $type;
            }
        }

        $logs = SmsLog::query()
            ->whereIn('type', array_values(array_unique($allTypes)))
            ->whereIn('meta->installment_id', $installmentIds)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get(['id', 'type', 'meta', 'sent_at']);

        foreach ($logs as $log) {
            $meta = is_array($log->meta) ? $log->meta : [];
            $installmentId = (int) ($meta['installment_id'] ?? 0);
            if ($installmentId < 1 || ! isset($stats[$installmentId])) {
                continue;
            }

            $smsKey = $this->resolveInstallmentSmsStatsKey((string) $log->type);
            if ($smsKey === null) {
                continue;
            }

            $stats[$installmentId][$smsKey]['count']++;
            $stats[$installmentId][$smsKey]['last_mode'] = $this->resolveSmsLogDeliveryMode($meta);
        }

        return $stats;
    }

    private function resolveInstallmentSmsStatsKey(string $logType): ?string
    {
        $normalized = str_replace('-', '_', trim($logType));
        foreach (self::INSTALLMENT_SMS_LOG_TYPE_GROUPS as $key => $types) {
            foreach ($types as $type) {
                if (str_replace('-', '_', $type) === $normalized) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveSmsLogDeliveryMode(array $meta): string
    {
        if (filter_var($meta['automated'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'auto';
        }

        return 'manual';
    }

    /**
     * @return array<string, string>
     */
    private function installmentSmsTemplateVars(Customer $customer, CustomerLoanFile $loanFile, CustomerLoanInstallment $inst): array
    {
        $due = Carbon::parse($inst->due_date)->startOfDay();
        $now = Carbon::now()->startOfDay();
        $daysUntil = $due->gt($now) ? (string) (int) $now->diffInDays($due) : '0';

        return [
            'store_name' => $this->appDisplayName(),
            'customer_name' => $customer->fullName(),
            'loan_code' => (string) $loanFile->loan_code,
            'installment_number' => (string) $inst->sequence,
            'installment_amount' => number_format((int) $inst->amount_toman, 0, '.', ',').' تومان',
            'days_until_due' => $daysUntil,
        ];
    }

    /**
     * متغیرهای قالب اقساط شامل مانده وام و مبلغ پرداختی برای قالب‌های «تشکر» و مشابه.
     *
     * @return array<string, string>
     */
    private function installmentSmsTemplateVarsExtended(Customer $customer, CustomerLoanFile $loanFile, CustomerLoanInstallment $inst): array
    {
        $vars = $this->installmentSmsTemplateVars($customer, $loanFile, $inst);
        $paid = (int) $inst->paid_amount_toman;
        $vars['paid_amount'] = number_format($paid, 0, '.', ',').' تومان';
        $loanFile->loadMissing('loanType');
        $mapped = $this->mapLoanFile($loanFile);
        $vars['remaining_loan'] = number_format((int) ($mapped['remaining_amount_toman'] ?? 0), 0, '.', ',').' تومان';

        return $vars;
    }

    /**
     * @return array{body: string, template_id: int|null}
     */
    private function composeQuickSmsModalContent(Customer $customer, string $smsType, ?CustomerLoanInstallment $installment, ?CustomerLoanFile $loanFile): array
    {
        if (in_array($smsType, ['installment_pre_due', 'installment_due', 'installment_overdue', 'installment_thanks'], true)) {
            if ($installment === null || $loanFile === null) {
                return ['body' => '', 'template_id' => null];
            }
            $loanFile->loadMissing('loanType');

            $prefId = $this->resolveInstallmentScenarioTemplateId($smsType);
            if ($prefId !== null) {
                $tpl = SmsTemplate::query()->find($prefId);
                if ($tpl !== null) {
                    $vars = $this->installmentSmsTemplateVarsExtended($customer, $loanFile, $installment);

                    return [
                        'body' => trim($this->renderTemplate($tpl->body, $vars)),
                        'template_id' => (int) $tpl->id,
                    ];
                }
            }

            $body = $this->defaultInstallmentSmsBody($smsType, $customer, $loanFile, $installment);
            $key = match ($smsType) {
                'installment_pre_due' => 'default_installment_pre_due_reminder',
                'installment_due' => 'default_installment_due_reminder',
                'installment_overdue' => 'default_installment_overdue_reminder',
                'installment_thanks' => 'default_installment_payment_thanks',
                default => null,
            };
            $tpl = $key !== null ? SmsTemplate::query()->where('template_key', $key)->first() : null;

            return [
                'body' => $body,
                'template_id' => $tpl !== null ? (int) $tpl->id : null,
            ];
        }

        if ($smsType === 'welcome') {
            $ids = app(SmsSettingsService::class)->scenarioTemplateIds();
            $tid = $this->parsePositiveIntOrNull($ids['tpl_register_welcome_id'] ?? '');
            if ($tid !== null) {
                $tpl = SmsTemplate::query()->find($tid);
                if ($tpl !== null) {
                    $vars = [
                        'store_name' => $this->appDisplayName(),
                        'customer_name' => $customer->fullName(),
                    ];

                    return [
                        'body' => trim($this->renderTemplate($tpl->body, $vars)),
                        'template_id' => (int) $tpl->id,
                    ];
                }
            }

            return [
                'body' => 'سلام '.$customer->fullName().'، به سامانه '.$this->appDisplayName().' خوش آمدید.',
                'template_id' => null,
            ];
        }

        return [
            'body' => 'سلام '.$customer->fullName().'، لینک شارژ کیف پول شما: —',
            'template_id' => null,
        ];
    }

    private function resolveInstallmentScenarioTemplateId(string $smsType): ?int
    {
        $rem = app(SmsSettingsService::class)->reminderSettings();
        $scen = app(SmsSettingsService::class)->scenarioTemplateIds();

        return match ($smsType) {
            'installment_pre_due' => $this->parsePositiveIntOrNull($rem['before_due_template_id'] ?? ''),
            'installment_due' => $this->parsePositiveIntOrNull($rem['due_day_template_id'] ?? ''),
            'installment_overdue' => $this->parsePositiveIntOrNull($rem['overdue_template_id'] ?? ''),
            'installment_thanks' => $this->parsePositiveIntOrNull($scen['tpl_installment_thanks_id'] ?? ''),
            default => null,
        };
    }

    private function parsePositiveIntOrNull(null|string|int $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $i = (int) $value;

        return $i > 0 ? $i : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInstantSettlementPreview(CustomerLoanFile $file): array
    {
        $file->load('loanType');
        if ($file->revoked_at !== null) {
            return [
                'scenario' => 'revoked',
                'headline' => 'قرارداد فسخ شده',
                'summary' => 'این پرونده فسخ شده است؛ محاسبه تسویه آنی برای آن معنا ندارد.',
                'primary_label' => 'مانده قابل تسویه',
                'primary_amount_toman' => 0,
                'rows' => [],
                'notes' => [
                    'طبق گزارش سیستم، کلیه اقساط این پرونده در زمان فسخ حذف شده‌اند.',
                ],
                'meta' => [
                    'loan_code' => (string) $file->loan_code,
                    'revoked_jdate_fa' => $file->revoked_at
                        ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($file->revoked_at))->format('Y/m/d'))
                        : '—',
                ],
            ];
        }

        $this->ensureLoanInstallmentSchedule($file);
        $file->load(['loanType', 'installments']);

        $mapped = $this->mapLoanFile($file);
        $totalRepayable = (int) $mapped['total_repayable_toman'];
        $totalProfit = (int) $mapped['calculated_profit_toman'];
        $discountRegistered = (int) ($mapped['discount_amount_toman'] ?? 0);
        $remainingAfterDiscount = (int) $mapped['remaining_amount_toman'];
        $profitMethod = (string) ($file->profit_calculation_method ?: LoanType::PROFIT_MONTHLY);

        $instColl = $file->installments->sortBy('sequence')->values();
        $totalPaid = (int) $instColl->sum(static fn (CustomerLoanInstallment $i): int => (int) $i->paid_amount_toman);
        $scheduleRemainingContract = max(0, $totalRepayable - $totalPaid);

        /** جمع ماندهٔ نامی هر ردیف قسط (برای تشخیص جابه‌جایی پرداخت بین سررسیدها؛ نه به‌عنوان ماندهٔ تعهد) */
        $nominalRemainSum = (int) $instColl->sum(static function (CustomerLoanInstallment $i): int {
            return max(0, (int) $i->amount_toman - (int) $i->paid_amount_toman);
        });

        $paidInstallmentsCount = (int) ($mapped['paid_installments_count'] ?? 0);
        $paidInstallmentsSlotCount = (int) ($mapped['paid_installments_slot_count'] ?? 0);

        $unpaidInstallmentsCount = (int) $instColl->filter(static function (CustomerLoanInstallment $i): bool {
            return (int) $i->paid_amount_toman < (int) $i->amount_toman;
        })->count();

        $profitRemaining = $totalRepayable > 0
            ? (int) round($totalProfit * ($scheduleRemainingContract / $totalRepayable))
            : 0;
        $profitRemaining = max(0, min($profitRemaining, $scheduleRemainingContract));

        $lt = $file->loanType;
        $earlyCoef = $lt !== null ? (float) $lt->daily_early_coefficient : 0.0;

        $now = Carbon::now()->startOfDay();
        $lastDueRaw = $instColl->max('due_date');
        $lastDue = $lastDueRaw !== null ? Carbon::parse($lastDueRaw)->startOfDay() : null;
        $daysUntilContractEnd = ($lastDue !== null && $now->lte($lastDue))
            ? (int) $now->diffInDays($lastDue)
            : 0;

        $contractStart = $file->loan_start_date ? Carbon::parse($file->loan_start_date)->startOfDay() : $now;
        $contractEnd = $lastDue ?? $now;
        $totalContractDays = max(1, (int) $contractStart->diffInDays($contractEnd));

        // تخفیف زودکرد: سهم تخمینی سود باقیمانده × حداکثر ۵۰٪ × (ضریب روزانه × روزهای مانده تا آخر قرارداد، سقف منطقی)
        $earlyFactor = min(0.5, $earlyCoef * min(max($daysUntilContractEnd, 0), 365));
        $earlyRebate = (int) round($profitRemaining * $earlyFactor);
        $earlyRebate = max(0, min($earlyRebate, $profitRemaining));
        $amountWithEarlyRaw = max(0, $scheduleRemainingContract - $earlyRebate);
        $amountWithEarly = min($remainingAfterDiscount, $amountWithEarlyRaw);

        // کسر معادل یک سهم سود از هر قسط معوق (تقریب «بهره ماهانه» برای روش ماهانه؛ برای بانکی یک قسط از سهم سود)
        $periodProfitSlice = $unpaidInstallmentsCount > 0
            ? (int) round($profitRemaining / $unpaidInstallmentsCount)
            : 0;
        $periodProfitSlice = min($periodProfitSlice, $profitRemaining);
        $bankExtraSlice = ($profitMethod === LoanType::PROFIT_BANK && $unpaidInstallmentsCount > 0)
            ? (int) round($periodProfitSlice / max(1, (int) $file->installments_count))
            : 0;
        $monthlyStyleCut = min($profitRemaining, $periodProfitSlice + $bankExtraSlice);
        $amountWithMonthlyStyleRaw = max(0, $scheduleRemainingContract - $monthlyStyleCut);
        $amountWithMonthlyStyle = min($remainingAfterDiscount, $amountWithMonthlyStyleRaw);

        $loanStartFa = $file->loan_start_date
            ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($file->loan_start_date))->format('Y/m/d'))
            : '—';
        $lastDueFa = $lastDue !== null
            ? Jalali::enToFaNumbers(Jalali::instance($lastDue)->format('Y/m/d'))
            : '—';

        $profitMethodLabel = $profitMethod === LoanType::PROFIT_BANK ? 'بانکی (روز شمار)' : 'ماهانه (روز شمار)';

        if ($file->is_settled) {
            $netObligationAfterDiscount = max(0, $totalRepayable - $discountRegistered);
            $creditor = max(0, $totalPaid - $netObligationAfterDiscount);
            if ($creditor > 0) {
                return [
                    'scenario' => 'settled_creditor',
                    'headline' => 'تسویه شده — بستانکار',
                    'summary' => 'پرونده در سیستم به‌عنوان تسویه‌شده ثبت شده و مجموع واریزی‌ها از تعهد خالص قرارداد (با احتساب تخفیف) بیشتر است.',
                    'primary_label' => 'مبلغ بستانکاری (تقریبی)',
                    'primary_amount_toman' => $creditor,
                    'rows' => array_values(array_filter([
                        ['label' => 'کل بازپرداخت قراردادی', 'amount_toman' => $totalRepayable],
                        $discountRegistered > 0 ? ['label' => 'تخفیف ثبت‌شده', 'amount_toman' => $discountRegistered] : null,
                        ['label' => 'تعهد خالص (کل − تخفیف)', 'amount_toman' => $netObligationAfterDiscount],
                        ['label' => 'مجموع دریافت‌شده از اقساط', 'amount_toman' => $totalPaid],
                        ['label' => 'اختلاف (بستانکار)', 'amount_toman' => $creditor],
                    ])),
                    'notes' => [
                        'این مبلغ بر اساس جمع «مبلغ پرداختی» ثبت‌شده روی اقساط است؛ در صورت نیاز با اسناد مالی تطبیق دهید.',
                    ],
                    'meta' => [
                        'loan_code' => (string) $file->loan_code,
                        'loan_start_jdate_fa' => $loanStartFa,
                        'last_due_jdate_fa' => $lastDueFa,
                        'paid_installments' => $paidInstallmentsCount,
                        'paid_installments_slot' => $paidInstallmentsSlotCount,
                        'unpaid_installments' => $unpaidInstallmentsCount,
                        'profit_method_label' => $profitMethodLabel,
                    ],
                ];
            }

            return [
                'scenario' => 'settled_ok',
                'headline' => 'پرونده تسویه‌شده',
                'summary' => 'این پرونده در سیستم به‌عنوان تسویه‌شده ثبت شده است.',
                'primary_label' => 'مانده قابل تسویه آنی',
                'primary_amount_toman' => 0,
                'rows' => array_values(array_filter([
                    ['label' => 'کل بازپرداخت قراردادی', 'amount_toman' => $totalRepayable],
                    $discountRegistered > 0 ? ['label' => 'تخفیف ثبت‌شده', 'amount_toman' => $discountRegistered] : null,
                    ['label' => 'مجموع دریافت‌شده از اقساط', 'amount_toman' => $totalPaid],
                ])),
                'notes' => [
                    'در صورت نیاز به تسویه واقعی در حساب‌ها، با مانده بانکی یا صندوق مقایسه کنید.',
                ],
                'meta' => [
                    'loan_code' => (string) $file->loan_code,
                    'loan_start_jdate_fa' => $loanStartFa,
                    'settled_jdate_fa' => $file->settled_at
                        ? Jalali::enToFaNumbers(Jalali::instance(Carbon::parse($file->settled_at))->format('Y/m/d'))
                        : '—',
                    'profit_method_label' => $profitMethodLabel,
                    'paid_installments' => $paidInstallmentsCount,
                    'paid_installments_slot' => $paidInstallmentsSlotCount,
                ],
            ];
        }

        if ($remainingAfterDiscount <= 0) {
            $netObligationAfterDiscount = max(0, $totalRepayable - $discountRegistered);
            $creditor = max(0, $totalPaid - $netObligationAfterDiscount);
            $crossPayNote = $nominalRemainSum > 0 && $creditor <= 0;

            return [
                'scenario' => 'closed_no_debt',
                'headline' => $creditor > 0 ? 'تعهد صفر — بستانکار' : 'تعهد بازپرداخت تسویه است',
                'summary' => $creditor > 0
                    ? 'جمع پرداخت‌های ثبت‌شده از تعهد خالص قرارداد (پس از تخفیف) بیشتر است؛ در صورت تمایل پرونده را رسماً تسویه‌شده علامت بزنید.'
                    : ($crossPayNote
                        ? 'تعهد قرارداد با جمع واریزی‌ها تسویه است. ممکن است روی برخی سررسیدها مبلغ کمتر و روی برخی بیشتر ثبت شده باشد؛ ماندهٔ هر ردیف تنها «سهم نامی» است نه ماندهٔ واقعی تعهد.'
                        : 'بر اساس مجموع پرداخت‌ها، ماندهٔ بازپرداخت قراردادی (با احتساب تخفیف) صفر است؛ در صورت تمایل از ویرایش پرونده، وضعیت تسویه رسمی را هم ثبت کنید.'),
                'primary_label' => $creditor > 0 ? 'مبلغ بستانکاری (تقریبی)' : 'مانده قابل تسویه آنی',
                'primary_amount_toman' => $creditor > 0 ? $creditor : 0,
                'rows' => array_values(array_filter([
                    ['label' => 'کل بازپرداخت قراردادی', 'amount_toman' => $totalRepayable],
                    $discountRegistered > 0 ? ['label' => 'تخفیف ثبت‌شده', 'amount_toman' => $discountRegistered] : null,
                    ['label' => 'تعهد خالص (کل − تخفیف)', 'amount_toman' => $netObligationAfterDiscount],
                    ['label' => 'مجموع پرداخت‌شده (ثبت شده روی اقساط)', 'amount_toman' => $totalPaid],
                    $crossPayNote && $nominalRemainSum > 0
                        ? ['label' => 'جمع ماندهٔ نامی هر ردیف قسط (اطلاعی؛ نه ماندهٔ تعهد)', 'amount_toman' => $nominalRemainSum, 'hint' => 'فقط برای دیدن این‌که کدام ردیف هنوز کمتر از مبلغ نامی دارد']
                        : null,
                ])),
                'notes' => array_values(array_filter([
                    'اگر هنوز پرونده را در سیستم تسویه نکرده‌اید، از ویرایش پرونده برای ثبت تسویه استفاده کنید.',
                    $crossPayNote ? 'در تسویه متقابل بین اقساط، به «مانده قابل تسویه آنی» که بر اساس تعهد کل است تکیه کنید نه به جمع ماندهٔ نامی ردیف‌ها.' : null,
                ])),
                'meta' => [
                    'loan_code' => (string) $file->loan_code,
                    'loan_start_jdate_fa' => $loanStartFa,
                    'profit_method_label' => $profitMethodLabel,
                    'paid_installments' => $paidInstallmentsCount,
                    'paid_installments_slot' => $paidInstallmentsSlotCount,
                    'unpaid_installments' => $unpaidInstallmentsCount,
                ],
            ];
        }

        $diffWarning = $remainingAfterDiscount > 0 && abs($nominalRemainSum - $scheduleRemainingContract) > 1000;

        return [
            'scenario' => 'active',
            'headline' => 'مبلغ قابل تسویه آنی (پیشنهاد سیستم)',
            'summary' => 'ماندهٔ واقعی بر پایهٔ تعهد کل قرارداد (و تخفیف) است؛ پیشنهادهای با کسر بهره، روی همین مانده و با تخمین سود باقیمانده محاسبه شده‌اند.',
            'primary_label' => 'مانده واقعی (هم‌ارز کارت پرونده ، پس از تخفیف)',
            'primary_amount_toman' => $remainingAfterDiscount,
            'rows' => array_values(array_filter([
                ['label' => 'کل بازپرداخت قراردادی', 'amount_toman' => $totalRepayable],
                ['label' => 'ماندهٔ تعهد قبل از تخفیف (کل − پرداخت‌ها)', 'amount_toman' => $scheduleRemainingContract],
                $discountRegistered > 0 ? ['label' => 'تخفیف ثبت‌شده', 'amount_toman' => $discountRegistered] : null,
                ['label' => 'مانده بعد از تخفیف (خط پایهٔ تسویه)', 'amount_toman' => $remainingAfterDiscount, 'emphasis' => true],
                $nominalRemainSum !== $scheduleRemainingContract
                    ? ['label' => 'جمع ماندهٔ نامی هر ردیف قسط (اطلاعی)', 'amount_toman' => $nominalRemainSum, 'hint' => 'اگر با خط بالا متفاوت است، پرداخت بین اقساط جابه‌جا شده است.']
                    : null,
                ['label' => 'مجموع پرداخت‌شده از اقساط', 'amount_toman' => $totalPaid],
                ['label' => 'سهم تخمینی سود باقیمانده (بر اساس ماندهٔ تعهد)', 'amount_toman' => $profitRemaining, 'hint' => 'به‌ازای ماندهٔ '.number_format((float) $scheduleRemainingContract, 0, '.', ',').' تومان از '.number_format((float) $totalRepayable, 0, '.', ',').' تومان کل تعهد'],
                ['label' => 'تخفیف زودکرد تخمینی (ضریب نوع وام)', 'amount_toman' => $earlyRebate, 'hint' => 'حداکثر '.round($earlyFactor * 100, 2).'٪ از سهم سود باقیمانده در این مدل'],
                ['label' => 'پیشنهاد تسویه با کسر ضریب زودکرد (سقف: ماندهٔ واقعی)', 'amount_toman' => $amountWithEarly, 'emphasis' => true],
                ['label' => 'کسر معادل یک دور سود از اقساط با ماندهٔ نامی («بهره ماهانه» تقریبی)', 'amount_toman' => $monthlyStyleCut],
                ['label' => 'پیشنهاد تسویه با کسر بهره دوره‌ای — سقف ماندهٔ واقعی', 'amount_toman' => $amountWithMonthlyStyle, 'emphasis' => true],
            ])),
            'notes' => array_values(array_filter([
                'روش بهره در پرونده: '.$profitMethodLabel.'.',
                'روزهای باقیمانده تا آخرین سررسید قرارداد: '.Jalali::enToFaNumbers((string) max(0, $daysUntilContractEnd)).' روز؛ طول تقریبی قرارداد: '.Jalali::enToFaNumbers((string) $totalContractDays).' روز.',
                $diffWarning ? 'جمع ماندهٔ نامی ردیف‌ها با ماندهٔ تعهد کل اختلاف دارد؛ معمولاً به‌خاطر انتقال پرداخت بین سررسیدهای مختلف است.' : null,
            ])),
            'meta' => [
                'loan_code' => (string) $file->loan_code,
                'loan_start_jdate_fa' => $loanStartFa,
                'last_due_jdate_fa' => $lastDueFa,
                'paid_installments' => $paidInstallmentsCount,
                'paid_installments_slot' => $paidInstallmentsSlotCount,
                'unpaid_installments' => $unpaidInstallmentsCount,
                'days_until_contract_end' => $daysUntilContractEnd,
                'total_contract_days' => $totalContractDays,
                'profit_method_label' => $profitMethodLabel,
                'daily_early_coefficient' => $earlyCoef,
            ],
        ];
    }

    private function defaultInstallmentSmsBody(string $smsType, Customer $customer, CustomerLoanFile $loanFile, CustomerLoanInstallment $inst): string
    {
        $key = match ($smsType) {
            'installment_pre_due' => 'default_installment_pre_due_reminder',
            'installment_due' => 'default_installment_due_reminder',
            'installment_overdue' => 'default_installment_overdue_reminder',
            'installment_thanks' => 'default_installment_payment_thanks',
            default => null,
        };
        if ($key !== null) {
            $tpl = SmsTemplate::query()->where('template_key', $key)->first();
            if ($tpl !== null) {
                return trim($this->renderTemplate($tpl->body, $this->installmentSmsTemplateVarsExtended($customer, $loanFile, $inst)));
            }
        }
        $amt = number_format((int) $inst->amount_toman, 0, '.', ',').' تومان';

        return match ($smsType) {
            'installment_pre_due' => 'مشتری گرامی '.$customer->fullName().'؛ سررسید قسط شماره '.(string) $inst->sequence.' به مبلغ '.$amt.' نزدیک است.',
            'installment_due' => 'مشتری گرامی '.$customer->fullName().'؛ امروز سررسید قسط شماره '.(string) $inst->sequence.' به مبلغ '.$amt.' است.',
            'installment_overdue' => 'مشتری گرامی '.$customer->fullName().'؛ قسط شماره '.(string) $inst->sequence.' به مبلغ '.$amt.' معوق شده است.',
            'installment_thanks' => 'مشتری گرامی '.$customer->fullName().'؛ از پرداخت قسط شماره '.(string) $inst->sequence.' سپاسگزاریم.',
            default => 'سلام '.$customer->fullName().'، '.$this->appDisplayName(),
        };
    }
}
