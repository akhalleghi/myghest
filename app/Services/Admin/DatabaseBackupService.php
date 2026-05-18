<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseBackupService
{
    private const BACKUP_DIR = 'backups';

    private const FILENAME_PATTERN = '/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.(sql|sqlite)$/';

    /**
     * @return list<array{filename: string, created_at: string, created_at_iso: string, size_bytes: int, size_label: string}>
     */
    public function listBackups(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BACKUP_DIR)) {
            $disk->makeDirectory(self::BACKUP_DIR);
        }

        $files = collect($disk->files(self::BACKUP_DIR))
            ->filter(static fn (string $path): bool => self::isValidBackupFilename(basename($path)))
            ->map(function (string $path) use ($disk): array {
                $filename = basename($path);
                $createdAt = $this->resolveBackupCreatedAt($filename, (int) $disk->lastModified($path));

                return [
                    'filename' => $filename,
                    'created_at' => $this->formatBackupDateTime($createdAt),
                    'created_at_iso' => $createdAt->toIso8601String(),
                    'size_bytes' => $disk->size($path),
                    'size_label' => Jalali::enToFaNumbers($this->formatBytes($disk->size($path))),
                ];
            })
            ->sortByDesc('created_at_iso')
            ->values()
            ->all();

        return $files;
    }

    /**
     * @return array{filename: string, created_at: string, size_label: string}
     */
    public function createBackup(): array
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");
        if (! is_array($config)) {
            throw new RuntimeException('پیکربندی پایگاه‌داده یافت نشد.');
        }

        $driver = (string) ($config['driver'] ?? '');
        $extension = in_array($driver, ['mysql', 'mariadb'], true) ? 'sql' : 'sqlite';
        $filename = 'backup_'.now()->format('Y-m-d_His').'.'.$extension;
        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));

        match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysqlDatabase($config, $absolutePath),
            'sqlite' => $this->dumpSqliteDatabase($config, $absolutePath),
            default => throw new RuntimeException('این نوع پایگاه‌داده برای بکاپ‌گیری خودکار پشتیبانی نمی‌شود.'),
        };

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            @unlink($absolutePath);
            throw new RuntimeException('فایل بکاپ ایجاد نشد یا خالی است.');
        }

        $created = $this->resolveBackupCreatedAt($filename, (int) (filemtime($absolutePath) ?: time()));

        return [
            'filename' => $filename,
            'created_at' => $this->formatBackupDateTime($created),
            'size_label' => Jalali::enToFaNumbers($this->formatBytes((int) filesize($absolutePath))),
        ];
    }

    public function restoreFromStoredFile(string $filename): void
    {
        $this->restoreFromPath($this->resolveBackupPath($filename));
    }

    public function restoreFromUpload(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $this->assertExtensionMatchesDriver($extension);

        $tempName = '.restore-'.Str::random(20).'.'.$extension;
        $relativePath = self::BACKUP_DIR.'/'.$tempName;
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::BACKUP_DIR);
        $disk->putFileAs(self::BACKUP_DIR, $file, $tempName);

        try {
            $this->restoreFromPath($disk->path($relativePath));
        } finally {
            $disk->delete($relativePath);
        }
    }

    public static function defaultDatabaseName(): string
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");
        if (! is_array($config)) {
            return '';
        }

        return (string) ($config['database'] ?? '');
    }

    private function restoreFromPath(string $sourcePath): void
    {
        if (! is_file($sourcePath) || filesize($sourcePath) === 0) {
            throw new RuntimeException('فایل بکاپ خالی است یا یافت نشد.');
        }

        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");
        if (! is_array($config)) {
            throw new RuntimeException('پیکربندی پایگاه‌داده یافت نشد.');
        }

        $driver = (string) ($config['driver'] ?? '');
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $this->assertExtensionMatchesDriver($extension, $driver);

        if (filter_var(config('backup.create_safety_backup', true), FILTER_VALIDATE_BOOL)) {
            try {
                $this->createBackup();
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'ایجاد بکاپ ایمنی قبل از بازگردانی ناموفق بود: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        match ($driver) {
            'mysql', 'mariadb' => $this->restoreMysqlFromSqlFile($sourcePath),
            'sqlite' => $this->restoreSqliteFromFile($sourcePath, $config),
            default => throw new RuntimeException('بازگردانی برای این نوع پایگاه‌داده پشتیبانی نمی‌شود.'),
        };
    }

    private function restoreMysqlFromSqlFile(string $sourcePath): void
    {
        $sql = file_get_contents($sourcePath);
        if (! is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('محتوای فایل SQL خالی است.');
        }

        if (! preg_match('/\b(CREATE TABLE|INSERT INTO|DROP TABLE)\b/i', $sql)) {
            throw new RuntimeException('فایل بکاپ SQL معتبر به نظر نمی‌رسد.');
        }

        $statements = $this->splitSqlStatements($sql);
        if ($statements === []) {
            throw new RuntimeException('هیچ دستور SQL معتبری در فایل بکاپ یافت نشد.');
        }

        $previousLimit = ini_get('max_execution_time');
        @set_time_limit(600);

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }

            throw new RuntimeException('بازگردانی پایگاه‌داده ناموفق بود: '.$e->getMessage(), 0, $e);
        } finally {
            if ($previousLimit !== false && $previousLimit !== '') {
                @set_time_limit((int) $previousLimit);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inString = false;
        $stringChar = '';

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];

            if (! $inString && $char === '-' && ($sql[$index + 1] ?? '') === '-') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }

                continue;
            }

            if (! $inString && $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }

                continue;
            }

            if (! $inString && $char === '/' && ($sql[$index + 1] ?? '') === '*') {
                $index += 2;
                while ($index < $length - 1 && ! ($sql[$index] === '*' && ($sql[$index + 1] ?? '') === '/')) {
                    $index++;
                }
                $index++;

                continue;
            }

            if ($inString) {
                $buffer .= $char;

                if ($char === '\\' && $index + 1 < $length) {
                    $buffer .= $sql[++$index];

                    continue;
                }

                if ($char === $stringChar) {
                    if ($stringChar === "'" && ($sql[$index + 1] ?? '') === "'") {
                        $buffer .= $sql[++$index];

                        continue;
                    }

                    $inString = false;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreSqliteFromFile(string $sourcePath, array $config): void
    {
        $databasePath = (string) ($config['database'] ?? '');
        if ($databasePath === '') {
            throw new RuntimeException('مسیر فایل SQLite مشخص نیست.');
        }

        $connectionName = (string) config('database.default');
        DB::disconnect($connectionName);

        if (! copy($sourcePath, $databasePath)) {
            throw new RuntimeException('جایگزینی فایل SQLite ناموفق بود.');
        }

        DB::reconnect($connectionName);
    }

    private function assertExtensionMatchesDriver(string $extension, ?string $driver = null): void
    {
        $driver = $driver ?? (string) config('database.connections.'.config('database.default').'.driver');
        $expected = in_array($driver, ['mysql', 'mariadb'], true) ? 'sql' : 'sqlite';

        if ($extension === 'txt' && $expected === 'sql') {
            return;
        }

        if ($extension !== $expected) {
            throw new RuntimeException(
                $expected === 'sql'
                    ? 'این سامانه از پایگاه MySQL استفاده می‌کند؛ فقط فایل .sql مجاز است.'
                    : 'این سامانه از SQLite استفاده می‌کند؛ فقط فایل .sqlite مجاز است.'
            );
        }
    }

    private function resolveBackupCreatedAt(string $filename, int $fallbackTimestamp): Carbon
    {
        if (preg_match('/^backup_(\d{4}-\d{2}-\d{2})_(\d{6})\./', $filename, $matches) === 1) {
            $parsed = Carbon::createFromFormat(
                'Y-m-d His',
                $matches[1].' '.$matches[2],
                (string) config('app.timezone', 'Asia/Tehran')
            );

            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        return Carbon::createFromTimestamp($fallbackTimestamp, (string) config('app.timezone', 'Asia/Tehran'));
    }

    private function formatBackupDateTime(Carbon $dateTime): string
    {
        return Jalali::enToFaNumbers(
            jalali($dateTime->copy()->timezone((string) config('app.timezone', 'Asia/Tehran')))->format('Y/m/d H:i')
        );
    }

    public function resolveBackupPath(string $filename): string
    {
        if (! self::isValidBackupFilename($filename)) {
            throw new RuntimeException('نام فایل بکاپ نامعتبر است.');
        }

        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            throw new RuntimeException('فایل بکاپ یافت نشد.');
        }

        return $disk->path($relativePath);
    }

    public function deleteBackup(string $filename): void
    {
        if (! self::isValidBackupFilename($filename)) {
            throw new RuntimeException('نام فایل بکاپ نامعتبر است.');
        }

        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            throw new RuntimeException('فایل بکاپ یافت نشد.');
        }

        if (! $disk->delete($relativePath)) {
            throw new RuntimeException('حذف فایل بکاپ ناموفق بود.');
        }
    }

    public static function isValidBackupFilename(string $filename): bool
    {
        return (bool) preg_match(self::FILENAME_PATTERN, $filename);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysqlDatabase(array $config, string $absolutePath): void
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('نام پایگاه‌داده مشخص نیست.');
        }

        $strategy = $this->resolveMysqlBackupStrategy();

        if ($strategy === 'php') {
            $this->dumpMysqlDatabaseViaPhp($database, $absolutePath);

            return;
        }

        $binary = $this->resolveMysqldumpBinary();
        if ($binary === null) {
            if ($strategy === 'mysqldump') {
                throw new RuntimeException(
                    'ابزار mysqldump یافت نشد. مسیر آن را در MYSQL_DUMP_PATH تنظیم کنید یا BACKUP_MYSQL_DRIVER=php بگذارید.'
                );
            }

            $this->dumpMysqlDatabaseViaPhp($database, $absolutePath);

            return;
        }

        try {
            $this->runMysqldump($binary, $config, $database, $absolutePath);
        } catch (RuntimeException $exception) {
            if ($strategy === 'mysqldump') {
                throw $exception;
            }

            $this->dumpMysqlDatabaseViaPhp($database, $absolutePath);
        }
    }

    /**
     * auto | php | mysqldump
     */
    private function resolveMysqlBackupStrategy(): string
    {
        if (filter_var(env('BACKUP_MYSQL_USE_PHP', false), FILTER_VALIDATE_BOOL)) {
            return 'php';
        }

        $configured = strtolower(trim((string) config('backup.mysql.driver', 'auto')));
        if (in_array($configured, ['php', 'mysqldump'], true)) {
            return $configured;
        }

        // XAMPP/ویندوز: mysqldump از زیر Apache معمولاً خطای TCP/localhost می‌دهد؛ PHP همان اتصال Laravel را دارد.
        if ($this->isWindows()) {
            return 'php';
        }

        return 'auto';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runMysqldump(string $binary, array $config, string $database, string $absolutePath): void
    {
        $defaultsFile = $this->writeMysqlDefaultsFile($config);
        try {
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--quick',
                '--lock-tables=false',
                '--result-file='.$absolutePath,
                $database,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException($error !== '' ? $error : 'اجرای mysqldump ناموفق بود.');
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    private function dumpMysqlDatabaseViaPhp(string $database, string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('ایجاد فایل بکاپ ممکن نشد.');
        }

        try {
            $pdo = DB::connection()->getPdo();
            $header = implode(PHP_EOL, [
                '-- MyGhest database backup (PHP)',
                '-- Database: '.$database,
                '-- Generated: '.now()->toDateTimeString(),
                '',
                'SET NAMES utf8mb4;',
                'SET FOREIGN_KEY_CHECKS=0;',
                'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";',
                '',
            ]);
            fwrite($handle, $header);

            $tableKey = 'Tables_in_'.$database;
            $tables = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);

            foreach ($tables as $tableRow) {
                $tableName = (string) ($tableRow->{$tableKey} ?? '');
                if ($tableName === '' || ! $this->isSafeSqlIdentifier($tableName)) {
                    continue;
                }

                $createRows = DB::select('SHOW CREATE TABLE `'.$tableName.'`');
                $createSql = (string) ($createRows[0]->{'Create Table'} ?? '');
                if ($createSql === '') {
                    continue;
                }

                fwrite($handle, 'DROP TABLE IF EXISTS `'.$tableName."`;\n");
                fwrite($handle, $createSql.";\n\n");

                $offset = 0;
                $chunkSize = 200;
                do {
                    $rows = DB::select(
                        'SELECT * FROM `'.$tableName.'` LIMIT '.$chunkSize.' OFFSET '.$offset
                    );
                    if ($rows === []) {
                        break;
                    }

                    $columnNames = array_keys((array) $rows[0]);
                    $columnList = implode(', ', array_map(
                        static fn (string $column): string => '`'.$column.'`',
                        $columnNames
                    ));

                    $valueSets = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columnNames as $column) {
                            $values[] = $this->quoteSqlValue($pdo, ((array) $row)[$column] ?? null);
                        }
                        $valueSets[] = '('.implode(', ', $values).')';
                    }

                    fwrite(
                        $handle,
                        'INSERT INTO `'.$tableName.'` ('.$columnList.') VALUES '.implode(', ', $valueSets).";\n"
                    );

                    $offset += $chunkSize;
                } while (count($rows) === $chunkSize);

                fwrite($handle, "\n");
            }

            fwrite($handle, 'SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL);
        } finally {
            fclose($handle);
        }
    }

    private function quoteSqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    private function isSafeSqlIdentifier(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpSqliteDatabase(array $config, string $absolutePath): void
    {
        $databasePath = (string) ($config['database'] ?? '');
        if ($databasePath === '' || ! is_file($databasePath)) {
            throw new RuntimeException('فایل پایگاه‌داده SQLite یافت نشد.');
        }

        if (! copy($databasePath, $absolutePath)) {
            throw new RuntimeException('کپی فایل SQLite ناموفق بود.');
        }
    }

    private function resolveMysqldumpBinary(): ?string
    {
        $configured = env('MYSQL_DUMP_PATH');
        if (is_string($configured) && $configured !== '') {
            return is_file($configured) ? $configured : null;
        }

        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp2\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp3\\mysql\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $process = new Process([$which, 'mysqldump']);
        $process->run();
        if ($process->isSuccessful()) {
            $line = trim($process->getOutput());
            if ($line !== '' && is_file(explode("\n", $line)[0])) {
                return explode("\n", $line)[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function buildMysqlClientDefaultsLines(array $config): array
    {
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        $lines = [
            '[client]',
            'user='.Str::ascii($username),
            'password='.$password,
        ];

        if ($socket !== '') {
            $lines[] = 'socket='.Str::ascii($socket);

            return $lines;
        }

        // همان host/port فایل .env — روی هاست معمولاً localhost یا آدرس داخلی سرور است.
        $lines[] = 'host='.Str::ascii((string) ($config['host'] ?? '127.0.0.1'));
        $lines[] = 'port='.preg_replace('/\D/', '', $port);

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeMysqlDefaultsFile(array $config): string
    {
        $content = implode(PHP_EOL, $this->buildMysqlClientDefaultsLines($config)).PHP_EOL;

        $path = storage_path('app/private/backups/.mysql-'.Str::random(16).'.cnf');
        File::ensureDirectoryExists(dirname($path));
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('ایجاد فایل تنظیمات موقت mysqldump ممکن نشد.');
        }

        @chmod($path, 0600);

        return $path;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' بایت';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' کیلوبایت';
        }

        return number_format($bytes / (1024 * 1024), 2).' مگابایت';
    }
}
