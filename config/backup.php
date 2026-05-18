<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | روش بکاپ MySQL
    |--------------------------------------------------------------------------
    |
    | auto       — روی ویندوز (XAMPP) از PHP؛ روی هاست لینوکس ابتدا mysqldump و در صورت خطا PHP
    | php        — همیشه از اتصال Laravel (مناسب XAMPP و هاست‌های بدون mysqldump)
    | mysqldump  — فقط mysqldump؛ در صورت خطا بکاپ شکست می‌خورد
    |
    | BACKUP_MYSQL_USE_PHP=true معادل driver=php است (سازگاری با نسخه‌های قبل).
    */
    'mysql' => [
        'driver' => env('BACKUP_MYSQL_DRIVER', 'auto'),
    ],

    'max_upload_mb' => (int) env('BACKUP_MAX_UPLOAD_MB', 100),

    'create_safety_backup' => filter_var(env('BACKUP_RESTORE_SAFETY', true), FILTER_VALIDATE_BOOL),

];
