<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['deposit-declarations', 'loan-guarantees', 'loan-guarantee-returns'] as $directory) {
            if (Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        }
    }

    public function down(): void
    {
        // Orphan cleanup is not reversible.
    }
};
