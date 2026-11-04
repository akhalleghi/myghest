<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerMedia;
use App\Support\PrivateStoragePaths;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CustomerMediaService
{
    public function available(): bool
    {
        return Schema::hasTable('customer_media');
    }

    /** Store customer changes and media atomically; compensate newly written files on rollback. */
    public function withChanges(Request $request, Closure $saveCustomer): Customer
    {
        $rules = [
            'customer_media' => ['sometimes', 'array:'.implode(',', array_keys(CustomerMedia::KINDS))],
            'remove_customer_media' => ['sometimes', 'array', 'max:4'],
            'remove_customer_media.*' => ['string', Rule::in(array_keys(CustomerMedia::KINDS))],
        ];
        $attributes = [];
        foreach (CustomerMedia::KINDS as $kind => $label) {
            $rules['customer_media.'.$kind] = $kind === 'video'
                ? ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:20480']
                : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $attributes['customer_media.'.$kind] = $label;
        }
        $validated = Validator::make($request->all(), $rules, [], $attributes)->validate();
        $uploads = array_filter($request->file('customer_media', []), fn ($file) => $file instanceof UploadedFile);
        $removals = $validated['remove_customer_media'] ?? [];
        if (($uploads !== [] || $removals !== []) && ! $this->available()) {
            throw ValidationException::withMessages(['customer_media' => 'برای استفاده از مدارک مشتری، ابتدا مهاجرت دیتابیس نسخه جدید اجرا شود.']);
        }

        $staged = [];
        $oldPaths = [];
        try {
            foreach ($uploads as $kind => $file) {
                $mime = (string) $file->getMimeType();
                $extension = match ($mime) {
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
                    default => throw ValidationException::withMessages(['customer_media.'.$kind => 'نوع فایل پشتیبانی نمی‌شود.']),
                };
                $filename = Str::uuid().'.'.$extension;
                $path = $file->storeAs('private/customer-media', $filename, 'local');
                if (! is_string($path) || $path === '') {
                    Storage::disk('local')->delete('private/customer-media/'.$filename);
                    throw ValidationException::withMessages(['customer_media.'.$kind => 'ذخیره فایل انجام نشد؛ دوباره تلاش کنید.']);
                }
                $staged[$kind] = [
                    'path' => $path, 'mime_type' => $mime, 'size_bytes' => $file->getSize(),
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                ];
            }
            $customer = DB::transaction(function () use ($saveCustomer, $staged, $removals, &$oldPaths): Customer {
                $customer = $saveCustomer();
                if ($staged === [] && $removals === []) {
                    return $customer;
                }
                // Serialize replacements for the same customer so concurrent edits cannot leak files.
                Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                foreach (array_unique([...array_keys($staged), ...$removals]) as $kind) {
                    $existing = $customer->media()->where('kind', $kind)->first();
                    if ($existing !== null) {
                        $oldPaths[] = $existing->path;
                    }
                    if (isset($staged[$kind])) {
                        $customer->media()->updateOrCreate(['kind' => $kind], $staged[$kind]);
                    } else {
                        $existing?->delete();
                    }
                }

                return $customer;
            });
        } catch (Throwable $exception) {
            foreach ($staged as $file) {
                Storage::disk('local')->delete($file['path']);
            }
            throw $exception;
        }
        DB::afterCommit(function () use ($oldPaths): void {
            foreach ($oldPaths as $path) {
                PrivateStoragePaths::delete($path);
            }
        });

        return $customer;
    }

    public function items(Customer $customer): array
    {
        if (! $this->available()) {
            return [];
        }

        return $customer->media()->orderBy('id')->get()->map(fn (CustomerMedia $media): array => [
            'id' => $media->id,
            'kind' => $media->kind,
            'label' => CustomerMedia::KINDS[$media->kind],
            'type' => str_starts_with($media->mime_type, 'video/') ? 'video' : 'image',
            'url' => route('admin.customers.media.show', ['customer' => $customer->id, 'media' => $media->id]),
            'original_name' => $media->original_name,
        ])->all();
    }

    public function deleteCustomer(Customer $customer): void
    {
        $paths = $this->available() ? $customer->media()->pluck('path')->all() : [];
        DB::transaction(fn () => $customer->delete());
        DB::afterCommit(function () use ($paths): void {
            foreach ($paths as $path) {
                PrivateStoragePaths::delete($path);
            }
        });
    }
}
