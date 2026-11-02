<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sms\SmsFreeSendExcelImporter;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class SmsFreeSendExcelImporterTest extends TestCase
{
    public function test_excel_import_normalizes_mobile_and_discards_invalid_and_duplicate_rows(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['شماره موبایل', 'متن پیامک'],
            ['۰۹۱۲۱۲۳۴۵۶۷', 'سلام مشتری اول'],
            ['09121234567', 'پیام تکراری'],
            ['invalid', 'شماره نامعتبر'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'sms-excel-');

        try {
            (new Xlsx($spreadsheet))->save($path);
            $file = new UploadedFile($path, 'contacts.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $parsed = app(SmsFreeSendExcelImporter::class)->parse($file);

            self::assertCount(1, $parsed['recipients']);
            self::assertSame('09121234567', $parsed['recipients'][0]['mobile']);
            self::assertSame('سلام مشتری اول', $parsed['recipients'][0]['message']);
            self::assertSame(1, $parsed['invalid_count']);
            self::assertSame(1, $parsed['duplicate_count']);
        } finally {
            @unlink($path);
        }
    }
}
