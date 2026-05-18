<?php

declare(strict_types=1);

namespace App\Services\Ui;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class LoginBackgroundService
{
    public const CONTEXT_ADMIN = 'admin';

    public const CONTEXT_CUSTOMER = 'customer';

    public const MODE_FIXED = 'fixed';

    public const MODE_RANDOM = 'random';

    private const BUNDLED_RELATIVE = 'images/login-backgrounds/bundled';

    private const UPLOAD_RELATIVE_PREFIX = 'uploads/login-backgrounds';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const MAX_UPLOAD_KB = 5120;

    /**
     * @return list<string>
     */
    public static function contexts(): array
    {
        return [self::CONTEXT_ADMIN, self::CONTEXT_CUSTOMER];
    }

    public function assertContext(string $context): void
    {
        if (! in_array($context, self::contexts(), true)) {
            throw new InvalidArgumentException('Invalid login background context.');
        }
    }

    public function mode(string $context): string
    {
        $this->assertContext($context);

        $value = AppSetting::query()
            ->where('key', $this->modeKey($context))
            ->value('value');

        return is_string($value) && $value === self::MODE_RANDOM
            ? self::MODE_RANDOM
            : self::MODE_FIXED;
    }

    public function selectedPath(string $context): ?string
    {
        $this->assertContext($context);

        $value = AppSetting::query()
            ->where('key', $this->selectedKey($context))
            ->value('value');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = $this->normalizeRelativePath($value);

        return $this->isAllowedImagePath($normalized) ? $normalized : null;
    }

    /**
     * @return array{
     *     mode: string,
     *     selected: string|null,
     *     bundled: list<array{id: string, url: string, is_custom: bool}>,
     *     custom: list<array{id: string, url: string, is_custom: bool}>
     * }
     */
    public function pickerState(string $context): array
    {
        $this->assertContext($context);

        return [
            'mode' => $this->mode($context),
            'selected' => $this->selectedPath($context),
            'bundled' => $this->listBundledImages(),
            'custom' => $this->listCustomImages($context),
        ];
    }

    /**
     * @return list<array{id: string, url: string, is_custom: bool}>
     */
    public function listBundledImages(): array
    {
        return $this->scanImageDirectory(public_path(self::BUNDLED_RELATIVE), self::BUNDLED_RELATIVE, false);
    }

    /**
     * @return list<array{id: string, url: string, is_custom: bool}>
     */
    public function listCustomImages(string $context): array
    {
        $this->assertContext($context);
        $relative = $this->uploadRelativeDir($context);

        return $this->scanImageDirectory(public_path($relative), $relative, true);
    }

    /**
     * @return list<array{id: string, url: string, is_custom: bool}>
     */
    public function listAllImages(string $context): array
    {
        return array_merge($this->listBundledImages(), $this->listCustomImages($context));
    }

    public function resolveUrl(string $context): ?string
    {
        $this->assertContext($context);

        $images = $this->listAllImages($context);
        if ($images === []) {
            return null;
        }

        if ($this->mode($context) === self::MODE_RANDOM) {
            $picked = $images[array_rand($images)];

            return $picked['url'];
        }

        $selected = $this->selectedPath($context);
        if ($selected !== null) {
            foreach ($images as $image) {
                if ($image['id'] === $selected) {
                    return $image['url'];
                }
            }
        }

        return $images[0]['url'];
    }

    public function previewCollageUrls(int $limit = 4): array
    {
        $bundled = $this->listBundledImages();
        if ($bundled === []) {
            return [];
        }

        shuffle($bundled);

        return array_values(array_map(
            static fn (array $item): string => $item['url'],
            array_slice($bundled, 0, min($limit, count($bundled))),
        ));
    }

