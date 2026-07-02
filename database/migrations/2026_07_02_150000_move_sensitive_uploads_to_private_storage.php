<?php

declare(strict_types=1);

use App\Support\PrivateStoragePaths;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_loan_guarantees')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->select(['id', 'attachment_path'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $legacyPath = is_string($row->attachment_path ?? null)
                        ? trim((string) $row->attachment_path)
                        : '';

                    if ($legacyPath === '' || str_starts_with($legacyPath, 'private/')) {
                        continue;
                    }

                    $privatePath = PrivateStoragePaths::migratePublicPathToPrivate($legacyPath);

                    DB::table('customer_loan_guarantees')
                        ->where('id', (int) $row->id)
                        ->update(['attachment_path' => $privatePath]);
                }
            });

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

                    $privatePath = PrivateStoragePaths::migratePublicPathToPrivate($legacyPath);

                    DB::table('customer_loan_guarantees')
                        ->where('id', (int) $row->id)
                        ->update(['return_document_path' => $privatePath]);
                }
            });

        DB::table('customer_deposit_declarations')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->select(['id', 'attachment_path'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $legacyPath = is_string($row->attachment_path ?? null)
                        ? trim((string) $row->attachment_path)
                        : '';

                    if ($legacyPath === '' || str_starts_with($legacyPath, 'private/')) {
                        continue;
                    }

                    $privatePath = PrivateStoragePaths::migratePublicPathToPrivate($legacyPath);

                    DB::table('customer_deposit_declarations')
                        ->where('id', (int) $row->id)
                        ->update(['attachment_path' => $privatePath]);
                }
            });
    }

    public function down(): void
    {
        $this->movePrivatePathsBackToPublic('customer_loan_guarantees', 'attachment_path', 'loan-guarantees/');
        $this->movePrivatePathsBackToPublic('customer_loan_guarantees', 'return_document_path', 'loan-guarantee-returns/');
        $this->movePrivatePathsBackToPublic('customer_deposit_declarations', 'attachment_path', 'deposit-declarations/');
    }

    private function movePrivatePathsBackToPublic(string $table, string $column, string $pathPrefix): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->where($column, 'like', 'private/'.$pathPrefix.'%')
            ->orderBy('id')
            ->select(['id', $column])
            ->chunkById(100, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $privatePath = is_string($row->{$column} ?? null)
                        ? trim((string) $row->{$column})
                        : '';

                    if ($privatePath === '') {
                        continue;
                    }

                    $publicPath = preg_replace('#^private/#', '', $privatePath, 1) ?? $privatePath;
                    if (Storage::disk('local')->exists($privatePath)) {
                        Storage::disk('public')->put($publicPath, Storage::disk('local')->get($privatePath));
                        Storage::disk('local')->delete($privatePath);
                    }

                    DB::table($table)
                        ->where('id', (int) $row->id)
                        ->update([$column => $publicPath]);
                }
            });
    }
};
