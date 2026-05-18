<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreDatabaseBackupRequest;
use App\Services\Admin\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdminDatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backups,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'backups' => $this->backups->listBackups(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $created = $this->backups->createBackup();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'ایجاد بکاپ ناموفق بود.',
            ], 422);
        }

        return response()->json([
            'message' => 'بکاپ با موفقیت ایجاد شد.',
            'backup' => $created,
            'backups' => $this->backups->listBackups(),
        ]);
    }

    public function download(string $backup): BinaryFileResponse
    {
        $path = $this->backups->resolveBackupPath($backup);

        $mime = str_ends_with($backup, '.sqlite') ? 'application/x-sqlite3' : 'application/sql';

        return response()->download($path, basename($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(string $backup): JsonResponse
    {
        try {
            $this->backups->deleteBackup($backup);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'حذف بکاپ ناموفق بود.',
            ], 422);
        }

        return response()->json([
            'message' => 'بکاپ با موفقیت حذف شد.',
            'backups' => $this->backups->listBackups(),
        ]);
    }

    public function restore(RestoreDatabaseBackupRequest $request): JsonResponse
    {
        try {
            if ($request->hasFile('file')) {
                $this->backups->restoreFromUpload($request->file('file'));
            } else {
                $this->backups->restoreFromStoredFile((string) $request->input('filename'));
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'بازگردانی بکاپ ناموفق بود.',
            ], 422);
        }

        return response()->json([
            'message' => 'بکاپ با موفقیت بازگردانی شد.',
            'backups' => $this->backups->listBackups(),
        ]);
    }
}