    public function savePreference(string $context, string $mode, ?string $selectedPath): void
    {
        $this->assertContext($context);

        $normalizedMode = $mode === self::MODE_RANDOM ? self::MODE_RANDOM : self::MODE_FIXED;

        if ($normalizedMode === self::MODE_FIXED) {
            $normalizedSelected = $selectedPath !== null && $selectedPath !== ''
                ? $this->normalizeRelativePath($selectedPath)
                : null;

            if ($normalizedSelected === null || ! $this->isAllowedImagePath($normalizedSelected)) {
                $fallback = $this->listAllImages($context);
                if ($fallback === []) {
                    throw new InvalidArgumentException('هیچ تصویری برای انتخاب وجود ندارد.');
                }
                $normalizedSelected = $fallback[0]['id'];
            }

            AppSetting::query()->updateOrCreate(
                ['key' => $this->selectedKey($context)],
                ['value' => $normalizedSelected],
            );
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $this->modeKey($context)],
            ['value' => $normalizedMode],
        );
    }

    public function storeUpload(string $context, UploadedFile $file): string
    {
        $this->assertContext($context);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('فرمت تصویر مجاز نیست. فقط JPG، PNG و WebP پذیرفته می‌شود.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_KB * 1024) {
            throw new InvalidArgumentException('حجم تصویر نباید بیشتر از ۵ مگابایت باشد.');
        }

        $mime = $file->getMimeType();
        if (! is_string($mime) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('نوع فایل تصویر معتبر نیست.');
        }

        $relativeDir = $this->uploadRelativeDir($context);
        $targetDir = public_path($relativeDir);
        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            throw new FileException('امکان ایجاد پوشهٔ ذخیره‌سازی وجود ندارد.');
        }

        $safeName = 'bg-'.Str::lower(Str::random(20)).'.'.$extension;
        $file->move($targetDir, $safeName);

        return $relativeDir.'/'.$safeName;
    }

    public function deleteCustom(string $context, string $relativePath): void
    {
        $this->assertContext($context);

        $normalized = $this->normalizeRelativePath($relativePath);
        $uploadPrefix = $this->uploadRelativeDir($context).'/';

        if (! str_starts_with($normalized, $uploadPrefix)) {
            throw new InvalidArgumentException('فقط تصاویر بارگذاری‌شده قابل حذف هستند.');
        }

        if (! $this->isAllowedImagePath($normalized)) {
            throw new InvalidArgumentException('مسیر تصویر معتبر نیست.');
        }

        $full = public_path($normalized);
        if (is_file($full)) {
            @unlink($full);
        }

        $selected = $this->selectedPath($context);
        if ($selected === $normalized) {
            AppSetting::query()->updateOrCreate(
                ['key' => $this->selectedKey($context)],
                ['value' => ''],
            );
        }
    }

    public function assetUrl(string $relativePath): string
    {
        return asset($this->normalizeRelativePath($relativePath));
    }

    private function modeKey(string $context): string
    {
        return 'login_bg_'.$context.'_mode';
    }

    private function selectedKey(string $context): string
    {
        return 'login_bg_'.$context.'_selected';
    }

    private function uploadRelativeDir(string $context): string
    {
        return self::UPLOAD_RELATIVE_PREFIX.'/'.$context;
    }

    private function normalizeRelativePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/'));
    }

    private function isAllowedImagePath(string $relativePath): bool
    {
        $normalized = $this->normalizeRelativePath($relativePath);
        $bundledPrefix = self::BUNDLED_RELATIVE.'/';
        $adminPrefix = $this->uploadRelativeDir(self::CONTEXT_ADMIN).'/';
        $customerPrefix = $this->uploadRelativeDir(self::CONTEXT_CUSTOMER).'/';

        if (
            ! str_starts_with($normalized, $bundledPrefix)
            && ! str_starts_with($normalized, $adminPrefix)
            && ! str_starts_with($normalized, $customerPrefix)
        ) {
            return false;
        }

        if (str_contains($normalized, '..')) {
            return false;
        }

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true) && is_file(public_path($normalized));
    }

    /**
     * @return list<array{id: string, url: string, is_custom: bool}>
     */
    private function scanImageDirectory(string $absoluteDir, string $relativeDir, bool $isCustom): array
    {
        if (! is_dir($absoluteDir)) {
            return [];
        }

        $files = File::files($absoluteDir);
        usort($files, static fn ($a, $b): int => strcmp($a->getFilename(), $b->getFilename()));

        $items = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $relative = $this->normalizeRelativePath($relativeDir.'/'.$file->getFilename());
            $items[] = [
                'id' => $relative,
                'url' => asset($relative),
                'is_custom' => $isCustom,
            ];
        }

        return $items;
    }
}
