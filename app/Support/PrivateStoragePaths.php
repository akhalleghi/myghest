<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PrivateStoragePaths
{
    public static function normalize(string $storedPath): string
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return '';
        }

        return str_starts_with($storedPath, 'private/')
            ? $storedPath
            : 'private/'.ltrim($storedPath, '/\\');
    }

    /**
     * @return array{disk: 'local'|'public', path: string}|null
     */
    public static function readableLocation(string $storedPath): ?array
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return null;
        }

        $privatePath = self::normalize($storedPath);
        if (Storage::disk('local')->exists($privatePath)) {
            return ['disk' => 'local', 'path' => $privatePath];
        }

        if (! str_starts_with($storedPath, 'private/') && Storage::disk('public')->exists($storedPath)) {
            return ['disk' => 'public', 'path' => $storedPath];
        }

        return null;
    }

    public static function delete(string $storedPath): void
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return;
        }

        Storage::disk('local')->delete(self::normalize($storedPath));

        if (! str_starts_with($storedPath, 'private/')) {
            Storage::disk('public')->delete($storedPath);
        }
    }

    public static function migratePublicPathToPrivate(string $publicPath): string
    {
        $publicPath = trim($publicPath);
        if ($publicPath === '' || str_starts_with($publicPath, 'private/')) {
            return self::normalize($publicPath);
        }

        $privatePath = self::normalize($publicPath);
        if (Storage::disk('public')->exists($publicPath)) {
            Storage::disk('local')->put($privatePath, Storage::disk('public')->get($publicPath));
            Storage::disk('public')->delete($publicPath);
        }

        return $privatePath;
    }
}
