<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Admin\DatabaseBackupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RestoreDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = max(1, (int) config('backup.max_upload_mb', 100)) * 1024;

        return [
            'filename' => ['nullable', 'string', 'max:128'],
            'file' => ['nullable', 'file', 'max:'.$maxKb],
            'confirm_database' => ['required', 'string', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasFile = $this->hasFile('file');
            $filename = trim((string) $this->input('filename', ''));

            if (! $hasFile && $filename === '') {
                $validator->errors()->add('filename', 'یک بکاپ از لیست انتخاب کنید یا فایل آپلود نمایید.');

                return;
            }

            if ($hasFile && $filename !== '') {
                $validator->errors()->add('filename', 'فقط یکی از «انتخاب از لیست» یا «آپلود فایل» را استفاده کنید.');

                return;
            }

            if ($filename !== '' && ! DatabaseBackupService::isValidBackupFilename($filename)) {
                $validator->errors()->add('filename', 'نام فایل بکاپ نامعتبر است.');
            }

            $expected = DatabaseBackupService::defaultDatabaseName();
            if ($expected === '' || (string) $this->input('confirm_database') !== $expected) {
                $validator->errors()->add(
                    'confirm_database',
                    'برای تأیید، نام دقیق پایگاه‌داده را وارد کنید: '.$expected
                );
            }

            if ($hasFile) {
                $extension = strtolower($this->file('file')->getClientOriginalExtension());
                if (! in_array($extension, ['sql', 'sqlite', 'txt'], true)) {
                    $validator->errors()->add('file', 'فقط فایل‌های .sql یا .sqlite مجاز هستند.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_database.required' => 'نام پایگاه‌داده برای تأیید الزامی است.',
            'file.max' => 'حجم فایل بکاپ بیش از حد مجاز است.',
        ];
    }
}
