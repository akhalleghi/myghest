<?php

declare(strict_types=1);

namespace App\Services\Cheques;

use App\Models\ChequeCustomer;
use App\Models\ChequeCustomerMedia;
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

final class ChequeCustomerMediaService
{
    public function available(): bool
    {
        return Schema::hasTable('cheque_customer_media');
    }

    public function withChanges(Request $request, Closure $saveCustomer): ChequeCustomer
    {
        $rules = ['customer_media' => ['sometimes', 'array:'.implode(',', array_keys(ChequeCustomerMedia::KINDS))],
            'remove_customer_media' => ['sometimes', 'array', 'max:4'],
            'remove_customer_media.*' => ['string', Rule::in(array_keys(ChequeCustomerMedia::KINDS))]];
        $attributes = [];
        foreach (ChequeCustomerMedia::KINDS as $kind => $label) {
            $rules['customer_media.'.$kind] = $kind === 'video'
                ? ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:20480']
                : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $attributes['customer_media.'.$kind] = $label;
        }
        $validated = Validator::make($request->all(), $rules, [], $attributes)->validate();
        $uploads = array_filter($request->file('customer_media', []), fn ($file) => $file instanceof UploadedFile);
        $removals = $validated['remove_customer_media'] ?? [];
        if (($uploads || $removals) && ! $this->available()) {
            throw ValidationException::withMessages(['customer_media' => 'ابتدا مایگریشن مدارک مشتریان چکی اجرا شود.']);
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
                $path = $file->storeAs('private/cheque-customer-media', $filename, 'local');
                if (! is_string($path) || $path === '') {
                    Storage::disk('local')->delete('private/cheque-customer-media/'.$filename);
                    throw ValidationException::withMessages(['customer_media.'.$kind => 'ذخیره فایل انجام نشد.']);
                }
                $staged[$kind] = ['path' => $path, 'mime_type' => $mime, 'size_bytes' => $file->getSize(),
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255)];
            }
            $customer = DB::transaction(function () use ($saveCustomer, $staged, $removals, &$oldPaths): ChequeCustomer {
                $customer = $saveCustomer();
                if (! $staged && ! $removals) {
                    return $customer;
                }
                ChequeCustomer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                foreach (array_unique([...array_keys($staged), ...$removals]) as $kind) {
                    $existing = $customer->media()->where('kind', $kind)->first();
                    if ($existing) {
                        $oldPaths[] = $existing->path;
                    }
                    if (isset($staged[$kind])) {
                        $customer->media()->updateOrCreate(['kind' => $kind], $staged[$kind]);
                    } else {
                        $existing?->delete();
                    }
                }
                $customer->events()->create(['action' => 'media_updated', 'admin_id' => auth('admin')->id(),
                    'payload' => ['uploaded_kinds' => array_keys($staged), 'removed_kinds' => array_values(array_diff($removals, array_keys($staged)))]]);

                return $customer;
            });
        } catch (Throwable $exception) {
            foreach ($staged as $item) {
                Storage::disk('local')->delete($item['path']);
            }
            throw $exception;
        }
        DB::afterCommit(function () use ($oldPaths): void {
            foreach ($oldPaths as $path) {
                Storage::disk('local')->delete($path);
            }
        });

        return $customer;
    }

    public function items(ChequeCustomer $customer): array
    {
        if (! $this->available()) {
            return [];
        }

        return $customer->media()->orderBy('id')->get()->map(fn (ChequeCustomerMedia $media) => [
            'id' => $media->id, 'kind' => $media->kind, 'label' => ChequeCustomerMedia::KINDS[$media->kind],
            'type' => str_starts_with($media->mime_type, 'video/') ? 'video' : 'image',
            'url' => route('admin.cheques.media.show', ['chequeCustomer' => $customer->id, 'media' => $media->id]),
            'original_name' => $media->original_name,
        ])->all();
    }
}
