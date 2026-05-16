<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Support\TicketHtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportTicketMessageWriter
{
    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    public function createMessage(
        SupportTicket $ticket,
        string $bodyHtml,
        ?int $senderAdminId,
        ?int $senderCustomerId,
        ?UploadedFile $attachment = null,
    ): SupportTicketMessage {
        $clean = TicketHtmlSanitizer::clean($bodyHtml);
        if (trim(strip_tags($clean)) === '') {
            throw ValidationException::withMessages(['body_html' => ['متن پیام الزامی است.']]);
        }

        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => (int) $ticket->id,
            'body_html' => $clean,
            'body_excerpt' => TicketHtmlSanitizer::excerptFromHtml($clean),
            'sender_admin_id' => $senderAdminId,
            'sender_customer_id' => $senderCustomerId,
        ]);

        if ($attachment !== null && $attachment->isValid()) {
            $this->storeAttachment($message, $attachment);
        }

        $ticket->update(['last_message_at' => Carbon::now()]);

        return $message->fresh(['attachments']);
    }

    public function storeAttachment(SupportTicketMessage $message, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'], true)) {
            throw ValidationException::withMessages([
                'attachment' => ['فرمت مجاز: jpg، png، pdf، doc، docx، zip'],
            ]);
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ];
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['attachment' => ['نوع فایل مجاز نیست.']]);
        }

        if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
            throw ValidationException::withMessages(['attachment' => ['حداکثر حجم فایل ۵ مگابایت است.']]);
        }

        $safeName = Str::lower(Str::random(20)).'.'.$ext;
        $dir = 'support-tickets/'.(int) $message->support_ticket_id;
        $path = $file->storeAs($dir, $safeName, 'local');

        SupportTicketAttachment::query()->create([
            'support_ticket_message_id' => (int) $message->id,
            'storage_path' => $path,
            'original_filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => mb_substr($mime, 0, 120),
            'file_size' => (int) $file->getSize(),
        ]);
    }

    /**
     * کپی ضمیمه‌های پیام اول برای تیکت‌های جداگانه (ارسال گروهی ادمین).
     */
    public function replicateAttachments(SupportTicketMessage $source, SupportTicketMessage $target): void
    {
        $source->loadMissing('attachments');
        $disk = Storage::disk('local');

        foreach ($source->attachments as $attachment) {
            $fromPath = (string) $attachment->storage_path;
            if ($fromPath === '' || ! $disk->exists($fromPath)) {
                continue;
            }

            $ext = pathinfo($fromPath, PATHINFO_EXTENSION) ?: 'bin';
            $toPath = 'support-tickets/'.(int) $target->support_ticket_id.'/'.Str::lower(Str::random(20)).'.'.$ext;

            $disk->copy($fromPath, $toPath);

            SupportTicketAttachment::query()->create([
                'support_ticket_message_id' => (int) $target->id,
                'storage_path' => $toPath,
                'original_filename' => (string) $attachment->original_filename,
                'mime_type' => (string) $attachment->mime_type,
                'file_size' => (int) $attachment->file_size,
            ]);
        }
    }
}
