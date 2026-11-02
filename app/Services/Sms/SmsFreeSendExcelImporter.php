<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Support\IranMobile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class SmsFreeSendExcelImporter
{
    private const MAX_DATA_ROWS = 5000;

    /**
     * @return array{recipients:list<array{mobile:string,message:string}>,invalid_count:int,duplicate_count:int,total_rows:int}
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $worksheetInfo = $reader->listWorksheetInfo($file->getRealPath());
            $rowCount = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
            if ($rowCount > self::MAX_DATA_ROWS + 1) {
                throw ValidationException::withMessages([
                    'contacts_file' => 'حداکثر ۵۰۰۰ ردیف مخاطب در هر فایل پذیرفته می‌شود.',
                ]);
            }
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'contacts_file' => 'فایل اکسل قابل خواندن نیست؛ لطفاً قالب نمونه را استفاده کنید.',
            ]);
        }

        if ($rows === [] || count($rows) < 2) {
            throw ValidationException::withMessages([
                'contacts_file' => 'فایل باید یک ردیف عنوان و دست‌کم یک ردیف شماره داشته باشد.',
            ]);
        }

        if (count($rows) - 1 > self::MAX_DATA_ROWS) {
            throw ValidationException::withMessages([
                'contacts_file' => 'حداکثر ۵۰۰۰ ردیف مخاطب در هر فایل پذیرفته می‌شود.',
            ]);
        }

        $header = array_map(fn (mixed $value): string => $this->normalizeHeader((string) $value), $rows[0]);
        $mobileColumn = $this->findColumn($header, ['mobile', 'phone', 'phonenumber', 'شمارهتلفن', 'شمارهتماس', 'شمارههمراه', 'شمارهموبایل', 'موبایل', 'تلفن']);
        $messageColumn = $this->findColumn($header, ['message', 'sms', 'متنپیامک', 'متنپیام', 'پیامک', 'پیام']);

        if ($mobileColumn === null || $messageColumn === null) {
            throw ValidationException::withMessages([
                'contacts_file' => 'ستون‌های «شماره موبایل» و «متن پیامک» در ردیف اول فایل لازم هستند.',
            ]);
        }

        $recipients = [];
        $seenMobiles = [];
        $invalidCount = 0;
        $duplicateCount = 0;

        foreach (array_slice($rows, 1) as $row) {
            $rawMobile = trim((string) ($row[$mobileColumn] ?? ''));
            $rawMessage = trim((string) ($row[$messageColumn] ?? ''));
            if ($rawMobile === '' && $rawMessage === '') {
                continue;
            }

            $mobile = IranMobile::normalize($this->normalizeMobileDigits($rawMobile));
            if ($mobile === null) {
                $invalidCount++;

                continue;
            }

            if (isset($seenMobiles[$mobile])) {
                $duplicateCount++;

                continue;
            }

            $seenMobiles[$mobile] = true;
            $recipients[] = ['mobile' => $mobile, 'message' => $rawMessage];
        }

        return [
            'recipients' => $recipients,
            'invalid_count' => $invalidCount,
            'duplicate_count' => $duplicateCount,
            'total_rows' => max(0, count($rows) - 1),
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ''], $value);

        return preg_replace('/[\s_\-]+/u', '', $value) ?? $value;
    }

    private function normalizeMobileDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidates
     */
    private function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array($header, $candidates, true)) {
                return $index;
            }
        }

        return null;
    }
}
