<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_loan_guarantees')
            ->whereNotNull('return_document_path')
            ->orderBy('id')
            ->select(['id', 'return_document_path'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $legacyPath = is_string($row->return_document_path ?? null)
                        ? trim((string) $row->return_document_path)
                        : '';

                    if ($legacyPath === '' || str_starts_with($legacyPath, 'private/')) {
                        continue;
                    }

                    $privatePath = 'private/'.ltrim($legacyPath, '/\\');
                    if (Storage::disk('public')->exists($legacyPath)) {
                        Storage::disk('local')->put($privatePath, Storage::disk('public')->get($legacyPath));
                        Storage::disk('public')->delete($legacyPath);
                    }

                    DB::table('customer_loan_guarantees')
                        ->where('id', (int) $row->id)
                        ->update(['return_document_path' => $privatePath]);
                }
            });
    }

    public function down(): void
    {
        DB::table('customer_loan_guarantees')
            ->whereNotNull('return_document_path')
            ->where('return_document_path', 'like', 'private/loan-guarantee-returns/%')
            ->orderBy('id')
            ->select(['id', 'return_document_path'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $privatePath = is_string($row->return_document_path ?? null)
                        ? trim((string) $row->return_document_path)
                        : '';

                    if ($privatePath === '') {
                        continue;
                    }

                    $publicPath = preg_replace('#^private/#', '', $privatePath, 1) ?? $privatePath;
                    if (Storage::disk('local')->exists($privatePath)) {
                        Storage::disk('public')->put($publicPath, Storage::disk('local')->get($privatePath));
                        Storage::disk('local')->delete($privatePath);
                    }

                    DB::table('customer_loan_guarantees')
                        ->where('id', (int) $row->id)
                        ->update(['return_document_path' => $publicPath]);
                }
            });
    }
};
